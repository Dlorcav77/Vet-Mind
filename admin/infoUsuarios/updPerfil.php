<?php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();

function jexit(string $status, string $message): void
{
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jexit('error', 'Método no permitido.');
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? ''));

if ($action !== 'modificar') {
    jexit('error', 'Acción no permitida.');
}

$id = (int)$usuario_id;
$telefono = trim((string)($_POST['telefono'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));

validar_length("Teléfono", $telefono, 20);
validar_length("Correo", $email, 255);

try {
    $stmt = $mysqli->prepare(
        "UPDATE usuarios
         SET telefono = ?, email = ?
         WHERE id = ?
           AND deleted_at IS NULL"
    );
    $stmt->bind_param('ssi', $telefono, $email, $id);
    $stmt->execute();
    $stmt->close();

    jexit('success', 'Perfil actualizado exitosamente.');
} catch (Throwable $e) {
    error_log('[updPerfil] ' . $e->getMessage());
    jexit('error', 'Error al actualizar el perfil.');
}