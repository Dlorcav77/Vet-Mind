<?php
declare(strict_types=1);

/**
 * Costo STT por minuto según ia_pricing_stt.
 * Devuelve USD para la duración dada (en segundos).
 */
function stt_costo_por_motor(mysqli $mysqli, string $motor, float $duracion_seg): float
{
    static $cache = [];

    if (!array_key_exists($motor, $cache)) {
        $cache[$motor] = null;
        if ($stmt = $mysqli->prepare(
            'SELECT price_min FROM ia_pricing_stt
             WHERE motor = ? AND activo = 1
             ORDER BY vigente_desde DESC LIMIT 1'
        )) {
            $stmt->bind_param('s', $motor);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $cache[$motor] = (float)$row['price_min'];
            }
            $stmt->close();
        }
    }

    if ($cache[$motor] === null) {
        return 0.0;
    }

    return round(($duracion_seg / 60.0) * $cache[$motor], 6);
}

/**
 * Guarda una transcripción (1 fila por audio, motores A y B).
 * Devuelve el id insertado, o 0 si falla (no rompe el flujo).
 */
function stt_guardar_transcripcion(mysqli $mysqli, array $d): int
{
    $audio_tmp = (string)($d['audio_tmp'] ?? '');
    if ($audio_tmp === '') {
        return 0;
    }

    $motor_a = (string)($d['motor_a'] ?? '');
    $motor_b = (string)($d['motor_b'] ?? '');
    $texto_a = (string)($d['texto_a'] ?? '');
    $texto_b = (string)($d['texto_b'] ?? '');
    $texto_doble = (string)($d['texto_doble'] ?? '');

    $disc_json = isset($d['discrepancias'])
        ? json_encode($d['discrepancias'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $dur_a = (float)($d['duracion_seg_a'] ?? 0);
    $dur_b = (float)($d['duracion_seg_b'] ?? 0);

    $cost_a = stt_costo_por_motor($mysqli, $motor_a, $dur_a);
    $cost_b = stt_costo_por_motor($mysqli, $motor_b, $dur_b);
    $cost_total = round($cost_a + $cost_b, 6);

    $created = date('Y-m-d H:i:s'); // PHP en America/Santiago

    $sql = 'INSERT INTO ia_transcripciones
        (audio_tmp, certificado_id, motor_a, motor_b, texto_a, texto_b, texto_doble,
         discrepancias_json, duracion_seg_a, duracion_seg_b, cost_a, cost_b, cost_total, created_at)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param(
        'sssssssddddds',
        $audio_tmp,
        $motor_a,
        $motor_b,
        $texto_a,
        $texto_b,
        $texto_doble,
        $disc_json,
        $dur_a,
        $dur_b,
        $cost_a,
        $cost_b,
        $cost_total,
        $created
    );

    $ok = $stmt->execute();
    $id = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();

    return $id;
}

/**
 * Enlaza una transcripción con el certificado creado después (por audio_tmp).
 */
function stt_link_certificado(mysqli $mysqli, string $audio_tmp, int $certificado_id): bool
{
    if ($audio_tmp === '' || $certificado_id <= 0) {
        return false;
    }

    $stmt = $mysqli->prepare(
        'UPDATE ia_transcripciones SET certificado_id = ? WHERE audio_tmp = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $certificado_id, $audio_tmp);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}