<?php
require_once("../../../funciones/conn/conn.php");
$mysqli = conn();

$id = $_POST['id'] ?? 0;
$id = intval($id);

$response = ['status' => 'error', 'message' => 'No se encontró correo'];

$stmt = $mysqli->prepare("
  SELECT t.email
  FROM certificados c
  LEFT JOIN pacientes p ON c.paciente_id = p.id
  LEFT JOIN tutores t ON p.tutor_id = t.id
  WHERE c.id = ?
");

if ($stmt) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();

  if ($row && !empty($row['email'])) {
    $response = ['status' => 'success', 'correo' => $row['email']];
  } else {
    $response['message'] = 'No hay correo asociado al tutor';
  }
} else {
  $response['message'] = 'Error en la consulta';
}

echo json_encode($response);
