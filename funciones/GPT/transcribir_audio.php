<?php
// funciones/GPT/transcribir_audio.php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

require_once(dirname(__DIR__, 2) . "/configP.php");

session_start();

/////////////////////////////////////////////////////////////////
// Motor de transcripción manual para pruebas.
// Valores:
// - ''       => usa AssemblyAI en este mismo archivo (comportamiento actual)
// - 'assembly'=> igual que '' (AssemblyAI)
// - 'grok'   => deriva a funciones/GPT/transcribir_audio_grok.php
$motor_stt = 'deepgram';
// $motor_stt = '';

$motor_stt = strtolower(trim($motor_stt));

if ($motor_stt === 'grok') {
    require_once(__DIR__ . '/transcribir_audio_grok.php');
    exit;
}
if ($motor_stt === 'deepgram') {
    require_once(__DIR__ . '/transcribir_audio_deepgram.php');
    exit;
}

$ROOT_DIR = dirname(__DIR__, 2);

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

$assemblyApiKey = $ASSEM_API_KEY ?? '';
if (!$assemblyApiKey) {
    echo json_encode([
        'status' => 'error',
        'message' => 'API Key de AssemblyAI no configurada.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizarRutaAudioTmp($ruta, $userId)
{
    $ruta = trim((string)$ruta);

    if ($ruta === '') {
        return null;
    }

    $ruta = str_replace('\\', '/', $ruta);
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = ltrim($ruta, '/');

    $prefix = 'uploads/tmp/audio/';

    if (strpos($ruta, $prefix) !== 0) {
        return null;
    }

    $nombreArchivo = basename($ruta);

    if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..') {
        return null;
    }

    $prefijoEsperado = 'tmp_audio_' . (int)$userId . '_';

    if (strpos($nombreArchivo, $prefijoEsperado) !== 0) {
        return null;
    }

    if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'wav') {
        return null;
    }

    return $prefix . $nombreArchivo;
}

function resolverAudioTmpFisico($rootDir, $rutaAudioTmp, $userId)
{
    $rutaNormalizada = normalizarRutaAudioTmp($rutaAudioTmp, $userId);

    if ($rutaNormalizada === null) {
        return '';
    }

    $baseTmp = realpath($rootDir . '/uploads/tmp/audio');

    if ($baseTmp === false) {
        return '';
    }

    $audioReal = realpath($rootDir . '/' . $rutaNormalizada);

    if ($audioReal === false || !is_file($audioReal)) {
        return '';
    }

    if (strpos($audioReal, $baseTmp . DIRECTORY_SEPARATOR) !== 0) {
        return '';
    }

    return $audioReal;
}

function guardarAudioSubidoParaTranscripcion($rootDir, $userId)
{
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        return [
            'status' => 'error',
            'message' => 'No se recibió ningún archivo de audio válido.',
            'path' => '',
            'audio_tmp' => ''
        ];
    }

    define('MAX_AUDIO_BYTES_TRANSCRIBIR', 25 * 1024 * 1024);

    $tmpFile = $_FILES['audio']['tmp_name'];
    $size = (int)($_FILES['audio']['size'] ?? 0);

    if ($size <= 0 && is_file($tmpFile)) {
        $size = filesize($tmpFile);
    }

    if ($size > MAX_AUDIO_BYTES_TRANSCRIBIR) {
        @unlink($tmpFile);

        return [
            'status' => 'error',
            'message' => 'Archivo demasiado grande (máx 25 MB).',
            'path' => '',
            'audio_tmp' => ''
        ];
    }

    $originalExt = strtolower(pathinfo($_FILES['audio']['name'] ?? 'audio.webm', PATHINFO_EXTENSION));

    if ($originalExt === '') {
        $originalExt = 'webm';
    }

    $extPermitidas = ['wav', 'webm', 'mp3', 'm4a', 'ogg', '3gp', '3g2'];

    if (!in_array($originalExt, $extPermitidas, true)) {
        $originalExt = 'webm';
    }

    $baseTmp = $rootDir . '/uploads/tmp/audio';

    if (!is_dir($baseTmp)) {
        if (!mkdir($baseTmp, 0775, true) && !is_dir($baseTmp)) {
            return [
                'status' => 'error',
                'message' => 'No se pudo crear la carpeta temporal de audio.',
                'path' => '',
                'audio_tmp' => ''
            ];
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
        return [
            'status' => 'error',
            'message' => 'No se pudo guardar el archivo de audio temporal.',
            'path' => '',
            'audio_tmp' => ''
        ];
    }

    $ffmpegPath = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?? '');

    if ($ffmpegPath === '') {
        @unlink($rutaOriginalTemporal);

        return [
            'status' => 'error',
            'message' => 'FFmpeg no está instalado o no está en $PATH.',
            'path' => '',
            'audio_tmp' => ''
        ];
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

        return [
            'status' => 'error',
            'message' => 'Error al convertir el audio subido a WAV.',
            'path' => '',
            'audio_tmp' => ''
        ];
    }

    @chmod($rutaConvertida, 0644);

    $audioTmp = 'uploads/tmp/audio/' . $nombreConvertido;

    return [
        'status' => 'success',
        'message' => 'Audio subido temporalmente.',
        'path' => $rutaConvertida,
        'audio_tmp' => $audioTmp
    ];
}

$audioPath = '';
$audioTmpRespuesta = '';

if (isset($_FILES['audio'])) {
    $resultadoUpload = guardarAudioSubidoParaTranscripcion($ROOT_DIR, $userId);

    if (($resultadoUpload['status'] ?? '') !== 'success') {
        echo json_encode([
            'status' => 'error',
            'message' => $resultadoUpload['message'] ?? 'No se pudo preparar el audio.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $audioPath = $resultadoUpload['path'];
    $audioTmpRespuesta = $resultadoUpload['audio_tmp'] ?? '';
} elseif (!empty($_POST['audio_tmp'])) {
    $audioTmpRespuesta = trim((string)$_POST['audio_tmp']);
    $audioPath = resolverAudioTmpFisico($ROOT_DIR, $audioTmpRespuesta, $userId);
} elseif (!empty($_POST['audio_url'])) {
    $audioTmpRespuesta = trim((string)$_POST['audio_url']);
    $audioPath = resolverAudioTmpFisico($ROOT_DIR, $audioTmpRespuesta, $userId);
} elseif (!empty($_POST['audio_filename'])) {
    $audioTmpRespuesta = 'uploads/tmp/audio/' . basename((string)$_POST['audio_filename']);
    $audioPath = resolverAudioTmpFisico($ROOT_DIR, $audioTmpRespuesta, $userId);
}

if (!$audioPath || !file_exists($audioPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Archivo de audio no encontrado.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ==============================
   2) subir a Assembly
   ============================== */
$curlCmd = "curl -s --request POST " .
           "--url https://api.assemblyai.com/v2/upload " .
           "--header " . escapeshellarg("authorization: $assemblyApiKey") . " " .
           "--header 'content-type: application/octet-stream' " .
           "--data-binary @" . escapeshellarg($audioPath);

$uploadResponse = shell_exec($curlCmd);
$uploadData = json_decode($uploadResponse, true);

if (!is_array($uploadData) || !isset($uploadData['upload_url'])) {
    file_put_contents(
        $logDir . '/assembly_upload_error.log',
        date('c') . " | user:$userId | resp:" . substr((string)$uploadResponse, 0, 2000) . "\n",
        FILE_APPEND
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Error al subir audio a AssemblyAI.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$uploadUrl = $uploadData['upload_url'];

/* ==============================
   3) pedir transcripción
   ============================== */
$transcriptionRequest = [
    'audio_url' => $uploadUrl,
    'language_code' => 'es',
    'format_text' => true,
    'disfluencies' => false,
    'word_boost' => [
        'Bazo', 'Yeyuno', 'Íleon', 'Duodeno', 'Páncreas', 'Colon', 'Estómago',
        'Riñón', 'Vejiga', 'Próstata', 'Hígado', 'Vesícula biliar', 'Linfonódulos',
        'Adrenal', 'Adrenales', 'Ciego', 'Peritoneo', 'Bilateral',
        'ecogenicidad', 'ecogénico', 'anecoico', 'hipoecoico', 'hiperecoico',
        'parénquima', 'cortico medular', 'estratificación', 'esplénico',
        'mucoso', 'submucosa', 'aguzado', 'reactivo', 'felino', 'puntiforme'
    ],
    'boost_param' => 'high'
];

$ch = curl_init('https://api.assemblyai.com/v2/transcript');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authorization: ' . $assemblyApiKey,
    'content-type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transcriptionRequest));
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$transcriptionResponse = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$transcriptionData = json_decode($transcriptionResponse, true);

if ($httpCode >= 400 || !isset($transcriptionData['id'])) {
    file_put_contents(
        $logDir . '/assembly_transcript_error.log',
        date('c') . " | user:$userId | http:$httpCode | resp:" . substr((string)$transcriptionResponse, 0, 2000) . "\n",
        FILE_APPEND
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Error al iniciar transcripción en AssemblyAI.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$transcriptionId = $transcriptionData['id'];
$text = '';
$delays = [2, 3, 5, 8, 8, 8, 8];

foreach ($delays as $wait) {
    $ch = curl_init("https://api.assemblyai.com/v2/transcript/$transcriptionId");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['authorization: ' . $assemblyApiKey]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $statusResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $statusData = json_decode($statusResponse, true);

    if ($httpCode >= 400 || !isset($statusData['status'])) {
        sleep($wait);
        continue;
    }

    if ($statusData['status'] === 'completed') {
        $text = trim((string)$statusData['text']);
        break;
    }

    if ($statusData['status'] === 'failed') {
        echo json_encode([
            'status' => 'error',
            'message' => 'La transcripción falló en AssemblyAI.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    sleep($wait);
}

if (!$text) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Texto transcrito vacío o timeout.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'success',
    'texto' => $text,
    'audio_tmp' => $audioTmpRespuesta
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;