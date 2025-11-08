<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action !== 'eliminar') {
    $veterinario_id  = $_POST['veterinario_id'];
    $nombre_completo = trim($_POST['nombre_completo']);
    $rut             = trim($_POST['rut']);
    $telefono        = trim($_POST['telefono']);
    $email           = trim($_POST['email']);
    $direccion       = trim($_POST['direccion']);

    validar_length("Nombre completo", $nombre_completo, 150);
    validar_length("Rut", $rut, 12, true);
    validar_length("Teléfono", $telefono, 20, true);
    validar_length("Email", $email, 100, true);
    validar_length("Dirección", $direccion, 200, true);
}

// Soft delete
if ($action === 'eliminar' && !empty($id)) {
    $delete_query = "DELETE FROM tutores WHERE id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Eliminación de tutor ID: $id");
        echo json_encode(['status' => 'success', 'message' => 'Tutor eliminado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el tutor.']);
    }
    exit;
}

// Modificar
if ($action === 'modificar') {
    // $sel = "SELECT id FROM tutores WHERE rut = ? AND veterinario_id = ? AND id != ?";
    // $stmt = $mysqli->prepare($sel);
    // $stmt->bind_param('sii', $rut, $veterinario_id, $id);
    // $stmt->execute();
    // $res = $stmt->get_result();

    // if ($res->num_rows > 0) {
    //     echo json_encode(['status' => 'error', 'message' => 'Ya existe un tutor con este RUT.']);
    //     exit;
    // }

    $update_query = "UPDATE tutores 
                     SET nombre_completo = ?, rut = ?, telefono = ?, email = ?, direccion = ?, updated_at = NOW() 
                     WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('sssssi', $nombre_completo, $rut, $telefono, $email, $direccion, $id);

    if ($stmt->execute()) {
        logg("Modificación de tutor ID: $id, RUT: $rut");
        echo json_encode(['status' => 'success', 'message' => 'Tutor actualizado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el tutor.']);
    }
    exit;
}

// Ingresar
if ($action === 'ingresar') {
    // $sel = "SELECT id FROM tutores WHERE rut = ? AND veterinario_id = ?";
    // $stmt = $mysqli->prepare($sel);
    // $stmt->bind_param('si', $rut, $veterinario_id);
    // $stmt->execute();
    // $res = $stmt->get_result();

    // if ($res->num_rows > 0) {
    //     echo json_encode(['status' => 'error', 'message' => 'Ya existe un tutor con este RUT.']);
    //     exit;
    // }

    $ins = "INSERT INTO tutores (veterinario_id, nombre_completo, rut, telefono, email, direccion) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('isssss', $veterinario_id, $nombre_completo, $rut, $telefono, $email, $direccion);

    if ($stmt->execute()) {
        logg("Inserción de tutor: $nombre_completo, RUT: $rut");
        echo json_encode(['status' => 'success', 'message' => 'Tutor ingresado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el tutor.']);
    }
}
?>
