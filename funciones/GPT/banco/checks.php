<?php
// funciones/GPT/banco_comparacion/checks.php
declare(strict_types=1);

function chk_sanitizacion(string $h): array {
    $bad = [];
    if (strpos($h, '```') !== false)      $bad[] = 'trae ``` (fences markdown)';
    if (stripos($h, '<style') !== false)  $bad[] = 'trae <style>';
    foreach (['<html','<head','<body'] as $t) if (stripos($h, $t) !== false) $bad[] = "trae $t";
    return ['dura'=>true, 'ok'=>empty($bad), 'detalle'=>$bad ? implode('; ', $bad) : 'HTML limpio'];
}

function chk_secciones(string $h, array $secciones): array {
    $faltan = [];
    foreach ($secciones as $s) if (mb_stripos($h, $s) === false) $faltan[] = $s;
    return ['dura'=>true, 'ok'=>empty($faltan), 'detalle'=>$faltan ? 'FALTAN: '.implode(', ', $faltan) : 'todas las secciones base presentes'];
}

function chk_flags(string $h): array {
    preg_match_all('/data-flag="(\d+)"/', $h, $mf);
    $flags = array_values(array_unique($mf[1]));
    $obs = '';
    if (($p = mb_stripos($h, 'Observaciones del Asistente')) !== false) $obs = mb_substr($h, $p);
    preg_match_all('/\((\d+)\)/', $obs, $mo);
    $obsNums = array_values(array_unique($mo[1]));
    if (empty($flags) && empty($obsNums)) return ['dura'=>true, 'ok'=>true, 'detalle'=>'sin flags'];
    sort($flags, SORT_NUMERIC); sort($obsNums, SORT_NUMERIC);
    $sinObs  = array_diff($flags, $obsNums);
    $sinFlag = array_diff($obsNums, $flags);
    $ok = empty($sinObs) && empty($sinFlag);
    $d = [];
    if ($sinObs)  $d[] = 'flags sin observación: '.implode(',', $sinObs);
    if ($sinFlag) $d[] = 'observaciones sin flag: '.implode(',', $sinFlag);
    return ['dura'=>true, 'ok'=>$ok, 'detalle'=>$ok ? ('flags ok: '.implode(',', $flags)) : implode('; ', $d)];
}

function chk_medidas(string $dictado, string $h): array {
    preg_match_all('/\d+[.,]\d+/', $dictado, $md);
    $nums = array_values(array_unique(array_map(fn($n)=>str_replace(',', '.', $n), $md[0])));
    $hn = str_replace(',', '.', $h);
    $faltan = [];
    foreach ($nums as $n) if (strpos($hn, $n) === false) $faltan[] = $n;
    return ['dura'=>false, 'ok'=>empty($faltan), 'detalle'=>$faltan ? 'medidas del dictado ausentes en salida: '.implode(', ', $faltan).' (puede ser ruido del audio)' : 'todas las medidas del dictado aparecen'];
}