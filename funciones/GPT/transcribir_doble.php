<?php
// funciones/GPT/transcribir_doble.php
// Orquestador del doble STT. Convierte el audio a WAV UNA vez y lo manda a 2 motores
// en paralelo (curl_multi a transcribir_audio.php pasando audio_tmp). Corre el validador
// y devuelve: texto (motor A) + texto_doble (bloques a anexar) + audio_tmp.
// No toca los motores. La conversión se hace aquí; los motores reusan el WAV.
declare(strict_types=1);
@set_time_limit(300);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

$ROOT_DIR = dirname(__DIR__, 2);
require_once($ROOT_DIR . "/configP.php");
require_once(__DIR__ . "/lib/stt_validador.php");
require_once($ROOT_DIR . "/funciones/conn/conn.php");
require_once(__DIR__ . "/lib/stt_store.php");

require_once(
    $ROOT_DIR
    . "/funciones/session/funcionesSesion.php"
);

configurarErroresAplicacion(true);
iniciarSesionSegura();

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode([
        'status' => 'error',
        'message' => 'Método HTTP no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// Motores del doble. A = redactor (original), B = comparador.
const MOTOR_A = 'deepgram';
const MOTOR_B = 'assembly_v3';

// Keyterms para AMBOS motores.
const USAR_KEYTERMS = '0';

$userId =
    isset($_SESSION['usuario_id'])
        ? (int)$_SESSION['usuario_id']
        : 0;

if ($userId <= 0) {
    http_response_code(401);

    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

validarTokenCsrf();

$csrfToken = tokenCsrf();

// ---- Preparación de audio (convertir 1 vez). Copiado del patrón de los motores. ----
function dbl_normalizar_ruta($ruta, $userId) {
    $ruta = trim((string)$ruta);
    if ($ruta === '') return null;
    $ruta = str_replace('\\', '/', $ruta);
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = ltrim($ruta, '/');
    $prefix = 'uploads/tmp/audio/';
    if (strpos($ruta, $prefix) !== 0) return null;
    $nombre = basename($ruta);
    if ($nombre === '' || $nombre === '.' || $nombre === '..') return null;
    if (strpos($nombre, 'tmp_audio_' . (int)$userId . '_') !== 0) return null;
    if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'wav') return null;
    return $prefix . $nombre;
}
function dbl_guardar_y_convertir($rootDir, $userId) {
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        return ['status'=>'error','message'=>'No se recibió ningún archivo de audio válido.','audio_tmp'=>''];
    }
    $maxBytes = 25 * 1024 * 1024;
    $tmpFile = $_FILES['audio']['tmp_name'];
    $size = (int)($_FILES['audio']['size'] ?? 0);
    if ($size <= 0 && is_file($tmpFile)) $size = filesize($tmpFile);
    if ($size > $maxBytes) { @unlink($tmpFile); return ['status'=>'error','message'=>'Archivo demasiado grande (máx 25 MB).','audio_tmp'=>'']; }
    $ext = strtolower(pathinfo($_FILES['audio']['name'] ?? 'audio.webm', PATHINFO_EXTENSION));
    if ($ext === '') $ext = 'webm';
    if (!in_array($ext, ['wav','webm','mp3','m4a','ogg','3gp','3g2'], true)) $ext = 'webm';
    $baseTmp = $rootDir . '/uploads/tmp/audio';
    if (!is_dir($baseTmp) && !mkdir($baseTmp, 0775, true) && !is_dir($baseTmp)) {
        return ['status'=>'error','message'=>'No se pudo crear la carpeta temporal de audio.','audio_tmp'=>''];
    }
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $day = $now->format('d'); $hmsms = $now->format('Hisv');
    $nomOrig = 'tmp_audio_original_' . (int)$userId . '_' . $day . '_' . $hmsms . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rutaOrig = $baseTmp . '/' . $nomOrig;
    $nomWav = 'tmp_audio_' . (int)$userId . '_' . $day . '_' . $hmsms . '_' . bin2hex(random_bytes(4)) . '.wav';
    $rutaWav = $baseTmp . '/' . $nomWav;
    if (!move_uploaded_file($tmpFile, $rutaOrig)) return ['status'=>'error','message'=>'No se pudo guardar el audio temporal.','audio_tmp'=>''];
    $ffmpeg = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?? '');
    if ($ffmpeg === '') { @unlink($rutaOrig); return ['status'=>'error','message'=>'FFmpeg no disponible.','audio_tmp'=>'']; }
    $cmd = escapeshellarg($ffmpeg) . " -nostdin -hide_banner -loglevel error -y -i " . escapeshellarg($rutaOrig)
         . " -vn -sn -dn -map a:0 -ar 16000 -ac 1 -c:a pcm_s16le " . escapeshellarg($rutaWav) . " 2>&1";
    exec($cmd, $out, $rv);
    @unlink($rutaOrig);
    if ($rv !== 0) { @unlink($rutaWav); return ['status'=>'error','message'=>'Error al convertir el audio a WAV.','audio_tmp'=>'']; }
    @chmod($rutaWav, 0644);
    return ['status'=>'success','audio_tmp'=>'uploads/tmp/audio/' . $nomWav];
}

// 1) Obtener audio_tmp (convertir si llega archivo nuevo; reusar si ya viene).
$audioTmp = '';
if (isset($_FILES['audio'])) {
    $r = dbl_guardar_y_convertir($ROOT_DIR, $userId);
    if (($r['status'] ?? '') !== 'success') { echo json_encode(['status'=>'error','message'=>$r['message']], JSON_UNESCAPED_UNICODE); exit; }
    $audioTmp = $r['audio_tmp'];
} elseif (!empty($_POST['audio_tmp'])) {
    $audioTmp = (string)$_POST['audio_tmp'];
} elseif (!empty($_POST['audio_filename'])) {
    $audioTmp = 'uploads/tmp/audio/' . basename((string)$_POST['audio_filename']);
}
if (dbl_normalizar_ruta($audioTmp, $userId) === null) {
    echo json_encode(['status'=>'error','message'=>'Audio no válido o no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) Liberar la sesión ANTES de las subllamadas (evita deadlock de sesión PHP).
$cookie = session_name() . '=' . session_id();
session_write_close();

// 3) Endpoint del motor, según el host actual (sirve en dev y main).
// El server redirige http->https (308). Detrás de proxy, $_SERVER['HTTPS'] puede no venir,
// así que consideramos también X-Forwarded-Proto y forzamos https si hay duda.
$fwd = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $fwd === 'https';
$scheme = $esHttps ? 'https' : 'https'; // forzamos https: el host no acepta http
$endpointStt = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/funciones/GPT/transcribir_audio.php';

function dbl_handle(
    string $url,
    string $audioTmp,
    string $motor,
    string $cookie,
    string $csrfToken
) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,

        CURLOPT_POSTFIELDS => [
            'audio_tmp'      => $audioTmp,
            'motor'          => $motor,
            'usar_keyterms'  => USAR_KEYTERMS
        ],

        CURLOPT_COOKIE => $cookie,

        CURLOPT_HTTPHEADER => [
            'X-CSRF-Token: ' . $csrfToken
        ],

        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTREDIR      => 7,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 200,
    ]);

    return $ch;
}

// 4) Dos transcripciones en paralelo.
$mh = curl_multi_init();
$chA = dbl_handle(
    $endpointStt,
    $audioTmp,
    MOTOR_A,
    $cookie,
    $csrfToken
);

$chB = dbl_handle(
    $endpointStt,
    $audioTmp,
    MOTOR_B,
    $cookie,
    $csrfToken
);
curl_multi_add_handle($mh, $chA);
curl_multi_add_handle($mh, $chB);
$running = null;
do { curl_multi_exec($mh, $running); if ($running > 0) curl_multi_select($mh, 1.0); } while ($running > 0);
$respA = (string)curl_multi_getcontent($chA);
$respB = (string)curl_multi_getcontent($chB);
$errA  = curl_error($chA); $httpA = (int)curl_getinfo($chA, CURLINFO_HTTP_CODE);
$errB  = curl_error($chB); $httpB = (int)curl_getinfo($chB, CURLINFO_HTTP_CODE);
curl_multi_remove_handle($mh, $chA);
curl_multi_remove_handle($mh, $chB);
curl_multi_close($mh);

$jA = json_decode($respA, true);
$jB = json_decode($respB, true);
$okA = is_array($jA) && ($jA['status'] ?? '') === 'success';
$okB = is_array($jB) && ($jB['status'] ?? '') === 'success';
$textoA = $okA ? trim((string)$jA['texto']) : '';
$textoB = $okB ? trim((string)$jB['texto']) : '';
$durA   = $okA ? (float)($jA['duracion_seg'] ?? 0) : 0.0;
$durB   = $okB ? (float)($jB['duracion_seg'] ?? 0) : 0.0;

$flujoId = 'ia_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

// 5) Motor A es obligatorio. Si falla, devolvemos el detalle para diagnosticar.
if (!$okA || $textoA === '') {
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Falló la transcripción principal (motor A).',
        'debug_A'  => ['http'=>$httpA, 'curl_err'=>$errA, 'endpoint'=>$endpointStt, 'audio_tmp'=>$audioTmp, 'resp'=>substr($respA, 0, 600)],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 6) Si B falló, devolvemos A solo (sin comparación), con aviso.
if (!$okB || $textoB === '') {
    $mysqliStt = conn();
    stt_guardar_transcripcion($mysqliStt, [
        'flujo_id'       => $flujoId,
        'audio_tmp'      => $audioTmp,
        'motor_a'        => MOTOR_A,
        'motor_b'        => MOTOR_B,
        'texto_a'        => $textoA,
        'texto_b'        => '',
        'texto_doble'    => '',
        'discrepancias'  => [],
        'duracion_seg_a' => $durA,
        'duracion_seg_b' => 0,
    ]);

    if ($mysqliStt instanceof mysqli) {
        @$mysqliStt->close();
    }

    echo json_encode([
        'status'      => 'success',
        'texto'       => $textoA,
        'texto_doble' => '',
        'audio_tmp'   => $audioTmp,
        'flujo_id'    => $flujoId,
        'aviso'       => 'El segundo motor no respondió; se usó solo el motor A sin comparación.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 7) Validador + bloques.
$disc = cmp_comparar($textoA, $textoB);
$sep  = org_procesar($disc, $ORGANOS_LISTA, $CONCEPTOS_LISTA);
$bloqueRes  = construir_bloque_resueltas($sep['resueltas']);
$bloqueDisc = construir_bloque_discrepancias($sep['a_ia']);
$textoDoble = $bloqueRes . $bloqueDisc;

// Guardar transcripción en BD (ia_transcripciones).
$mysqliStt = conn();
stt_guardar_transcripcion($mysqliStt, [
    'flujo_id'        => $flujoId,
    'audio_tmp'       => $audioTmp,
    'motor_a'         => MOTOR_A,
    'motor_b'         => MOTOR_B,
    'texto_a'         => $textoA,
    'texto_b'         => $textoB,
    'texto_doble'     => $textoDoble,
    'discrepancias'   => $sep['a_ia'],
    'duracion_seg_a'  => $durA,
    'duracion_seg_b'  => $durB,
]);
if ($mysqliStt instanceof mysqli) {
    @$mysqliStt->close();
}

echo json_encode([
    'status'        => 'success',
    'texto'         => $textoA,
    'texto_doble'   => $textoDoble,
    'audio_tmp'     => $audioTmp,
    'flujo_id'      => $flujoId,
    'resueltas'     => $sep['resueltas'],
    'discrepancias' => $sep['a_ia'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);