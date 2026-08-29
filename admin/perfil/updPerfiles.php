<?php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();
$mysqli->set_charset('utf8mb4');

function jexit(string $status, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function reemplazarPermisos(mysqli $mysqli, int $perfilId, array $permisos): void
{
    $stmt = $mysqli->prepare("DELETE FROM perfiles_permisos WHERE perfil_id = ?");
    $stmt->bind_param('i', $perfilId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO perfiles_permisos (perfil_id, permiso_id) VALUES (?, ?)");
    $permisoId = 0;
    $stmt->bind_param('ii', $perfilId, $permisoId);

    foreach ($permisos as $permisoId) {
        $stmt->execute();
    }

    $stmt->close();
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
        credenciales('perfil', 'ingresar');
        break;

    case 'modificar':
        credenciales('perfil', 'modificar');
        break;

    case 'eliminar':
        credenciales('perfil', 'eliminar');
        break;
}

if ($action === 'eliminar') {
    if ($id <= 0) {
        jexit('error', 'ID de perfil inválido.');
    }

    try {
        $stmt = $mysqli->prepare(
            "SELECT id
             FROM usuarios_perfil
             WHERE perfiles_id = ?
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'No se puede eliminar el perfil porque está siendo utilizado por un usuario.');
        }

        $stmt = $mysqli->prepare(
            "UPDATE perfiles
             SET deleted_at = NOW()
             WHERE id = ?
               AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            jexit('error', 'El perfil no existe o ya se encuentra eliminado.');
        }

        $stmt->close();

        jexit('success', 'Perfil eliminado exitosamente.');
    } catch (Throwable $e) {
        error_log('[updPerfiles][eliminar] ' . $e->getMessage());
        jexit('error', 'Error al eliminar el perfil.');
    }
}

$nombre = trim((string)($_POST['nombre'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));
$aplicaciones = $_POST['aplicaciones'] ?? [];

if ($nombre === '') {
    jexit('error', 'El campo Nombre no puede estar vacío.');
}

validar_length("Nombre", $nombre, 30);
validar_length("Descripción", $descripcion, 255);

if (!is_array($aplicaciones)) {
    $aplicaciones = [$aplicaciones];
}

$aplicaciones = array_values(array_unique(array_filter(
    array_map('intval', $aplicaciones),
    static fn(int $permisoId): bool => $permisoId > 0
)));

if (empty($aplicaciones)) {
    jexit('error', 'El campo Aplicaciones no puede estar vacío.');
}

if ($action === 'modificar') {
    if ($id <= 0) {
        jexit('error', 'ID de perfil inválido.');
    }

    try {
        $stmt = $mysqli->prepare(
            "SELECT id
             FROM perfiles
             WHERE nombre = ?
               AND id != ?
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bind_param('si', $nombre, $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'El perfil con el mismo nombre ya existe.');
        }

        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare(
            "UPDATE perfiles
             SET nombre = ?, descripcion = ?, updated_at = NOW()
             WHERE id = ?
               AND deleted_at IS NULL"
        );
        $stmt->bind_param('ssi', $nombre, $descripcion, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();

            $stmt = $mysqli->prepare(
                "SELECT id
                 FROM perfiles
                 WHERE id = ?
                   AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existe) {
                throw new RuntimeException('Perfil inexistente o eliminado.');
            }
        } else {
            $stmt->close();
        }

        reemplazarPermisos($mysqli, $id, $aplicaciones);

        $mysqli->commit();

        jexit('success', 'Perfil actualizado exitosamente.');
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log('[updPerfiles][modificar] ' . $e->getMessage());
        jexit('error', 'Error al actualizar el perfil.');
    }
}

if ($action === 'ingresar') {
    try {
        $stmt = $mysqli->prepare(
            "SELECT id
             FROM perfiles
             WHERE nombre = ?
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'El perfil con el mismo nombre ya existe.');
        }

        $stmt = $mysqli->prepare(
            "SELECT id
             FROM perfiles
             WHERE nombre = ?
               AND deleted_at IS NOT NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $resEliminado = $stmt->get_result();
        $stmt->close();

        $mysqli->begin_transaction();

        if ($resEliminado->num_rows > 0) {
            $perfilRecuperado = $resEliminado->fetch_assoc();
            $perfilId = (int)$perfilRecuperado['id'];

            $stmt = $mysqli->prepare(
                "UPDATE perfiles
                 SET nombre = ?, descripcion = ?, deleted_at = NULL, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('ssi', $nombre, $descripcion, $perfilId);
            $stmt->execute();
            $stmt->close();

            reemplazarPermisos($mysqli, $perfilId, $aplicaciones);

            $mysqli->commit();

            jexit('success', 'Perfil reactivado exitosamente.');
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO perfiles (nombre, descripcion, created_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->bind_param('ss', $nombre, $descripcion);
        $stmt->execute();

        $perfilId = (int)$stmt->insert_id;
        $stmt->close();

        reemplazarPermisos($mysqli, $perfilId, $aplicaciones);

        $mysqli->commit();

        jexit('success', 'Perfil ingresado exitosamente.');
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log('[updPerfiles][ingresar] ' . $e->getMessage());
        jexit('error', 'Error al ingresar el perfil.');
    }
}

jexit('error', 'Acción inválida.');