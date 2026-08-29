<?php
// admin/usuario/updUsuarios.php

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
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!in_array($action, ['ingresar', 'modificar', 'eliminar'], true)) {
    jexit('error', 'Acción inválida.');
}

switch ($action) {
    case 'ingresar':
        credenciales('usuario', 'ingresar');
        break;
    case 'modificar':
        credenciales('usuario', 'modificar');
        break;
    case 'eliminar':
        credenciales('usuario', 'eliminar');
        break;
}

try {
    if ($action === 'eliminar') {
        if ($id <= 0) jexit('error', 'ID de usuario inválido.');

        $stmt = $mysqli->prepare("UPDATE usuarios SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            jexit('error', 'El usuario no existe o ya se encuentra eliminado.');
        }

        $stmt->close();
        logg("Eliminación de usuario ID: $id");
        jexit('success', 'Usuario eliminado exitosamente.');
    }

    $rut = trim((string)($_POST['rut'] ?? ''));
    $rutDb = $rut !== '' ? $rut : null;
    $nombres = trim((string)($_POST['nombres'] ?? ''));
    $apellidos = trim((string)($_POST['apellidos'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $estado = trim((string)($_POST['estado'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $passwordPlano = (string)($_POST['password'] ?? '');

    if ($rut !== '') validar_length("Rut", $rut, 12);
    validar_length("Estado", $estado, 50);
    validar_length("Nombres", $nombres, 255);
    validar_length("Apellidos", $apellidos, 255);
    validar_length("Teléfono", $telefono, 20);
    validar_length("email", $email, 255);

    if ($action === 'modificar') {
        if ($id <= 0) jexit('error', 'ID de usuario inválido.');

        if ($rutDb !== null) {
            $stmt = $mysqli->prepare("SELECT id FROM usuarios WHERE rut = ? AND id != ? LIMIT 1");
            $stmt->bind_param('si', $rutDb, $id);
            $stmt->execute();
            $resU = $stmt->get_result();
            $stmt->close();

            if ($resU->num_rows > 0) {
                jexit('error', 'El usuario con este rut ya existe.');
            }
        }

        $stmt = $mysqli->prepare("SELECT id FROM usuarios WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $resE = $stmt->get_result();
        $stmt->close();

        if ($resE->num_rows > 0) {
            jexit('error', 'No se puede modificar este usuario, ya ha sido eliminado.');
        }

        if ($passwordPlano !== '') {
            $password = password_hash($passwordPlano, PASSWORD_BCRYPT);

            if ($password === false) {
                throw new RuntimeException('No se pudo generar el hash de contraseña.');
            }

            $stmt = $mysqli->prepare(
                "UPDATE usuarios
                 SET rut = ?, nombres = ?, apellidos = ?, email = ?, estado = ?, telefono = ?, password = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('sssssssi', $rutDb, $nombres, $apellidos, $email, $estado, $telefono, $password, $id);
        } else {
            $stmt = $mysqli->prepare(
                "UPDATE usuarios
                 SET rut = ?, nombres = ?, apellidos = ?, email = ?, estado = ?, telefono = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('ssssssi', $rutDb, $nombres, $apellidos, $email, $estado, $telefono, $id);
        }

        $stmt->execute();
        $stmt->close();

        logg("Modificación de usuario ID: $id");
        jexit('success', 'Usuario actualizado exitosamente.');
    }

    if ($action === 'ingresar') {
        if ($passwordPlano === '') {
            jexit('error', 'La contraseña es obligatoria.');
        }

        $password = password_hash($passwordPlano, PASSWORD_BCRYPT);

        if ($password === false) {
            throw new RuntimeException('No se pudo generar el hash de contraseña.');
        }

        if ($rutDb !== null) {
            $stmt = $mysqli->prepare("SELECT id, deleted_at FROM usuarios WHERE rut = ? LIMIT 1");
            $stmt->bind_param('s', $rutDb);
            $stmt->execute();
            $resU = $stmt->get_result();
            $stmt->close();

            if ($resU->num_rows > 0) {
                $row = $resU->fetch_assoc();

                if ($row['deleted_at'] !== null) {
                    $id = (int)$row['id'];

                    $stmt = $mysqli->prepare(
                        "UPDATE usuarios
                         SET estado = ?, nombres = ?, apellidos = ?, telefono = ?, email = ?, password = ?, deleted_at = NULL, updated_at = NOW()
                         WHERE id = ?"
                    );
                    $stmt->bind_param('ssssssi', $estado, $nombres, $apellidos, $telefono, $email, $password, $id);
                    $stmt->execute();
                    $stmt->close();

                    logg("Usuario reactivado ID: $id");
                    jexit('success', 'Usuario ingresado exitosamente.');
                }

                jexit('error', 'El usuario con este Rut ya existe.');
            }
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO usuarios (rut, estado, nombres, apellidos, telefono, email, password)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssssss', $rutDb, $estado, $nombres, $apellidos, $telefono, $email, $password);
        $stmt->execute();
        $stmt->close();

        logg("Inserción de usuario: $email");
        jexit('success', 'Usuario ingresado exitosamente.');
    }
} catch (Throwable $e) {
    error_log('[updUsuarios] ' . $e->getMessage());
    jexit('error', 'No se pudo completar la operación.');
}