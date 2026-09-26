<?php
declare(strict_types=1);

/**
 * Vincula un informe generado con una variante del banco de pruebas.
 * Comprueba la propiedad de la prueba y la correspondencia del flujo.
 */
function banco_registrar_resultado(
    mysqli $mysqli,
    int $pruebaId,
    int $usuarioId,
    string $modo,
    string $rid,
    ?int $interpretacionId,
    int $duracionMs
): int {
    if ($pruebaId <= 0 || $usuarioId <= 0 || trim($rid) === '') {
        throw new InvalidArgumentException('Datos de prueba no válidos.');
    }

    if (!in_array($modo, ['actual', 'php', 'ia'], true)) {
        throw new InvalidArgumentException('Modo de comparación no válido.');
    }

    if (($modo === 'actual') !== ($interpretacionId === null)) {
        throw new InvalidArgumentException(
            'El identificador de interpretación no corresponde al modo.'
        );
    }

    // Localizar exactamente el informe de esta prueba.
    $stmt = $mysqli->prepare(
        'SELECT r.id
         FROM ia_requests r
         INNER JOIN ia_pruebas p ON p.flujo_id = r.flujo_id
         WHERE p.id = ?
           AND p.usuario_id = ?
           AND p.flujo_id IS NOT NULL
           AND p.flujo_id <> ""
           AND r.rid = ?
           AND r.tipo = "informe"
         LIMIT 1'
    );

    $stmt->bind_param('iis', $pruebaId, $usuarioId, $rid);
    $stmt->execute();
    $informe = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$informe) {
        throw new RuntimeException(
            'El informe no existe o no corresponde al flujo de la prueba.'
        );
    }

    // Validar también la interpretación asociada, cuando corresponda.
    if ($interpretacionId !== null) {
        $stmt = $mysqli->prepare(
            'SELECT i.id
             FROM ia_interpretaciones i
             INNER JOIN ia_pruebas p
                ON p.flujo_id = i.flujo_id
             WHERE p.id = ?
               AND p.usuario_id = ?
               AND i.id = ?
               AND i.usuario_id = ?
               AND i.estado = "completado"
               AND (
                   p.transcripcion_id IS NULL
                   OR p.transcripcion_id = i.transcripcion_id
               )
             LIMIT 1'
        );

        $stmt->bind_param(
            'iiii',
            $pruebaId,
            $usuarioId,
            $interpretacionId,
            $usuarioId
        );
        $stmt->execute();
        $interpretacion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$interpretacion) {
            throw new RuntimeException(
                'La interpretación no corresponde a esta prueba.'
            );
        }
    }

    $requestId = (int)$informe['id'];
    $duracionMs = max(0, $duracionMs);

    $stmt = $mysqli->prepare(
        'INSERT INTO ia_prueba_resultados
            (prueba_id, modo, interpretacion_id, ia_request_id,
             estado, duracion_total_ms)
         VALUES (?, ?, ?, ?, "completado", ?)'
    );

    $stmt->bind_param(
        'isiii',
        $pruebaId,
        $modo,
        $interpretacionId,
        $requestId,
        $duracionMs
    );

    try {
        if (!$stmt->execute()) {
            throw new RuntimeException(
                'No se pudo registrar el resultado.'
            );
        }

        return (int)$stmt->insert_id;
    } finally {
        $stmt->close();
    }
}
