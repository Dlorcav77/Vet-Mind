<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action !== 'eliminar') {
  $usuario_id   = $_POST['usuario'] ?? '';
  $perfil_id    = $_POST['perfil'] ?? '';
  $fecha_inicio = $_POST['fecha_inicio'] ?? '';
  $fecha_termino = $_POST['fecha_termino'] ?? null; // opcional
  $estado       = $_POST['estado'] ?? 'activo';

  // print"estado :m $estado";

  validar_length("Usuario", $usuario_id, 11);
  validar_length("Perfil", $perfil_id, 11); 
  validar_length("Fecha Inicio", $fecha_inicio, 10);
  validar_length("Fecha Termino", $fecha_termino, 10, true);
  validar_length("Estado", $estado, 11);
}







if ($action === 'eliminar' && !empty($id)) {
  $delete_query = "DELETE FROM usuarios_perfil WHERE id = ?";
  $stmt = $mysqli->prepare($delete_query);
  $stmt->bind_param('i', $id);

  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Asignación eliminada exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la asignación.']);
  }
  exit;
}







if ($action === 'modificar') {

  $sel = "SELECT id FROM usuarios_perfil WHERE usuario_id = ? AND perfiles_id = ? AND id != ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('iii', $usuario_id, $perfil_id, $id);
  $stmt->execute();
  $resP = $stmt->get_result();

  if ($resP->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'El usuario ya tiene asignado este perfil.']);
    exit;
  }

  if (!empty($fecha_termino)) {
      $update_query = "UPDATE usuarios_perfil 
                      SET usuario_id = ?, perfiles_id = ?, fecha_inicio = ?, fecha_termino = ?, estado = ?, updated_at = NOW() 
                      WHERE id = ?";
      $stmt = $mysqli->prepare($update_query);
      $stmt->bind_param('iisssi', $usuario_id, $perfil_id, $fecha_inicio, $fecha_termino, $estado, $id);
  } else {
      $update_query = "UPDATE usuarios_perfil 
                      SET usuario_id = ?, perfiles_id = ?, fecha_inicio = ?, estado = ?, updated_at = NOW() 
                      WHERE id = ?";
      $stmt = $mysqli->prepare($update_query);
      $stmt->bind_param('iissi', $usuario_id, $perfil_id, $fecha_inicio, $estado, $id);
  }

  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Asignación actualizada exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la asignación.']);
  }
  exit;
}







if ($action === 'ingresar') {
  $sel = "SELECT id FROM usuarios_perfil WHERE usuario_id = ? AND perfiles_id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('ii', $usuario_id, $perfil_id);
  $stmt->execute();
  $resP = $stmt->get_result();

  if ($resP->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'El usuario ya tiene este perfil asignado.']);
    exit;
  }

  if (empty($fecha_termino)) {
    $ins = "INSERT INTO usuarios_perfil (usuario_id, perfiles_id, fecha_inicio, estado, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('iiss', $usuario_id, $perfil_id, $fecha_inicio, $estado);
  } else {
    $ins = "INSERT INTO usuarios_perfil (usuario_id, perfiles_id, fecha_inicio, fecha_termino, estado, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('iisss', $usuario_id, $perfil_id, $fecha_inicio, $fecha_termino, $estado);
  }
  
//   $ins = "INSERT INTO usuarios_perfil (usuario_id, perfiles_id, fecha_inicio, fecha_termino, estado, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
//   $stmt = $mysqli->prepare($ins);
//   $stmt->bind_param('iisss', $usuario_id, $perfil_id, $fecha_inicio, $fecha_termino, $estado);
// print"$ins  --  $usuario_id, $perfil_id, $fecha_inicio, $fecha_termino, $estado";
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Perfil asignado exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al asignar el perfil.']);
  }
}

?>
