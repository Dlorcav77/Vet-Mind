<?php
// admin/plantilla_informe/guardar_ejemplo.php

require_once("../config.php");

$mysqli = conn();
$veterinario_id = (int)$usuario_id;

function responderEjemplo(string $status, string $message): void
{
    echo json_encode([
        'status' => $status,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderEjemplo('error', 'Método no permitido.');
}

validarTokenCsrf();

if ($veterinario_id <= 0) {
    http_response_code(401);
    responderEjemplo('error', 'Sesión inválida.');
}

$action = trim((string)($_POST['action'] ?? ''));

if (!in_array($action, ['agregar', 'editar_individual', 'eliminar'], true)) {
    responderEjemplo('error', 'Acción no reconocida.');
}

/* AGREGAR */
if ($action === 'agregar') {
    credenciales('plantilla_informe', 'ingresar');

    $plantilla_id = (int)($_POST['plantilla_informe_id'] ?? 0);
    $ejemplo = trim((string)($_POST['ejemplo'] ?? ''));

    if ($plantilla_id <= 0 || $ejemplo === '') {
        responderEjemplo('error', 'Datos faltantes.');
    }

    $stmtPlantilla = $mysqli->prepare(
        "SELECT id
         FROM plantilla_informe
         WHERE id = ?
           AND veterinario_id = ?
           AND deleted_at IS NULL
         LIMIT 1"
    );

    if (!$stmtPlantilla) {
        error_log('[guardar_ejemplo][agregar][plantilla_prepare] ' . $mysqli->error);
        responderEjemplo('error', 'No se pudo validar la plantilla.');
    }

    $stmtPlantilla->bind_param('ii', $plantilla_id, $veterinario_id);

    if (!$stmtPlantilla->execute()) {
        error_log('[guardar_ejemplo][agregar][plantilla_execute] ' . $stmtPlantilla->error);
        $stmtPlantilla->close();
        responderEjemplo('error', 'No se pudo validar la plantilla.');
    }

    $existePlantilla = $stmtPlantilla->get_result()->num_rows > 0;
    $stmtPlantilla->close();

    if (!$existePlantilla) {
        http_response_code(403);
        responderEjemplo('error', 'Plantilla no encontrada o sin permisos.');
    }

    $stmt = $mysqli->prepare(
        "INSERT INTO plantilla_informe_ejemplo
            (plantilla_informe_id, ejemplo)
         VALUES (?, ?)"
    );

    if (!$stmt) {
        error_log('[guardar_ejemplo][agregar][prepare] ' . $mysqli->error);
        responderEjemplo('error', 'No se pudo preparar el ejemplo.');
    }

    $stmt->bind_param('is', $plantilla_id, $ejemplo);

    if (!$stmt->execute()) {
        error_log('[guardar_ejemplo][agregar][execute] ' . $stmt->error);
        $stmt->close();
        responderEjemplo('error', 'Error al agregar.');
    }

    $stmt->close();
    responderEjemplo('success', 'Ejemplo agregado correctamente.');
}

/* EDITAR */
if ($action === 'editar_individual') {
    credenciales('plantilla_informe', 'modificar');

    $id = (int)($_POST['id'] ?? 0);
    $ejemplo = trim((string)($_POST['ejemplo'] ?? ''));

    if ($id <= 0 || $ejemplo === '') {
        responderEjemplo('error', 'Datos faltantes.');
    }

    $stmt = $mysqli->prepare(
        "UPDATE plantilla_informe_ejemplo e
         INNER JOIN plantilla_informe p
            ON p.id = e.plantilla_informe_id
         SET e.ejemplo = ?
         WHERE e.id = ?
           AND p.veterinario_id = ?
           AND p.deleted_at IS NULL"
    );

    if (!$stmt) {
        error_log('[guardar_ejemplo][editar][prepare] ' . $mysqli->error);
        responderEjemplo('error', 'No se pudo preparar la actualización.');
    }

    $stmt->bind_param('sii', $ejemplo, $id, $veterinario_id);

    if (!$stmt->execute()) {
        error_log('[guardar_ejemplo][editar][execute] ' . $stmt->error);
        $stmt->close();
        responderEjemplo('error', 'Error al actualizar.');
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        responderEjemplo('error', 'Ejemplo no encontrado, sin permisos o sin cambios.');
    }

    $stmt->close();
    responderEjemplo('success', 'Ejemplo actualizado correctamente.');
}

/* ELIMINAR */
credenciales('plantilla_informe', 'eliminar');

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    responderEjemplo('error', 'ID inválido.');
}

$stmt = $mysqli->prepare(
    "DELETE e
     FROM plantilla_informe_ejemplo e
     INNER JOIN plantilla_informe p
        ON p.id = e.plantilla_informe_id
     WHERE e.id = ?
       AND p.veterinario_id = ?
       AND p.deleted_at IS NULL"
);

if (!$stmt) {
    error_log('[guardar_ejemplo][eliminar][prepare] ' . $mysqli->error);
    responderEjemplo('error', 'No se pudo preparar la eliminación.');
}

$stmt->bind_param('ii', $id, $veterinario_id);

if (!$stmt->execute()) {
    error_log('[guardar_ejemplo][eliminar][execute] ' . $stmt->error);
    $stmt->close();
    responderEjemplo('error', 'Error al eliminar.');
}

if ($stmt->affected_rows === 0) {
    $stmt->close();
    responderEjemplo('error', 'Ejemplo no encontrado o sin permisos.');
}

$stmt->close();
responderEjemplo('success', 'Ejemplo eliminado.');