<?php
require_once("../../../../funciones/conn/conn.php");

$mysqli = conn();

$especie_id = intval($_POST['especie_id']);
$nombre = trim($_POST['nombre_raza']);
$tamano = trim($_POST['tamano_raza']);

if (!$nombre || !$especie_id) {
  echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
  exit;
}

$nombre = $mysqli->real_escape_string($nombre);
$tamano_sql = $tamano ? "'".$mysqli->real_escape_string($tamano)."'" : "NULL";

$raza_id = isset($_POST['raza_id']) ? intval($_POST['raza_id']) : 0;

if ($raza_id > 0) {
  // UPDATE
  $q = "UPDATE razas SET nombre = '$nombre', tamano = $tamano_sql WHERE id = $raza_id";
  $ok = $mysqli->query($q);
  echo json_encode([
    'status' => $ok ? 'ok' : 'error',
    'message' => $ok ? 'Raza actualizada correctamente.' : 'Error al actualizar.'
  ]);
} else {
  // INSERT
  $q = "INSERT INTO razas (especie_id, nombre, tamano, activo) VALUES ($especie_id, '$nombre', $tamano_sql, 1)";
  $ok = $mysqli->query($q);
  echo json_encode([
    'status' => $ok ? 'ok' : 'error',
    'message' => $ok ? 'Raza agregada correctamente.' : 'Error al guardar. ¿Ya existe?'
  ]);
}
