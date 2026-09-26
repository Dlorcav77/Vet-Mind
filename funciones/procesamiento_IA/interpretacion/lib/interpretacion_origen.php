<?php
declare(strict_types=1);

/**
 * Recupera las transcripciones asociadas al usuario y al flujo.
 * Comprueba que correspondan exactamente al dictado recibido.
 */
function interpretacion_cargar_origen(
    mysqli $mysqli,
    int $usuarioId,
    string $flujoId,
    string $textoRecibido
): ?array {
    $flujoId = trim($flujoId);
    $textoRecibido = trim($textoRecibido);

    if ($usuarioId <= 0 || $flujoId === '' || $textoRecibido === '') {
        return null;
    }

    $sql = 'SELECT
                id, flujo_id, motor_a, motor_b,
                texto_a, texto_b, texto_doble,
                resueltas_json, discrepancias_json
            FROM ia_transcripciones
            WHERE usuario_id = ? AND flujo_id = ?
            ORDER BY id DESC
            LIMIT 1';

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('is', $usuarioId, $flujoId);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        return null;
    }

    $textoOriginal = trim(
        (string)$fila['texto_a'] . (string)$fila['texto_doble']
    );

    if ($textoOriginal !== $textoRecibido) {
        return null;
    }

    $decodificar = static function (?string $json): array {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $datos = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($datos) || !array_is_list($datos)) {
            throw new UnexpectedValueException(
                'Formato de datos STT no válido.'
            );
        }

        return $datos;
    };

    $resueltas = $decodificar($fila['resueltas_json']);
    $discrepancias = $decodificar($fila['discrepancias_json']);

    unset($fila['resueltas_json'], $fila['discrepancias_json']);

    $fila['id'] = (int)$fila['id'];
    $fila['resueltas'] = $resueltas;
    $fila['discrepancias'] = $discrepancias;

    return $fila;
}