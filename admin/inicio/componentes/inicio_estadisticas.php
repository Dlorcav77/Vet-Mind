<?php
if (!isset($usuario_id)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $usuario_id = $_SESSION['usuario_id'] ?? null;
}
require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/conn/conn.php");
$mysqli = conn();

// Obtener mes/año actual o desde URL
$mes_actual = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio_actual = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

$mes_anterior = $mes_actual - 1;
$anio_anterior = $anio_actual;
$mes_siguiente = $mes_actual + 1;
$anio_siguiente = $anio_actual;

if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $anio_anterior--;
}
if ($mes_siguiente > 12) {
    $mes_siguiente = 1;
    $anio_siguiente++;
}

$es_mes_actual = ($mes_actual == date('n') && $anio_actual == date('Y'));
$meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$nombre_mes = $meses[$mes_actual];

$hoy_mes = (int)date('n');
$hoy_anio = (int)date('Y');
$mostrar_btn_siguiente = !($anio_actual >= $hoy_anio && $mes_actual >= $hoy_mes);

// Consultas
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM certificados WHERE DATE(fecha_examen) = CURDATE() AND veterinario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$informes_hoy = $res->fetch_assoc()['total'] ?? 0;

$stmt = $mysqli->prepare("
  SELECT COUNT(*) AS total 
  FROM certificados 
  WHERE MONTH(created_at) = ? 
    AND YEAR(created_at) = ? 
    AND veterinario_id = ?
");
$stmt->bind_param("iii", $mes_actual, $anio_actual, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$informes_mes = $res->fetch_assoc()['total'] ?? 0;

$stmt = $mysqli->prepare("
  SELECT te.nombre AS nombre_estudio, COUNT(*) AS total
  FROM certificados c
  INNER JOIN plantilla_informe te ON c.tipo_estudio = te.id
  WHERE MONTH(c.created_at) = ? 
    AND YEAR(c.created_at) = ? 
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
  $top_estudios[] = $row;
}
?>
<!-- Card principal de estadísticas -->
<div class="card shadow-sm mb-4">
  <div class="card-body">

    <!-- Título -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">
        <i class="fas fa-chart-bar me-2 text-primary"></i>
        Estadísticas del mes
      </h5>

      <!-- Navegación de meses -->
      <div>
        <button class="btn btn-outline-secondary btn-sm me-1" onclick="cargarEstadisticas(<?= $mes_anterior ?>, <?= $anio_anterior ?>)">
          ←
        </button>
        <span class="fw-bold"><?= ucfirst($nombre_mes) . " $anio_actual" ?></span>
        <?php if ($mostrar_btn_siguiente): ?>
          <button class="btn btn-outline-secondary btn-sm ms-1" onclick="cargarEstadisticas(<?= $mes_siguiente ?>, <?= $anio_siguiente ?>)">
            →
          </button>
        <?php else: ?>
          <button class="btn btn-outline-secondary btn-sm ms-1" disabled>→</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Contenido -->
    <div class="row g-4 align-items-stretch">

      <!-- Informes del día y mes -->
      <?php if ($es_mes_actual): ?>
        <div class="col-md-3">
          <div class="d-flex flex-column gap-3 h-100">
            <div class="card text-center shadow-sm bg-primary-subtle border-0">
              <div class="card-body">
                <div class="fs-3 fw-bold text-primary"><?= $informes_hoy ?></div>
                <div class="fw-semibold text-dark">Informes hoy</div>
              </div>
            </div>

            <div class="card text-center shadow-sm bg-success-subtle border-0">
              <div class="card-body">
                <div class="fs-3 fw-bold text-success"><?= $informes_mes ?></div>
                <div class="fw-semibold text-dark">Informes del mes</div>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="col-md-3 col-lg-4 col-xl-3">
          <div class="card text-center shadow-sm bg-success-subtle border-0 h-100">
            <div class="card-body d-flex flex-column justify-content-center">
              <div class="fs-3 fw-bold text-success"><?= $informes_mes ?></div>
              <div class="fw-semibold text-dark">Informes del mes</div>
            </div>
          </div>
        </div>
      <?php endif; ?>


      <!-- Top 5 estudios -->
      <div class="col-md-9">

<div class="card shadow-sm border-0 h-100" style="background-color: #f1f3f5;">
  <div class="card-body d-flex flex-column">

    <!-- Título -->
    <div class="d-flex align-items-center mb-3">
      <i class="fas fa-stethoscope me-2 text-info fs-5"></i>
      <h6 class="mb-0 text-info fw-bold">Exámenes más realizados</h6>
    </div>

    <!-- Tabla -->
    <div class="flex-grow-1">
      <table class="table table-sm table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>Examenes</th>
            <th class="text-end">Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_estudios as $estudio): ?>
            <tr>
              <td><?= htmlspecialchars($estudio['nombre_estudio']) ?></td>
              <td class="text-end"><?= $estudio['total'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($top_estudios)): ?>
            <tr>
              <td colspan="2" class="text-center text-muted">Sin exámenes registrados este mes</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>


      </div>

    </div> <!-- .row -->
  </div> <!-- .card-body -->
</div> <!-- .card -->


<!-- Script AJAX -->
<script>
function cargarEstadisticas(mes, anio) {
  const contenedor = document.getElementById('bloque-estadisticas');
  if (!contenedor) return;

  contenedor.innerHTML = '<div class="text-center py-4">Cargando estadísticas...</div>';

  fetch('inicio/componentes/inicio_estadisticas.php?mes=' + mes + '&anio=' + anio)
    .then(res => res.text())
    .then(html => contenedor.innerHTML = html)
    .catch(err => {
      console.error(err);
      contenedor.innerHTML = '<div class="alert alert-danger text-center">Error al cargar estadísticas.</div>';
    });
}
</script>
