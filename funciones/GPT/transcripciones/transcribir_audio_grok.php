<?php
// funciones/GPT/transcribir_audio_grok.php
// Variante de transcripción usando Grok STT (xAI) en vez de AssemblyAI.
// Se invoca desde transcribir_audio.php cuando $motor_stt === 'grok'.
// Reusa la sesión, configP y $ROOT_DIR ya definidos NO: este archivo se incluye
// ANTES de definir $ROOT_DIR, así que define lo suyo igual que el original.

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

require_once(dirname(__DIR__, 3) . "/configP.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$ROOT_DIR = dirname(__DIR__, 3);

$logDir = $ROOT_DIR . '/funciones/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$xaiApiKey = $XAI_API_KEY ?? '';
if (!$xaiApiKey) {
    echo json_encode([
        'status' => 'error',
        'message' => 'API Key de xAI (Grok) no configurada.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizarRutaAudioTmpGrok($ruta, $userId)
{
    $ruta = trim((string)$ruta);
    if ($ruta === '') return null;
    $ruta = str_replace('\\', '/', $ruta);
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = ltrim($ruta, '/');
    $prefix = 'uploads/tmp/audio/';
    if (strpos($ruta, $prefix) !== 0) return null;
    $nombreArchivo = basename($ruta);
    if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..') return null;
    $prefijoEsperado = 'tmp_audio_' . (int)$userId . '_';
    if (strpos($nombreArchivo, $prefijoEsperado) !== 0) return null;
    if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'wav') return null;
    return $prefix . $nombreArchivo;
}

function resolverAudioTmpFisicoGrok($rootDir, $rutaAudioTmp, $userId)
{
    $rutaNormalizada = normalizarRutaAudioTmpGrok($rutaAudioTmp, $userId);
    if ($rutaNormalizada === null) return '';
    $baseTmp = realpath($rootDir . '/uploads/tmp/audio');
    if ($baseTmp === false) return '';
    $audioReal = realpath($rootDir . '/' . $rutaNormalizada);
    if ($audioReal === false || !is_file($audioReal)) return '';
    if (strpos($audioReal, $baseTmp . DIRECTORY_SEPARATOR) !== 0) return '';
    return $audioReal;
}

function guardarAudioSubidoParaTranscripcionGrok($rootDir, $userId)
{
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        return ['status'=>'error','message'=>'No se recibió ningún archivo de audio válido.','path'=>'','audio_tmp'=>''];
    }

    $maxBytes = 25 * 1024 * 1024;
    $tmpFile = $_FILES['audio']['tmp_name'];
    $size = (int)($_FILES['audio']['size'] ?? 0);
    if ($size <= 0 && is_file($tmpFile)) $size = filesize($tmpFile);
    if ($size > $maxBytes) {
        @unlink($tmpFile);
        return ['status'=>'error','message'=>'Archivo demasiado grande (máx 25 MB).','path'=>'','audio_tmp'=>''];
    }

    $originalExt = strtolower(pathinfo($_FILES['audio']['name'] ?? 'audio.webm', PATHINFO_EXTENSION));
    if ($originalExt === '') $originalExt = 'webm';
    $extPermitidas = ['wav','webm','mp3','m4a','ogg','3gp','3g2'];
    if (!in_array($originalExt, $extPermitidas, true)) $originalExt = 'webm';

    $baseTmp = $rootDir . '/uploads/tmp/audio';
    if (!is_dir($baseTmp)) {
        if (!mkdir($baseTmp, 0775, true) && !is_dir($baseTmp)) {
            return ['status'=>'error','message'=>'No se pudo crear la carpeta temporal de audio.','path'=>'','audio_tmp'=>''];
        }
    }

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $day = $now->format('d');
    $hmsms = $now->format('Hisv');

    $nombreOriginalTemporal = 'tmp_audio_original_' . (int)$userId . '_' . $day . '_' . $hmsms . '_' . bin2hex(random_bytes(4)) . '.' . $originalExt;
    $rutaOriginalTemporal = $baseTmp . '/' . $nombreOriginalTemporal;

    $nombreConvertido = 'tmp_audio_' . (int)$userId . '_' . $day . '_' . $hmsms . '_' . bin2hex(random_bytes(4)) . '.wav';
    $rutaConvertida = $baseTmp . '/' . $nombreConvertido;

    if (!move_uploaded_file($tmpFile, $rutaOriginalTemporal)) {
        return ['status'=>'error','message'=>'No se pudo guardar el archivo de audio temporal.','path'=>'','audio_tmp'=>''];
    }

    $ffmpegPath = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?? '');
    if ($ffmpegPath === '') {
        @unlink($rutaOriginalTemporal);
        return ['status'=>'error','message'=>'FFmpeg no está instalado o no está en $PATH.','path'=>'','audio_tmp'=>''];
    }

    $cmd = escapeshellarg($ffmpegPath) . " -nostdin -hide_banner -loglevel error -y " .
        "-i " . escapeshellarg($rutaOriginalTemporal) . " " .
        "-vn -sn -dn -map a:0 " .
        "-ar 16000 -ac 1 -c:a pcm_s16le " .
        escapeshellarg($rutaConvertida) . " 2>&1";
    exec($cmd, $output, $returnVar);
    @unlink($rutaOriginalTemporal);

    if ($returnVar !== 0) {
        @unlink($rutaConvertida);
        return ['status'=>'error','message'=>'Error al convertir el audio subido a WAV.','path'=>'','audio_tmp'=>''];
    }

    @chmod($rutaConvertida, 0644);
    return ['status'=>'success','message'=>'Audio subido temporalmente.','path'=>$rutaConvertida,'audio_tmp'=>'uploads/tmp/audio/' . $nombreConvertido];
}

$audioPath = '';
$audioTmpRespuesta = '';

if (isset($_FILES['audio'])) {
    $resultadoUpload = guardarAudioSubidoParaTranscripcionGrok($ROOT_DIR, $userId);
    if (($resultadoUpload['status'] ?? '') !== 'success') {
        echo json_encode(['status'=>'error','message'=>$resultadoUpload['message'] ?? 'No se pudo preparar el audio.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $audioPath = $resultadoUpload['path'];
    $audioTmpRespuesta = $resultadoUpload['audio_tmp'] ?? '';
} elseif (!empty($_POST['audio_tmp'])) {
    $audioTmpRespuesta = trim((string)$_POST['audio_tmp']);
    $audioPath = resolverAudioTmpFisicoGrok($ROOT_DIR, $audioTmpRespuesta, $userId);
} elseif (!empty($_POST['audio_url'])) {
    $audioTmpRespuesta = trim((string)$_POST['audio_url']);
    $audioPath = resolverAudioTmpFisicoGrok($ROOT_DIR, $audioTmpRespuesta, $userId);
} elseif (!empty($_POST['audio_filename'])) {
    $audioTmpRespuesta = 'uploads/tmp/audio/' . basename((string)$_POST['audio_filename']);
    $audioPath = resolverAudioTmpFisicoGrok($ROOT_DIR, $audioTmpRespuesta, $userId);
}

if (!$audioPath || !file_exists($audioPath)) {
    echo json_encode(['status'=>'error','message'=>'Archivo de audio no encontrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ==============================
   Transcripción con Grok STT (xAI)
   Endpoint: POST https://api.x.ai/v1/stt  (multipart/form-data)
   La respuesta trae el transcript en el campo "text".
   ============================== */
$ch = curl_init('https://api.x.ai/v1/stt');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $xaiApiKey,
]);
$postFields = [
    'file'     => new CURLFile($audioPath, 'audio/wav', basename($audioPath)),
    'model'    => 'grok-stt',
    'language' => 'es-MX',
    'format'   => 'true',
];

// Términos veterinarios para sesgar la transcripción (los que Grok erró antes).
$keyterms = [
    'Bazo', 'Yeyuno', 'Íleon', 'Duodeno', 'Páncreas', 'Colon',
    'Riñón', 'Adrenal', 'ecogenicidad', 'anecoico', 'hipoecoico',
    'parénquima', 'cortico medular', 'estratificación', 'linfonódulos',
    'esplénico', 'vesícula biliar', 'peritoneo', 'ciego', 'felino',
];
foreach ($keyterms as $kt) {
    $postFields['keyterm[]'] = $kt; // se repite el parámetro por cada término
}

curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);

$resp = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

if ($curlErr !== '' || $httpCode >= 400) {
    file_put_contents(
        $logDir . '/grok_stt_error.log',
        date('c') . " | user:$userId | http:$httpCode | err:$curlErr | resp:" . substr((string)$resp, 0, 2000) . "\n",
        FILE_APPEND
    );
    echo json_encode(['status'=>'error','message'=>'Error al transcribir con Grok STT.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = json_decode((string)$resp, true);
$text = is_array($data) ? trim((string)($data['text'] ?? '')) : '';

if ($text === '') {
    file_put_contents(
        $logDir . '/grok_stt_error.log',
        date('c') . " | user:$userId | texto vacío | resp:" . substr((string)$resp, 0, 2000) . "\n",
        FILE_APPEND
    );
    echo json_encode(['status'=>'error','message'=>'Texto transcrito vacío.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'success',
    'texto' => $text,
    'audio_tmp' => $audioTmpRespuesta
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;