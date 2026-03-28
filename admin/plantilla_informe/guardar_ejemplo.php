<?php
//admin/plantilla_informe/guardar_ejemplo.php
require_once("../config.php");
$mysqli = conn();

$action = $_POST['action'] ?? '';
if ($action === 'agregar') {
    $plantilla_id = intval($_POST['plantilla_informe_id'] ?? 0);
    $ejemplo = trim($_POST['ejemplo'] ?? '');
    if (!$plantilla_id || !$ejemplo) {
        echo json_encode(['status' => 'error', 'message' => 'Datos faltantes.']);
        exit;
    }
    $stmt = $mysqli->prepare("INSERT INTO plantilla_informe_ejemplo (plantilla_informe_id, ejemplo) VALUES (?, ?)");
    $stmt->bind_param('is', $plantilla_id, $ejemplo);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Ejemplo agregado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al agregar.']);
    }
    exit;
}

if ($action === 'editar_individual') {
    $id = intval($_POST['id'] ?? 0);
    $ejemplo = trim($_POST['ejemplo'] ?? '');
    if (!$id || !$ejemplo) {
        echo json_encode(['status' => 'error', 'message' => 'Datos faltantes.']);
        exit;
    }
    $stmt = $mysqli->prepare("UPDATE plantilla_informe_ejemplo SET ejemplo=? WHERE id=?");
    $stmt->bind_param('si', $ejemplo, $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Ejemplo actualizado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar.']);
    }
    exit;
}


if ($action === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID faltante.']);
        exit;
    }
    $stmt = $mysqli->prepare("DELETE FROM plantilla_informe_ejemplo WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Ejemplo eliminado.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);
