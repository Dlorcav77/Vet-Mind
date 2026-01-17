<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
  echo json_encode(['ok' => 0, 'error' => 'Sesión no válida.']);
  exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/conn/conn.php");
$mysqli = conn();

// mes/año desde URL
$mes_actual  = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$anio_actual = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

if ($mes_actual < 1 || $mes_actual > 12) $mes_actual = (int)date('n');
if ($anio_actual < 2000 || $anio_actual > 2100) $anio_actual = (int)date('Y');

$mes_anterior = $mes_actual - 1;
$anio_anterior = $anio_actual;
$mes_siguiente = $mes_actual + 1;
$anio_siguiente = $anio_actual;

if ($mes_anterior < 1) { $mes_anterior = 12; $anio_anterior--; }
if ($mes_siguiente > 12) { $mes_siguiente = 1; $anio_siguiente++; }

$es_mes_actual = ($mes_actual == (int)date('n') && $anio_actual == (int)date('Y'));

$meses = [
  1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
  5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
  9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$nombre_mes = $meses[$mes_actual] ?? '';

$hoy_mes  = (int)date('n');
$hoy_anio = (int)date('Y');
$mostrar_btn_siguiente = !($anio_actual >= $hoy_anio && $mes_actual >= $hoy_mes);

// consultas
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM certificados WHERE DATE(fecha_examen) = CURDATE() AND veterinario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$informes_hoy = (int)($res->fetch_assoc()['total'] ?? 0);

$stmt = $mysqli->prepare("
  SELECT COUNT(*) AS total
  FROM certificados
  WHERE MONTH(fecha_examen) = ?
    AND YEAR(fecha_examen) = ?
    AND veterinario_id = ?
");
$stmt->bind_param("iii", $mes_actual, $anio_actual, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$informes_mes = (int)($res->fetch_assoc()['total'] ?? 0);

$stmt = $mysqli->prepare("
  SELECT te.nombre AS nombre_estudio, COUNT(*) AS total
  FROM certificados c
  INNER JOIN plantilla_informe te ON c.tipo_estudio = te.id
  WHERE MONTH(c.fecha_examen) = ?
    AND YEAR(c.fecha_examen) = ?
    AND c.veterinario_id = ?
  GROUP BY te.nombre
  ORDER BY total DESC
  LIMIT 5
");
$stmt->bind_param("iii", $mes_actual, $anio_actual, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();

$top_estudios = [];
while ($row = $res->fetch_assoc()) {
  $top_estudios[] = [
    'nombre_estudio' => (string)($row['nombre_estudio'] ?? ''),
    'total' => (int)($row['total'] ?? 0),
  ];
}

echo json_encode([
  'ok' => 1,
  'mes' => $mes_actual,
  'anio' => $anio_actual,
  'label' => ucfirst($nombre_mes) . " " . $anio_actual,
  'mes_anterior' => $mes_anterior,
  'anio_anterior' => $anio_anterior,
  'mes_siguiente' => $mes_siguiente,
  'anio_siguiente' => $anio_siguiente,
  'mostrar_siguiente' => $mostrar_btn_siguiente ? 1 : 0,
  'es_mes_actual' => $es_mes_actual ? 1 : 0,
  'informes_hoy' => $informes_hoy,
  'informes_mes' => $informes_mes,
  'top_estudios' => $top_estudios,
], JSON_UNESCAPED_UNICODE);
