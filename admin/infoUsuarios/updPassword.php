<?php
require_once("../config.php");

$mysqli = conn();
$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action === 'modificar' && !empty($id)) {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_repetida = $_POST['password_repetida'];

    // Verificar que las nuevas contraseñas coincidan
    if ($password_nueva !== $password_repetida) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden.']);
        exit;
    }

    // Consultar la contraseña actual en la base de datos
    $sel = "SELECT password FROM usuarios WHERE id = ?";
    $stmt = $mysqli->prepare($sel);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $fila = $res->fetch_assoc();

    if (!password_verify($password_actual, $fila['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña actual es incorrecta.']);
        exit;
    }

    // Actualizar la contraseña
    $password_hashed = password_hash($password_nueva, PASSWORD_BCRYPT);
    $update_query = "UPDATE usuarios SET password = ? WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('si', $password_hashed, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la contraseña.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Acción no permitida.']);
}
?>
