<?php
declare(strict_types=1);

require_once __DIR__ . '/patient_category.php'; // usa limpiar_acentos y helpers previos

function rr_load(string $path): array {
    static $cache = [];
    if (isset($cache[$path])) return $cache[$path];
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    $cache[$path] = $data;
    return $data;
}

function rr_norm(string $s): string {
    return limpiar_acentos(trim(mb_strtolower($s, 'UTF-8')));
}

function rr_canon_key(string $raw, array $aliases): string {
    $k = rr_norm($raw);
    if (isset($aliases[$k])) return $aliases[$k];
    return $k;
}

/**
 * Busca un rango con cadena de fallbacks:
 * 1) especie/size/life_stage
 * 2) especie/_any/life_stage
 * 3) especie/_any/_any
 * 4) _fallback/_global
 */
function rr_get_range(
    array $rr,                 // ref_ranges JSON ya cargado
    ?string $species,          // "canino" | "felino"
    ?string $size,             // "small" | "cat" | ...
    ?string $life,             // "adult" | ...
    string $organRaw,          // texto del dictado ("renal", "riñón", etc.)
    string $measureRaw         // texto del dictado ("grosor pared", "relación...", etc.)
): ?array {
    $aliasesOrg = $rr['aliases']['organ']   ?? [];
    $aliasesMea = $rr['aliases']['measure'] ?? [];

    $organ   = rr_canon_key($organRaw, $aliasesOrg);
    $measure = rr_canon_key($measureRaw, $aliasesMea);

    // 1) especie/size/life
    if ($species && $size && $life && isset($rr[$species][$size][$life][$organ][$measure])) {
        return $rr[$species][$size][$life][$organ][$measure];
    }
    // 2) especie/_any/life
    if ($species && $life && isset($rr[$species]['_any'][$life][$organ][$measure])) {
        return $rr[$species]['_any'][$life][$organ][$measure];
    }
    // 3) especie/_any/_any
    if ($species && isset($rr[$species]['_any']['_any'][$organ][$measure])) {
        return $rr[$species]['_any']['_any'][$organ][$measure];
    }
    // 4) _global fallback
    if (isset($rr['_fallback']['_global'][$organ][$measure])) {
        return $rr['_fallback']['_global'][$organ][$measure];
    }
    return null;
}
