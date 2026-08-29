<?php
// admin/certificado/tipo_examen/getPlantillaPorTipo.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

validarTokenCsrf();
credenciales('certificado', 'listar');

$mysqli = conn();
$veterinario_id = (int)($_SESSION['usuario_id'] ?? 0);
$plantilla_id = (int)($_POST['plantilla_informe_id'] ?? 0);

if (!$mysqli) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($veterinario_id <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($plantilla_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Plantilla no proporcionada.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT contenido
     FROM plantilla_informe
     WHERE id = ?
       AND veterinario_id = ?
       AND estado = 'activo'
       AND deleted_at IS NULL
     LIMIT 1"
);

if (!$stmt) {
    error_log('[getPlantillaPorTipo][prepare] ' . $mysqli->error);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo consultar la plantilla.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt->bind_param('ii', $plantilla_id, $veterinario_id);

if (!$stmt->execute()) {
    error_log('[getPlantillaPorTipo][execute] ' . $stmt->error);
    $stmt->close();

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo consultar la plantilla.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    echo json_encode([
        'status' => 'success',
        'contenido' => (string)($row['contenido'] ?? '')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(404);

echo json_encode([
    'status' => 'error',
    'message' => 'No se encontró una plantilla activa para este tipo de examen.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;