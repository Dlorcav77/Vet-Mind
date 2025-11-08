<?php
require_once("../../config.php");

header('Content-Type: application/json');

$mysqli = conn();
$veterinario_id = $_SESSION['usuario_id'] ?? 0;
$plantilla_id = intval($_POST['plantilla_informe_id'] ?? 0);

if ($plantilla_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Plantilla no proporcionada.']);
    exit;
}
// Buscar plantilla activa para el tipo de examen
$query = "
    SELECT contenido
    FROM plantilla_informe
    WHERE id = ? AND veterinario_id = ? AND estado = 'activo' AND deleted_at IS NULL
    LIMIT 1
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $plantilla_id, $veterinario_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode(['status' => 'success', 'contenido' => $row['contenido']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró una plantilla activa para este tipo de examen.']);
}
