<?php
require_once("../../../funciones/conn/conn.php");
$mysqli = conn();
session_start();
$usuario_id = $_SESSION['usuario_id'] ?? 0;

$out = ['status' => 'success', 'clinicas' => []];

$stmt = $mysqli->prepare("
  SELECT id, nombre_clinica, correo
  FROM clinicas
  WHERE veterinario_id = ? AND correo <> ''
  ORDER BY nombre_clinica ASC
");
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $out['clinicas'][] = $r;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
