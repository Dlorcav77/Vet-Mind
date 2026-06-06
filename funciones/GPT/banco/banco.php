<?php
// /funciones/GPT/banco/banco.php
declare(strict_types=1);
@set_time_limit(600);
mb_internal_encoding('UTF-8');

// ===== CONFIG (edita si cambia tu ruta/endpoint) =====
const ENDPOINT = 'https://dev-app.vet-mind.cl/funciones/GPT/proceso_gpt.php';
$RESULT_DIR = __DIR__ . '/resultados';
if (!is_dir($RESULT_DIR)) { @mkdir($RESULT_DIR, 0775, true); }

$data       = require(__DIR__ . '/casos.php');
$PLANTILLA  = $data['plantilla'];
$CASOS      = $data['casos'];

// Secciones base que un buen informe debe conservar siempre.
$SECCIONES = ['Vejiga','Riñón izquierdo','Riñón derecho','Bazo','Hígado','Vesícula biliar','Estómago','Páncreas','Linfonódulos','adrenales'];

// ---- Llamada a la IA real ----
function ia_call(array $caso, string $plantilla): array {
    $post = [
        'paciente'       => $caso['paciente'],
        'especie'        => $caso['especie'],
        'raza'           => $caso['raza'],
        'edad'           => $caso['edad'],
        'sexo'           => $caso['sexo'],
        'tipo_estudio'   => $caso['tipo_estudio'],
        'motivo'         => $caso['motivo'],
        'plantilla_base' => $plantilla,
        'texto'          => $caso['dictado'],
        'plantilla_id'   => 0,
    ];
    $ch = curl_init(ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 180,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    if ($err !== '') return ['ok'=>false, 'content'=>'', 'err'=>$err, 'usage'=>[]];
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) return ['ok'=>false, 'content'=>'', 'err'=>'JSON inválido del endpoint', 'usage'=>[]];
    return [
        'ok'      => (($j['status'] ?? '') === 'success'),
        'content' => (string)($j['content'] ?? ''),
        'err'     => (string)($j['message'] ?? ''),
        'usage'   => $j['usage'] ?? [],
    ];
}

// ---- Chequeos ----
function chk_sanitizacion(string $h): array {
    $bad = [];
    if (strpos($h, '```') !== false)      $bad[] = 'trae ``` (fences markdown)';
    if (stripos($h, '<style') !== false)  $bad[] = 'trae <style>';
    foreach (['<html','<head','<body'] as $t) if (stripos($h, $t) !== false) $bad[] = "trae $t";
    return ['dura'=>true, 'ok'=>empty($bad), 'detalle'=>$bad ? implode('; ', $bad) : 'HTML limpio'];
}
function chk_secciones(string $h, array $secciones): array {
    $faltan = [];
    foreach ($secciones as $s) if (mb_stripos($h, $s) === false) $faltan[] = $s;
    return ['dura'=>true, 'ok'=>empty($faltan), 'detalle'=>$faltan ? 'FALTAN: '.implode(', ', $faltan) : 'todas las secciones base presentes'];
}
function chk_flags(string $h): array {
    preg_match_all('/data-flag="(\d+)"/', $h, $mf);
    $flags = array_values(array_unique($mf[1]));
    $obs = '';
    if (($p = mb_stripos($h, 'Observaciones del Asistente')) !== false) $obs = mb_substr($h, $p);
    preg_match_all('/\((\d+)\)/', $obs, $mo);
    $obsNums = array_values(array_unique($mo[1]));
    if (empty($flags) && empty($obsNums)) return ['dura'=>true, 'ok'=>true, 'detalle'=>'sin flags'];
    sort($flags, SORT_NUMERIC); sort($obsNums, SORT_NUMERIC);
    $sinObs  = array_diff($flags, $obsNums);
    $sinFlag = array_diff($obsNums, $flags);
    $ok = empty($sinObs) && empty($sinFlag);
    $d = [];
    if ($sinObs)  $d[] = 'flags sin observación: '.implode(',', $sinObs);
    if ($sinFlag) $d[] = 'observaciones sin flag: '.implode(',', $sinFlag);
    return ['dura'=>true, 'ok'=>$ok, 'detalle'=>$ok ? ('flags ok: '.implode(',', $flags)) : implode('; ', $d)];
}
function chk_medidas(string $dictado, string $h): array {
    preg_match_all('/\d+[.,]\d+/', $dictado, $md);
    $nums = array_values(array_unique(array_map(fn($n)=>str_replace(',', '.', $n), $md[0])));
    $hn = str_replace(',', '.', $h);
    $faltan = [];
    foreach ($nums as $n) if (strpos($hn, $n) === false) $faltan[] = $n;
    return ['dura'=>false, 'ok'=>empty($faltan), 'detalle'=>$faltan ? 'medidas del dictado ausentes en salida: '.implode(', ', $faltan).' (puede ser ruido del audio)' : 'todas las medidas del dictado aparecen'];
}

// ---- Ejecución ----
$run  = isset($_GET['run']);
$solo = $_GET['caso'] ?? '';
$resultados = [];

if ($run) {
    foreach ($CASOS as $key => $caso) {
        if ($solo !== '' && $solo !== $key) continue;
        $r = ia_call($caso, $PLANTILLA);
        $checks = [];
        if ($r['ok']) {
            $checks['Sanitización'] = chk_sanitizacion($r['content']);
            $checks['Secciones']    = chk_secciones($r['content'], $SECCIONES);
            $checks['Flags ↔ Obs']  = chk_flags($r['content']);
            $checks['Medidas']      = chk_medidas($caso['dictado'], $r['content']);
            @file_put_contents($RESULT_DIR.'/'.$key.'_'.date('Ymd_His').'.html', $r['content']);
        }
        $resultados[$key] = ['caso'=>$caso, 'r'=>$r, 'checks'=>$checks];
    }
}

// ---- Salida HTML ----
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco de pruebas · Informes ecográficos</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f5f7;color:#1f2733}
  .top{background:#0f766e;color:#fff;padding:16px 22px}
  .top h1{margin:0;font-size:18px}
  .wrap{max-width:1200px;margin:0 auto;padding:18px}
  .btn{display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:9px 16px;border-radius:8px;font-size:14px;margin:4px 6px 4px 0}
  .btn.alt{background:#475569}
  .case{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin:16px 0;overflow:hidden}
  .case h2{margin:0;padding:12px 16px;background:#f1f5f9;font-size:15px;border-bottom:1px solid #e2e8f0}
  .cols{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0}
  .col{padding:12px 16px;border-right:1px solid #eef2f6;font-size:13px;line-height:1.5}
  .col:last-child{border-right:none}
  .col h3{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
  .col.ia{background:#fafdfc}
  .checks{padding:12px 16px;border-top:1px solid #e2e8f0;background:#fcfdff}
  .chk{font-size:13px;margin:5px 0;padding:6px 10px;border-radius:7px}
  .ok{background:#ecfdf5;color:#065f46}
  .fail{background:#fef2f2;color:#991b1b}
  .soft{opacity:.85}
  .notas{font-size:12px;color:#7c2d12;background:#fff7ed;padding:8px 12px;border-top:1px solid #fed7aa}
  .err{background:#fef2f2;color:#991b1b;padding:10px 16px}
  pre{white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit}
  @media(max-width:800px){.cols{grid-template-columns:1fr}.col{border-right:none;border-bottom:1px solid #eef2f6}}
</style></head><body>
<div class="top"><h1>Banco de pruebas · Informes ecográficos</h1></div>
<div class="wrap">
  <a class="btn" href="?run=1">▶ Correr los <?= count($CASOS) ?> casos</a>
  <?php foreach ($CASOS as $k => $c): ?>
    <a class="btn alt" href="?run=1&caso=<?= e($k) ?>"><?= e($c['paciente']) ?></a>
  <?php endforeach; ?>

  <?php if (!$run): ?>
    <p style="color:#475569;font-size:14px">Cada corrida llama a la IA real (el motor que tengas activo en <code>proceso_gpt.php</code>) una vez por caso. Compara la salida contra la respuesta de la ayudante y aplica chequeos automáticos.</p>
  <?php endif; ?>

  <?php foreach ($resultados as $key => $res):
        $caso = $res['caso']; $r = $res['r']; ?>
    <div class="case">
      <h2><?= e($caso['paciente']) ?> <span style="color:#94a3b8;font-weight:400">(<?= e($key) ?>)</span></h2>
      <?php if (!$r['ok']): ?>
        <div class="err">Error: <?= e($r['err'] ?: 'sin contenido') ?></div>
      <?php else: ?>
        <div class="cols">
          <div class="col"><h3>Dictado (audio)</h3><pre><?= e($caso['dictado']) ?></pre></div>
          <div class="col"><h3>Ayudante (referencia)</h3><pre><?= e($caso['ayudante']) ?></pre></div>
          <div class="col ia"><h3>IA (<?= e($r['usage']['model'] ?? 'salida') ?>)</h3><?= $r['content'] ?></div>
        </div>
        <div class="checks">
          <?php foreach ($res['checks'] as $nombre => $c):
                $cls = $c['ok'] ? 'ok' : 'fail';
                $soft = $c['dura'] ? '' : ' soft'; ?>
            <div class="chk <?= $cls.$soft ?>">
              <strong><?= e($nombre) ?><?= $c['dura'] ? '' : ' (blando)' ?>:</strong> <?= e($c['detalle']) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($caso['notas'])): ?>
        <div class="notas"><strong>Notas del caso:</strong> <?= e($caso['notas']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
</body></html>