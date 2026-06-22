<?php
// funciones/GPT/lib/stt_validador.php
// Comparación de 2 transcripciones + validador (órganos/conceptos) + armado de bloques.
// Compartido por el banco y por transcribir_doble.php. Sin salida, solo define funciones/listas.
declare(strict_types=1);

// ---- Comparación por código (LCS) ----
function cmp_pre_limpiar(string $t): string {
    return preg_replace('/(\d)\s*([.,])\s*(\d)/u', '$1$2$3', $t);
}
function cmp_norm(string $w): string {
    $w = mb_strtolower($w, 'UTF-8');
    $w = strtr($w, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $w = str_replace('-', '', $w);
    $w = preg_replace('/[^0-9a-z.,]/u', '', $w);
    $w = trim($w, '.,');
    if (preg_match('/\d/', $w)) $w = str_replace(',', '.', $w);
    return $w;
}
function cmp_tokens(string $t): array {
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t === '' ? [] : explode(' ', $t);
}
function cmp_es_numero_norm(string $w): bool {
    return (bool)preg_match('/^\d+(\.\d+)?$/', $w);
}
function cmp_comparar(string $textoA, string $textoB): array {
    $textoA = cmp_pre_limpiar($textoA);
    $textoB = cmp_pre_limpiar($textoB);
    $A = cmp_tokens($textoA); $B = cmp_tokens($textoB);
    $na = count($A); $nb = count($B);
    $nA = array_map('cmp_norm', $A); $nB = array_map('cmp_norm', $B);
    $dp = array_fill(0, $na + 1, array_fill(0, $nb + 1, 0));
    for ($i = $na - 1; $i >= 0; $i--) {
        for ($j = $nb - 1; $j >= 0; $j--) {
            if ($nA[$i] !== '' && $nA[$i] === $nB[$j]) $dp[$i][$j] = $dp[$i+1][$j+1] + 1;
            else $dp[$i][$j] = max($dp[$i+1][$j], $dp[$i][$j+1]);
        }
    }
    $i = 0; $j = 0;
    $disc = []; $bufA = []; $bufB = []; $bufNA = []; $bufNB = [];
    $flush = function() use (&$bufA, &$bufB, &$bufNA, &$bufNB, &$disc) {
        if (empty($bufA) && empty($bufB)) return;
        $hayNum = false;
        foreach (array_merge($bufNA, $bufNB) as $w) if (cmp_es_numero_norm($w)) $hayNum = true;
        if (empty($bufB))      $tipo = 'solo_A';
        elseif (empty($bufA))  $tipo = 'solo_B';
        elseif ($hayNum)       $tipo = 'numero';
        else                   $tipo = 'cambio';
        $disc[] = ['tipo'=>$tipo, 'a'=>implode(' ', $bufA), 'b'=>implode(' ', $bufB)];
        $bufA = []; $bufB = []; $bufNA = []; $bufNB = [];
    };
    while ($i < $na && $j < $nb) {
        if ($nA[$i] !== '' && $nA[$i] === $nB[$j]) { $flush(); $i++; $j++; }
        elseif ($dp[$i+1][$j] >= $dp[$i][$j+1]) { $bufA[] = $A[$i]; $bufNA[] = $nA[$i]; $i++; }
        else { $bufB[] = $B[$j]; $bufNB[] = $nB[$j]; $j++; }
    }
    while ($i < $na) { $bufA[] = $A[$i]; $bufNA[] = $nA[$i]; $i++; }
    while ($j < $nb) { $bufB[] = $B[$j]; $bufNB[] = $nB[$j]; $j++; }
    $flush();
    return $disc;
}

// ---- Diccionarios ----
$ORGANOS_LISTA = [
    'vejiga','riñon','riñones','bazo','higado','vesicula','estomago','pancreas',
    'linfonodulos','adrenal','adrenales','yeyuno','ileon','duodeno','colon',
    'prostata','ciego','peritoneo','ovario','utero','cuerno','cuerpo','testiculos',
];
$CONCEPTOS_LISTA = [
    'ecogenicidad','anecoico','anecoica','anecoicas',
    'hipoecoico','hipoecoica','hipoecoicas','hiperecoico','hiperecoica','hiperecoicas',
    'parenquima','estratificacion','esplenico','felino','aguzados','engrosado','engrosada','engrosadas',
    'mucoso','corticomedular','vasculatura','homogeneo','lobulo','reactivo','distendida',
    'redondeados','conservado','pelvica','grosor',
];
function org_norm(string $w): string {
    $w = mb_strtolower($w, 'UTF-8');
    $w = strtr($w, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    return preg_replace('/[^a-z0-9]/', '', $w);
}
function org_es_organo(string $tok, array $lista): bool { return in_array(org_norm($tok), $lista, true); }
function org_limpia_borde(string $tok): string { return trim($tok, " .,;:"); }
function org_validar(string $a, string $b, array $lista): array {
    $a1 = org_limpia_borde($a); $b1 = org_limpia_borde($b);
    if (strpos($a1, ' ') !== false || strpos($b1, ' ') !== false) return ['accion'=>'pasa'];
    if ($a1 === '' || $b1 === '') return ['accion'=>'pasa'];
    $oa = org_es_organo($a1, $lista); $ob = org_es_organo($b1, $lista);
    if ($oa && !$ob) return ['accion'=>'resuelto','elegido'=>$a1,'descartado'=>$b1];
    if ($ob && !$oa) return ['accion'=>'resuelto','elegido'=>$b1,'descartado'=>$a1];
    return ['accion'=>'pasa'];
}
function concepto_es(string $tok, array $lista): bool { return in_array(org_norm($tok), $lista, true); }
function concepto_similar(string $valido, string $otro): bool {
    $a = org_norm($valido); $b = org_norm($otro);
    if ($a === '' || $b === '') return false;
    $umbral = max(1, (int)floor(mb_strlen($a) / 3));
    return levenshtein($a, $b) <= $umbral;
}
function concepto_validar(string $a, string $b, array $conceptos): array {
    $a1 = org_limpia_borde($a); $b1 = org_limpia_borde($b);
    if (strpos($a1, ' ') !== false || strpos($b1, ' ') !== false) return ['accion'=>'pasa'];
    if ($a1 === '' || $b1 === '') return ['accion'=>'pasa'];
    $ca = concepto_es($a1, $conceptos); $cb = concepto_es($b1, $conceptos);
    if ($ca && !$cb && concepto_similar($a1, $b1)) return ['accion'=>'resuelto','elegido'=>$a1,'descartado'=>$b1];
    if ($cb && !$ca && concepto_similar($b1, $a1)) return ['accion'=>'resuelto','elegido'=>$b1,'descartado'=>$a1];
    return ['accion'=>'pasa'];
}
function org_procesar(array $disc, array $organos, array $conceptos = []): array {
    $resueltas = []; $aIA = [];
    foreach ($disc as $d) {
        $r = org_validar($d['a'], $d['b'], $organos);
        if ($r['accion'] === 'resuelto') { $resueltas[] = ['elegido'=>$r['elegido'],'descartado'=>$r['descartado'],'origen'=>'organo']; continue; }
        $c = concepto_validar($d['a'], $d['b'], $conceptos);
        if ($c['accion'] === 'resuelto') { $resueltas[] = ['elegido'=>$c['elegido'],'descartado'=>$c['descartado'],'origen'=>'concepto']; continue; }
        $aIA[] = $d;
    }
    return ['resueltas'=>$resueltas, 'a_ia'=>$aIA];
}

// ---- Armado de bloques para anexar al dictado ----
function construir_bloque_resueltas(array $resueltas): string {
    if (empty($resueltas)) return '';
    $lineas = [];
    foreach ($resueltas as $r) {
        $lineas[] = '- Usa "' . $r['elegido'] . '" (un motor transcribio mal como "' . $r['descartado'] . '")';
    }
    return "\n\n=== CORRECCIONES YA RESUELTAS (diferencias entre las 2 transcripciones del mismo audio, ya decididas porque un motor transcribio mal; usa SIEMPRE el termino correcto indicado; no incluyas esta nota en el informe) ===\n"
         . implode("\n", $lineas);
}
function construir_bloque_discrepancias(array $disc): string {
    if (empty($disc)) return '';
    $lineas = [];
    foreach ($disc as $d) {
        $a = $d['a'] !== '' ? $d['a'] : '(nada)';
        $b = $d['b'] !== '' ? $d['b'] : '(nada)';
        $lineas[] = '- Motor A dice "' . $a . '" / Motor B dice "' . $b . '"';
    }
    return "\n\n=== NOTA: DIFERENCIAS ENTRE 2 TRANSCRIPCIONES DEL MISMO AUDIO (elige la correcta segun contexto clinico; no incluyas esta nota en el informe) ===\n"
         . implode("\n", $lineas);
}