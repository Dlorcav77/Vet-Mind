<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

require_once(dirname(__DIR__, 2) . "/configP.php"); // ajusta si lo tienes en otro lado

session_start();

$logDir = dirname(__DIR__, 2) . '/funciones/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit;
}

$assemblyApiKey = $ASSEM_API_KEY ?? '';
if (!$assemblyApiKey) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de AssemblyAI no configurada.']);
    exit;
}

// carpeta base de audios ya guardados
$baseDir = dirname(__DIR__, 2) . '/uploads/grabaciones';

function sanitizeRelativeAudioPath($path) {
    $path = str_replace('\\', '/', $path);
    $path = trim($path);
    $path = ltrim($path, '/');
    $path = preg_replace('#\.\./#', '', $path);
    $path = preg_replace('#[^a-zA-Z0-9/_\.\-]#', '', $path);
    if (strpos($path, 'uploads/grabaciones/') === 0) {
        $path = substr($path, strlen('uploads/grabaciones/'));
    }
    return $path;
}

function findAudioByFilename($baseDir, $filename) {
    $pattern = rtrim($baseDir, '/') . '/*/*/' . $filename;
    $matches = glob($pattern, GLOB_NOSORT);
    if (!$matches) return '';
    $latest = '';
    $latestMtime = -1;
    foreach ($matches as $m) {
        $t = @filemtime($m);
        if ($t !== false && $t > $latestMtime) {
            $latestMtime = $t;
            $latest = $m;
        }
    }
    return $latest ?: $matches[0];
}

$audioPath = '';

// 1) viene un archivo subido ahora
if (isset($_FILES['audio'])) {
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $year  = $now->format('Y');
    $month = $now->format('m');
    $day   = $now->format('d');
    $hmsms = $now->format('Hisv');

    $uploadDir = $baseDir . '/' . $year . '/' . $month;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    define('MAX_AUDIO_BYTES', 25 * 1024 * 1024);
    $tmpFile = $_FILES['audio']['tmp_name'];
    $size = (int)($_FILES['audio']['size'] ?? 0);
    if ($size <= 0 && is_file($tmpFile)) {
        $size = filesize($tmpFile);
    }
    if ($size > MAX_AUDIO_BYTES) {
        @unlink($tmpFile);
        echo json_encode(['status' => 'error', 'message' => 'Archivo demasiado grande (máx 25 MB).']);
        exit;
    }

    $originalExt = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
    if ($originalExt === '') $originalExt = 'webm';

    $tempName = 'upload_' . $userId . '_' . $day . '_' . $hmsms . '.' . $originalExt;
    $tempPath = $uploadDir . '/' . $tempName;

    if (!move_uploaded_file($tmpFile, $tempPath)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo de audio.']);
        exit;
    }

    // si quieres aquí lo puedes convertir a wav como en tus otros scripts;
    // para transcribir puedes subirlo así mismo a Assembly
    $audioPath = $tempPath;

} elseif (isset($_POST['audio_filename']) || isset($_POST['audio_url'])) {
    $input = $_POST['audio_filename'] ?? $_POST['audio_url'];
    $rel   = sanitizeRelativeAudioPath($input);

    if (strpos($rel, '/') === false) {
        $found = findAudioByFilename($baseDir, $rel);
        if ($found !== '' && file_exists($found)) {
            $audioPath = $found;
        }
    } else {
        $candidate = $baseDir . '/' . $rel;
        if (file_exists($candidate)) {
            $audioPath = $candidate;
        }
    }
}

if (!$audioPath || !file_exists($audioPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Archivo de audio no encontrado.']);
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
    file_put_contents($logDir . '/assembly_upload_error.log',
        date('c') . " | user:$userId | resp:" . substr((string)$uploadResponse, 0, 2000) . "\n",
        FILE_APPEND
    );
    echo json_encode(['status' => 'error', 'message' => 'Error al subir audio a AssemblyAI.']);
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
    'disfluencies' => false
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
    file_put_contents($logDir . '/assembly_transcript_error.log',
        date('c') . " | user:$userId | http:$httpCode | resp:" . substr((string)$transcriptionResponse, 0, 2000) . "\n",
        FILE_APPEND
    );
    echo json_encode(['status' => 'error', 'message' => 'Error al iniciar transcripción en AssemblyAI.']);
    exit;
}

$transcriptionId = $transcriptionData['id'];
$text = '';
$delays = [2,3,5,8,8,8,8];

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
        echo json_encode(['status' => 'error', 'message' => 'La transcripción falló en AssemblyAI.']);
        exit;
    }

    sleep($wait);
}

if (!$text) {
    echo json_encode(['status' => 'error', 'message' => 'Texto transcrito vacío o timeout.']);
    exit;
}

// ✅ éxito
echo json_encode([
    'status' => 'success',
    'texto'  => $text
], JSON_UNESCAPED_UNICODE);
