<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

session_start();

$logDir = dirname(__DIR__) . '/funciones/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}



$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida. Inicia sesión para grabar.']);
    exit;
}

$baseDir = dirname(__DIR__) . '/uploads/grabaciones';

// ✅ Crear carpeta base si no existe
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta grabaciones']);
        exit;
    }
}

// 📆 Componentes de fecha/hora (usa tz del servidor)
$now = new DateTime('now', new DateTimeZone('America/Santiago'));
$year  = $now->format('Y');
$month = $now->format('m');
$day   = $now->format('d');
$hmsms = $now->format('Hisv'); // HHMMSSmmm

// 📁 Directorio final: AAAA/MM
$uploadDir = $baseDir . '/' . $year . '/' . $month;
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta destino']);
        exit;
    }
}


// ✅ Validar archivo recibido
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió ningún archivo de audio válido']);
    exit;
}

define('MAX_AUDIO_BYTES', 25 * 1024 * 1024); // 25 MB

$size = (int)($_FILES['audio']['size'] ?? 0);
if ($size <= 0 && is_file($_FILES['audio']['tmp_name'])) {
    $size = filesize($_FILES['audio']['tmp_name']);
}

if ($size > MAX_AUDIO_BYTES) {
    @unlink($_FILES['audio']['tmp_name']);
    echo json_encode(['status' => 'error', 'message' => 'Archivo demasiado grande (máx 25 MB).']);
    exit;
}

$audioFile = $_FILES['audio']['tmp_name'];
$originalName = $_FILES['audio']['name'];
$originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($originalExt === '') { $originalExt = 'webm'; } // fallback común

// 📝 Nombre base sin extensión para el archivo convertido
$safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', uniqid('grabacion_', true));
$convertedName = $userId . '_' . $day . '_' . $hmsms . '.wav';
$convertedPath = $uploadDir . '/' . $convertedName;


// ✅ Mover archivo temporal al servidor (con nombre original)
$tempPath = $uploadDir . '/' . $safeName . '.' . $originalExt;
// Validación de MIME real
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['audio']['tmp_name']) ?: '';

$allowedMimes = [
    'audio/wav', 'audio/x-wav',
    'audio/webm', 'video/webm',
    'audio/ogg',
    'audio/mpeg',     // mp3
    'audio/mp4',      // m4a
    'audio/3gpp', 'audio/3gpp2'
];

$allowedExts = ['wav','webm','mp3','m4a','ogg','3gp','3g2'];
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
    echo json_encode(['status' => 'error', 'message' => 'Formato de audio no permitido. Usa WAV, WEBM, MP3, OGG o M4A.']);
    exit;
}

if (!move_uploaded_file($audioFile, $tempPath)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo de audio']);
    exit;
}

$ffmpegPath = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?? '');
if ($ffmpegPath === '') {
    echo json_encode(['status' => 'error', 'message' => 'FFmpeg no está instalado o no está en $PATH.']);
    exit;
}

// 🔧 Normalizar SIEMPRE: re-encode a WAV 16kHz mono (pcm_s16le)
$cmd = escapeshellarg($ffmpegPath) . " -nostdin -hide_banner -loglevel error -y " .
       "-i " . escapeshellarg($tempPath) . " " .
       "-vn -sn -dn -map a:0 " .
       "-ar 16000 -ac 1 -c:a pcm_s16le " .
       escapeshellarg($convertedPath) . " 2>&1";
exec($cmd, $output, $returnVar);

if ($returnVar !== 0) {
    file_put_contents($logDir . '/ffmpeg_error.log', implode("\n", $output), FILE_APPEND);
    unlink($tempPath); // ❌ Limpiar archivo original
    echo json_encode(['status' => 'error', 'message' => 'Error al convertir el audio a WAV. Revisa ' . $logDir . '/ffmpeg_error.log']);
    exit;
}
unlink($tempPath); // 🗑 Eliminar el original


// 🔒 Asignar permisos seguros
chmod($convertedPath, 0644);

// 📝 Registrar en log
// file_put_contents( dirname(__DIR__) . '/funciones/logs/guardar_audio_debug.log', "Audio guardado y convertido: $convertedPath\n", FILE_APPEND);

// 📢 Respuesta con la URL
echo json_encode([
    'status'   => 'success',
    'audio_url'=> '/uploads/grabaciones/' . $year . '/' . $month . '/' . $convertedName,
    'filename' => $convertedName
]);
