<?php
// admin/inicio/componentes/inicio_grafico.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/conn/conn.php");
$mysqli = conn();

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
  echo '<div class="alert alert-danger">Sesión no válida.</div>';
  exit;
}

// color primario
$color_primario = '#3498db';
$stC = $mysqli->prepare("SELECT color_primario FROM configuracion_informes WHERE veterinario_id = ? LIMIT 1");
$stC->bind_param('i', $usuario_id);
$stC->execute();
if ($rowC = $stC->get_result()->fetch_assoc()) {
  if (!empty($rowC['color_primario'])) $color_primario = $rowC['color_primario'];
}

$scope  = $_GET['scope']  ?? 'month';
$anchor = $_GET['anchor'] ?? date('Y-m-d');

$scope = in_array($scope, ['week','month','year'], true) ? $scope : 'week';
$dtAnchor = DateTime::createFromFormat('Y-m-d', $anchor);
if (!$dtAnchor) $dtAnchor = new DateTime();

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

    $diaNum = (int)$iter->format('w');
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

    $labels[] = $iter->format('d');

    $diaNum = (int)$iter->format('w');
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
  $startStr = $year . '-01-01';
  $endStr   = $year . '-12-31';

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

$payload = [
  'ok'         => 1,
  'scope'      => $scope,
  'anchor'     => $dtAnchor->format('Y-m-d'),
  'rangeLabel' => $rangeLabel,
  'labels'     => $labels,
  'data'       => $data,
  'weekendIdx' => $weekendIdx,
  'color'      => $color_primario,
  'hasData'    => $hasData ? 1 : 0,
  'anchorPrev' => $anchorPrev,
  'anchorNext' => $anchorNext,
  'disableNext'=> $disableNext ? 1 : 0,
  'total'      => (int)$total_periodo,
];
?>

<div class="card mb-4" id="inicioGrafico" data-scope="<?= htmlspecialchars($scope) ?>" data-anchor="<?= htmlspecialchars($dtAnchor->format('Y-m-d')) ?>">
  <div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-3 position-relative">

      <!-- IZQUIERDA -->
      <div class="flex-grow-1">
        <h5 class="mb-0">
          <i class="fas fa-chart-line me-2"></i> Informes (por fecha de examen)
        </h5>
      </div>

      <!-- CENTRO REAL (absoluto) -->
      <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none;">
        <div class="fw-semibold" style="font-size: 1.05rem; line-height: 1.2;" id="inicioGraficoRange">
          <?= htmlspecialchars($rangeLabel) ?>
        </div>
      </div>

      <!-- DERECHA -->
      <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-end">

        <!-- "primer botón" (solo muestra total) -->
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                id="inicioGraficoTotalBtn"
                disabled
                style="opacity:1; cursor:default;">
          Total: <?= (int)$total_periodo ?>
        </button>

        <div class="btn-group" role="group" aria-label="Filtro gráfico">
          <button type="button"
                  class="btn btn-sm <?= $scope==='week'?'btn-primary':'btn-outline-primary' ?>"
                  data-inicio-graf-scope="week">Semana</button>
          <button type="button"
                  class="btn btn-sm <?= $scope==='month'?'btn-primary':'btn-outline-primary' ?>"
                  data-inicio-graf-scope="month">Mes</button>
          <button type="button"
                  class="btn btn-sm <?= $scope==='year'?'btn-primary':'btn-outline-primary' ?>"
                  data-inicio-graf-scope="year">Año</button>
        </div>

        <div class="btn-group" role="group" aria-label="Navegación gráfico">
          <button type="button"
                  class="btn btn-sm btn-outline-secondary"
                  data-inicio-graf-nav="prev">←</button>

          <button type="button"
                  class="btn btn-sm btn-outline-secondary"
                  data-inicio-graf-nav="next"
                  <?= $disableNext ? 'disabled' : '' ?>>→</button>
        </div>

        <!-- <span id="inicioGraficoLoading" class="text-muted small ms-2" style="display:none;">Cargando…</span> -->
      </div>
    </div>

    <div id="inicioGraficoNoData" class="text-center text-muted py-4" style="<?= $hasData ? 'display:none;' : '' ?>">
      Sin información para este período.
    </div>

    <div id="inicioGraficoWrap" class="position-relative" style="<?= $hasData ? '' : 'display:none;' ?>; height: 280px;">
      <canvas id="inicioGraficoCanvasBg" style="position:absolute; inset:0; z-index:0; width:100%; height:100%; display:block;"></canvas>
      <canvas id="inicioGraficoCanvas" style="position:relative; z-index:1; width:100%; height:100%; display:block;"></canvas>
    </div>

    <script type="application/json" id="inicioGraficoPayload"><?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?></script>
  </div>
</div>

<script>
(function () {
  // una sola vez
  if (!window.__vetmindInicioGraficoBooted) window.__vetmindInicioGraficoBooted = true;

  function ensureChartJs(cb) {
    if (window.Chart) return cb();
    if (window.__vetmindChartJsLoading) {
      const t = setInterval(() => { if (window.Chart) { clearInterval(t); cb(); } }, 50);
      return;
    }
    window.__vetmindChartJsLoading = true;
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = () => cb();
    document.head.appendChild(s);
  }

  function vmCalcSuggestedMax(arr, extra) {
    extra = extra ?? 2;
    const nums = (arr || []).map(v => Number(v)).filter(v => Number.isFinite(v));
    const maxVal = nums.length ? Math.max(...nums) : 0;
    return Math.max(3, maxVal + extra);
  }

  const vmValueLabels = {
    id: 'vmValueLabels',
    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      if (!ctx) return;

      ctx.save();
      ctx.font = '12px Arial';
      ctx.fillStyle = '#111';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'bottom';

      const ds = chart.data.datasets[0];
      if (!ds) { ctx.restore(); return; }

      const meta = (typeof chart.getDatasetMeta === 'function') ? chart.getDatasetMeta(0) : null;
      const points = meta && meta.data ? meta.data : (chart.datasets && chart.datasets[0] && chart.datasets[0].points ? chart.datasets[0].points : []);

      (ds.data || []).forEach((raw, i) => {
        const v = Number(raw);
        if (!Number.isFinite(v) || v === 0) return;

        const p = points[i];
        if (!p) return;

        const x = (p.x !== undefined) ? p.x : (p._model && p._model.x);
        const y = (p.y !== undefined) ? p.y : (p._model && p._model.y);
        if (x == null || y == null) return;

        ctx.fillText(String(v), x, y - 8);
      });

      ctx.restore();
    }
  };

  function vmSyncBgCanvasSize(bg, canvas) {
    bg.width = canvas.width;
    bg.height = canvas.height;
  }

  function vmGetPointCenters(chart) {
    if (typeof chart.getDatasetMeta === 'function') {
      const meta = chart.getDatasetMeta(0);
      if (meta && meta.data && meta.data.length) return meta.data.map(p => p.x);
    }
    if (chart.datasets && chart.datasets[0] && chart.datasets[0].points) return chart.datasets[0].points.map(p => p.x);
    return [];
  }

  function vmGetArea(chart, bg) {
    if (chart.chartArea) return chart.chartArea;
    if (chart.scale) {
      const top = (chart.scale.endPoint != null) ? chart.scale.endPoint : 0;
      const bottom = (chart.scale.startPoint != null) ? chart.scale.startPoint : bg.height;
      const left = (chart.scale.xScalePaddingLeft != null) ? chart.scale.xScalePaddingLeft : 0;
      const right = bg.width - ((chart.scale.xScalePaddingRight != null) ? chart.scale.xScalePaddingRight : 0);
      return { top, bottom, left, right };
    }
    return { top: 0, bottom: bg.height, left: 0, right: bg.width };
  }

  function vmDrawWeekendBands(payload, chart, bg, canvas) {
    const bctx = bg.getContext('2d');
    bctx.clearRect(0, 0, bg.width, bg.height);

    if (!payload || payload.scope === 'year') return;

    const idxs = Array.isArray(payload.weekendIdx) ? payload.weekendIdx : [];
    if (!idxs.length) return;

    const centers = vmGetPointCenters(chart);
    if (!centers.length) return;

    const area = vmGetArea(chart, bg);

    bctx.save();
    bctx.fillStyle = 'rgba(108,117,125,0.10)';

    const total = centers.length;
    idxs.forEach((i) => {
      if (i < 0 || i >= total) return;

      const c = centers[i];
      const p = (i > 0) ? centers[i - 1] : null;
      const n = (i < total - 1) ? centers[i + 1] : null;

      let left, right;
      if (p !== null) left = (p + c) / 2;
      else if (n !== null) left = c - (n - c) / 2;
      else left = area.left;

      if (n !== null) right = (c + n) / 2;
      else if (p !== null) right = c + (c - p) / 2;
      else right = area.right;

      left = Math.max(left, area.left);
      right = Math.min(right, area.right);

      bctx.fillRect(left, area.top, (right - left), (area.bottom - area.top));
    });

    bctx.restore();

    // asegurar sizes correctos
    setTimeout(() => {
      vmSyncBgCanvasSize(bg, canvas);
      bctx.clearRect(0, 0, bg.width, bg.height);
      vmDrawWeekendBands(payload, chart, bg, canvas);
    }, 0);
  }

  function readPayloadFromDom() {
    const el = document.getElementById('inicioGraficoPayload');
    if (!el) return null;
    try { return JSON.parse(el.textContent || '{}'); } catch(e) { return null; }
  }

  function setActiveScopeButtons(scope) {
    document.querySelectorAll('[data-inicio-graf-scope]').forEach(btn => {
      const s = btn.getAttribute('data-inicio-graf-scope');
      btn.classList.remove('btn-primary');
      btn.classList.remove('btn-outline-primary');
      btn.classList.add(s === scope ? 'btn-primary' : 'btn-outline-primary');
    });
  }

  function updateUI(payload) {
    const wrap = document.getElementById('inicioGrafico');
    if (wrap) {
      wrap.setAttribute('data-scope', payload.scope || 'week');
      wrap.setAttribute('data-anchor', payload.anchor || '');
    }

    const range = document.getElementById('inicioGraficoRange');
    if (range) range.textContent = payload.rangeLabel || '';

    const totalBtn = document.getElementById('inicioGraficoTotalBtn');
    if (totalBtn) {
      const t = Number(payload.total ?? 0);
      totalBtn.textContent = 'Total: ' + (Number.isFinite(t) ? t : 0);
    }

    const noData = document.getElementById('inicioGraficoNoData');
    const box = document.getElementById('inicioGraficoWrap');

    const hasData = String(payload.hasData) === '1';
    if (noData) noData.style.display = hasData ? 'none' : '';
    if (box) box.style.display = hasData ? '' : 'none';

    const btnPrev = document.querySelector('[data-inicio-graf-nav="prev"]');
    const btnNext = document.querySelector('[data-inicio-graf-nav="next"]');

    if (btnPrev) btnPrev.disabled = false;

    if (btnNext) {
      btnNext.disabled = (String(payload.disableNext) === '1');
    }

    setActiveScopeButtons(payload.scope || 'week');
  }

  function renderChart(payload) {
    updateUI(payload);

    const canvas = document.getElementById('inicioGraficoCanvas');
    const bg     = document.getElementById('inicioGraficoCanvasBg');
    const wrap   = document.getElementById('inicioGraficoWrap');
    if (!canvas || !bg) return;

    if (String(payload.hasData) !== '1') return;

    ensureChartJs(function () {
      if (window.__vetmindInicioChart) {
        try { window.__vetmindInicioChart.destroy(); } catch(e) {}
        window.__vetmindInicioChart = null;
      }

      const suggestedMax = vmCalcSuggestedMax(payload.data, 2);
      const ctx = canvas.getContext('2d');

      window.__vetmindInicioChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: payload.labels || [],
          datasets: [{
            label: 'Informes',
            data: payload.data || [],
            borderColor: payload.color || '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 5
          }]
        },
        plugins: [vmValueLabels],
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true,
                min: 0,
                max: suggestedMax,
                stepSize: 1,
                callback: function(value) { return Number.isInteger(value) ? value : ''; }
              }
            }],
            y: {
              beginAtZero: true,
              min: 0,
              max: suggestedMax,
              ticks: {
                stepSize: 1,
                precision: 0,
                callback: function(value) { return Number.isInteger(value) ? value : ''; }
              }
            }
          }
        }
      });

      // fondo fines de semana
      setTimeout(() => {
        vmSyncBgCanvasSize(bg, canvas);
        vmDrawWeekendBands(payload, window.__vetmindInicioChart, bg, canvas);
        window.dispatchEvent(new Event('resize'));
      }, 0);

      setTimeout(() => {
        vmSyncBgCanvasSize(bg, canvas);
        vmDrawWeekendBands(payload, window.__vetmindInicioChart, bg, canvas);
        window.dispatchEvent(new Event('resize'));
      }, 250);
    });
  }

  function fetchPayload(scope, anchor) {
    const loading = document.getElementById('inicioGraficoLoading');
    const btnPrev = document.querySelector('[data-inicio-graf-nav="prev"]');
    const btnNext = document.querySelector('[data-inicio-graf-nav="next"]');
    const scopeBtns = document.querySelectorAll('[data-inicio-graf-scope]');

    if (loading) loading.style.display = '';
    if (btnPrev) btnPrev.disabled = true;
    if (btnNext) btnNext.disabled = true;
    scopeBtns.forEach(b => b.disabled = true);

    const url = 'inicio/componentes/inicio_grafico_data.php?scope=' + encodeURIComponent(scope) + '&anchor=' + encodeURIComponent(anchor);

    return fetch(url)
      .then(r => r.json())
      .then(data => {
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'Respuesta inválida');
        return data;
      })
      .catch(err => {
        console.error(err);
        throw err;
      })
      .finally(() => {
        if (loading) loading.style.display = 'none';
        if (btnPrev) btnPrev.disabled = false; // <-- clave: se re-habilita SIEMPRE
        if (btnNext) btnNext.disabled = false; // luego updateUI() lo deshabilita si corresponde
        scopeBtns.forEach(b => b.disabled = false);
      });
  }

  function getTodayYMD() {
    return new Date().toISOString().slice(0,10);
  }

  function anchorPrev(scope, anchor) {
    const d = new Date(anchor + 'T00:00:00');
    if (scope === 'week')  d.setDate(d.getDate() - 7);
    else if (scope === 'month') d.setMonth(d.getMonth() - 1);
    else d.setFullYear(d.getFullYear() - 1);
    return d.toISOString().slice(0,10);
  }
  function anchorNext(scope, anchor) {
    const d = new Date(anchor + 'T00:00:00');
    if (scope === 'week')  d.setDate(d.getDate() + 7);
    else if (scope === 'month') d.setMonth(d.getMonth() + 1);
    else d.setFullYear(d.getFullYear() + 1);
    return d.toISOString().slice(0,10);
  }

  // Delegación de eventos (card fija)
  document.addEventListener('click', function(e) {
    const btnScope = e.target.closest('[data-inicio-graf-scope]');
    if (btnScope) {
      const scope = btnScope.getAttribute('data-inicio-graf-scope');

      const wrap = document.getElementById('inicioGrafico');
      const anchor = (wrap && wrap.getAttribute('data-anchor')) || getTodayYMD();

      fetchPayload(scope, anchor).then(renderChart).catch(console.error);
      return;
    }

    const btnNav = e.target.closest('[data-inicio-graf-nav]');
    if (btnNav) {
      if (btnNav.disabled) return;

      const wrap = document.getElementById('inicioGrafico');
      const scope = (wrap && wrap.getAttribute('data-scope')) || 'week';
      const anchor = (wrap && wrap.getAttribute('data-anchor')) || getTodayYMD();

      const dir = btnNav.getAttribute('data-inicio-graf-nav');
      const nextAnchor = (dir === 'prev') ? anchorPrev(scope, anchor) : anchorNext(scope, anchor);

      fetchPayload(scope, nextAnchor).then(renderChart).catch(console.error);
      return;
    }
  });

  // render inicial desde payload embebido (sin AJAX)
  const initial = readPayloadFromDom();
  if (initial && initial.ok) renderChart(initial);
})();
</script>
