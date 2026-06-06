<?php
declare(strict_types=1);

/**
 * Post-proceso del HTML que devuelve el modelo (GPT/Grok/Claude).
 *
 * Filosofía: el modelo es el autor del informe y de las Observaciones.
 * El post-proceso NO reescribe lo que el modelo redactó bien; solo:
 *   1) Sanitiza HTML que no debe llegar al visor (<style>, fences, <html>...).
 *   2) Quita la conclusión si no correspondía.
 *   3) Renumera los flags para que queden 1,2,3... en orden de aparición.
 *   4) Verifica la correspondencia flag <-> observación y rellena SOLO lo que falte.
 */

function gpt_postprocess_html(string $html, bool $incluir_conclusion, array $ctxPaciente = []): string
{
    // 1) Sanitizar: sacar lo que nunca debe llegar al visor del veterinario.
    $html = gpt_sanitizar_html($html);

    // 2) Quitar conclusión si no venía en el dictado.
    if (!$incluir_conclusion) {
        $html = preg_replace('#<p>\s*<strong>\s*CONCLUSI(Ó|O)N:\s*</strong>\s*<br>\s*.*?</p>#is', '', $html);
    }

    // 2.5) Marcar los XX sueltos (medidas no dictadas que quedaron en la plantilla).
    $html = gpt_marcar_xx_faltantes($html);

    // 3) Renumerar flags en orden de aparición (corrige saltos del modelo).
    $html = gpt_renumerar_flags($html);

    // 4) Verificar correspondencia flag <-> observación; rellenar solo lo faltante.
    $html = gpt_reparar_observaciones($html);

    return $html;
}

/**
 * Elimina del HTML cualquier cosa que sea andamiaje o ruido y no parte del informe:
 * bloques <style>, fences markdown ```), y etiquetas de documento <html>/<head>/<body>.
 * El color de los flags vive en el CSS del sistema, NO en la respuesta.
 */
function gpt_sanitizar_html(string $html): string
{
    // bloques <style>...</style> completos
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);

    // fences markdown ``` o ```html
    $html = preg_replace('#```[a-z]*#i', '', $html);

    // etiquetas de documento que el modelo no debe mandar
    $html = preg_replace('#</?(?:html|head|body)\b[^>]*>#i', '', $html);

    return trim($html);
}

/**
 * Renumera los flags 1,2,3... en orden de aparición y conserva su data-tipo.
 * Devuelve el HTML con los <sup> normalizados.
 */
function gpt_renumerar_flags(string $html): string
{
    $seq = 0;
    return preg_replace_callback(
        '#<sup\b[^>]*class=[\'"]flag[\'"][^>]*>.*?</sup>#is',
        function ($m) use (&$seq) {
            $seq++;
            $tipo = 'valor_sospechoso';
            if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $m[0], $mt)) {
                $tipo = $mt[1];
            }
            return "<sup class=\"flag\" data-flag=\"{$seq}\" data-tipo=\"{$tipo}\">({$seq})</sup>";
        },
        $html
    );
}

/**
 * Inspecciona la correspondencia entre flags del informe y líneas del bloque
 * "Observaciones del Asistente". NO reescribe las observaciones del modelo.
 * Acciones:
 *   - Si hay flags pero no hay bloque de observaciones: lo crea con placeholders.
 *   - Si un flag (N) no tiene su línea: agrega una línea placeholder para (N).
 *   - Si el bloque tiene líneas (N) cuyo flag ya no existe: las elimina.
 *   - Si no hay flags: elimina el bloque de observaciones si quedó alguno.
 */
function gpt_reparar_observaciones(string $html): string
{
    // Flags presentes en el informe: [N => tipo]
    $flags = [];
    if (preg_match_all('#<sup\b[^>]*data-flag=["\'](\d+)["\'][^>]*>#i', $html, $mf, PREG_SET_ORDER)) {
        foreach ($mf as $f) {
            $n = (int)$f[1];
            $tipo = 'valor_sospechoso';
            if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $f[0], $mt)) {
                $tipo = strtolower($mt[1]);
            }
            $flags[$n] = $tipo;
        }
    }
    ksort($flags);

    // Extraer bloque de observaciones del modelo (si existe).
    $obsBlock = '';
    if (preg_match('#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>(.*?)</p>#is', $html, $mb)) {
        $obsBlock = $mb[1];
    }

    // Sin flags: no debe haber bloque. Si quedó, se elimina.
    if (empty($flags)) {
        return gpt_quitar_bloque_observaciones($html);
    }

    // Mapear qué números ya tienen línea en el bloque del modelo.
    $lineasModelo = [];   // N => "texto html de la línea (sin el (N) inicial)"
    if ($obsBlock !== '') {
        $partes = preg_split('#<br\s*/?>#i', $obsBlock);
        foreach ($partes as $linea) {
            $plain = trim($linea);
            if ($plain === '') continue;
            if (preg_match('/^\s*\((\d+)\)\s*(.*)$/s', strip_tags($plain), $ml)) {
                $lineasModelo[(int)$ml[1]] = trim($plain);
            }
        }
    }

    // Construir el bloque final: para cada flag, usar la línea del modelo si existe,
    // o un placeholder mínimo si falta. Se descartan líneas de flags inexistentes.
    $lineasFinales = [];
    foreach ($flags as $n => $tipo) {
        if (isset($lineasModelo[$n])) {
            $lineasFinales[] = $lineasModelo[$n];
        } else {
            $lineasFinales[] = gpt_placeholder_observacion($n, $tipo);
        }
    }

    // Quitar el bloque viejo y reinsertar el reconstruido al final.
    $html = gpt_quitar_bloque_observaciones($html);

    $obs = "<p><strong>Observaciones del Asistente:</strong><br>\n"
         . implode("<br>\n", $lineasFinales)
         . "<br>\n</p>";

    return trim($html) . "\n" . $obs;
}

/**
 * Elimina el bloque "Observaciones del Asistente" del HTML, si existe.
 */
function gpt_quitar_bloque_observaciones(string $html): string
{
    return preg_replace(
        '#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>.*?</p>#is',
        '',
        $html
    );
}

/**
 * Texto mínimo cuando el modelo dejó un flag sin su observación.
 * No inventa hallazgos: solo señala que ese punto quedó marcado y debe revisarse.
 */
function gpt_placeholder_observacion(int $n, string $tipo): string
{
    $glos = [
        'valor_sospechoso' => 'valor marcado como inusual; revisar en el informe.',
        'falta_unidad'     => 'medida sin unidad clara (cm/mm); confirmar la unidad.',
        'termino_confuso'  => 'término marcado como posible error de dictado; confirmar.',
        'incongruencia'    => 'posible contradicción en el informe; revisar.',
        'medida_ilegible'  => 'el dictado traía una medida que no se pudo descifrar; revisar el audio original.',
        'valor_faltante'   => 'el dictado no mencionó esta medida; completar si corresponde.',
    ];
    $txt = $glos[$tipo] ?? 'punto marcado para revisión.';
    return "({$n}) {$tipo} → {$txt}";
}

/**
 * Marca con flag los "XX" que quedaron sueltos en el informe (medidas de la
 * plantilla que el dictado no mencionó). NO toca los XX que ya están dentro de
 * un <sup class="flag"> (esos son medida_ilegible y ya los marcó el modelo).
 *
 * Debe ejecutarse ANTES de renumerar flags y de reparar observaciones, para que
 * esos pasos posteriores numeren y generen la observación de estos flags nuevos.
 */
function gpt_marcar_xx_faltantes(string $html): string
{
    return preg_replace_callback(
        '/\bXX\b(?!\s*<sup\b[^>]*class=["\']flag)/',
        function () {
            // Envuelve el XX en <mark> para resaltarlo en amarillo y le pega el flag.
            // El <mark> viaja con el contenido hasta el PDF: si un XX se cuela, salta a la vista.
            return '<mark class="xx-faltante">XX</mark>'
                 . '<sup class="flag" data-flag="0" data-tipo="valor_faltante">(0)</sup>';
        },
        $html
    );
}