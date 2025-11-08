<?php
declare(strict_types=1);

/**
 * Devuelve array de medidas detectadas:
 * [
 *   ['organo'=>'Riñón derecho', 'subparametro'=>'corteza', 'valor'=>3.2, 'unidad'=>'mm', 'valor_mm'=>3.2, 'evidencia_html'=>'...'],
 *   ...
 * ]
 */
function parser_extraer_medidas(string $html): array {
    $out = [];
    if (!preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $paras)) return $out;

    foreach ($paras[1] as $pHtml) {
        // cachea los <strong> con offsets una sola vez por párrafo
        $strongs = [];
        if (preg_match_all('#<strong>(.*?)</strong>#is', $pHtml, $mStrong, PREG_OFFSET_CAPTURE)) {
            $strongs = $mStrong; // [0] => tag, [1] => texto, ambos con offsets
        }

        // 2) Medidas (cm|mm)
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(cm|mm)\b/iu', $pHtml, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $full) {
                $posMedida = $m[0][$i][1];                // offset de la medida dentro del párrafo
                $numRaw    = $m[1][$i][0];
                $uni       = mb_strtolower($m[2][$i][0], 'UTF-8');
                $val       = (float) str_replace(',', '.', $numRaw);
                $mm        = ($uni === 'cm') ? $val * 10.0 : $val;

                // 2.a) Órgano = <strong> más cercano ANTERIOR a la medida
                $organo = 'General';
                if (!empty($strongs)) {
                    foreach ($strongs[0] as $j => $sTag) {
                        $posStrong = $sTag[1];
                        if ($posStrong < $posMedida) {
                            $organo = trim(strip_tags($strongs[1][$j][0]));
                        } else {
                            break; // el siguiente <strong> ya está después de la medida
                        }
                    }
                }

                // 3) Subparámetro por contexto local
                $sub = _parser_guess_subparametro($pHtml, $posMedida);

                $plain_para = trim(strip_tags($pHtml));
                $out[] = [
                    'organo'         => $organo ?: 'General',
                    'subparametro'   => $sub ?: 'medida',
                    'valor'          => $val,
                    'unidad'         => $uni,
                    'valor_mm'       => $mm,
                    'evidencia_html' => _recorte_contexto($pHtml, $posMedida),
                    'contexto'       => mb_strtolower($plain_para, 'UTF-8'),
                ];
            }
        }
    }
    return $out;
}


function _parser_guess_subparametro(string $pHtml, int $posMedida): string {
    // Busca palabras cerca de la medida
    $win = 200; // ventana ±
    $start = max(0, $posMedida - $win);
    $frag = mb_substr(strip_tags($pHtml), $start, $win*2, 'UTF-8');
    $low = mb_strtolower($frag, 'UTF-8');

    // Ajusta a tu terminología
    $map = [
        // --- tamaños/longitud renal “global” ---
        'tamaño'        => 'tamano_global',
        'tamano'        => 'tamano_global',
        'longitud'      => 'tamano_global',
        'eje mayor'     => 'tamano_global',
        'diametro renal'=> 'tamano_global',
        'diámetro renal'=> 'tamano_global',

        // --- pared / capas / genéricos (como ya tenías) ---
        'pared'         => 'pared',
        'cortical'      => 'cortical',
        'mucosa'        => 'mucosa',
        'duodenal'      => 'duodenal',
        'vesical'       => 'vesical',
        'renal'         => 'renal',
        'uretero'       => 'ureteral',
        'relación'      => 'relacion',
        'relaci'        => 'relacion',
        'diámetro'      => 'diametro',
        'diametro'      => 'diametro',
        'espesor'       => 'espesor',
        'ancho'         => 'ancho',
        'alto'          => 'alto',
        'long'          => 'longitud',

        // --- pelvis / urolitos ---
        'pelvi'         => 'pelvis_renal',
        'urolit'        => 'urolito_diametro',
        'cálcul'        => 'urolito_diametro',
        'calcu'         => 'urolito_diametro',
        'litias'        => 'urolito_diametro',
        'piedr'         => 'urolito_diametro',

        // --- otros que ya tenías ---
        'polo craneal'  => 'polo_craneal',
        'craneal'       => 'polo_craneal',
        'polo caudal'   => 'polo_caudal',

        'prostata'      => 'global',
        'vejiga'        => 'vesical',
        'grosor'        => 'grosor',
        'vesicula'      => 'vesicula',
        'biliar'        => 'vesicula',
        'vesicular'     => 'vesicula',
        'colecisto'     => 'vesicula',
        'colecistica'   => 'vesicula',
    ];
    

    foreach ($map as $needle => $label) {
        if (str_contains($low, $needle)) return $label;
    }
    return 'medida';
}

function _recorte_contexto(string $htmlPara, int $pos): string {
    // Devuelve un trozo pequeño del párrafo original (para mostrar)
    $plain = strip_tags($htmlPara);
    $start = max(0, $pos - 40);
    $frag  = mb_substr($plain, $start, 80, 'UTF-8');
    return htmlspecialchars($frag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// Normaliza tildes y minúsculas (ya tienes algo similar)
function vn_normalizar(string $s): string {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $repl = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u'];
    return strtr($s, $repl);
}

/**
 * Devuelve una clave canónica para el órgano.
 * Ej: "Riñón Der.", "rinon derecho", "riñón dcho" => "rinon derecho"
 */
function canonizar_organo(string $organo_texto, ?string $contexto = null): ?string {
    $t = vn_normalizar($organo_texto);         // minúsculas + sin tildes
    $t = preg_replace('/\s+/', ' ', $t);
    $c = $contexto ? vn_normalizar($contexto) : '';

    // primero variantes con lateralidad
    // derecho: derecho | der | dcho | der.
    if (
        (preg_match('/\brinon\b/u', $t) && preg_match('/\b(derecho|der\.?|dcho)\b/u', $t)) ||
        (preg_match('/\b(derecho|der\.?|dcho)\b/u', $t) && preg_match('/\brinon\b/u', $t))
    ) {
        return 'rinon derecho';
    }

    // izquierdo: izquierdo | izq | izdo | izq.
    if (
        (preg_match('/\brinon\b/u', $t) && preg_match('/\b(izquierdo|izq\.?|izdo)\b/u', $t)) ||
        (preg_match('/\b(izquierdo|izq\.?|izdo)\b/u', $t) && preg_match('/\brinon\b/u', $t))
    ) {
        return 'rinon izquierdo';
    }

    // genérico (sin lado) – solo si te sirve tenerlo
    if (preg_match('/\brinon\b/u', $t)) {
        return 'rinon';
    }
    
    // ADRENAL - derecha
    if (
        // "adrenal", "suprarrenal", "sr", "glandula adrenal"
        (preg_match('/\b(adrenal(es)?|suprarrenal(es)?|sr|gl(a|á)ndula(s)? adrenal(es)?)\b/u', $t)
            && preg_match('/\b(derech[oa]|der\.?|dcho)\b/u', $t))
        ||
        (preg_match('/\b(derech[oa]|der\.?|dcho)\b/u', $t)
            && preg_match('/\b(adrenal(es)?|suprarrenal(es)?|sr|gl(a|á)ndula(s)? adrenal(es)?)\b/u', $t))
    ) {
        return 'adrenal derecha';
    }

    // ADRENAL - izquierda
    if (
        (preg_match('/\b(adrenal(es)?|suprarrenal(es)?|sr|gl(a|á)ndula(s)? adrenal(es)?)\b/u', $t)
            && preg_match('/\b(izquierd[oa]|izq\.?|izdo)\b/u', $t))
        ||
        (preg_match('/\b(izquierd[oa]|izq\.?|izdo)\b/u', $t)
            && preg_match('/\b(adrenal(es)?|suprarrenal(es)?|sr|gl(a|á)ndula(s)? adrenal(es)?)\b/u', $t))
    ) {
        return 'adrenal izquierda';
    }

    // ===== PRÓSTATA (GLOBAL) =====
    if (preg_match('/\b(pr[oó]stata|prostatic[ao])\b/u', $t)) {
        // si no hay variantes de pared/subpartes, lo mapeamos al global
        return 'prostata (global)';
    }

    // ===== VEJIGA / VESICAL =====
    if (preg_match('/\b(vejiga|vesical)\b/u', $t)) {
        // mirar primero en el CONTEXTO del párrafo (fuera del <strong>)
        if ($c !== '') {
            if (preg_match('/poco\s*distend|no\s*distend|vac[ii]a|pobremente\s*distend/u', $c)) {
                return 'vejiga pared poco distendida';
            }
            if (preg_match('/\bdistend/u', $c)) {
                return 'vejiga pared distendida';
            }
        }
        // fallback: si el heading dice “pared” pero sin calificar
        if (preg_match('/\bpared\b/u', $t)) {
            return 'vejiga pared poco distendida';
        }
        // último recurso: asumir “distendida” por defecto
        return 'vejiga pared distendida';
    }

    // ===== ESTÓMAGO (ANTRO) — PARED =====
    if (preg_match('/\b(estomago|ant(o|ro))\b/u', $t)) {
        // usa contexto para el grado de distensión
        if ($c !== '') {
            if (preg_match('/poco\s*distend|semidistend/u', $c)) {
                return 'estomago (antro) pared (poco distendido / semidistendido)';
            }
            if (preg_match('/\b(distend|lleno|contenido)\b/u', $c)) {
                return 'estomago (antro) pared (distendido)';
            }
            if (preg_match('/\b(contrai|vaci[oa])\b/u', $c)) {
                return 'estomago (antro) pared (contraido/vacio)';
            }
        }
        // fallback razonable
        return 'estomago (antro) pared (distendido)';
    }

    // ===== DUODENO — PARED TOTAL =====
    if (preg_match('/\b(duodeno|duodenal)\b/u', $t) || preg_match('/\b(duodeno|duodenal)\b/u', $c)) {
        return 'duodeno pared total'; // <- sin guion, igual que en tu tabla
    }

    if (
        preg_match('/\bcolon\b/u', $t) || preg_match('/\bcolonic[oa]\b/u', $t) ||
        ($c !== '' && (preg_match('/\bcolon\b/u', $c) || preg_match('/\bcolonic[oa]\b/u', $c)))
    ) {
        return 'colon pared total'; // igual que en tu organo_key
    }

    if (
        preg_match('/\byeyuno\b/u', $t) || preg_match('/\byeyunal\b/u', $t) ||
        ($c !== '' && (preg_match('/\byeyuno\b/u', $c) || preg_match('/\byeyunal\b/u', $c)))
    ) {
        return 'yeyuno pared total';
    }

    if (
        preg_match('/\b(i|í)leon\b/u', $t) || preg_match('/\b(i|í)leal\b/u', $t) ||
        ($c !== '' && (preg_match('/\b(i|í)leon\b/u', $c) || preg_match('/\b(i|í)leal\b/u', $c)))
    ) {
        return 'ileon pared total';
    }

    // ===== DUODENO — PARED TOTAL =====
    if (
        preg_match('/\bduodeno\b/u', $t) || preg_match('/\bduoden(al|o)\b/u', $t) ||
        ($c !== '' && (preg_match('/\bduodeno\b/u', $c) || preg_match('/\bduoden(al|o)\b/u', $c)))
    ) {
        return 'duodeno pared total';
    }

    // ===== VESÍCULA BILIAR — PARED (distendida / poco distendida) =====
    if (
        preg_match('/\b(ves[ií]cula(\s+biliar)?|vesicular|colec[ií]sto|colec[ií]stica|vb)\b/u', $t)
        || ($c !== '' && preg_match('/\b(ves[ií]cula(\s+biliar)?|vesicular|colec[ií]sto|colec[ií]stica|vb)\b/u', $c))
    ) {
        // decidir por contexto
        if ($c !== '' && preg_match('/poco\s*distend|no\s*distend|vac[ií]a|colapsad|pobremente\s*distend/u', $c)) {
            return 'vesicula biliar pared (poco distendida)';
        }
        if ($c !== '' && preg_match('/\bdistend|llena|repleta|plena/u', $c)) {
            return 'vesicula biliar pared (distendida)';
        }
        // fallback si el <strong> trae "pared" pero sin calificar
        if (preg_match('/\bpared\b/u', $t)) {
            return 'vesicula biliar pared (poco distendida)';
        }
        // último recurso
        return 'vesicula biliar pared (distendida)';
    }

    if (
        preg_match('/\b(p[aá]ncreas|pancreas|pancre[aá]tico|pancreatico)\b/u', $t)
        || ($c !== '' && preg_match('/\b(p[aá]ncreas|pancreas|pancre[aá]tico|pancreatico)\b/u', $c))
    ) {
        return 'pancreas espesor (global)';
    }

    // ===== URÉTER PROXIMAL — DERECHO =====
    if (
        (preg_match('/\bur[ée]ter(al)?\b/u', $t) || ($c !== '' && preg_match('/\bur[ée]ter(al)?\b/u', $c)))
        && (preg_match('/\bprox(imal)?\b/u', $t) || ($c !== '' && preg_match('/\bprox(imal)?\b/u', $c)))
        && (preg_match('/\b(derech[oa]|der\.?|dcho)\b/u', $t) || ($c !== '' && preg_match('/\b(derech[oa]|der\.?|dcho)\b/u', $c)))
    ) {
        return 'ureter proximal derecho';
    }

    // ===== URÉTER PROXIMAL — IZQUIERDO =====
    if (
        (preg_match('/\bur[ée]ter(al)?\b/u', $t) || ($c !== '' && preg_match('/\bur[ée]ter(al)?\b/u', $c)))
        && (preg_match('/\bprox(imal)?\b/u', $t) || ($c !== '' && preg_match('/\bprox(imal)?\b/u', $c)))
        && (preg_match('/\b(izquierd[oa]|izq\.?|izdo)\b/u', $t) || ($c !== '' && preg_match('/\b(izquierd[oa]|izq\.?|izdo)\b/u', $c)))
    ) {
        return 'ureter proximal izquierdo';
    }






    return null; // no reconocido
}

