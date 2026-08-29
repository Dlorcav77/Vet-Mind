<?php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();

function jexit(string $status, string $message): void
{
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function examen_pertenece_al_veterinario(mysqli $db, int $id, int $veterinarioId): bool
{
    $stmt = $db->prepare(
        "SELECT id
         FROM tipo_examen
         WHERE id = ?
           AND veterinario_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $id, $veterinarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    return $res->num_rows > 0;
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
    jexit('error', 'Acción inválida.');
}

switch ($action) {
    case 'ingresar':
        credenciales('examenes', 'ingresar');
        break;
    case 'modificar':
        credenciales('examenes', 'modificar');
        break;
    case 'eliminar':
        credenciales('examenes', 'eliminar');
        break;
}

try {
    if ($action === 'eliminar') {
        if ($id <= 0) {
            jexit('error', 'Tipo de examen inválido.');
        }

        if (!examen_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
            jexit('error', 'No tienes permiso para eliminar este tipo de examen.');
        }

        $stmt = $mysqli->prepare(
            "DELETE FROM tipo_examen
             WHERE id = ?
               AND veterinario_id = ?"
        );
        $stmt->bind_param('ii', $id, $veterinarioId);
        $stmt->execute();
        $stmt->close();

        logg("Eliminación de tipo_examen ID: $id");
        jexit('success', 'Tipo de examen eliminado exitosamente.');
    }

    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $estado = trim((string)($_POST['estado'] ?? 'activo'));

    validar_length("Nombre", $nombre, 255);
    validar_length("Descripción", $descripcion, 500, true);
    validar_length("Estado", $estado, 50);

    if ($action === 'modificar') {
        if ($id <= 0) {
            jexit('error', 'Tipo de examen inválido.');
        }

        if (!examen_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
            jexit('error', 'No tienes permiso para modificar este tipo de examen.');
        }

        $stmt = $mysqli->prepare(
            "SELECT id
             FROM tipo_examen
             WHERE nombre = ?
               AND id != ?
               AND veterinario_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('sii', $nombre, $id, $veterinarioId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'Ya existe un tipo de examen con este nombre.');
        }

        $stmt = $mysqli->prepare(
            "UPDATE tipo_examen
             SET nombre = ?, descripcion = ?, estado = ?, updated_at = NOW()
             WHERE id = ?
               AND veterinario_id = ?"
        );
        $stmt->bind_param('sssii', $nombre, $descripcion, $estado, $id, $veterinarioId);
        $stmt->execute();
        $stmt->close();

        logg("Modificación de tipo_examen ID: $id");
        jexit('success', 'Tipo de examen actualizado exitosamente.');
    }

    if ($action === 'ingresar') {
        $stmt = $mysqli->prepare(
            "SELECT id
             FROM tipo_examen
             WHERE nombre = ?
               AND veterinario_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('si', $nombre, $veterinarioId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'Ya existe un tipo de examen con este nombre.');
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO tipo_examen
                (veterinario_id, nombre, descripcion, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->bind_param('isss', $veterinarioId, $nombre, $descripcion, $estado);
        $stmt->execute();
        $stmt->close();

        logg("Inserción de tipo_examen: $nombre");
        jexit('success', 'Tipo de examen ingresado exitosamente.');
    }
} catch (Throwable $e) {
    error_log('[updExamenes] ' . $e->getMessage());
    jexit('error', 'No se pudo completar la operación.');
}