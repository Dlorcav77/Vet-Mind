<?php
require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

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

$query = "
    SELECT contenido
    FROM plantilla_informe
    WHERE id = ?
      AND veterinario_id = ?
      AND estado = 'activo'
      AND deleted_at IS NULL
    LIMIT 1
";

$stmt = $mysqli->prepare($query);

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error preparando consulta de plantilla.',
        'mysql_error' => $mysqli->error
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt->bind_param("ii", $plantilla_id, $veterinario_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'status' => 'success',
        'contenido' => (string)($row['contenido'] ?? '')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'No se encontró una plantilla activa para este tipo de examen.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;