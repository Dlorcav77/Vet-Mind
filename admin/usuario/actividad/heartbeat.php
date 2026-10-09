<?php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    http_response_code(401);

    echo json_encode([
        'status' => 'expired'
    ]);

    exit;
}

$sessionId = session_id();

if ($sessionId === '') {
    http_response_code(401);

    echo json_encode([
        'status' => 'expired'
    ]);

    exit;
}

$mysqli = conn();

$stmt = $mysqli->prepare(
    "UPDATE usuario_sesiones
     SET ultima_actividad = NOW(),
         cerrada_en = NULL
     WHERE usuario_id = ?
       AND session_id = ?"
);

$stmt->bind_param(
    'is',
    $usuarioId,
    $sessionId
);

$stmt->execute();

if ($stmt->affected_rows === 0) {

    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    $stmt->close();

    $stmt = $mysqli->prepare(
        "INSERT INTO usuario_sesiones (
            usuario_id,
            session_id,
            ip,
            user_agent,
            iniciada_en,
            ultima_actividad,
            cerrada_en
        ) VALUES (?, ?, ?, ?, NOW(), NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            ultima_actividad = NOW(),
            cerrada_en = NULL"
    );

    $stmt->bind_param(
        'isss',
        $usuarioId,
        $sessionId,
        $ip,
        $userAgent
    );

    $stmt->execute();
}

$stmt->close();

echo json_encode([
    'status' => 'success'
]);
