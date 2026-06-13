<?php
// /funciones/GPT/transcripciones/banco_stt_doble.php
// Banco de pruebas DOBLE MOTOR.
// Sube un audio, elige 2 motores, transcribe con ambos (HTTP a transcribir_audio.php),
// muestra ambas transcripciones, marca discrepancias y permite enviar a la IA (proceso_gpt.php).
// No toca producción: transcribir_audio.php, proceso_gpt.php ni el prompt quedan intactos.
declare(strict_types=1);
@set_time_limit(600);
mb_internal_encoding('UTF-8');

// ===== CONFIG =====
const ENDPOINT_STT = 'https://dev-app.vet-mind.cl/funciones/GPT/transcribir_audio.php';
const ENDPOINT_GPT = 'https://dev-app.vet-mind.cl/funciones/GPT/proceso_gpt.php';
const TEST_TOKEN   = 'gondolengua'; // debe coincidir con STT_TEST_TOKEN
const AUDIO_DIR    = __DIR__ . '/banco_audios';

// Motores disponibles. Clave = valor que recibe transcribir_audio.php en $_POST['motor'].
$MOTORES = [
    'assembly'    => 'AssemblyAI viejo (/v2 clásico)',
    'deepgram'    => 'Deepgram Nova-3',
    'assembly_v3' => 'AssemblyAI Universal-3 Pro',
    'grok'        => 'Grok',
    'openai_4o'   => 'GPT-4o',
];

// ---- Listar audios .ogg ----
function listar_audios(): array {
    if (!is_dir(AUDIO_DIR)) return [];
    $files = glob(AUDIO_DIR . '/*.ogg') ?: [];
    sort($files);
    return array_map('basename', $files);
}

// ---- Llamar a un motor por HTTP (sube el .ogg) ----
function probar_motor(string $audioFile, string $motor): array {
    $ruta = AUDIO_DIR . '/' . basename($audioFile);
    if (!is_file($ruta)) {
        return ['ok' => false, 'texto' => '', 'err' => 'Audio no encontrado', 'ms' => 0];
    }
    $post = [
        'audio'      => new CURLFile($ruta, 'audio/ogg', basename($ruta)),
        'test_token' => TEST_TOKEN,
        'motor'      => $motor,
    ];
    $t0 = microtime(true);
    $ch = curl_init(ENDPOINT_STT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 180,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($err !== '') return ['ok' => false, 'texto' => '', 'err' => "cURL: $err", 'ms' => $ms];
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) return ['ok' => false, 'texto' => '', 'err' => "HTTP $http · no-JSON: " . substr((string)$resp, 0, 200), 'ms' => $ms];
    return [
        'ok'    => (($j['status'] ?? '') === 'success'),
        'texto' => (string)($j['texto'] ?? ''),
        'err'   => (string)($j['message'] ?? ''),
        'ms'    => $ms,
    ];
}

// ---- Ejecución ----
$audios   = listar_audios();
$audioSel = $_GET['audio'] ?? '';
$motorA   = $_GET['motor_a'] ?? 'deepgram';
$motorB   = $_GET['motor_b'] ?? 'openai_4o';
$resA = null;
$resB = null;

$run = isset($_GET['run']) && $audioSel !== '' && $motorA !== '' && $motorB !== '';
if ($run && in_array($audioSel, $audios, true)) {
    if (isset($MOTORES[$motorA])) $resA = probar_motor($audioSel, $motorA);
    if (isset($MOTORES[$motorB])) $resB = probar_motor($audioSel, $motorB);
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco STT Doble · 2 motores</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f5f7;color:#1f2733}
  .top{background:#0f766e;color:#fff;padding:16px 22px}
  .top h1{margin:0;font-size:18px}
  .wrap{max-width:1200px;margin:0 auto;padding:18px}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:16px}
  .panel h2{margin:0 0 12px;font-size:14px;color:#334155}
  select{width:100%;max-width:480px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
  .dos{display:flex;gap:16px;flex-wrap:wrap}
  .dos > div{flex:1;min-width:240px}
  .btn{display:inline-block;background:#0f766e;color:#fff;border:none;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;cursor:pointer;margin-top:12px}
  .cols{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff}
  .col{padding:14px 16px;border-right:1px solid #eef2f6}
  .col:last-child{border-right:none}
  .col h3{margin:0 0 4px;font-size:13px;color:#0f766e}
  .col .meta{font-size:12px;color:#64748b;margin-bottom:8px}
  pre{white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit;font-size:13px;line-height:1.55}
  .err{background:#fef2f2;color:#991b1b;padding:8px 10px;border-radius:7px;font-size:13px}
  .empty{color:#64748b;font-size:14px}
  label.lbl{display:block;font-size:13px;color:#475569;margin-bottom:4px}
</style></head><body>
<div class="top"><h1>Banco STT Doble · comparar 2 motores</h1></div>
<div class="wrap">

  <?php if (empty($audios)): ?>
    <div class="panel"><p class="empty">No hay audios en <code><?= e(AUDIO_DIR) ?></code>. Sube tus <code>.ogg</code> ahí.</p></div>
  <?php else: ?>
    <form class="panel" method="get">
      <h2>1 · Elige audio</h2>
      <select name="audio">
        <?php foreach ($audios as $a): ?>
          <option value="<?= e($a) ?>" <?= $a === $audioSel ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>

      <h2 style="margin-top:16px">2 · Elige 2 motores</h2>
      <div class="dos">
        <div>
          <label class="lbl">Motor A (principal)</label>
          <select name="motor_a">
            <?php foreach ($MOTORES as $k => $nombre): ?>
              <option value="<?= e($k) ?>" <?= $k === $motorA ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="lbl">Motor B (segundo)</label>
          <select name="motor_b">
            <?php foreach ($MOTORES as $k => $nombre): ?>
              <option value="<?= e($k) ?>" <?= $k === $motorB ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button class="btn" type="submit" name="run" value="1">▶ Transcribir con ambos</button>
    </form>
  <?php endif; ?>

  <?php if ($run && $resA !== null && $resB !== null): ?>
    <div class="panel" style="padding:0">
      <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0">Audio: <?= e($audioSel) ?></h2>
      <div class="cols">
        <div class="col">
          <h3><?= e($MOTORES[$motorA]) ?> · A</h3>
          <div class="meta"><?= (int)$resA['ms'] ?> ms</div>
          <?php if ($resA['ok']): ?>
            <pre><?= e($resA['texto']) ?></pre>
          <?php else: ?>
            <div class="err"><?= e($resA['err'] ?: 'Error') ?></div>
          <?php endif; ?>
        </div>
        <div class="col">
          <h3><?= e($MOTORES[$motorB]) ?> · B</h3>
          <div class="meta"><?= (int)$resB['ms'] ?> ms</div>
          <?php if ($resB['ok']): ?>
            <pre><?= e($resB['texto']) ?></pre>
          <?php else: ?>
            <div class="err"><?= e($resB['err'] ?: 'Error') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php elseif (isset($_GET['run'])): ?>
    <div class="panel"><p class="empty">Elige un audio y 2 motores.</p></div>
  <?php endif; ?>

</div>
</body></html>