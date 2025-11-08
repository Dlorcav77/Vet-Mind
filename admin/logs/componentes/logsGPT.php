<?php
require_once("../config.php");
$mysqli = conn();

$logs_dir = realpath(__DIR__ . "/../../../funciones/logs");

// Funciones auxiliares
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

function fmt($n) {
    return is_numeric($n) ? number_format($n, 0, ',', '.') : $n;
}
function fmt_usd($n) {
    return '$' . number_format($n, 6, '.', ',');
}

// === Procesamiento de logs ===
$app_file = "$logs_dir/gpt_app-$fecha.log.jsonl";
$body_logs = cargar_body_logs($fecha, $logs_dir);
$eventos = cargar_logs($app_file);

$requests = 0;
$responses = 0;
$http = [];
$latencias = [];
$cost_total = 0;
foreach ($eventos as $e) {
    if (($e['event'] ?? '') === 'request') $requests++;
    if (($e['event'] ?? '') === 'response') {
        $responses++;
        $h = $e['http'] ?? 0;
        $http[$h] = ($http[$h] ?? 0) + 1;
        if (isset($e['ms'])) $latencias[] = (int)$e['ms'];
        if (isset($e['cost_usd'])) $cost_total += (float)$e['cost_usd'];
    }
}
sort($latencias);
$p50 = $latencias[(int)(count($latencias)*0.5)] ?? 0;
$p95 = $latencias[(int)(count($latencias)*0.95)] ?? 0;
$p99 = $latencias[(int)(count($latencias)*0.99)] ?? 0;
$max = end($latencias);
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
        $rid = $e['rid'] ?? '';
        $event = $e['event'] ?? '';
        $ts = $e['ts_local'] ?? $e['ts_utc'] ?? '';
        $hora = substr($ts, 11, 8);
        $modelo = $e['model'] ?? '';
        $httpc = $e['http'] ?? '';
        $ms = $e['ms'] ?? '';
        $tok = ($e['total_tokens'] ?? ($e['completion_tokens'] ?? 0));
        $usd = $e['cost_usd'] ?? '';
      ?>
        <tr>
          <td><?= $hora ?></td>
          <td><?= $event ?></td>
          <td><code><?= substr($rid, 0, 8) ?></code></td>
          <td><?= htmlspecialchars($modelo) ?></td>
          <td><?= $httpc ?></td>
          <td><?= $ms ?></td>
          <td><?= $tok ?></td>
          <td><?= $usd ? fmt_usd($usd) : '' ?></td>
          <td>
            <?php if (!empty($body_logs[$rid])): ?>
              <button class="btn btn-sm btn-outline-primary" onclick="toggleBody('<?= $rid ?>')">Ver</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php if (!empty($body_logs[$rid])): ?>
          <tr id="body-<?= $rid ?>" style="display:none; background:#fefefe;">
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
