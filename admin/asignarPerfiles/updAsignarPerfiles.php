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

if (!in_array($action, ['ingresar', 'modificar', 'eliminar'], true)) {
    jexit('error', 'Acción no válida.');
}

switch ($action) {
    case 'ingresar':
        credenciales('asignarPerfiles', 'ingresar');
        break;
    case 'modificar':
        credenciales('asignarPerfiles', 'modificar');
        break;
    case 'eliminar':
        credenciales('asignarPerfiles', 'eliminar');
        break;
}

try {
    if ($action === 'eliminar') {
        if ($id <= 0) {
            jexit('error', 'Asignación inválida.');
        }

        $stmt = $mysqli->prepare("DELETE FROM usuarios_perfil WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            jexit('error', 'La asignación no existe.');
        }

        $stmt->close();
        jexit('success', 'Asignación eliminada exitosamente.');
    }

    $usuarioId = isset($_POST['usuario']) ? (int)$_POST['usuario'] : 0;
    $perfilId = isset($_POST['perfil']) ? (int)$_POST['perfil'] : 0;
    $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
    $fechaTermino = trim((string)($_POST['fecha_termino'] ?? ''));
    $fechaTerminoDb = $fechaTermino !== '' ? $fechaTermino : null;
    $estado = trim((string)($_POST['estado'] ?? 'activo'));

    if ($usuarioId <= 0) {
        jexit('error', 'Usuario inválido.');
    }

    if ($perfilId <= 0) {
        jexit('error', 'Perfil inválido.');
    }

    validar_length("Fecha Inicio", $fechaInicio, 10);
    validar_length("Fecha Termino", $fechaTermino, 10, true);
    validar_length("Estado", $estado, 11);

    $stmt = $mysqli->prepare(
        "SELECT id
         FROM usuarios
         WHERE id = ?
           AND deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $usuarioExiste = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$usuarioExiste) {
        jexit('error', 'El usuario no existe o se encuentra eliminado.');
    }

    $stmt = $mysqli->prepare(
        "SELECT id
         FROM perfiles
         WHERE id = ?
           AND deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->bind_param('i', $perfilId);
    $stmt->execute();
    $perfilExiste = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$perfilExiste) {
        jexit('error', 'El perfil no existe o se encuentra eliminado.');
    }

    if ($action === 'modificar') {
        if ($id <= 0) {
            jexit('error', 'Asignación inválida.');
        }

        $stmt = $mysqli->prepare(
            "SELECT id
             FROM usuarios_perfil
             WHERE usuario_id = ?
               AND perfiles_id = ?
               AND id != ?
             LIMIT 1"
        );
        $stmt->bind_param('iii', $usuarioId, $perfilId, $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'El usuario ya tiene asignado este perfil.');
        }

        $stmt = $mysqli->prepare(
            "UPDATE usuarios_perfil
             SET usuario_id = ?, perfiles_id = ?, fecha_inicio = ?, fecha_termino = ?, estado = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('iisssi', $usuarioId, $perfilId, $fechaInicio, $fechaTerminoDb, $estado, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();

            $stmt = $mysqli->prepare("SELECT id FROM usuarios_perfil WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existe) {
                jexit('error', 'La asignación no existe.');
            }
        } else {
            $stmt->close();
        }

        jexit('success', 'Asignación actualizada exitosamente.');
    }

    if ($action === 'ingresar') {
        $stmt = $mysqli->prepare(
            "SELECT id
             FROM usuarios_perfil
             WHERE usuario_id = ?
               AND perfiles_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $usuarioId, $perfilId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'El usuario ya tiene este perfil asignado.');
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO usuarios_perfil
                (usuario_id, perfiles_id, fecha_inicio, fecha_termino, estado, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('iisss', $usuarioId, $perfilId, $fechaInicio, $fechaTerminoDb, $estado);
        $stmt->execute();
        $stmt->close();

        jexit('success', 'Perfil asignado exitosamente.');
    }
} catch (Throwable $e) {
    error_log('[updAsignarPerfiles] ' . $e->getMessage());
    jexit('error', 'No se pudo completar la operación.');
}