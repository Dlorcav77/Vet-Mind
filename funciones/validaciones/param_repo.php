<?php
/**
 * Busca un registro en organos_parametros por especie + banda (tamano) + etapa + organo.
 * Aplica fallbacks si no encuentra coincidencia exacta.
 * Devuelve la fila + los límites convertidos a MM (si venían en cm).
 */
function op_get_param(
    mysqli $db,
    int $especie_id,
    ?string $banda,     // miniatura|pequeño|mediano|grande|gigante|normal
    ?string $etapa,     // adulto|cachorro|null
    string $organo_key  // ej "rinon derecho" (canonizado)
): ?array {
    // Tu tabla usa "tamano" y a veces "normal" -> mapeamos a "mediano"
    $bandaNorm = $banda ? sr_canonizar_banda($banda) : null;
    if ($bandaNorm === 'mediano' && $banda === 'normal') {
        $bandaNorm = 'mediano';
    }

    // helper: intento con filtros opcionales
    $try = function(?string $tamano, ?string $etapaTry) use ($db, $especie_id, $organo_key): ?array {
        if ($tamano === null && $etapaTry === null) {
            // Fallback 100% general: solo filas sin banda y sin etapa
            $sql = "SELECT organo, organo_key, especie_id, tamano, etapa,
                        tamano_min, tamano_max, tamano_min_critico, tamano_max_critico, unidad
                    FROM organos_parametros
                    WHERE estado=1
                    AND especie_id=?
                    AND organo_key=?
                    AND tamano IS NULL
                    AND etapa IS NULL
                    LIMIT 1";
            $st = $db->prepare($sql);
            $st->bind_param('is', $especie_id, $organo_key);
        } elseif ($tamano === null) {
            // Fallback sin banda pero con etapa → solo filas sin banda
            $sql = "SELECT organo, organo_key, especie_id, tamano, etapa,
                        tamano_min, tamano_max, tamano_min_critico, tamano_max_critico, unidad
                    FROM organos_parametros
                    WHERE estado=1
                    AND especie_id=?
                    AND organo_key=?
                    AND tamano IS NULL
                    AND etapa = ?
                    LIMIT 1";
            $st = $db->prepare($sql);
            $st->bind_param('iss', $especie_id, $organo_key, $etapaTry);
        } elseif ($etapaTry === null) {
            // Match exacto de banda, sin etapa
            $sql = "SELECT organo, organo_key, especie_id, tamano, etapa,
                        tamano_min, tamano_max, tamano_min_critico, tamano_max_critico, unidad
                    FROM organos_parametros
                    WHERE estado=1
                    AND especie_id=?
                    AND organo_key=?
                    AND tamano = ?
                    AND etapa IS NULL
                    LIMIT 1";
            $st = $db->prepare($sql);
            $st->bind_param('iss', $especie_id, $organo_key, $tamano);
        } else {
            // Match exacto banda+etapa
            $sql = "SELECT organo, organo_key, especie_id, tamano, etapa,
                        tamano_min, tamano_max, tamano_min_critico, tamano_max_critico, unidad
                    FROM organos_parametros
                    WHERE estado=1
                    AND especie_id=?
                    AND organo_key=?
                    AND tamano = ?
                    AND etapa  = ?
                    LIMIT 1";
            $st = $db->prepare($sql);
            $st->bind_param('isss', $especie_id, $organo_key, $tamano, $etapaTry);
        }

        $st->execute();
        $rs = $st->get_result();
        $row = $rs->fetch_assoc() ?: null;
        if (!$row) return null;

        $u = strtolower($row['unidad'] ?? 'mm');
        $k = ($u === 'cm') ? 10.0 : 1.0;
        foreach (['tamano_min','tamano_max','tamano_min_critico','tamano_max_critico'] as $col) {
            $row[$col.'_mm'] = ($row[$col] !== null && $row[$col] !== '') ? floatval($row[$col]) * $k : null;
        }
        $row['unidad_base'] = $u;
        return $row;
    };


    // 1) Match exacto: banda + etapa
    if (($r = $try($bandaNorm, $etapa))) return $r;
    // 2) Match sin etapa
    if (($r = $try($bandaNorm, null)))   return $r;
    // 3) Match sin banda
    if (($r = $try(null, $etapa)))       return $r;
    // 4) Match general (organo solo)
    if (($r = $try(null, null)))         return $r;

    return null;
}
