<?php
// admin/certificado/configuracion/get_campos_visibles.php

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
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$configuracion_informe_id = (int)($_POST['configuracion_informe_id'] ?? 0);

if (!$mysqli) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($usuario_id <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($configuracion_informe_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Plantilla de diseño inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT id, recinto_default
     FROM configuracion_informes
     WHERE id = ? AND veterinario_id = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log('[get_campos_visibles][config][prepare] ' . $mysqli->error);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo validar la plantilla de diseño.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt->bind_param('ii', $configuracion_informe_id, $usuario_id);

if (!$stmt->execute()) {
    error_log('[get_campos_visibles][config][execute] ' . $stmt->error);
    $stmt->close();

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo validar la plantilla de diseño.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$config) {
    http_response_code(403);

    echo json_encode([
        'status' => 'error',
        'message' => 'La plantilla no pertenece al veterinario actual.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT x.campo
     FROM (
        SELECT
            cp.id AS campo_id,
            cp.campo,
            MIN(cic.orden) AS orden_min,
            MIN(cic.id) AS id_min
        FROM configuracion_informe_campos cic
        INNER JOIN campos_permitidos cp ON cp.id = cic.campo_id
        WHERE cic.configuracion_informe_id = ?
          AND cic.veterinario_id = ?
          AND cic.visible = 1
        GROUP BY cp.id, cp.campo
     ) x
     ORDER BY x.orden_min ASC, x.id_min ASC"
);

if (!$stmt) {
    error_log('[get_campos_visibles][campos][prepare] ' . $mysqli->error);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudieron consultar los campos visibles.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt->bind_param('ii', $configuracion_informe_id, $usuario_id);

if (!$stmt->execute()) {
    error_log('[get_campos_visibles][campos][execute] ' . $stmt->error);
    $stmt->close();

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudieron consultar los campos visibles.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$res = $stmt->get_result();
$campos = [];

while ($row = $res->fetch_assoc()) {
    $campos[] = $row['campo'];
}

$stmt->close();

$recinto_default = trim((string)($config['recinto_default'] ?? ''));
$recinto_visible = in_array('recinto', $campos, true);

if (!$recinto_visible && $recinto_default === '') {
    $campos[] = 'recinto';
}

echo json_encode([
    'status' => 'success',
    'campos' => $campos
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;