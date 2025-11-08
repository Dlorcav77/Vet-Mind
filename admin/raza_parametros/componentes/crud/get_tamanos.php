<?php
require_once("../../../../funciones/conn/conn.php");
header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$especie_id = isset($_GET['especie_id']) ? intval($_GET['especie_id']) : 0;

if ($especie_id <= 0) { echo json_encode([]); exit; }

$sql = "SELECT DISTINCT tamano
        FROM razas
        WHERE especie_id = ?
          AND tamano IS NOT NULL
          AND tamano <> ''
          AND activo = 1
        ORDER BY CASE LOWER(TRIM(tamano))
          WHEN 'miniatura' THEN 1
          WHEN 'pequeño'  THEN 2
          WHEN 'mediano'  THEN 3
          WHEN 'grande'   THEN 4
          WHEN 'gigante'  THEN 5
          ELSE 99
        END, tamano;
        ";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $especie_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($r = $res->fetch_assoc()) { $out[] = $r['tamano']; }

echo json_encode($out);
