<?php
// admin/plantilla_informe/updPlantillaInforme.php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();

function jexit(string $status, string $message): void
{
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function plantilla_pertenece_al_veterinario(mysqli $db, int $id, int $veterinarioId): bool
{
    $stmt = $db->prepare(
        "SELECT id
         FROM plantilla_informe
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
        credenciales('plantilla_informe', 'ingresar');
        break;
    case 'modificar':
        credenciales('plantilla_informe', 'modificar');
        break;
    case 'eliminar':
        credenciales('plantilla_informe', 'eliminar');
        break;
}

try {
    if ($action === 'eliminar') {
        if ($id <= 0) {
            jexit('error', 'Plantilla inválida.');
        }

        if (!plantilla_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
            jexit('error', 'No tienes permiso para eliminar esta plantilla.');
        }

        $stmt = $mysqli->prepare(
            "UPDATE plantilla_informe
             SET deleted_at = NOW()
             WHERE id = ?
               AND veterinario_id = ?
               AND deleted_at IS NULL"
        );
        $stmt->bind_param('ii', $id, $veterinarioId);
        $stmt->execute();
        $stmt->close();

        logg("Eliminación de plantilla_informe ID: $id");
        jexit('success', 'Plantilla eliminada exitosamente.');
    }

    $tipoExamenId = isset($_POST['tipo_examen_id']) ? (int)$_POST['tipo_examen_id'] : 0;
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $contenido = trim((string)($_POST['contenido'] ?? ''));
    $estado = trim((string)($_POST['estado'] ?? ''));

    validar_length("Nombre", $nombre, 100);
    validar_length("Estado", $estado, 10);

    if ($tipoExamenId <= 0) {
        jexit('error', 'Tipo de examen inválido.');
    }

    if ($action === 'modificar') {
        if ($id <= 0) {
            jexit('error', 'Plantilla inválida.');
        }

        if (!plantilla_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
            jexit('error', 'No tienes permiso para modificar esta plantilla.');
        }

        $stmt = $mysqli->prepare(
            "SELECT id
             FROM plantilla_informe
             WHERE nombre = ?
               AND veterinario_id = ?
               AND id != ?
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->bind_param('sii', $nombre, $veterinarioId, $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'Ya existe una plantilla con este nombre.');
        }

        $stmt = $mysqli->prepare(
            "UPDATE plantilla_informe
             SET tipo_examen_id = ?, nombre = ?, contenido = ?, estado = ?, updated_at = NOW()
             WHERE id = ?
               AND veterinario_id = ?"
        );
        $stmt->bind_param('isssii', $tipoExamenId, $nombre, $contenido, $estado, $id, $veterinarioId);
        $stmt->execute();
        $stmt->close();

        logg("Modificación de plantilla_informe ID: $id por veterinario: $veterinarioId");
        jexit('success', 'Plantilla actualizada exitosamente.');
    }

    if ($action === 'ingresar') {
        $stmt = $mysqli->prepare(
            "SELECT id, deleted_at
             FROM plantilla_informe
             WHERE nombre = ?
               AND veterinario_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('si', $nombre, $veterinarioId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();

            if ($row['deleted_at'] !== null) {
                $id = (int)$row['id'];

                $stmt = $mysqli->prepare(
                    "UPDATE plantilla_informe
                     SET tipo_examen_id = ?, contenido = ?, estado = ?, deleted_at = NULL, updated_at = NOW()
                     WHERE id = ?
                       AND veterinario_id = ?"
                );
                $stmt->bind_param('issii', $tipoExamenId, $contenido, $estado, $id, $veterinarioId);
                $stmt->execute();
                $stmt->close();

                logg("Reactivación de plantilla_informe ID: $id por veterinario: $veterinarioId");
                jexit('success', 'Plantilla reactivada exitosamente.');
            }

            jexit('error', 'Ya existe una plantilla con este nombre.');
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO plantilla_informe
                (veterinario_id, tipo_examen_id, nombre, contenido, estado)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iisss', $veterinarioId, $tipoExamenId, $nombre, $contenido, $estado);
        $stmt->execute();
        $stmt->close();

        logg("Inserción de plantilla_informe: $nombre por veterinario: $veterinarioId");
        jexit('success', 'Plantilla ingresada exitosamente.');
    }
} catch (Throwable $e) {
    error_log('[updPlantillaInforme] ' . $e->getMessage());
    jexit('error', 'No se pudo completar la operación.');
}