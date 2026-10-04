<?php
declare(strict_types=1);

/**
 * Guarda una ejecución de interpretación clínica.
 * Admite resultados PHP e IA, tanto exitosos como fallidos.
 */
function interpretacion_guardar_resultado(mysqli $mysqli, array $datos): int
{
    $usuarioId = (int)($datos['usuario_id'] ?? 0);
    $transcripcionId = !empty($datos['transcripcion_id'])
        ? (int)$datos['transcripcion_id']
        : null;

    $flujoId = trim((string)($datos['flujo_id'] ?? ''));
    $textoEntrada = (string)($datos['texto_entrada'] ?? '');
    $motor = trim((string)($datos['motor'] ?? ''));
    $modelo = $datos['modelo'] ?? null;
    $estado = (string)($datos['estado'] ?? 'completado');
    $resultado = $datos['resultado'] ?? null;

    if ($usuarioId <= 0 || trim($textoEntrada) === '' || $motor === '') {
        throw new InvalidArgumentException(
            'Faltan datos obligatorios de la interpretación.'
        );
    }

    if (!in_array($estado, ['completado', 'fallido'], true)) {
        throw new InvalidArgumentException('Estado de interpretación no válido.');
    }

    if ($estado === 'completado' && !is_array($resultado)) {
        throw new InvalidArgumentException(
            'Una interpretación completada requiere un resultado JSON.'
        );
    }

    if ($resultado !== null && !is_array($resultado)) {
        throw new InvalidArgumentException('Resultado de interpretación no válido.');
    }

    // Verificar la propiedad de la transcripción, cuando exista.
    if ($transcripcionId !== null) {
        $stmt = $mysqli->prepare(
            'SELECT id FROM ia_transcripciones
             WHERE id = ? AND usuario_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $transcripcionId, $usuarioId);
        $stmt->execute();
        $encontrada = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$encontrada) {
            throw new RuntimeException(
                'La transcripción no pertenece al usuario.'
            );
        }
    }

    $resultadoJson = $resultado === null
        ? null
        : json_encode(
            $resultado,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

    $flujoId = $flujoId !== '' ? $flujoId : null;
    $versionEsquema = (string)(
        $resultado['version_esquema']
        ?? $datos['version_esquema']
        ?? '1'
    );
    $versionPrompt = $datos['version_prompt'] ?? null;
    $errorDetalle = $datos['error_detalle'] ?? null;
    $promptTokens = max(0, (int)($datos['prompt_tokens'] ?? 0));
    $completionTokens = max(0, (int)($datos['completion_tokens'] ?? 0));
    $costUsd = round(max(0, (float)($datos['cost_usd'] ?? 0)), 6);
    $duracionMs = isset($datos['duracion_ms'])
        ? max(0, (int)$datos['duracion_ms'])
        : null;

    $sql = 'INSERT INTO ia_interpretaciones (
        transcripcion_id, usuario_id, flujo_id, texto_entrada,
        resultado_json, motor, modelo, version_esquema,
        version_prompt, estado, error_detalle, prompt_tokens,
        completion_tokens, cost_usd, duracion_ms
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'No se pudo preparar el registro de interpretación.'
        );
    }

    $tipos = 'ii' . str_repeat('s', 9) . 'iidi';

    $stmt->bind_param(
        $tipos,
        $transcripcionId,
        $usuarioId,
        $flujoId,
        $textoEntrada,
        $resultadoJson,
        $motor,
        $modelo,
        $versionEsquema,
        $versionPrompt,
        $estado,
        $errorDetalle,
        $promptTokens,
        $completionTokens,
        $costUsd,
        $duracionMs
    );

    try {
        if (!$stmt->execute()) {
            throw new RuntimeException(
                'No se pudo guardar la interpretación.'
            );
        }

        return (int)$stmt->insert_id;
    } finally {
        $stmt->close();
    }
}

/**
 * Recupera la última interpretación completada asociada
 * al usuario, flujo y dictado exacto.
 */
function interpretacion_cargar_resultado_flujo(
    mysqli $mysqli,
    int $usuarioId,
    string $flujoId,
    string $textoEntrada
): ?array {
    $flujoId = trim($flujoId);
    $textoEntrada = trim($textoEntrada);

    if (
        $usuarioId <= 0
        || $flujoId === ''
        || $textoEntrada === ''
    ) {
        return null;
    }

    $sql = "
        SELECT
            id,
            transcripcion_id,
            motor,
            modelo,
            version_esquema,
            version_prompt,
            resultado_json
        FROM ia_interpretaciones
        WHERE usuario_id = ?
          AND flujo_id = ?
          AND estado = 'completado'
          AND texto_entrada = ?
        ORDER BY id DESC
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'No se pudo preparar la lectura de interpretación.'
        );
    }

    $stmt->bind_param(
        'iss',
        $usuarioId,
        $flujoId,
        $textoEntrada
    );

    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$fila) {
        return null;
    }

    $resultado = json_decode(
        (string)($fila['resultado_json'] ?? ''),
        true
    );

    if (!is_array($resultado)) {
        return null;
    }

    unset($fila['resultado_json']);

    $fila['id'] = (int)$fila['id'];

    $fila['transcripcion_id'] =
        $fila['transcripcion_id'] !== null
            ? (int)$fila['transcripcion_id']
            : null;

    $fila['resultado'] = $resultado;

    return $fila;
}