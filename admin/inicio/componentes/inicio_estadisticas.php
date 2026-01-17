<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$usuario_id = $_SESSION['usuario_id'] ?? null;

require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/conn/conn.php");
$mysqli = conn();

// Obtener mes/año actual o desde URL
$mes_actual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$anio_actual = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

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

$es_mes_actual = ($mes_actual == (int)date('n') && $anio_actual == (int)date('Y'));

$meses = [
  1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
  5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
  9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$nombre_mes = $meses[$mes_actual] ?? '';

$hoy_mes = (int)date('n');
$hoy_anio = (int)date('Y');
$mostrar_btn_siguiente = !($anio_actual >= $hoy_anio && $mes_actual >= $hoy_mes);

// Consultas (render inicial server-side)
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
  $top_estudios[] = $row;
}
?>

<!-- Card principal de estadísticas (estructura FIJA) -->
<div class="card shadow-sm mb-4" id="inicioStatsCard">
  <div class="card-body">

    <!-- Título -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">
        <i class="fas fa-chart-bar me-2 text-primary"></i>
        Estadísticas del mes
      </h5>

      <!-- Navegación de meses -->
      <div class="d-flex align-items-center gap-2">
        <button
          id="inicioStatsBtnPrev"
          class="btn btn-outline-secondary btn-sm"
          type="button"
          onclick="cargarEstadisticas(<?= (int)$mes_anterior ?>, <?= (int)$anio_anterior ?>)">
          ←
        </button>

        <span class="fw-bold" id="inicioStatsMesLabel"><?= htmlspecialchars(ucfirst($nombre_mes) . " " . $anio_actual) ?></span>

        <button
          id="inicioStatsBtnNext"
          class="btn btn-outline-secondary btn-sm"
          type="button"
          onclick="cargarEstadisticas(<?= (int)$mes_siguiente ?>, <?= (int)$anio_siguiente ?>)"
          <?= $mostrar_btn_siguiente ? '' : 'disabled' ?>>
          →
        </button>

        <!-- <span id="inicioStatsLoading" class="text-muted small ms-2" style="display:none;">Cargando…</span> -->
      </div>
    </div>

    <!-- Contenido (solo esto cambia por AJAX) -->
    <div class="row g-4 align-items-stretch">

      <!-- Bloque IZQ: variantes -->
      <div class="col-md-3" id="inicioStatsColDoble" style="<?= $es_mes_actual ? '' : 'display:none;' ?>">
        <div class="d-flex flex-column gap-3 h-100">
          <div class="card text-center shadow-sm bg-primary-subtle border-0">
            <div class="card-body">
              <div class="fs-3 fw-bold text-primary" id="inicioStatsInformesHoy"><?= (int)$informes_hoy ?></div>
              <div class="fw-semibold text-dark">Informes hoy</div>
            </div>
          </div>

          <div class="card text-center shadow-sm bg-success-subtle border-0">
            <div class="card-body">
              <div class="fs-3 fw-bold text-success" id="inicioStatsInformesMesA"><?= (int)$informes_mes ?></div>
              <div class="fw-semibold text-dark">Informes del mes</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-lg-4 col-xl-3" id="inicioStatsColSimple" style="<?= $es_mes_actual ? 'display:none;' : '' ?>">
        <div class="card text-center shadow-sm bg-success-subtle border-0 h-100">
          <div class="card-body d-flex flex-column justify-content-center">
            <div class="fs-3 fw-bold text-success" id="inicioStatsInformesMesB"><?= (int)$informes_mes ?></div>
            <div class="fw-semibold text-dark">Informes del mes</div>
          </div>
        </div>
      </div>

      <!-- Top 5 estudios -->
      <div class="col-md-9">
        <div class="card shadow-sm border-0 h-100" style="background-color: #f1f3f5;">
          <div class="card-body d-flex flex-column">

            <div class="d-flex align-items-center mb-3">
              <i class="fas fa-stethoscope me-2 text-info fs-5"></i>
              <h6 class="mb-0 text-info fw-bold">Exámenes más realizados</h6>
            </div>

            <div class="flex-grow-1">
              <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Examenes</th>
                    <th class="text-end">Cantidad</th>
                  </tr>
                </thead>
                <tbody id="inicioStatsTopBody">
                  <?php foreach ($top_estudios as $estudio): ?>
                    <tr>
                      <td><?= htmlspecialchars($estudio['nombre_estudio']) ?></td>
                      <td class="text-end"><?= (int)$estudio['total'] ?></td>
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

    </div><!-- .row -->
  </div><!-- .card-body -->
</div><!-- .card -->

<script>
function cargarEstadisticas(mes, anio) {
  const btnPrev = document.getElementById('inicioStatsBtnPrev');
  const btnNext = document.getElementById('inicioStatsBtnNext');
  const label   = document.getElementById('inicioStatsMesLabel');
  const loading = document.getElementById('inicioStatsLoading');

  const colDoble  = document.getElementById('inicioStatsColDoble');
  const colSimple = document.getElementById('inicioStatsColSimple');

  const informesHoy = document.getElementById('inicioStatsInformesHoy');
  const informesMesA = document.getElementById('inicioStatsInformesMesA');
  const informesMesB = document.getElementById('inicioStatsInformesMesB');
  const topBody = document.getElementById('inicioStatsTopBody');

  if (loading) loading.style.display = '';
  if (btnPrev) btnPrev.disabled = true;
  if (btnNext) btnNext.disabled = true;

  fetch('inicio/componentes/inicio_estadisticas_data.php?mes=' + encodeURIComponent(mes) + '&anio=' + encodeURIComponent(anio))
    .then(res => res.json())
    .then(data => {
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'Respuesta inválida');

      if (label) label.textContent = data.label || '';

      // actualizar navegación (sin rearmar la card)
      if (btnPrev) {
        btnPrev.disabled = false;
        btnPrev.setAttribute('onclick', 'cargarEstadisticas(' + data.mes_anterior + ',' + data.anio_anterior + ')');
      }
      if (btnNext) {
        const canNext = (String(data.mostrar_siguiente) === '1');
        btnNext.disabled = !canNext;
        btnNext.setAttribute('onclick', 'cargarEstadisticas(' + data.mes_siguiente + ',' + data.anio_siguiente + ')');
      }

      // layout hoy/mes
      const esMesActual = (String(data.es_mes_actual) === '1');
      if (colDoble) colDoble.style.display = esMesActual ? '' : 'none';
      if (colSimple) colSimple.style.display = esMesActual ? 'none' : '';

      // números
      if (informesHoy) informesHoy.textContent = data.informes_hoy ?? 0;
      if (informesMesA) informesMesA.textContent = data.informes_mes ?? 0;
      if (informesMesB) informesMesB.textContent = data.informes_mes ?? 0;

      // tabla
      if (topBody) {
        const items = Array.isArray(data.top_estudios) ? data.top_estudios : [];
        if (!items.length) {
          topBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Sin exámenes registrados este mes</td></tr>';
        } else {
          let html = '';
          items.forEach(it => {
            const nombre = (it.nombre_estudio ?? '').toString()
              .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
            const total = Number(it.total ?? 0);
            html += '<tr><td>' + nombre + '</td><td class="text-end">' + (Number.isFinite(total) ? total : 0) + '</td></tr>';
          });
          topBody.innerHTML = html;
        }
      }
    })
    .catch(err => {
      console.error(err);
      // mantiene la card quieta; solo muestra mensaje en tabla
      if (topBody) topBody.innerHTML = '<tr><td colspan="2" class="text-center text-danger">Error al cargar estadísticas.</td></tr>';
    })
    .finally(() => {
      if (loading) loading.style.display = 'none';
      if (btnPrev) btnPrev.disabled = false;

      // btnNext depende del estado actual; si falló dejamos habilitado para no “encerrar” al usuario
      if (btnNext && btnNext.hasAttribute('disabled') === false) {
        btnNext.disabled = false;
      }
    });
}
</script>
