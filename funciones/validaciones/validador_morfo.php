<?php
declare(strict_types=1);

require_once(__DIR__ . '/size_resolver.php');
require_once(__DIR__ . '/param_repo.php');
require_once(__DIR__ . '/parser_html_informe.php');

/**
 * @param mysqli $db
 * @param string $html_in
 * @param array{especie_id?:?int, especie?:?string, raza?:?string, raza_id?:?int, peso_kg?:?float, etapa?:?string} $ctx
 * @return array{html_out:string, items:array<int, array>}
 */

function validar_informe_html(mysqli $db, string $html_in, array $ctx): array
{
    // ===== 0) Normalizar entrada desde proceso (solo texto) =====
    $especie_txt = isset($ctx['especie']) ? trim((string)$ctx['especie']) : '';
    $raza_txt    = isset($ctx['raza'])    ? trim((string)$ctx['raza'])    : '';
    $edad_in     = isset($ctx['edad'])    ? trim((string)$ctx['edad'])    : '';

    // a) especie_id desde texto (si no existe, fallback null)
    $especie_id = $especie_txt !== '' ? vm_get_especie_id($db, $especie_txt) : null;

    // b) edad_meses desde "edad" (fecha nac. o edad textual)
    $edad_meses = $edad_in !== '' ? vm_edad_str_o_fecha_a_meses($edad_in) : null;

    // c) resolver banda con size_resolver SOLO por especie+raza (sin peso)
    //    Asumimos que tu size_resolver acepta este contrato; si no, lo ajustamos en el siguiente paso.
    $binfo  = resolver_banda_paciente($db, [
        'especie'     => $especie_txt ?: null,
        'raza'        => $raza_txt ?: null,
        'edad_meses'  => $edad_meses,
        // sin peso
    ]);
    $banda  = $binfo['banda'] ?? null;

    // Si NO es Canino, forzar banda "normal" (según lo que comentaste)
    $esp_norm = vm_norm($especie_txt);
    if (!in_array($esp_norm, ['canino','perro','canina'], true)) {
        $banda = 'normal';
    }

    // d) etapa (adulto|cachorro) resuelta aquí (no viene del front)
    $etapa = vm_resolver_etapa($especie_txt, $banda, $edad_meses) ?? 'adulto'; // default adulto si no hay edad


    // 1) parsear medidas del HTML (ya lo tienes o usa un stub)
    $meds = parser_extraer_medidas($html_in);

    $items = [];
    foreach ($meds as $m) {
        $org_key = canonizar_organo($m['organo'] ?? '', $m['contexto'] ?? null);
        if (!$org_key) continue;

        $sub = $m['subparametro'] ?? 'medida';



        // 1) Normaliza valor/unidad e infiere si falta
$val = (float)($m['valor'] ?? 0);
$uni = (string)($m['unidad'] ?? '');
if ($uni === '' || $uni === null) {
    $uni = inferir_unidad_por_organo($org_key, $val);
}

// 2) Filtros por órgano (ya con $uni correcto)
if (in_array($org_key, ['rinon izquierdo','rinon derecho'], true)) {
    if ($sub !== 'tamano_global') continue;
    if (mb_strtolower($uni) === 'cm' && $val < 1.5) continue;
}





        $row = op_get_param($db, $especie_id, $banda, $etapa, $org_key);
        if (!$row) continue;

        $eval = vm_evaluar_medida($val, $uni, $row);

        $obs = sprintf(
            '%s: %s %s → %s (ref %s–%s %s; banda %s).',
            $m['organo'],
            rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.'),
            $uni,
            $eval['estado'],
            $row['tamano_min'] ?? '—',
            $row['tamano_max'] ?? '—',
            $row['unidad_base'] ?? 'mm',
            $row['tamano'] ?? ($banda ?: 'N/A')
        );

        $items[] = [
            'organo'         => $m['organo'],
            'subparametro'   => $sub,
            'valor'          => $val,
            'unidad'         => $uni,
            'valor_mm'       => vm_valor_a_mm($val, $uni),
            'banda'          => $banda,
            'etapa'          => $etapa,
            'rango'          => [
                'min'=>$row['tamano_min_mm'],
                'max'=>$row['tamano_max_mm'],
                'min_err'=>$row['tamano_min_critico_mm'],
                'max_err'=>$row['tamano_max_critico_mm'],
                'unidad'=>'mm'
            ],
            'severidad'      => $eval['estado'],
            'evidencia_html' => $m['evidencia_html'],
            'observacion'    => $obs
        ];
    }


    // Agregar bloque solo si hay algo distinto de "ok"
    $items_show = $items;
    // $items_show = array_values(array_filter($items, fn($it)=>$it['severidad']!=='ok'));
    $html_out = _agregar_bloque_validaciones($html_in, $items_show);

    return ['html_out'=>$html_out, 'items'=>$items];
}
function validar_informe_desde_json(mysqli $db, string $html_in, array $medidas, array $ctx): array
{
    // ===== 0) Resolver contexto (ES IGUAL que en validar_informe_html) =====
    $especie_txt = isset($ctx['especie']) ? trim((string)$ctx['especie']) : '';
    $raza_txt    = isset($ctx['raza'])    ? trim((string)$ctx['raza'])    : '';
    $edad_in     = isset($ctx['edad'])    ? trim((string)$ctx['edad'])    : '';

    // a) especie_id desde texto
    $especie_id = $especie_txt !== '' ? vm_get_especie_id($db, $especie_txt) : null;

    // b) edad (meses)
    $edad_meses = $edad_in !== '' ? vm_edad_str_o_fecha_a_meses($edad_in) : null;

    // c) banda (por especie+raza)
    $binfo = resolver_banda_paciente($db, [
        'especie'    => $especie_txt ?: null,
        'raza'       => $raza_txt ?: null,
        'edad_meses' => $edad_meses,
    ]);
    $banda = $binfo['banda'] ?? null;

    // Si NO es canino, fuerza "normal" (según tu decisión previa)
    $esp_norm = vm_norm($especie_txt);
    if (!in_array($esp_norm, ['canino','perro','canina'], true)) {
        $banda = 'normal';
    }

    // d) etapa (adulto|cachorro)
    $etapa = vm_resolver_etapa($especie_txt, $banda, $edad_meses) ?? 'adulto';

    // ===== 1) Evaluar medidas del JSON =====
    $items = [];
    foreach ($medidas as $m) {
        $org_txt = (string)($m['organo'] ?? '');
        $sub     = (string)($m['subparametro'] ?? '');
        $val     = (float) ($m['valor'] ?? 0);
        $uni     = (string)($m['unidad'] ?? '');

        // Canoniza órgano (por si IA se desvía levemente)
        $org_key = canonizar_organo($org_txt, null);
        if (!$org_key) continue;

        // 🔒 Filtros por órgano/subparámetro (para evitar falsos positivos)
        if (in_array($org_key, ['rinon izquierdo','rinon derecho'], true)) {
            if ($sub !== 'tamano_global') continue;             // solo tamaño renal global
            if (mb_strtolower($uni) === 'cm' && $val < 1.5) {   // corta diametros de urolitos “colados”
                continue;
            }
        }
        // (Aquí puedes agregar más reglas, p.ej. intestino=solo grosor_pared, etc.)

        // Cargar rango de referencia
        $row = op_get_param($db, $especie_id, $banda, $etapa, $org_key);
        if (!$row) continue;


        if ($uni === '' || $uni === null) {
            $uni = inferir_unidad_por_organo($org_key, $val);
            if (function_exists('app_log') && isset($rid)) {
                app_log('info', ['rid'=>$rid,'at'=>'unidad_inferida','org'=>$org_key,'valor'=>$val,'unidad'=>$uni], 'INFO');
            }
        }

        // Evaluación
        $eval = vm_evaluar_medida($val, $uni, $row);

        // Presentar rango en la MISMA unidad que la medida (legibilidad)
        $mostrar_en_cm = (mb_strtolower($uni) === 'cm');
        $min_disp = $row['tamano_min_mm'] !== null ? ($mostrar_en_cm ? $row['tamano_min_mm']/10 : $row['tamano_min_mm']) : null;
        $max_disp = $row['tamano_max_mm'] !== null ? ($mostrar_en_cm ? $row['tamano_max_mm']/10 : $row['tamano_max_mm']) : null;
        $unidad_disp = $mostrar_en_cm ? 'cm' : 'mm';

        $obs = sprintf(
            '%s: %s %s → %s (ref %s–%s %s; banda %s).',
            $org_txt,
            rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.'),
            $uni,
            $eval['estado'],
            $min_disp !== null ? rtrim(rtrim(number_format($min_disp, 2, '.', ''), '0'), '.') : '—',
            $max_disp !== null ? rtrim(rtrim(number_format($max_disp, 2, '.', ''), '0'), '.') : '—',
            $unidad_disp,
            $row['tamano'] ?? ($banda ?: 'N/A')
        );

        $items[] = [
            'organo'         => $org_txt,
            'subparametro'   => $sub,
            'valor'          => $val,
            'unidad'         => $uni,
            'valor_mm'       => vm_valor_a_mm($val, $uni),
            'banda'          => $banda,
            'etapa'          => $etapa,
            'rango'          => [
                'min'     => $row['tamano_min_mm'],
                'max'     => $row['tamano_max_mm'],
                'min_err' => $row['tamano_min_critico_mm'],
                'max_err' => $row['tamano_max_critico_mm'],
                'unidad'  => 'mm'
            ],
            'severidad'      => $eval['estado'],
            'evidencia_html' => $m['evidencia'] ?? '',
            'observacion'    => $obs
        ];
    }

    // Solo mostramos lo no-OK en el bloque de validaciones (como haces)
    $items_show = $items;
    // $items_show = array_values(array_filter($items, fn($it)=>$it['severidad']!=='ok'));
    $html_out = _agregar_bloque_validaciones($html_in, $items_show);

    return ['html_out'=>$html_out, 'items'=>$items];
}



/** severidad simple según *mm* y rangos */
function _evaluar_severidad_mm(float $mm, ?array $r): string {
    if (!$r) return 'informativo';
    $min = $r['min'] ?? null; $max = $r['max'] ?? null;
    $min_err = $r['min_err'] ?? null; $max_err = $r['max_err'] ?? null;

    if ($min !== null && $mm < $min) {
        if ($min_err !== null && $mm < $min_err) return 'marcado';
        return 'borderline';
    }
    if ($max !== null && $mm > $max) {
        if ($max_err !== null && $mm > $max_err) return 'marcado';
        return 'borderline';
    }
    return 'ok';
}

function _armar_observacion(array $m, ?array $r, string $sev): string {
    $base = "{$m['organo']} / {$m['subparametro']}: {$m['valor']} {$m['unidad']}";
    if (!$r) return "$base (sin rango de referencia).";
    $rTxt = 'ref ';
    $min = $r['min'] ?? null; $max = $r['max'] ?? null;
    if ($min !== null && $max !== null)      $rTxt .= "{$min}–{$max} mm";
    elseif ($min !== null && $max === null)  $rTxt .= "≥ {$min} mm";
    elseif ($min === null && $max !== null)  $rTxt .= "≤ {$max} mm";
    else                                     $rTxt .= "n/d";

    return "$base; $rTxt → $sev.";
}

/** bloque al final del HTML solo si hay items con algo no-ok (o todos, como prefieras) */
function _agregar_bloque_validaciones(string $html, array $items): string
{
    if (empty($items)) return $html;

    $lineas = [];
    foreach ($items as $it) {
        $org    = $it['organo']        ?? '—';
        $val    = $it['valor']         ?? null;
        $uni    = strtolower($it['unidad'] ?? '');
        $sev    = $it['severidad']     ?? 'informativo';
        $banda  = $it['banda']         ?? 'N/A';

        // Rango viene en mm; lo mostramos en la MISMA unidad de la medida
        $rango  = $it['rango'] ?? null;
        $min_mm = $rango['min'] ?? null;
        $max_mm = $rango['max'] ?? null;

        $conv = function($mm, $uniMedida) {
            if ($mm === null) return null;
            if ($uniMedida === 'cm') return rtrim(rtrim(number_format($mm/10, 2, '.', ''), '0'), '.');
            return rtrim(rtrim(number_format($mm,    2, '.', ''), '0'), '.');
        };

        $min_disp = $conv($min_mm, $uni);
        $max_disp = $conv($max_mm, $uni);
        $uni_disp = ($uni === 'cm') ? 'cm' : 'mm';

        // Valor mostrado (formateado) con su unidad
        $val_disp = ($val === null) ? '—' : rtrim(rtrim(number_format((float)$val, 2, '.', ''), '0'), '.');

        // Texto del rango
        if ($min_disp !== null && $max_disp !== null) {
            $r_txt = "{$min_disp}–{$max_disp} {$uni_disp}";
        } elseif ($min_disp !== null) {
            $r_txt = "≥ {$min_disp} {$uni_disp}";
        } elseif ($max_disp !== null) {
            $r_txt = "≤ {$max_disp} {$uni_disp}";
        } else {
            $r_txt = "n/d";
        }

        // Línea final (sin usar $it['observacion'])
        $lineas[] = "• {$org}: {$val_disp} " . ($uni ?: 'mm') . " → {$sev} (ref {$r_txt}; banda {$banda}).";
    }

    $bloque = "<p><strong>Validaciones del Sistema:</strong><br>\n"
            . implode("<br>\n", $lineas)
            . "<br>\n</p>";

    return $html . $bloque;
}



/**
 * Convierte el valor a mm según su unidad ('mm'|'cm').
 */
function vm_valor_a_mm(float $valor, string $unidad): float {
    $u = mb_strtolower($unidad, 'UTF-8');
    return ($u === 'cm') ? $valor * 10.0 : $valor;
}

function inferir_unidad_por_organo(string $org_key, float $valor): string {
    $k = vm_norm($org_key);

    // Riñón: tamaño global suele venir en cm (3–5 cm en pequeños animales)
    if ($k === 'rinon izquierdo' || $k === 'rinon derecho') {
        if ($valor >= 1.5 && $valor <= 10.0) return 'cm'; // 1.5–10 → muy probable cm
        return 'mm';
    }

    // Pared de estómago/intestino suele venir en mm (< 7 mm típicamente)
    if (preg_match('/(duodeno|yeyuno|ileon|colon|estomago)/', $k)) {
        return ($valor <= 10.0) ? 'mm' : 'cm';
    }

    // Por defecto, mm
    return 'mm';
}


/**
 * Evalúa la medida contra min/max y críticos.
 * Devuelve: ['estado'=>'ok|disminuida|aumentada|critico_bajo|critico_alto', 'detalle'=>string]
 */
function vm_evaluar_medida(float $valor, string $unidad, array $row): array {
    $vmm = vm_valor_a_mm($valor, $unidad);

    $min  = $row['tamano_min_mm'];
    $max  = $row['tamano_max_mm'];
    $minc = $row['tamano_min_critico_mm'];
    $maxc = $row['tamano_max_critico_mm'];

    // límites críticos pueden ser null → desactivar ese lado
    if ($min !== null && $vmm < $min) {
        if ($minc !== null && $vmm < $minc) return ['estado'=>'critico_bajo', 'detalle'=>"{$valor} {$unidad} < min crítico"];
        return ['estado'=>'disminuida', 'detalle'=>"{$valor} {$unidad} < min"];
    }
    if ($max !== null && $vmm > $max) {
        if ($maxc !== null && $vmm > $maxc) return ['estado'=>'critico_alto', 'detalle'=>"{$valor} {$unidad} > max crítico"];
        return ['estado'=>'aumentada', 'detalle'=>"{$valor} {$unidad} > max"];
    }
    return ['estado'=>'ok', 'detalle'=>"dentro de rango"];
}
















/**
 * Busca especie_id por nombre textual exacto (case-insensitive).
 * Devuelve null si no existe.
 */
function vm_get_especie_id(mysqli $db, string $especie_txt): ?int {
    $sql = "SELECT id FROM especies WHERE LOWER(nombre)=LOWER(?) LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('s', $especie_txt);
    $st->execute();
    $id = null;
    if ($rs = $st->get_result()) {
        if ($row = $rs->fetch_assoc()) $id = (int)$row['id'];
    }
    $st->close();
    return $id;
}

/**
 * Normaliza strings tipo "años" vs "anos", espacios, etc.
 */
function vm_norm(string $s): string {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $repl = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u'];
    $s = strtr($s, $repl);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

/**
 * Recibe $edad_txt que puede ser:
 *  - Fecha de nacimiento (YYYY-MM-DD o DD/MM/YYYY)
 *  - Edad textual: "6 anos 11 meses", "1 año", "10 meses", etc.
 * Devuelve edad en meses (int) o null si no se puede calcular.
 * Usa la fecha actual del servidor (America/Santiago).
 */
function vm_edad_str_o_fecha_a_meses(string $edad_txt): ?int {
    $t = trim($edad_txt);
    if ($t === '') return null;

    // 1) ¿Formato fecha?
    //    Aceptamos YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
        try {
            $dob = new DateTime($t, new DateTimeZone('America/Santiago'));
            $now = new DateTime('now', new DateTimeZone('America/Santiago'));
            if ($dob > $now) return null;
            $diff = $dob->diff($now);
            return max(0, ($diff->y * 12) + $diff->m);
        } catch (Throwable $e) {
            // sigue abajo
        }
    }
    //    Aceptamos DD/MM/YYYY
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $t)) {
        $parts = explode('/', $t);
        if (count($parts) === 3) {
            [$d,$m,$y] = $parts;
            if (checkdate((int)$m, (int)$d, (int)$y)) {
                try {
                    $dob = new DateTime(sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d), new DateTimeZone('America/Santiago'));
                    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
                    if ($dob > $now) return null;
                    $diff = $dob->diff($now);
                    return max(0, ($diff->y * 12) + $diff->m);
                } catch (Throwable $e) {
                    // sigue abajo
                }
            }
        }
    }

    // 2) Edad textual
    $n = vm_norm($t); // "6 anos 11 meses" -> "6 anos 11 meses"
    // normaliza "año"/"anos"/"mes"/"meses" posibles
    $years = 0; $months = 0;

    if (preg_match('/(\d+)\s*a(?:no|n?os?)/u', $n, $m)) {
        $years = (int)$m[1];
    }
    if (preg_match('/(\d+)\s*m(?:es(?:es)?)/u', $n, $m)) {
        $months = (int)$m[1];
    }
    // si dice solo "X meses"
    if ($years === 0 && $months === 0 && preg_match('/^\d+\s*m/u', $n)) {
        $months = (int)filter_var($n, FILTER_SANITIZE_NUMBER_INT);
    }
    // si dice solo "X años"
    if ($years > 0 && !preg_match('/m(?:es|eses)/u', $n)) {
        // ok, solo años
    }

    $total = $years * 12 + $months;
    return $total >= 0 ? $total : null;
}

/**
 * Determina etapa (adulto|cachorro) por especie y banda usando edad en meses.
 * - Canino: umbrales por banda (ajustables).
 * - Otras especies (Felino, etc.): umbral simple (12 meses).
 */
function vm_resolver_etapa(string $especie_txt, ?string $banda, ?int $edad_meses): ?string {
    if ($edad_meses === null) return null;
    $esp = vm_norm($especie_txt);

    // Umbrales productivos (ajústalos luego si quieres)
    $CANINO_UMBRAL_POR_BANDA = [
        'miniatura' => 12,
        'pequeno'   => 12,
        'mediano'   => 15,
        'grande'    => 15,
        'gigante'   => 18,
    ];
    $FELINO_UMBRAL = 12;

    if (in_array($esp, ['canino','perro','canina'], true)) {
        $b = $banda ? vm_norm($banda) : 'mediano';
        $umbral = $CANINO_UMBRAL_POR_BANDA[$b] ?? 15;
        return ($edad_meses >= $umbral) ? 'adulto' : 'cachorro';
    }
    // Felino y otras especies: regla simple
    return ($edad_meses >= $FELINO_UMBRAL) ? 'adulto' : 'cachorro';
}
