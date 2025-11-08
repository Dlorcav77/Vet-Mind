<?php
require_once("../config.php");
$mysqli = conn();

$color = $color_primario ?? "#3498db";

// 1) Fecha: GET con fallback a hoy (America/Santiago)
$fecha = $_GET['fecha'] ?? (new DateTime('now', new DateTimeZone('America/Santiago')))->format('Y-m-d');

// 2) Detección de "modo parcial" (para AJAX)
$is_partial = isset($_GET['partial']) && $_GET['partial'] == '1';

// 3) Ruta de logs
$logs_dir = realpath(__DIR__ . "/../../funciones/logs");

// ---------- Helpers ----------
function cargar_logs(string $file): array {
    $eventos = [];
    if (!is_file($file)) return $eventos;
    $f = fopen($file, 'rb');
    while ($linea = fgets($f)) {
        $json = json_decode(trim($linea), true);
        if (is_array($json)) $eventos[] = $json;
    }
    fclose($f);
    return $eventos;
}
function cargar_body_logs(string $date, string $logs_dir): array {
    $file = "$logs_dir/gpt_bodies-$date.log.jsonl";
    $by_rid = [];
    if (!is_file($file)) return $by_rid;
    $f = fopen($file, 'rb');
    while ($linea = fgets($f)) {
        $row = json_decode(trim($linea), true);
        if (!is_array($row)) continue;
        $rid = $row['rid'] ?? null;
        if (!$rid) continue;
        $kind = $row['kind'] ?? '';
        $by_rid[$rid][$kind] = $row;
    }
    fclose($f);
    return $by_rid;
}
function fmt($n) { return is_numeric($n) ? number_format($n, 0, ',', '.') : $n; }
function fmt_usd($n) { return '$' . number_format($n, 6, '.', ','); }

// ---------- Cálculos ----------
$app_file  = "$logs_dir/gpt_app-$fecha.log.jsonl";
$body_logs = cargar_body_logs($fecha, $logs_dir);
$eventos   = cargar_logs($app_file);

$requests   = 0;
$responses  = 0;
$http       = [];
$latencias  = [];
$cost_total = 0.0;

foreach ($eventos as $e) {
    if (($e['event'] ?? '') === 'request') $requests++;
    if (($e['event'] ?? '') === 'response') {
        $responses++;
        $h = $e['http'] ?? 0;
        $http[$h] = ($http[$h] ?? 0) + 1;
        if (isset($e['ms']))        $latencias[] = (int)$e['ms'];
        if (isset($e['cost_usd']))  $cost_total += (float)$e['cost_usd'];
    }
}
sort($latencias);
$p50 = $latencias[(int)(count($latencias) * 0.50)] ?? 0;
$p95 = $latencias[(int)(count($latencias) * 0.95)] ?? 0;
$p99 = $latencias[(int)(count($latencias) * 0.99)] ?? 0;
$max = $latencias ? end($latencias) : 0;

// ---------- Render del bloque interno (parcial) ----------
ob_start();
?>
<?php if (empty($eventos)): ?>
  <div class="alert alert-warning">No se encontraron logs para esta fecha.</div>
<?php else: ?>
  <div class="card shadow-sm mb-4 border">
    <div class="card-body">
      <div class="d-flex align-items-center mb-3">
        <i class="fas fa-chart-bar me-2 text-primary fs-5"></i>
        <h5 class="mb-0 text-primary fw-bold">Resumen del día</h5>
        <span class="ms-auto text-muted small">Logs de <?= htmlspecialchars($fecha) ?></span>
      </div>

      <div class="row g-3">
        <div class="col-md-2"><div class="p-2 bg-light rounded border text-center"><div class="text-muted small">Requests</div><div class="fw-bold fs-5"><?= fmt($requests) ?></div></div></div>
        <div class="col-md-2"><div class="p-2 bg-light rounded border text-center"><div class="text-muted small">Respuestas</div><div class="fw-bold fs-5"><?= fmt($responses) ?></div></div></div>
        <div class="col-md-2"><div class="p-2 bg-light rounded border text-center"><div class="text-muted small">HTTP 200</div><div class="fw-bold fs-5"><?= fmt($http[200] ?? 0) ?></div></div></div>
        <div class="col-md-2"><div class="p-2 bg-light rounded border text-center"><div class="text-muted small">Errores</div><div class="fw-bold fs-5"><?= fmt(($http[400] ?? 0) + ($http[500] ?? 0)) ?></div></div></div>
        <div class="col-md-4"><div class="p-2 bg-light rounded border text-center"><div class="text-muted small">Costo total (USD)</div><div class="fw-bold fs-5"><?= fmt_usd($cost_total) ?></div></div></div>
      </div>

      <div class="row g-3 mt-3">
        <div class="col-md-3"><div class="p-2 bg-white rounded border text-center"><div class="text-muted small">p50 latencia</div><div class="fw-semibold"><?= fmt($p50) ?> ms</div></div></div>
        <div class="col-md-3"><div class="p-2 bg-white rounded border text-center"><div class="text-muted small">p95 latencia</div><div class="fw-semibold"><?= fmt($p95) ?> ms</div></div></div>
        <div class="col-md-3"><div class="p-2 bg-white rounded border text-center"><div class="text-muted small">p99 latencia</div><div class="fw-semibold"><?= fmt($p99) ?> ms</div></div></div>
        <div class="col-md-3"><div class="p-2 bg-white rounded border text-center"><div class="text-muted small">Latencia máx.</div><div class="fw-semibold"><?= fmt($max) ?> ms</div></div></div>
      </div>
    </div>
  </div>

  <table class="table table-hover table-bordered align-middle bg-white rounded shadow-sm mt-4" style="overflow: hidden;">
    <thead>
      <tr><th>Hora</th><th>Tipo</th><th>RID</th><th>Modelo</th><th>HTTP</th><th>ms</th><th>Tokens</th><th>$</th><th>Acción</th></tr>
    </thead>
    <tbody>
      <?php foreach ($eventos as $e):
        $rid   = $e['rid'] ?? '';
        $event = $e['event'] ?? '';
        $ts    = $e['ts_local'] ?? $e['ts_utc'] ?? '';
        $hora  = substr($ts, 11, 8);
        $modelo= $e['model'] ?? '';
        $httpc = $e['http'] ?? '';
        $ms    = $e['ms'] ?? '';
        $tok   = ($e['total_tokens'] ?? ($e['completion_tokens'] ?? 0));
        $usd   = $e['cost_usd'] ?? '';
      ?>
        <tr>
          <td><?= htmlspecialchars($hora) ?></td>
          <td><?= htmlspecialchars($event) ?></td>
          <td><code><?= htmlspecialchars(substr($rid, 0, 8)) ?></code></td>
          <td><?= htmlspecialchars($modelo) ?></td>
          <td><?= htmlspecialchars($httpc) ?></td>
          <td><?= htmlspecialchars((string)$ms) ?></td>
          <td><?= htmlspecialchars((string)$tok) ?></td>
          <td><?= $usd ? fmt_usd($usd) : '' ?></td>
          <td>
            <?php if (!empty($body_logs[$rid])): ?>
              <button class="btn btn-sm btn-outline-primary" onclick="toggleBody('<?= htmlspecialchars($rid) ?>')">Ver</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php if (!empty($body_logs[$rid])): ?>
          <tr id="body-<?= htmlspecialchars($rid) ?>" style="display:none; background:#fefefe;">
            <td colspan="9">
              <div><strong>Prompt:</strong><pre style="white-space:pre-wrap"><?= htmlspecialchars($body_logs[$rid]['prompt']['prompt'] ?? '') ?></pre></div>
              <div><strong>Output:</strong><pre style="white-space:pre-wrap"><?= htmlspecialchars($body_logs[$rid]['output']['content'] ?? '') ?></pre></div>
              <div><strong>Raw:</strong><pre style="white-space:pre-wrap; max-height:200px; overflow:auto;"><?= htmlspecialchars($body_logs[$rid]['raw_response']['body'] ?? '') ?></pre></div>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<script>
function toggleBody(id) {
  const el = document.getElementById('body-' + id);
  if (!el) return;
  el.style.display = (el.style.display === 'none') ? 'table-row' : 'none';
}
</script>
<?php
$contenido_parcial = ob_get_clean();

// ---------- Si es parcial, devuelvo solo el bloque ----------
if ($is_partial) {
    echo $contenido_parcial;
    exit;
}

// ---------- Si NO es parcial, renderizo página completa ----------
?>
<div id="logs" data-page-id="logs">
  <div class="container py-4" style="background: linear-gradient(135deg, <?= $color ?>31 0%, #fff 90%); min-height: 100vh;">
    <h3 class="mb-4">
      <i class="fas fa-robot text-primary me-2"></i>
      Logs GPT - <span id="titulo-fecha"><?= htmlspecialchars($fecha) ?></span>
    </h3>

    <form method="get" class="mb-4" onsubmit="event.preventDefault();">
      <div class="d-flex align-items-center">
        <label for="fecha" class="me-2 fw-semibold"><i class="fas fa-calendar-day me-1"></i> Ver logs del día:</label>
        <input type="date" id="fecha" name="fecha" class="form-control form-control-sm" style="max-width: 180px;"
               value="<?= htmlspecialchars($fecha) ?>">
        <button type="button" class="btn btn-sm btn-primary ms-2" onclick="cargarLogs()">Ir</button>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="irHoy()">Hoy</button>
      </div>
    </form>

    <div id="contenedor-logs">
      <?= $contenido_parcial ?>
    </div>
  </div>
</div>

<script>
function getFechaInput() {
  const inp = document.getElementById('fecha');
  return inp && inp.value ? inp.value : new Date().toISOString().slice(0,10);
}

function actualizarTitulo(fecha) {
  const titulo = document.getElementById('titulo-fecha');
  if (!titulo) return;
  // Muestra en YYYY-MM-DD (o cambia a DD-MM-YYYY si prefieres)
  // const [y,m,d] = fecha.split('-'); titulo.textContent = `${d}-${m}-${y}`;
  titulo.textContent = fecha;
}

function cargarLogs(fecha) {
  fecha = fecha || getFechaInput();
  const cont = document.getElementById('contenedor-logs');
  cont.innerHTML = '<div class="text-center py-4">Cargando logs...</div>';

  fetch('/admin/logs/logs.php?partial=1&fecha=' + encodeURIComponent(fecha))
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.text();
    })
    .then(html => {
      cont.innerHTML = html;

      // Actualiza título y input
      actualizarTitulo(fecha);
      const input = document.getElementById('fecha');
      if (input) input.value = fecha;

      // Mantiene la URL actual con ?fecha=...
      const url = new URL(window.location.href);
      url.searchParams.set('fecha', fecha);
      window.history.replaceState({}, '', url);
    })
    .catch(err => {
      console.error(err);
      cont.innerHTML = '<div class="alert alert-danger">No se pudo cargar el día solicitado.</div>';
    });
}

function irHoy() {
  const hoy = new Date().toISOString().slice(0,10);
  const input = document.getElementById('fecha');
  if (input) input.value = hoy;
  cargarLogs(hoy);
}

// Si la URL trae ?fecha=... carga ese día y sincroniza el título
document.addEventListener('DOMContentLoaded', () => {
  const url = new URL(window.location.href);
  const f = url.searchParams.get('fecha');
  if (f) {
    cargarLogs(f);
  } else {
    actualizarTitulo(document.getElementById('fecha').value);
  }
});
</script>
