<?php
require_once("../../../../funciones/conn/conn.php");
$mysqli = conn();

$id = intval($_POST['id']);
$ok = $mysqli->query("DELETE FROM razas WHERE id = $id"); // o UPDATE activo = 0

echo json_encode([
  'status' => $ok ? 'ok' : 'error',
  'message' => $ok ? 'Raza eliminada correctamente.' : 'Error al eliminar.'
]);
