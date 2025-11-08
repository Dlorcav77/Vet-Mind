<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action !== 'eliminar') {
  $nombre       = $_POST['nombre'];
  $descripcion  = $_POST['descripcion'];
  $aplicaciones = $_POST['aplicaciones'] ?? [];

  validar_length("Nombre", $nombre, 30);
  validar_length("Descripción", $descripcion, 255);
  if (empty($aplicaciones)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'El campo Aplicaciones no puede estar vacío.'
    ]);
    exit;
  }
}


if ($action === 'eliminar' && !empty($id)) {

  $sel = "SELECT id FROM usuarios_perfil WHERE perfiles_id = ? AND deleted_at IS NULL";
  $stmtP = $mysqli->prepare($sel);
  $stmtP->bind_param('i', $id);
  $stmtP->execute();
  $resP = $stmtP->get_result();

  if ($resP->num_rows > 0) {
      echo json_encode(['status' => 'error', 'message' => 'No se puede eliminar el perfil porque está siendo utilizado por un usuario.']);
      exit;
  }
  
  $delete_query = "UPDATE perfiles SET deleted_at = NOW() WHERE id = ?";
  $stmt = $mysqli->prepare($delete_query);
  $stmt->bind_param('i', $id);

  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Perfil eliminado exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el perfil.']);
  }
  exit;
}



if ($action === 'modificar') {
  // Verificar si ya existe un perfil con el mismo nombre en la misma sede (excepto este ID)
  $sel = "SELECT id FROM perfiles WHERE nombre = ? AND codsede = ? AND id != ? AND deleted_at IS NULL";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('ssi', $nombre, $codsede, $id);
  $stmt->execute();
  $resP = $stmt->get_result();

  if ($resP->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'El perfil con el mismo nombre ya existe.']);
    exit;
  }

  // Actualizar perfil (sin categorías ahora)
  $update_query = "UPDATE perfiles SET nombre = ?, descripcion = ?, updated_at = NOW() WHERE id = ?";
  $stmt = $mysqli->prepare($update_query);
  $stmt->bind_param('ssi', $nombre, $descripcion, $id);

  if ($stmt->execute()) {
    // Eliminar permisos anteriores
    $delete_permisos_query = "DELETE FROM perfiles_permisos WHERE perfil_id = ?";
    $stmt = $mysqli->prepare($delete_permisos_query);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    // Insertar nuevos permisos
    foreach ($aplicaciones as $permiso_id) {
      $insert_permiso_query = "INSERT INTO perfiles_permisos (perfil_id, permiso_id) VALUES (?, ?)";
      $stmt = $mysqli->prepare($insert_permiso_query);
      $stmt->bind_param('ii', $id, $permiso_id);
      $stmt->execute();
    }

    echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el perfil.']);
  }
  exit;
}


if ($action === 'ingresar') {
  // Verificar si ya existe un perfil con el mismo nombre (sin estar eliminado)
  $sel = "SELECT id FROM perfiles WHERE nombre = ? AND codsede = ? AND deleted_at IS NULL";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('ss', $nombre, $codsede);
  $stmt->execute();
  $resP = $stmt->get_result();

  if ($resP->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'El perfil con el mismo nombre ya existe.']);
    exit;
  }

  // Verificar si existe eliminado lógicamente (para reactivar)
  $sel = "SELECT id FROM perfiles WHERE nombre = ? AND codsede = ? AND deleted_at IS NOT NULL";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('ss', $nombre, $codsede);
  $stmt->execute();
  $resEliminado = $stmt->get_result();

  if ($resEliminado->num_rows > 0) {
    $perfilRecuperado = $resEliminado->fetch_assoc();
    $perfil_id = $perfilRecuperado['id'];

    // Reactivar perfil
    $upd = "UPDATE perfiles SET descripcion = ?, deleted_at = NULL, updated_at = NOW() WHERE id = ?";
    $stmt = $mysqli->prepare($upd);
    $stmt->bind_param('si', $descripcion, $perfil_id);
    $stmt->execute();

    // Eliminar permisos anteriores
    $del = "DELETE FROM perfiles_permisos WHERE perfil_id = ?";
    $stmt = $mysqli->prepare($del);
    $stmt->bind_param('i', $perfil_id);
    $stmt->execute();

    // Insertar nuevos permisos
    foreach ($aplicaciones as $permiso_id) {
      $insert_permiso = "INSERT INTO perfiles_permisos (perfil_id, permiso_id) VALUES (?, ?)";
      $stmt = $mysqli->prepare($insert_permiso);
      $stmt->bind_param('ii', $perfil_id, $permiso_id);
      $stmt->execute();
    }

    echo json_encode(['status' => 'success', 'message' => 'Perfil reactivado exitosamente.']);
    exit;
  }

  // Si no existe ni activo ni eliminado, lo crea nuevo
  $ins = "INSERT INTO perfiles (nombre, descripcion, codsede, created_at) VALUES (?, ?, ?, NOW())";
  $stmt = $mysqli->prepare($ins);
  $stmt->bind_param('sss', $nombre, $descripcion, $codsede);

  if ($stmt->execute()) {
    $perfil_id = $stmt->insert_id;

    foreach ($aplicaciones as $permiso_id) {
      $insert_permiso = "INSERT INTO perfiles_permisos (perfil_id, permiso_id) VALUES (?, ?)";
      $stmt = $mysqli->prepare($insert_permiso);
      $stmt->bind_param('ii', $perfil_id, $permiso_id);
      $stmt->execute();
    }

    echo json_encode(['status' => 'success', 'message' => 'Perfil ingresado exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el perfil.']);
  }
  exit;
}


?>
