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
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$plantilla_id = (int)($_POST['plantilla_informe_id'] ?? 0);
$certificado_id = (int)($_POST['certificado_id'] ?? 0);
$veterinario_contexto = $usuario_id;

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

if ($plantilla_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Plantilla no proporcionada.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($certificado_id > 0) {
    $stmtContexto = $mysqli->prepare("
        SELECT c.veterinario_id
        FROM certificados c
        LEFT JOIN certificado_compartidos cc
            ON cc.certificado_id = c.id
            AND cc.usuario_id = ?
            AND cc.estado = 'activo'
        WHERE c.id = ?
          AND (
              c.veterinario_id = ?
              OR (
                  cc.id IS NOT NULL
                  AND cc.puede_ver = 1
                  AND cc.puede_editar = 1
              )
          )
        LIMIT 1
    ");

    if (!$stmtContexto) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo validar el acceso al informe.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtContexto->bind_param(
        'iii',
        $usuario_id,
        $certificado_id,
        $usuario_id
    );

    $stmtContexto->execute();
    $rowContexto = $stmtContexto->get_result()->fetch_assoc();
    $stmtContexto->close();

    if (!$rowContexto) {
        http_response_code(403);

        echo json_encode([
            'status' => 'error',
            'message' => 'No tienes permiso para modificar este informe.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $veterinario_contexto = (int)$rowContexto['veterinario_id'];
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

$stmt->bind_param('ii', $plantilla_id, $veterinario_contexto);

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