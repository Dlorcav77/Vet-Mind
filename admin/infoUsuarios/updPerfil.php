<?php
require_once("../config.php");

$mysqli = conn();
$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action === 'modificar' && !empty($id)) {
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    validar_length("Teléfono", $telefono, 20);
    validar_length("Correo", $email, 255);

    $update_query = "UPDATE usuarios SET telefono = ?, email = ? WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('ssi', $telefono, $email, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el perfil.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Acción no permitida.']);
}
?>
