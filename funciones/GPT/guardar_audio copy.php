<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$uploadDir = dirname(__DIR__) . '/uploads/grabaciones';

// ✅ Crear carpeta si no existe
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta grabaciones']);
        exit;
    }
}

// ✅ Validar archivo recibido
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió ningún archivo de audio válido']);
    exit;
}

$audioFile = $_FILES['audio']['tmp_name'];
$originalName = $_FILES['audio']['name'];
$originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

// 📝 Nombre base sin extensión para el archivo convertido
$safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', uniqid('grabacion_', true));
$convertedName = $safeName . '.wav'; // 🎯 Siempre convertimos a WAV
$convertedPath = $uploadDir . '/' . $convertedName;

// ✅ Mover archivo temporal al servidor (con nombre original)
$tempPath = $uploadDir . '/' . $safeName . '.' . $originalExt;
if (!move_uploaded_file($audioFile, $tempPath)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo de audio']);
    exit;
}

// 🔥 Convertir a WAV si no es WAV ya
if ($originalExt !== 'wav') {
    $cmd = "ffmpeg -y -i " . escapeshellarg($tempPath) . " -ar 16000 -ac 1 -c:a pcm_s16le " . escapeshellarg($convertedPath) . " 2>&1";
    exec($cmd, $output, $returnVar);

    if ($returnVar !== 0) {
        file_put_contents('/tmp/ffmpeg_error.log', implode("\n", $output), FILE_APPEND);
        unlink($tempPath); // ❌ Limpiar archivo original
        echo json_encode(['status' => 'error', 'message' => 'Error al convertir el audio a WAV. Revisa /tmp/ffmpeg_error.log']);
        exit;
    }
    unlink($tempPath); // 🗑 Eliminar el original no convertido
} else {
    // 👍 Ya está en WAV, solo renombrar
    rename($tempPath, $convertedPath);
}

// 🔒 Asignar permisos seguros
chmod($convertedPath, 0644);

// 📝 Registrar en log
file_put_contents('/tmp/guardar_audio_debug.log', "Audio guardado y convertido: $convertedPath\n", FILE_APPEND);

// 📢 Respuesta con la URL
echo json_encode([
    'status'   => 'success',
    'audio_url'=> '/uploads/grabaciones/' . $convertedName,
    'filename' => $convertedName
]);
