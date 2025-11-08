<?php
require_once("../../../../funciones/conn/conn.php");
$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id > 0) {
  $stmt = $mysqli->prepare("DELETE FROM organos_parametros WHERE id = ?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Órgano eliminado correctamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: ' . $stmt->error]);
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
}
