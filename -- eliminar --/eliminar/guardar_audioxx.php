<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

session_start();
$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0; // fallback seguro

$projectRoot = dirname(__DIR__); // si este archivo vive en /funciones, el root es 1 nivel arriba
$baseDir     = $projectRoot . '/uploads/grabaciones';

// === Fecha con milisegundos
$nowFloat = microtime(true);
$dt       = DateTime::createFromFormat('U.u', sprintf('%.6F', $nowFloat));
// $dt->setTimezone(new DateTimeZone('UTC')); // descomenta si prefieres UTC
$year     = $dt->format('Y');
$month    = $dt->format('m');

$uploadDir = $baseDir . '/' . $year . '/' . $month;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 02775, true)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el directorio de destino']);
    exit;
}
@chmod($uploadDir, 02775);

// === Validar archivo recibido
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió ningún archivo de audio válido']);
    exit;
}

$allowedExt = ['wav','webm','mp3','m4a','ogg','oga'];
$originalName = $_FILES['audio']['name'] ?? '';
$originalExt  = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($originalExt, $allowedExt, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Formato de audio no permitido']);
    exit;
}

$audioTmp = $_FILES['audio']['tmp_name'];

// === Guardar temporal en carpeta destino
$tmpName  = 'tmp_' . bin2hex(random_bytes(4)) . '.' . $originalExt;
$tempPath = $uploadDir . '/' . $tmpName;

if (!move_uploaded_file($audioTmp, $tempPath)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo de audio (temp)']);
    exit;
}

// === Armar nombre final USERID_YYYYMMDD_HHMMSSmmm.wav (sin sufijos -1, -2, por tu preferencia)
$ms        = substr($dt->format('u'), 0, 3); // milisegundos
$timestamp = $dt->format('Ymd') . '_' . $dt->format('His') . $ms;
$baseName  = $userId . '_' . $timestamp;
$finalPath = $uploadDir . '/' . $baseName . '.wav';

// === Convertir a WAV 16kHz mono PCM
$cmd = "ffmpeg -y -hide_banner -i " . escapeshellarg($tempPath) .
       " -ar 16000 -ac 1 -c:a pcm_s16le " . escapeshellarg($finalPath) . " 2>&1";
exec($cmd, $output, $returnVar);

if ($returnVar !== 0 || !file_exists($finalPath)) {
    @file_put_contents($projectRoot . '/funciones/logs/ffmpeg_error.log',
        "[" . date('Y-m-d H:i:s') . "] CMD: $cmd\n" . implode("\n", $output) . "\nTEMP: $tempPath\n\n",
        FILE_APPEND
    );
    // no borro temp para inspección
    echo json_encode(['status' => 'error', 'message' => 'Error al convertir a WAV. Revisa funciones/logs/ffmpeg_error.log']);
    exit;
}

// OK: limpiar temp y permisos finales
@unlink($tempPath);
@chmod($finalPath, 0664);

// Logs (crear carpeta si no existe)
$logDir = $projectRoot . '/funciones/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 02775, true); }
@file_put_contents($logDir . '/guardar_audio_debug.log',
    "[" . date('Y-m-d H:i:s') . "] OK: $finalPath\n", FILE_APPEND
);

// === Respuesta
$relativePath = $year . '/' . $month . '/' . basename($finalPath);
$audioUrl     = '/uploads/grabaciones/' . $relativePath;

echo json_encode([
    'status'        => 'success',
    'audio_url'     => $audioUrl,          // para reproducir en navegador
    'filename'      => basename($finalPath),
    'relative_path' => $relativePath,      // << usar este para proceso_audio_gpt.php
    'user_id'       => $userId,
    'sample_rate'   => 16000,
]);
