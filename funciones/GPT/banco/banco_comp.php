<?php
// funciones/GPT/banco_comparacion/banco_comp.php
declare(strict_types=1);
@set_time_limit(600);
mb_internal_encoding('UTF-8');

require_once(__DIR__ . '/driver_comp.php');
require_once(__DIR__ . '/checks.php');

$MOTORES = require(__DIR__ . '/motores.php');
$data    = require(__DIR__ . '/casos.php');
$PLANTILLA = $data['plantilla'];
$CASOS     = $data['casos'];

$SECCIONES = ['Vejiga','Riñón izquierdo','Riñón derecho','Bazo','Hígado','Vesícula biliar','Estómago','Páncreas','Linfonódulos','adrenales'];

// ---- Ejecución ----
$run  = isset($_GET['run']);
$solo = $_GET['caso'] ?? '';

// Motores elegidos (?motor[]=grok&motor[]=claude...). Si no llega ninguno, los 4.
$motoresSel = $_GET['motor'] ?? array_keys($MOTORES);
if (!is_array($motoresSel)) $motoresSel = [$motoresSel];
$motoresSel = array_values(array_intersect(array_keys($MOTORES), $motoresSel));
if (empty($motoresSel)) $motoresSel = array_keys($MOTORES);

$resultados = [];

if ($run) {
    foreach ($CASOS as $key => $caso) {
        if ($solo !== '' && $solo !== $key) continue;

        $input = [
            'paciente'       => $caso['paciente'],
            'especie'        => $caso['especie'],
            'raza'           => $caso['raza'],
            'edad'           => $caso['edad'],
            'sexo'           => $caso['sexo'],
            'tipo_estudio'   => $caso['tipo_estudio'],
            'motivo'         => $caso['motivo'],
            'plantilla_base' => $PLANTILLA,
            'texto'          => $caso['dictado'],
            'plantilla_id'   => 0,
        ];

        $porMotor = [];
        foreach ($motoresSel as $mk) {
            $r = comp_call_motor($MOTORES[$mk], $input);
            $checks = [];
            if ($r['ok']) {
                $checks['Sanitización'] = chk_sanitizacion($r['content']);
                $checks['Secciones']    = chk_secciones($r['content'], $SECCIONES);
                $checks['Flags ↔ Obs']  = chk_flags($r['content']);
                $checks['Medidas']      = chk_medidas($caso['dictado'], $r['content']);
            }
            $porMotor[$mk] = ['r'=>$r, 'checks'=>$checks];
        }

        $resultados[$key] = ['caso'=>$caso, 'motores'=>$porMotor];
    }
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco comparación · 4 motores</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f5f7;color:#1f2733}
  .top{background:#0f766e;color:#fff;padding:16px 22px}
  .top h1{margin:0;font-size:18px}
  .wrap{max-width:1500px;margin:0 auto;padding:18px}
  .btn{display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:9px 16px;border-radius:8px;font-size:14px;margin:4px 6px 4px 0}
  .btn.alt{background:#475569}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin:12px 0}
  .panel label{font-size:14px;margin-right:14px;white-space:nowrap}
  .case{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin:16px 0;overflow:hidden}
  .case h2{margin:0;padding:12px 16px;background:#f1f5f9;font-size:15px;border-bottom:1px solid #e2e8f0}
  .cols{display:grid;gap:0}
  .col{padding:12px 16px;border-right:1px solid #eef2f6;font-size:13px;line-height:1.5;vertical-align:top}
  .col:last-child{border-right:none}
  .col h3{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#0f766e}
  .col .meta{font-size:11px;color:#64748b;margin-bottom:8px}
  .ia{background:#fafdfc}
  .checks{margin-top:10px;border-top:1px dashed #e2e8f0;padding-top:8px}
  .chk{font-size:12px;margin:4px 0;padding:5px 8px;border-radius:6px}
  .ok{background:#ecfdf5;color:#065f46}
  .fail{background:#fef2f2;color:#991b1b}
  .soft{opacity:.85}
  .notas{font-size:12px;color:#7c2d12;background:#fff7ed;padding:8px 12px;border-top:1px solid #fed7aa}
  .err{background:#fef2f2;color:#991b1b;padding:10px 16px}
  .ref{padding:12px 16px;border-bottom:1px solid #eef2f6;font-size:13px}
  .ref pre{white-space:pre-wrap;word-break:break-word;margin:6px 0 0;font-family:inherit}
  pre.dictado{white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit}
</style></head><body>
<div class="top"><h1>Banco comparación · hasta 4 motores en paralelo</h1></div>
<div class="wrap">

  <form method="get" class="panel">
    <input type="hidden" name="run" value="1">
    <strong style="font-size:13px">Motores:</strong>
    <?php foreach ($MOTORES as $mk => $mv):
          $checked = in_array($mk, $motoresSel, true) ? 'checked' : ''; ?>
      <label><input type="checkbox" name="motor[]" value="<?= e($mk) ?>" <?= $checked ?>> <?= e($mv['label']) ?></label>
    <?php endforeach; ?>
    <div style="margin-top:10px">
      <button class="btn" type="submit">▶ Correr los <?= count($CASOS) ?> casos</button>
    </div>
  </form>

  <div class="panel">
    <?php foreach ($CASOS as $k => $c):
          $qs = 'run=1&caso='.urlencode($k);
          foreach ($motoresSel as $mk) $qs .= '&motor[]='.urlencode($mk); ?>
      <a class="btn alt" href="?<?= $qs ?>"><?= e($c['paciente']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$run): ?>
    <p style="color:#475569;font-size:14px">Elige motores y corre. Cada motor recibe el mismo prompt (mismo helper que producción) y se muestra lado a lado con sus chequeos.</p>
  <?php endif; ?>

  <?php foreach ($resultados as $key => $res):
        $caso = $res['caso'];
        $n = max(1, count($res['motores'])); ?>
    <div class="case">
      <h2><?= e($caso['paciente']) ?> <span style="color:#94a3b8;font-weight:400">(<?= e($key) ?>)</span></h2>

      <div class="ref">
        <strong style="font-size:12px;color:#64748b">DICTADO (audio)</strong>
        <pre class="dictado"><?= e($caso['dictado']) ?></pre>
      </div>

      <div class="cols" style="grid-template-columns:repeat(<?= $n ?>,1fr)">
        <?php foreach ($res['motores'] as $mk => $mres):
              $r = $mres['r'];
              $label = $MOTORES[$mk]['label']; ?>
          <div class="col ia">
            <h3><?= e($label) ?></h3>
            <?php if (!$r['ok']): ?>
              <div class="err">Error: <?= e($r['err'] ?: 'sin contenido') ?></div>
            <?php else:
                  $u = $r['usage']; ?>
              <div class="meta">
                <?= (int)$r['ms'] ?> ms ·
                in <?= (int)($u['prompt_tokens'] ?? 0) ?> ·
                out <?= (int)($u['completion_tokens'] ?? 0) ?> ·
                $<?= number_format((float)($u['cost_usd'] ?? 0), 6) ?>
              </div>
              <?= $r['content'] ?>
              <div class="checks">
                <?php foreach ($mres['checks'] as $nombre => $c):
                      $cls = $c['ok'] ? 'ok' : 'fail';
                      $soft = $c['dura'] ? '' : ' soft'; ?>
                  <div class="chk <?= $cls.$soft ?>">
                    <strong><?= e($nombre) ?><?= $c['dura'] ? '' : ' (blando)' ?>:</strong> <?= e($c['detalle']) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($caso['notas'])): ?>
        <div class="notas"><strong>Notas del caso:</strong> <?= e($caso['notas']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
</body></html>