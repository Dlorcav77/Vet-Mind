<?php
// admin/raza_parametros/componentes/crud/delete_organo.php

declare(strict_types=1);

require_once('../../../config.php');

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$mysqli->set_charset('utf8mb4');

function jexit(string $status, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jexit('error', 'Método no permitido.', 405);
}

validarTokenCsrf();
credenciales('raza_parametros', 'eliminar');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    jexit('error', 'ID inválido.');
}

$stmt = $mysqli->prepare("DELETE FROM organos_parametros WHERE id = ?");

if (!$stmt) {
    error_log('[delete_organo] Error prepare: ' . $mysqli->error);
    jexit('error', 'No se pudo preparar la eliminación.', 500);
}

$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    error_log('[delete_organo] Error execute: ' . $stmt->error);
    jexit('error', 'No se pudo eliminar el órgano.', 500);
}

jexit('success', 'Órgano eliminado correctamente.');