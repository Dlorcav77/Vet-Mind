<?php
declare(strict_types=1);

function _norm(string $s): string {
    $s = limpiar_acentos(trim(mb_strtolower($s, 'UTF-8')));
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

/**
 * Carga JSON con cache estático.
 */
function load_json_cached(string $path): array {
    static $cache = [];
    if (isset($cache[$path])) return $cache[$path];

    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    $cache[$path] = $data;
    return $data;
}

/**
 * Convierte edad (texto libre) a meses (int) si puede.
 * Acepta: "8 años", "7.5a", "10 meses", "3m", "1 año 6 meses", etc.
 */
function edad_a_meses(?string $edad): ?int {
    if (!$edad) return null;
    $txt = _norm($edad);

    $anos = 0.0; $meses = 0.0;

    // patrones básicos
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*a(?:ños|nos)?/u', $txt, $m)) {
        $anos = (float)str_replace(',', '.', $m[1]);
    }
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*m(?:eses)?/u', $txt, $m)) {
        $meses = (float)str_replace(',', '.', $m[1]);
    }
    // atajos tipo "7a" o "10m"
    if ($anos == 0.0 && preg_match('/^(\d+(?:[.,]\d+)?)\s*a$/u', $txt, $m)) {
        $anos = (float)str_replace(',', '.', $m[1]);
    }
    if ($meses == 0.0 && preg_match('/^(\d+(?:[.,]\d+)?)\s*m$/u', $txt, $m)) {
        $meses = (float)str_replace(',', '.', $m[1]);
    }

    $total = (int) round($anos * 12 + $meses);
    return ($total > 0) ? $total : null;
}

/**
 * Etapa de vida por especie + meses (simple, ajustable).
 * canino: <12 puppy, 12–96 adult, >96 senior
 * felino: <12 kitten, 12–120 adult, >120 senior
 */
function resolver_life_stage(string $especieNorm, ?int $edadMeses): ?string {
    if ($edadMeses === null) return null;

    if ($especieNorm === 'canino') {
        if ($edadMeses < 12) return 'juvenile';
        if ($edadMeses <= 96) return 'adult';
        return 'senior';
    }
    if ($especieNorm === 'felino') {
        if ($edadMeses < 12) return 'juvenile';
        if ($edadMeses <= 120) return 'adult';
        return 'senior';
    }
    return null; // otras especies: decidir luego
}

/**
 * Mapea especie libre → canon ("canino" | "felino" | ...)
 */
function normalizar_especie(string $especie): ?string {
    $e = _norm($especie);
    if ($e === '') return null;
    if (preg_match('/^can/i', $e))  return 'canino';
    if (preg_match('/^fel/i', $e))  return 'felino';
    // ampliar aquí si agregas especies
    return null;
}

/**
 * Busca clase de tamaño por especie+raza en breed_size_map.
 * Devuelve null si no hay mapeo fiable.
 */
function resolver_size_class(?string $especieNorm, string $raza, string $breedMapPath): ?string {
    if (!$especieNorm) return null;

    $data = load_json_cached($breedMapPath);
    if (empty($data[$especieNorm])) return null;

    $razaNorm = _norm($raza);
    $map = $data[$especieNorm]['map'] ?? [];
    $def = $data[$especieNorm]['_default'] ?? null;

    if ($razaNorm !== '' && isset($map[$razaNorm])) {
        return $map[$razaNorm];
    }
    return $def; // para felino devolvemos "cat"; para canino null si no hay
}

/**
 * Resuelve categoría del paciente para usar en rangos.
 * Retorna:
 * - especie_norm
 * - size_class (p. ej., small/medium/large/cat) o null
 * - life_stage (juvenile/adult/senior) o null
 * - edad_meses (int) o null
 * - category_key (p. ej., "canino/small/adult") o null si faltan piezas
 */
function resolve_patient_category(
    string $especie,
    string $raza,
    ?string $edad,
    string $breedMapPath
): array {
    $especieNorm = normalizar_especie($especie);
    $edadMeses   = edad_a_meses($edad);
    $lifeStage   = $especieNorm ? resolver_life_stage($especieNorm, $edadMeses) : null;
    $sizeClass   = resolver_size_class($especieNorm, $raza, $breedMapPath);

    $categoryKey = null;
    if ($especieNorm && $sizeClass && $lifeStage) {
        $categoryKey = "{$especieNorm}/{$sizeClass}/{$lifeStage}";
    }

    return [
        'especie_norm' => $especieNorm,
        'size_class'   => $sizeClass,
        'life_stage'   => $lifeStage,
        'edad_meses'   => $edadMeses,
        'category_key' => $categoryKey
    ];
}
