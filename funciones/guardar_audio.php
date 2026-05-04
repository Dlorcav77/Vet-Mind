<?php
// funciones/guardar_audio.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

session_start();

$ROOT_DIR = dirname(__DIR__);
$FUNC_DIR = __DIR__;
$logDir   = $FUNC_DIR . '/logs';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida. Inicia sesión para grabar.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$baseDir = $ROOT_DIR . '/uploads/tmp/audio';

if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo crear la carpeta temporal de audio.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$now = new DateTime('now', new DateTimeZone('America/Santiago'));
$day   = $now->format('d');
$hmsms = $now->format('Hisv');

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se recibió ningún archivo de audio válido'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

define('MAX_AUDIO_BYTES', 25 * 1024 * 1024);

$size = (int)($_FILES['audio']['size'] ?? 0);

if ($size <= 0 && is_file($_FILES['audio']['tmp_name'])) {
    $size = filesize($_FILES['audio']['tmp_name']);
}

if ($size > MAX_AUDIO_BYTES) {
    @unlink($_FILES['audio']['tmp_name']);

    echo json_encode([
        'status' => 'error',
        'message' => 'Archivo demasiado grande (máx 25 MB).'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$audioFile = $_FILES['audio']['tmp_name'];
$originalName = $_FILES['audio']['name'] ?? 'grabacion.webm';
$originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($originalExt === '') {
    $originalExt = 'webm';
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['audio']['tmp_name']) ?: '';

$allowedMimes = [
    'audio/wav',
    'audio/x-wav',
    'audio/webm',
    'video/webm',
    'audio/ogg',
    'audio/mpeg',
    'audio/mp4',
    'audio/3gpp',
    'audio/3gpp2'
];

$allowedExts = ['wav', 'webm', 'mp3', 'm4a', 'ogg', '3gp', '3g2'];

$mimeOk = in_array($mime, $allowedMimes, true) ||
    ($mime === 'application/octet-stream' && in_array($originalExt, $allowedExts, true));

if (!$mimeOk) {
    file_put_contents(
        $logDir . '/upload_rechazado.log',
        date('c') . " | userId:$userId | ip:" . ($_SERVER['REMOTE_ADDR'] ?? '-') .
        " | ua:" . ($_SERVER['HTTP_USER_AGENT'] ?? '-') .
        " | mime:$mime | nombre:$originalName\n",
        FILE_APPEND
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Formato de audio no permitido. Usa WAV, WEBM, MP3, OGG o M4A.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', uniqid('tmp_audio_original_', true));
$tempPath = $baseDir . '/' . $safeName . '.' . $originalExt;

$convertedName = 'tmp_audio_' . $userId . '_' . $day . '_' . $hmsms . '_' . bin2hex(random_bytes(4)) . '.wav';
$convertedPath = $baseDir . '/' . $convertedName;

if (!move_uploaded_file($audioFile, $tempPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo guardar el archivo de audio temporal'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ffmpegPath = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?? '');

if ($ffmpegPath === '') {
    @unlink($tempPath);

    echo json_encode([
        'status' => 'error',
        'message' => 'FFmpeg no está instalado o no está en $PATH.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$cmd = escapeshellarg($ffmpegPath) . " -nostdin -hide_banner -loglevel error -y " .
    "-i " . escapeshellarg($tempPath) . " " .
    "-vn -sn -dn -map a:0 " .
    "-ar 16000 -ac 1 -c:a pcm_s16le " .
    escapeshellarg($convertedPath) . " 2>&1";

exec($cmd, $output, $returnVar);

if ($returnVar !== 0) {
    file_put_contents($logDir . '/ffmpeg_error.log', implode("\n", $output), FILE_APPEND);
    @unlink($tempPath);

    echo json_encode([
        'status' => 'error',
        'message' => 'Error al convertir el audio a WAV. Revisa ' . $logDir . '/ffmpeg_error.log'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

@unlink($tempPath);
@chmod($convertedPath, 0644);

echo json_encode([
    'status' => 'success',
    'audio_url' => '/uploads/tmp/audio/' . $convertedName,
    'filename' => $convertedName,
    'audio_tmp' => 'uploads/tmp/audio/' . $convertedName
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;