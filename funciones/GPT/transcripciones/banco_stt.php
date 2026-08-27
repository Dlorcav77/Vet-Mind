<?php
// /funciones/GPT/transcripciones/banco_stt.php
// Banco de pruebas para motores STT.
// Disponible únicamente en development y con sesión VetMind válida.

declare(strict_types=1);

@set_time_limit(600);
mb_internal_encoding('UTF-8');


$ROOT_DIR = dirname(__DIR__, 3);

require_once(
    $ROOT_DIR
    . '/funciones/session/funcionesSesion.php'
);


$entorno = strtolower(
    trim(
        (string)(
            getenv('APP_ENV')
            ?: ($_ENV['APP_ENV'] ?? '')
            ?: ($_SERVER['APP_ENV'] ?? '')
            ?: 'production'
        )
    )
);


if (
    !in_array(
        $entorno,
        ['development', 'dev', 'local'],
        true
    )
) {
    http_response_code(404);
    exit;
}


configurarErroresAplicacion();
iniciarSesionSegura();
exigirAutenticacion('/index.php');


$csrfToken = tokenCsrf();

$sessionCookie =
    session_name()
    . '='
    . session_id();


// ===== CONFIG =====
const ENDPOINT =
    'https://dev-app.vet-mind.cl/funciones/GPT/transcribir_audio.php';

const AUDIO_DIR =
    __DIR__ . '/banco_audios';

// Motores disponibles para probar. Clave = valor que recibe transcribir_audio.php en $_POST['motor'].
$MOTORES = [
    'assembly'    => 'AssemblyAI viejo (/v2 clásico)',
    'deepgram'    => 'Deepgram Nova-3',
    'assembly_v3' => 'AssemblyAI Universal-3 Pro',
    'grok'        => 'Grok',
    'openai_4o'   => 'GPT-4o',
];

// ---- Listar audios .ogg disponibles ----
function listar_audios(): array {
    if (!is_dir(AUDIO_DIR)) return [];
    $files = glob(AUDIO_DIR . '/*.ogg') ?: [];
    sort($files);
    return array_map('basename', $files);
}

function probar_motor(
    string $audioFile,
    string $motor,
    string $sessionCookie,
    string $csrfToken
): array {

    $ruta =
        AUDIO_DIR
        . '/'
        . basename($audioFile);

    if (!is_file($ruta)) {

        return [
            'ok'        => false,
            'texto'     => '',
            'err'       => 'Audio no encontrado',
            'ms'        => 0,
            'audio_tmp' => ''
        ];
    }


    $post = [
        'audio' => new CURLFile(
            $ruta,
            'audio/ogg',
            basename($ruta)
        ),
        'motor' => $motor
    ];


    $t0 = microtime(true);

    $ch = curl_init(ENDPOINT);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_COOKIE         => $sessionCookie,

            CURLOPT_HTTPHEADER => [
                'X-CSRF-Token: ' . $csrfToken
            ],

            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 180
        ]
    );


    $resp = curl_exec($ch);

    $err =
        curl_errno($ch)
            ? curl_error($ch)
            : '';

    $http =
        (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);


    $ms =
        (int)round(
            (microtime(true) - $t0)
            * 1000
        );


    if ($err !== '') {

        return [
            'ok'        => false,
            'texto'     => '',
            'err'       => 'cURL: ' . $err,
            'ms'        => $ms,
            'audio_tmp' => ''
        ];
    }


    $j =
        json_decode(
            (string)$resp,
            true
        );


    if (!is_array($j)) {

        return [
            'ok'        => false,
            'texto'     => '',
            'err'       =>
                'HTTP '
                . $http
                . ' · respuesta no-JSON: '
                . substr(
                    (string)$resp,
                    0,
                    300
                ),
            'ms'        => $ms,
            'audio_tmp' => ''
        ];
    }


    return [
        'ok' =>
            (($j['status'] ?? '') === 'success'),

        'texto' =>
            (string)($j['texto'] ?? ''),

        'err' =>
            (string)($j['message'] ?? ''),

        'ms' =>
            $ms,

        'audio_tmp' =>
            (string)($j['audio_tmp'] ?? '')
    ];
}

$audios =
    listar_audios();

$audioSel =
    (string)($_POST['audio'] ?? '');

$motoresSel =
    $_POST['motores'] ?? [];

if (!is_array($motoresSel)) {
    $motoresSel = [];
}

$resultados = [];


$run =
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['run'])
    && $audioSel !== ''
    && !empty($motoresSel);


if ($run) {

    validarTokenCsrf();


    /*
     * Liberamos el lock de sesión antes de llamar
     * por cURL a otro PHP que utilizará la misma sesión.
     */
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        session_write_close();
    }


    if (
        in_array(
            $audioSel,
            $audios,
            true
        )
    ) {

        foreach ($motoresSel as $m) {

            $m =
                (string)$m;

            if (!isset($MOTORES[$m])) {
                continue;
            }

            $resultados[$m] =
                probar_motor(
                    $audioSel,
                    $m,
                    $sessionCookie,
                    $csrfToken
                );
        }
    }
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco STT · Comparar motores</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f5f7;color:#1f2733}
  .top{background:#0f766e;color:#fff;padding:16px 22px}
  .top h1{margin:0;font-size:18px}
  .wrap{max-width:1200px;margin:0 auto;padding:18px}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:16px}
  .panel h2{margin:0 0 12px;font-size:14px;color:#334155}
  label{display:block;font-size:14px;margin:4px 0;cursor:pointer}
  select{width:100%;max-width:480px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
  .motores{display:flex;flex-wrap:wrap;gap:10px;margin-top:6px}
  .motores label{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin:0}
  .btn{display:inline-block;background:#0f766e;color:#fff;border:none;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;cursor:pointer;margin-top:12px}
  .cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff}
  .col{padding:14px 16px;border-right:1px solid #eef2f6}
  .col:last-child{border-right:none}
  .col h3{margin:0 0 4px;font-size:13px;color:#0f766e}
  .col .meta{font-size:12px;color:#64748b;margin-bottom:8px}
  pre{white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit;font-size:13px;line-height:1.55}
  .err{background:#fef2f2;color:#991b1b;padding:8px 10px;border-radius:7px;font-size:13px}
  .empty{color:#64748b;font-size:14px}
</style></head><body>
<div class="top"><h1>Banco STT · Comparar motores de transcripción</h1></div>
<div class="wrap">

  <?php if (empty($audios)): ?>
    <div class="panel"><p class="empty">No hay audios en <code><?= e(AUDIO_DIR) ?></code>. Sube tus <code>.ogg</code> ahí.</p></div>
  <?php else: ?>
    <form class="panel" method="post">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($csrfToken) ?>"
    >
      <h2>1 · Elige audio</h2>
      <select name="audio">
        <?php foreach ($audios as $a): ?>
          <option value="<?= e($a) ?>" <?= $a === $audioSel ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>

      <h2 style="margin-top:16px">2 · Elige motores</h2>
      <div class="motores">
        <?php foreach ($MOTORES as $k => $nombre): ?>
          <label>
            <input type="checkbox" name="motores[]" value="<?= e($k) ?>"
              <?= in_array($k, $motoresSel, true) ? 'checked' : '' ?>>
            <?= e($nombre) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <button class="btn" type="submit" name="run" value="1">▶ Transcribir</button>
    </form>
  <?php endif; ?>

  <?php if ($run && !empty($resultados)): ?>
    <div class="panel" style="padding:0">
      <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0">
        Audio: <?= e($audioSel) ?>
      </h2>
      <div class="cols">
        <?php foreach ($resultados as $m => $r): ?>
          <div class="col">
            <h3><?= e($MOTORES[$m] ?? $m) ?></h3>
            <div class="meta"><?= (int)$r['ms'] ?> ms<?= $r['audio_tmp'] !== '' ? ' · ' . e($r['audio_tmp']) : '' ?></div>
            <?php if ($r['ok']): ?>
              <pre><?= e($r['texto']) ?></pre>
            <?php else: ?>
              <div class="err"><?= e($r['err'] ?: 'Error desconocido') ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php elseif (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && isset($_POST['run'])
    ): ?>
    <div class="panel"><p class="empty">Elige un audio y al menos un motor.</p></div>
  <?php endif; ?>

</div>
</body></html>
