<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id     = $_POST['id'] ?? '';

if ($action !== 'eliminar') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado      = trim($_POST['estado'] ?? 'activo');

    validar_length("Nombre", $nombre, 255);
    validar_length("Descripción", $descripcion, 500, true);
    validar_length("Estado", $estado, 50);
}

// 🗑 Eliminar
if ($action === 'eliminar' && !empty($id)) {
    $delete_query = "DELETE FROM tipo_examen WHERE id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Eliminación de tipo_examen ID: $id");
        echo json_encode(['status' => 'success', 'message' => 'Tipo de examen eliminado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el tipo de examen.']);
    }
    exit;
}

// ✏️ Modificar
if ($action === 'modificar' && !empty($id)) {
    // Validar duplicado
    $sel = "SELECT id FROM tipo_examen WHERE nombre = ? AND id != ? and veterinario_id = ?";
    $stmt = $mysqli->prepare($sel);
    $stmt->bind_param('sii', $nombre, $id, $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe un tipo de examen con este nombre.']);
        exit;
    }

    $update_query = "UPDATE tipo_examen SET veterinario_id = ?, nombre = ?, descripcion = ?, estado = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('isssi', $usuario_id, $nombre, $descripcion, $estado, $id);

    if ($stmt->execute()) {
        logg("Modificación de tipo_examen ID: $id");
        echo json_encode(['status' => 'success', 'message' => 'Tipo de examen actualizado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el tipo de examen.']);
    }
    exit;
}

// ➕ Ingresar
if ($action === 'ingresar') {
    // Validar duplicado
    $sel = "SELECT id FROM tipo_examen WHERE nombre = ? and veterinario_id = ?";
    $stmt = $mysqli->prepare($sel);
    $stmt->bind_param('si', $nombre, $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe un tipo de examen con este nombre.']);
        exit;
    }

    $ins = "INSERT INTO tipo_examen (veterinario_id, nombre, descripcion, estado, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('isss', $usuario_id, $nombre, $descripcion, $estado);

    if ($stmt->execute()) {
        logg("Inserción de tipo_examen: $nombre");
        echo json_encode(['status' => 'success', 'message' => 'Tipo de examen ingresado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el tipo de examen.']);
    }
}
?>
