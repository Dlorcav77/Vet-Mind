<?php
require_once("../config.php");

$mysqli  = conn();
global $usuario_id; // dueño de las clínicas

$action = $_POST['action'] ?? '';
$id     = intval($_POST['id'] ?? 0);

if ($action !== 'eliminar') {
  $nombre_clinica = trim($_POST['nombre_clinica'] ?? '');
  $correo         = trim($_POST['correo'] ?? '');
  $telefono       = trim($_POST['telefono'] ?? '');

  // Validaciones
  validar_length("Nombre de la clínica", $nombre_clinica, 150);
  validar_length("Correo", $correo, 255);
  validar_length("Teléfono", $telefono, 50, true); // puede venir vacío
  if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'El correo no es válido.']);
    exit;
  }
}

/**
 * ELIMINAR (hard delete)
 */
if ($action === 'eliminar' && $id > 0) {
  $q = "DELETE FROM clinicas WHERE id = ? AND veterinario_id = ?";
  $stmt = $mysqli->prepare($q);
  $stmt->bind_param('ii', $id, $usuario_id);

  if ($stmt->execute()) {
    logg("Eliminación de clínica ID: $id por veterinario_id: $usuario_id");
    echo json_encode(['status' => 'success', 'message' => 'Clínica eliminada exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la clínica.']);
  }
  exit;
}

/**
 * MODIFICAR
 */
if ($action === 'modificar' && $id > 0) {
  // Verificar duplicado por (veterinario_id, correo)
  $sel = "SELECT id FROM clinicas 
          WHERE correo = ? AND veterinario_id = ? AND id != ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('sii', $correo, $usuario_id, $id);
  $stmt->execute();
  $dups = $stmt->get_result();
  if ($dups->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Ya existe una clínica con ese correo.']);
    exit;
  }

  $upd = "UPDATE clinicas 
          SET nombre_clinica = ?, correo = ?, telefono = ?, updated_at = NOW()
          WHERE id = ? AND veterinario_id = ?";
  $stmt = $mysqli->prepare($upd);
  $stmt->bind_param('sssii', $nombre_clinica, $correo, $telefono, $id, $usuario_id);

  if ($stmt->execute()) {
    logg("Modificación clínica ID: $id, veterinario_id: $usuario_id");
    echo json_encode(['status' => 'success', 'message' => 'Clínica actualizada exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la clínica.']);
  }
  exit;
}

/**
 * INGRESAR
 */
if ($action === 'ingresar') {
  // Duplicado por (veterinario_id, correo)
  $sel = "SELECT id FROM clinicas WHERE correo = ? AND veterinario_id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('si', $correo, $usuario_id);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Ya existe una clínica con ese correo.']);
    exit;
  }

  $ins = "INSERT INTO clinicas (veterinario_id, nombre_clinica, correo, telefono, created_at) 
          VALUES (?, ?, ?, ?, NOW())";
  $stmt = $mysqli->prepare($ins);
  $stmt->bind_param('isss', $usuario_id, $nombre_clinica, $correo, $telefono);

  if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    logg("Inserción de clínica ID: $newId, veterinario_id: $usuario_id, correo: $correo");
    echo json_encode(['status' => 'success', 'message' => 'Clínica ingresada exitosamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al ingresar la clínica.']);
  }
  exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
