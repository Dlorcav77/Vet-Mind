<?php
// admin/inicio/componentes/inicio_grafico_data.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/conn/conn.php");
$mysqli = conn();

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
  echo json_encode(['ok' => 0, 'error' => 'Sesión no válida.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// color primario (si existe config)
$color_primario = '#3498db';
$stC = $mysqli->prepare("SELECT color_primario FROM configuracion_informes WHERE veterinario_id = ? LIMIT 1");
$stC->bind_param('i', $usuario_id);
$stC->execute();
if ($rowC = $stC->get_result()->fetch_assoc()) {
  if (!empty($rowC['color_primario'])) $color_primario = $rowC['color_primario'];
}

$scope  = $_GET['scope']  ?? 'month';
$anchor = $_GET['anchor'] ?? date('Y-m-d'); // YYYY-mm-dd

$scope = in_array($scope, ['week','month','year'], true) ? $scope : 'week';
$dtAnchor = DateTime::createFromFormat('Y-m-d', $anchor);
if (!$dtAnchor) $dtAnchor = new DateTime();

// helpers
$today = new DateTime();
$todayStr = $today->format('Y-m-d');

function _vm_fmt_es($ymd) {
  $d = DateTime::createFromFormat('Y-m-d', $ymd);
  return $d ? $d->format('d-m-Y') : $ymd;
}
function _vm_month_es($n) {
  $meses = [
    1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
  ];
  return $meses[(int)$n] ?? '';
}

$rangeStart = null;
$rangeEnd   = null;
$rangeLabel = '';
$labels     = [];
$data       = [];
$hasData    = false;
$weekendIdx = [];

if ($scope === 'week') {
  $rangeEnd   = (clone $dtAnchor);
  $rangeStart = (clone $dtAnchor)->modify('-6 days');

  $startStr = $rangeStart->format('Y-m-d');
  $endStr   = $rangeEnd->format('Y-m-d');

  $st = $mysqli->prepare("
    SELECT DATE(fecha_examen) AS fecha, COUNT(*) AS total
    FROM certificados
    WHERE DATE(fecha_examen) BETWEEN ? AND ?
      AND veterinario_id = ?
    GROUP BY DATE(fecha_examen)
    ORDER BY fecha ASC
  ");
  $st->bind_param('ssi', $startStr, $endStr, $usuario_id);
  $st->execute();
  $res = $st->get_result();

  $map = [];
  $dias_es = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

  $iter = clone $rangeStart;
  $idx  = 0;
  while ($iter <= $rangeEnd) {
    $k = $iter->format('Y-m-d');
    $map[$k] = 0;

    $diaNum = (int)$iter->format('w'); // 0=Dom .. 6=Sáb
    $labels[] = $dias_es[$diaNum] . ' ' . $iter->format('d');

    if ($diaNum === 0 || $diaNum === 6) $weekendIdx[] = $idx;

    $idx++;
    $iter->modify('+1 day');
  }

  while ($row = $res->fetch_assoc()) {
    $k = $row['fecha'];
    if (array_key_exists($k, $map)) $map[$k] = (int)$row['total'];
  }

  $data = array_values($map);
  $hasData = array_sum($data) > 0;
  $rangeLabel = _vm_fmt_es($startStr) . " al " . _vm_fmt_es($endStr);

} elseif ($scope === 'month') {
  $rangeStart = new DateTime($dtAnchor->format('Y-m-01'));
  $rangeEnd   = (clone $rangeStart)->modify('last day of this month');

  $startStr = $rangeStart->format('Y-m-d');
  $endStr   = $rangeEnd->format('Y-m-d');

  $st = $mysqli->prepare("
    SELECT DATE(fecha_examen) AS fecha, COUNT(*) AS total
    FROM certificados
    WHERE DATE(fecha_examen) BETWEEN ? AND ?
      AND veterinario_id = ?
    GROUP BY DATE(fecha_examen)
    ORDER BY fecha ASC
  ");
  $st->bind_param('ssi', $startStr, $endStr, $usuario_id);
  $st->execute();
  $res = $st->get_result();

  $map = [];
  $iter = clone $rangeStart;
  $idx  = 0;
  while ($iter <= $rangeEnd) {
    $k = $iter->format('Y-m-d');
    $map[$k] = 0;

    $labels[] = $iter->format('d'); // 01..31

    $diaNum = (int)$iter->format('w'); // 0=Dom .. 6=Sáb
    if ($diaNum === 0 || $diaNum === 6) $weekendIdx[] = $idx;

    $idx++;
    $iter->modify('+1 day');
  }

  while ($row = $res->fetch_assoc()) {
    $k = $row['fecha'];
    if (array_key_exists($k, $map)) $map[$k] = (int)$row['total'];
  }

  $data = array_values($map);
  $hasData = array_sum($data) > 0;
  $rangeLabel = ucfirst(_vm_month_es((int)$rangeStart->format('n'))) . ' ' . $rangeStart->format('Y');

} else { // year
  $year = (int)$dtAnchor->format('Y');
  $rangeStart = new DateTime($year . '-01-01');
  $rangeEnd   = new DateTime($year . '-12-31');

  $startStr = $rangeStart->format('Y-m-d');
  $endStr   = $rangeEnd->format('Y-m-d');

  $st = $mysqli->prepare("
    SELECT MONTH(fecha_examen) AS mes, COUNT(*) AS total
    FROM certificados
    WHERE DATE(fecha_examen) BETWEEN ? AND ?
      AND veterinario_id = ?
    GROUP BY MONTH(fecha_examen)
    ORDER BY mes ASC
  ");
  $st->bind_param('ssi', $startStr, $endStr, $usuario_id);
  $st->execute();
  $res = $st->get_result();

  $map = [];
  for ($m=1; $m<=12; $m++) {
    $map[$m] = 0;
    $labels[] = ucfirst(substr(_vm_month_es($m), 0, 3));
  }

  while ($row = $res->fetch_assoc()) {
    $m = (int)$row['mes'];
    if (isset($map[$m])) $map[$m] = (int)$row['total'];
  }

  $data = array_values($map);
  $hasData = array_sum($data) > 0;
  $rangeLabel = (string)$year;
}

// navegación: anchors prev/next
function _vm_anchor_prev($scope, DateTime $dt) {
  $d = clone $dt;
  if ($scope === 'week')  return $d->modify('-7 days')->format('Y-m-d');
  if ($scope === 'month') return $d->modify('first day of this month')->modify('-1 month')->format('Y-m-d');
  return $d->modify('-1 year')->format('Y-m-d');
}
function _vm_anchor_next($scope, DateTime $dt) {
  $d = clone $dt;
  if ($scope === 'week')  return $d->modify('+7 days')->format('Y-m-d');
  if ($scope === 'month') return $d->modify('first day of this month')->modify('+1 month')->format('Y-m-d');
  return $d->modify('+1 year')->format('Y-m-d');
}
$anchorPrev = _vm_anchor_prev($scope, $dtAnchor);
$anchorNext = _vm_anchor_next($scope, $dtAnchor);

// deshabilitar next si pasa hoy
$disableNext = false;
if ($scope === 'week') {
  $nextEnd = (clone DateTime::createFromFormat('Y-m-d', $anchorNext))->format('Y-m-d');
  $disableNext = ($nextEnd > $todayStr);
} elseif ($scope === 'month') {
  $nextMonthStart = (clone DateTime::createFromFormat('Y-m-d', $anchorNext))->format('Y-m-01');
  $disableNext = ($nextMonthStart > $todayStr);
} else {
  $disableNext = ((int)substr($anchorNext,0,4) > (int)date('Y'));
}

$total_periodo = array_sum($data);

echo json_encode([
  'ok'         => 1,
  'scope'      => $scope,
  'anchor'     => $dtAnchor->format('Y-m-d'),
  'rangeLabel' => $rangeLabel,
  'labels'     => $labels,
  'data'       => $data,
  'total'      => (int)$total_periodo,
  'weekendIdx' => $weekendIdx,
  'color'      => $color_primario,
  'hasData'    => $hasData ? 1 : 0,
  'anchorPrev' => $anchorPrev,
  'anchorNext' => $anchorNext,
  'disableNext'=> $disableNext ? 1 : 0,
], JSON_UNESCAPED_UNICODE);