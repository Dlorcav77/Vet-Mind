<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action !== 'eliminar') {
  $rut       = $_POST['rut'];
  $nombres   = $_POST['nombres'];
  $apellidos = $_POST['apellidos'];
  $email     = $_POST['email'];
  $estado    = $_POST['estado'];
  $telefono  = $_POST['telefono'];
  $password  = password_hash($_POST['password'], PASSWORD_BCRYPT);

  validar_length("Rut", $rut, 12);
  validar_length("Estado", $estado, 50);
  validar_length("Nombres", $nombres, 255);
  validar_length("Apellidos", $apellidos, 255);
  validar_length("Teléfono", $telefono, 20);
  validar_length("email", $email, 255);
}



if ($action === 'eliminar' && !empty($id)) {

  $delete_query = "UPDATE usuarios SET deleted_at = NOW() WHERE id = ?";
  $stmt = $mysqli->prepare($delete_query);
  $stmt->bind_param('i', $id);

  if ($stmt->execute()) {
      logg("Eliminación de usuario ID: $id ");
      echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado exitosamente.']);
  } else {
      echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el usuario.']);
  }
  exit;
}



if ($action === 'modificar') {
  validar_length("Password", $password, 255, true);

  $sel = "SELECT id FROM usuarios WHERE rut = ?  AND id != ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('si', $rut, $id);
  $stmt->execute();
  $resU = $stmt->get_result();

  if ($resU->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'El usuario con este rut ya existe.']);
    exit;
  }

  $selE = "SELECT id FROM usuarios WHERE id = ? AND deleted_at IS NOT NULL";
  $stmt = $mysqli->prepare($selE);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $resE = $stmt->get_result();

  if ($resE->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'No se puede modificar este usuario, ya ha sido eliminado.']);
    exit;
  }

  $update_query = "UPDATE usuarios SET rut = ?, nombres = ?, apellidos = ?, email = ?, estado = ?, telefono = ?, updated_at = NOW()";

  if (!empty($password)) {
    $update_query .= ", password = ?";
    $stmt = $mysqli->prepare($update_query . " WHERE id = ?");
    $stmt->bind_param('sssssssi', $rut, $nombres, $apellidos, $email, $estado, $telefono, $password, $id);
  } else {
    $stmt = $mysqli->prepare($update_query . " WHERE id = ?");
    $stmt->bind_param('ssssssi', $rut, $nombres, $apellidos, $email, $estado, $telefono, $id);
  }

  if ($stmt->execute()) {
    logg("Modificación de usuario ID: $id con RUT: $rut ");
    echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el usuario.']);
  }
  exit;
}


if ($action === 'ingresar') {
  validar_length("Password", $password, 255);

  $sel = "SELECT id, deleted_at FROM usuarios WHERE rut = ? ";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('s', $rut,);
  $stmt->execute();
  $resU = $stmt->get_result();

  if ($resU->num_rows > 0) {
    $row = $resU->fetch_assoc();

    if (!is_null($row['deleted_at'])) {
      $id = $row['id'];
      $update_query = "UPDATE usuarios SET estado = ?, nombres = ?, apellidos = ?, telefono = ?, email = ?, password = ?, deleted_at = NULL, updated_at = NOW() WHERE id = ?";
      $stmt = $mysqli->prepare($update_query);
      $stmt->bind_param('ssssssi', $estado, $nombres, $apellidos, $telefono, $email, $password, $id);

      if ($stmt->execute()) {
        logg("Inserción de usuario: $email con RUT: $rut. reactivado");
        echo json_encode(['status' => 'success', 'message' => 'Usuario ingresado exitosamente.']);
      } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el usuario eliminado.']);
      }
    } else {
      echo json_encode(['status' => 'error', 'message' => 'El usuario con este Rut ya existe.']);
    }
    exit;
  }

  $ins = "INSERT INTO usuarios (rut, estado, nombres, apellidos, telefono, email, password) 
          VALUES (?, ?, ?, ?, ?, ?, ?)";
  $stmt = $mysqli->prepare($ins);
  $stmt->bind_param('sssssss', $rut, $estado, $nombres, $apellidos, $telefono, $email, $password);

  if ($stmt->execute()) {
    logg("Inserción de usuario: $email con RUT: $rut ");
    echo json_encode(['status' => 'success', 'message' => 'Usuario ingresado exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el usuario.']);
  }
}

?>
