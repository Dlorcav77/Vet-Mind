<?php
require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$configuracion_informe_id = intval($_POST['configuracion_informe_id'] ?? 0);

if ($usuario_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ]);
    exit;
}

if ($configuracion_informe_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Plantilla de diseño inválida.'
    ]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT id
    FROM configuracion_informes
    WHERE id = ? AND veterinario_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $configuracion_informe_id, $usuario_id);
$stmt->execute();
$config = $stmt->get_result()->fetch_assoc();

if (!$config) {
    echo json_encode([
        'status' => 'error',
        'message' => 'La plantilla no pertenece al veterinario actual.'
    ]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT x.campo
    FROM (
        SELECT 
            cp.id AS campo_id,
            cp.campo,
            MIN(cic.orden) AS orden_min,
            MIN(cic.id) AS id_min
        FROM configuracion_informe_campos cic
        INNER JOIN campos_permitidos cp ON cp.id = cic.campo_id
        WHERE cic.configuracion_informe_id = ?
          AND cic.visible = 1
        GROUP BY cp.id, cp.campo
    ) x
    ORDER BY x.orden_min ASC, x.id_min ASC
");
$stmt->bind_param("i", $configuracion_informe_id);
$stmt->execute();
$res = $stmt->get_result();

$campos = [];
while ($row = $res->fetch_assoc()) {
    $campos[] = $row['campo'];
}

$campos_generales = ['antecedentes', 'm_solicitante', 'recinto'];
$campos_manuales = array_values(array_diff($campos, $campos_generales));

echo json_encode([
    'status' => 'success',
    'campos' => $campos,
    'campos_manuales' => $campos_manuales,
    'campos_generales' => $campos_generales
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;