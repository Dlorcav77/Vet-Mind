<?php
declare(strict_types=1);

/**
 * Resolver de banda de tamaño del paciente.
 * Estrategia:
 *   1) Si viene peso_kg ⇒ asigna banda por umbrales.
 *   2) Si no, si viene raza_id o raza ⇒ consulta tabla `razas` para obtener `tamano`.
 *   3) Si no hay nada ⇒ banda = null (sin validación dura).
 *
 * Devuelve: ['banda'=>?string, 'origen'=>'peso'|'raza'|null, 'peso_rango_teorico'=>?array{min:?float,max:?float}]
 */

function sr_normalizar(?string $s): string {
    $s = $s ?? '';
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $repl = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'
    ];
    return strtr($s, $repl);
}

function sr_canonizar_banda(?string $tamano): ?string {
    $t = sr_normalizar($tamano);
    if ($t === '') return null;

    // Sinónimos comunes
    $map = [
        'mini' => 'miniatura',
        'miniatura' => 'miniatura',
        'toy' => 'miniatura',

        'pequeno' => 'pequeño',
        'pequeño' => 'pequeño',
        'small'   => 'pequeño',

        'mediano' => 'mediano',
        'medio'   => 'mediano',
        'medium'  => 'mediano',
        'normal'  => 'mediano', // <- si tu tabla vieja usa "normal", lo mapeamos a "mediano"

        'grande' => 'grande',
        'large'  => 'grande',
        'xl'     => 'grande',

        'gigante'   => 'gigante',
        'giant'     => 'gigante',
        'muy grande'=> 'gigante',
        'xxl'       => 'gigante',
    ];

    if (isset($map[$t])) return $map[$t];
    foreach ($map as $k => $v) {
        if (str_contains($t, $k)) return $v;
    }
    return null;
}

function sr_banda_por_peso(?float $kg): ?string {
    if ($kg === null || $kg <= 0) return null;
    if ($kg <= 5.0)  return 'miniatura';
    if ($kg <= 10.0) return 'pequeño';
    if ($kg <= 25.0) return 'mediano';
    if ($kg <= 40.0) return 'grande';
    return 'gigante';
}

/**
 * Busca banda por raza en la tabla `razas`.
 * Columnas esperadas: id, especie_id, nombre, tamano
 * @return ?string banda canónica o null
 */
function sr_banda_por_raza(mysqli $db, ?int $especie_id, ?string $raza, ?int $raza_id = null): ?string {
    // 1) Si viene raza_id, usarlo directo
    if ($raza_id) {
        $sql = "SELECT tamano FROM razas WHERE id = ? LIMIT 1";
        if ($st = $db->prepare($sql)) {
            $st->bind_param('i', $raza_id);
            if ($st->execute() && ($rs = $st->get_result()) && ($row = $rs->fetch_assoc())) {
                return sr_canonizar_banda($row['tamano'] ?? null);
            }
        }
    }

    $razaNorm = mb_strtolower((string)$raza, 'UTF-8');
    if ($razaNorm === '') return null;

    // 2) Búsqueda exacta case-insensitive por nombre (y especie si está)
    if ($especie_id) {
        $sql = "SELECT tamano FROM razas WHERE especie_id = ? AND LOWER(nombre) = ? LIMIT 1";
        if ($st = $db->prepare($sql)) {
            $st->bind_param('is', $especie_id, $razaNorm);
            if ($st->execute() && ($rs = $st->get_result()) && ($row = $rs->fetch_assoc())) {
                return sr_canonizar_banda($row['tamano'] ?? null);
            }
        }
    } else {
        $sql = "SELECT tamano FROM razas WHERE LOWER(nombre) = ? LIMIT 1";
        if ($st = $db->prepare($sql)) {
            $st->bind_param('s', $razaNorm);
            if ($st->execute() && ($rs = $st->get_result()) && ($row = $rs->fetch_assoc())) {
                return sr_canonizar_banda($row['tamano'] ?? null);
            }
        }
    }

    // 3) Fallback LIKE (por si hay tildes/espacios/variantes)
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $razaNorm) . '%';
    if ($especie_id) {
        $sql = "SELECT tamano FROM razas WHERE especie_id = ? AND LOWER(nombre) LIKE ? LIMIT 1";
        if ($st = $db->prepare($sql)) {
            $st->bind_param('is', $especie_id, $like);
            if ($st->execute() && ($rs = $st->get_result()) && ($row = $rs->fetch_assoc())) {
                return sr_canonizar_banda($row['tamano'] ?? null);
            }
        }
    } else {
        $sql = "SELECT tamano FROM razas WHERE LOWER(nombre) LIKE ? LIMIT 1";
        if ($st = $db->prepare($sql)) {
            $st->bind_param('s', $like);
            if ($st->execute() && ($rs = $st->get_result()) && ($row = $rs->fetch_assoc())) {
                return sr_canonizar_banda($row['tamano'] ?? null);
            }
        }
    }

    return null;
}

/**
 * Punto de resolución de banda.
 * @return array{banda:?string, origen:?string, peso_rango_teorico:?array}
 */
function resolver_banda_paciente(mysqli $db, array $ctx): array {
    // 1) Intentar por peso (si algún día lo mandas)
    $banda = sr_banda_por_peso($ctx['peso_kg'] ?? null);
    if ($banda) {
        return [
            'banda' => $banda,
            'origen' => 'peso',
            'peso_rango_teorico' => _rango_teorico_por_banda($banda),
        ];
    }

    // 2) Intentar por raza (lo que tú usas hoy)
    $banda = sr_banda_por_raza(
        $db,
        $ctx['especie_id'] ?? null,
        $ctx['raza'] ?? null,
        $ctx['raza_id'] ?? null
    );
    if ($banda) {
        return [
            'banda' => $banda,
            'origen' => 'raza',
            'peso_rango_teorico' => _rango_teorico_por_banda($banda),
        ];
    }

    // 3) Sin datos → sin validación dura
    return ['banda' => null, 'origen' => null, 'peso_rango_teorico' => null];
}

/**
 * Opcional: rango teórico de peso por banda (SOLO informativo; no se usa en validaciones).
 */
function _rango_teorico_por_banda(string $banda): ?array {
    switch ($banda) {
        case 'miniatura': return ['min'=>0.0,  'max'=>5.0];
        case 'pequeño':   return ['min'=>5.0,  'max'=>10.0];
        case 'mediano':   return ['min'=>10.0, 'max'=>25.0];
        case 'grande':    return ['min'=>25.0, 'max'=>40.0];
        case 'gigante':   return ['min'=>40.0, 'max'=>null];
        default: return null;
    }
}
