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
$passwordActual = (string)($_POST['password_actual'] ?? '');
$passwordNueva = (string)($_POST['password_nueva'] ?? '');
$passwordRepetida = (string)($_POST['password_repetida'] ?? '');

if ($passwordActual === '' || $passwordNueva === '' || $passwordRepetida === '') {
    jexit('error', 'Debes completar todos los campos de contraseña.');
}

if ($passwordNueva !== $passwordRepetida) {
    jexit('error', 'Las contraseñas no coinciden.');
}

try {
    $stmt = $mysqli->prepare(
        "SELECT password
         FROM usuarios
         WHERE id = ?
           AND deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $fila = $res->fetch_assoc();
    $stmt->close();

    if (!$fila || !password_verify($passwordActual, (string)$fila['password'])) {
        jexit('error', 'La contraseña actual es incorrecta.');
    }

    $passwordHash = password_hash($passwordNueva, PASSWORD_BCRYPT);

    if ($passwordHash === false) {
        throw new RuntimeException('No se pudo generar el hash.');
    }

    $stmt = $mysqli->prepare(
        "UPDATE usuarios
         SET password = ?
         WHERE id = ?
           AND deleted_at IS NULL"
    );
    $stmt->bind_param('si', $passwordHash, $id);
    $stmt->execute();
    $stmt->close();

    jexit('success', 'Contraseña actualizada exitosamente.');
} catch (Throwable $e) {
    error_log('[updPassword] ' . $e->getMessage());
    jexit('error', 'Error al actualizar la contraseña.');
}