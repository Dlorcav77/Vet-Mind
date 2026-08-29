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
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$veterinarioId = (int)$usuario_id;

if (!in_array($action, ['ingresar', 'modificar', 'eliminar'], true)) {
    jexit('error', 'Acción no válida.');
}

switch ($action) {
    case 'ingresar':
        credenciales('clinicas', 'ingresar');
        break;
    case 'modificar':
        credenciales('clinicas', 'modificar');
        break;
    case 'eliminar':
        credenciales('clinicas', 'eliminar');
        break;
}

try {
    if ($action === 'eliminar') {
        if ($id <= 0) {
            jexit('error', 'Clínica inválida.');
        }

        $stmt = $mysqli->prepare(
            "DELETE FROM clinicas
             WHERE id = ?
               AND veterinario_id = ?"
        );
        $stmt->bind_param('ii', $id, $veterinarioId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            jexit('error', 'La clínica no existe o no tienes permiso para eliminarla.');
        }

        $stmt->close();

        logg("Eliminación de clínica ID: $id por veterinario_id: $veterinarioId");
        jexit('success', 'Clínica eliminada exitosamente.');
    }

    $nombreClinica = trim((string)($_POST['nombre_clinica'] ?? ''));
    $correo = trim((string)($_POST['correo'] ?? ''));
    $correoDb = $correo !== '' ? $correo : null;
    $telefono = trim((string)($_POST['telefono'] ?? ''));

    validar_length("Nombre de la clínica", $nombreClinica, 150);
    validar_length("Teléfono", $telefono, 50, true);

    if ($correo !== '') {
        validar_length("Correo", $correo, 255);

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            jexit('error', 'El correo no es válido.');
        }
    }

    if ($action === 'modificar') {
        if ($id <= 0) {
            jexit('error', 'Clínica inválida.');
        }

        if ($correoDb !== null) {
            $stmt = $mysqli->prepare(
                "SELECT id
                 FROM clinicas
                 WHERE correo = ?
                   AND veterinario_id = ?
                   AND id != ?
                 LIMIT 1"
            );
            $stmt->bind_param('sii', $correoDb, $veterinarioId, $id);
            $stmt->execute();
            $dups = $stmt->get_result();
            $stmt->close();

            if ($dups->num_rows > 0) {
                jexit('error', 'Ya existe una clínica con ese correo.');
            }
        }

        $stmt = $mysqli->prepare(
            "UPDATE clinicas
             SET nombre_clinica = ?, correo = ?, telefono = ?, updated_at = NOW()
             WHERE id = ?
               AND veterinario_id = ?"
        );
        $stmt->bind_param('sssii', $nombreClinica, $correoDb, $telefono, $id, $veterinarioId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();

            $stmt = $mysqli->prepare(
                "SELECT id
                 FROM clinicas
                 WHERE id = ?
                   AND veterinario_id = ?
                 LIMIT 1"
            );
            $stmt->bind_param('ii', $id, $veterinarioId);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existe) {
                jexit('error', 'La clínica no existe o no tienes permiso para modificarla.');
            }
        } else {
            $stmt->close();
        }

        logg("Modificación clínica ID: $id, veterinario_id: $veterinarioId");
        jexit('success', 'Clínica actualizada exitosamente.');
    }

    if ($action === 'ingresar') {
        if ($correoDb !== null) {
            $stmt = $mysqli->prepare(
                "SELECT id
                 FROM clinicas
                 WHERE correo = ?
                   AND veterinario_id = ?
                 LIMIT 1"
            );
            $stmt->bind_param('si', $correoDb, $veterinarioId);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();

            if ($res->num_rows > 0) {
                jexit('error', 'Ya existe una clínica con ese correo.');
            }
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO clinicas
                (veterinario_id, nombre_clinica, correo, telefono, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('isss', $veterinarioId, $nombreClinica, $correoDb, $telefono);
        $stmt->execute();

        $newId = (int)$stmt->insert_id;
        $stmt->close();

        logg("Inserción de clínica ID: $newId, veterinario_id: $veterinarioId");
        jexit('success', 'Clínica ingresada exitosamente.');
    }
} catch (Throwable $e) {
    error_log('[updClinicas] ' . $e->getMessage());
    jexit('error', 'No se pudo completar la operación.');
}