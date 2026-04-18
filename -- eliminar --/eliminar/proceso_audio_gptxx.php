<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

session_start();
$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

require_once("../configP.php");

// ===== API Key AssemblyAI
$assemblyApiKey = $ASSEM_API_KEY ?? '';
if (!$assemblyApiKey) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de AssemblyAI no configurada.']);
    exit;
}

$projectRoot = dirname(__DIR__);
$baseDir     = $projectRoot . '/uploads/grabaciones';

// --- Helpers
function sanitizeBasename($p) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($p));
}
function quitarTildes($string) {
    $originales = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú'];
    $sinTildes  = ['a','e','i','o','u','A','E','I','O','U'];
    return str_replace($originales, $sinTildes, $string);
}
function buildPathFromRelative($baseDir, $rel) {
    $rel = ltrim($rel, '/'); // "YYYY/MM/file.wav"
    $full = $baseDir . '/' . $rel;
    $realBase = realpath($baseDir);
    $realFull = realpath($full);
    if ($realBase !== false && $realFull !== false && strpos($realFull, $realBase) === 0) {
        return $realFull;
    }
    return $full; // si no hay realpath (archivo recién creado), devolvemos calculado
}

// --- Resolver $audioPath (PRIORIDAD: relative_path > filename > url > upload)
$audioPath = null;

// 1) relative_path (recomendado)
if (!empty($_POST['relative_path'])) {
    $audioPath = buildPathFromRelative($baseDir, trim($_POST['relative_path']));

// 2) compat: audio_filename (si viene con subcarpeta funciona igual)
} elseif (!empty($_POST['audio_filename'])) {
    $fn = sanitizeBasename($_POST['audio_filename']);
    $maybe = $baseDir . '/' . $fn;
    $audioPath = file_exists($maybe) ? $maybe : $projectRoot . '/uploads/grabaciones/' . $fn;

// 3) audio_url -> resolvemos a ruta local
} elseif (!empty($_POST['audio_url'])) {
    $url = $_POST['audio_url'];
    $prefix = '/uploads/grabaciones/';
    $pos = strpos($url, $prefix);
    if ($pos !== false) {
        $rel = substr($url, $pos + strlen($prefix)); // YYYY/MM/file.wav
        $audioPath = buildPathFromRelative($baseDir, $rel);
    }

// 4) upload directo (fallback): GUARDAR COMO EN guardar_audio.php
} elseif (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {

    // === Fecha con milisegundos
    $nowFloat = microtime(true);
    $dt       = DateTime::createFromFormat('U.u', sprintf('%.6F', $nowFloat));
    // $dt->setTimezone(new DateTimeZone('UTC')); // si prefieres UTC
    $year     = $dt->format('Y');
    $month    = $dt->format('m');

    $uploadDir = $baseDir . '/' . $year . '/' . $month;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 02775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el directorio destino']);
        exit;
    }
    @chmod($uploadDir, 02775);

    // Guardar temporal en esa carpeta
    $originalName = $_FILES['audio']['name'] ?? '';
    $originalExt  = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($originalExt === '') { $originalExt = 'wav'; } // fallback

    $tempPath = $uploadDir . '/tmp_' . bin2hex(random_bytes(4)) . '.' . $originalExt;
    if (!move_uploaded_file($_FILES['audio']['tmp_name'], $tempPath)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo subido (temp)']);
        exit;
    }

    // Nombre final USERID_YYYYMMDD_HHMMSSmmm.wav
    $ms        = substr($dt->format('u'), 0, 3);
    $timestamp = $dt->format('Ymd') . '_' . $dt->format('His') . $ms;
    $baseName  = $userId . '_' . $timestamp;
    $finalPath = $uploadDir . '/' . $baseName . '.wav';

    // Convertir a WAV 16kHz mono PCM
    $cmd = "ffmpeg -y -hide_banner -i " . escapeshellarg($tempPath) .
           " -ar 16000 -ac 1 -c:a pcm_s16le " . escapeshellarg($finalPath) . " 2>&1";
    exec($cmd, $output, $returnVar);

    if ($returnVar !== 0 || !file_exists($finalPath)) {
        @file_put_contents($projectRoot . '/funciones/logs/ffmpeg_error.log',
            "[" . date('Y-m-d H:i:s') . "] CMD: $cmd\n" . implode("\n", $output) . "\nTEMP: $tempPath\n\n",
            FILE_APPEND
        );
        echo json_encode(['status' => 'error', 'message' => 'Error al convertir a WAV. Revisa funciones/logs/ffmpeg_error.log']);
        exit;
    }

    @unlink($tempPath);
    @chmod($finalPath, 0664);
    $audioPath = $finalPath;
}

if (!$audioPath || !file_exists($audioPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Archivo de audio no encontrado (' . ($audioPath ?: 'null') . ')']);
    exit;
}

// === Subir a AssemblyAI
$cmdUpload = "curl -s --request POST --url https://api.assemblyai.com/v2/upload " .
             "--header " . escapeshellarg("authorization: $assemblyApiKey") . " " .
             "--data-binary @" . escapeshellarg($audioPath);
$uploadResponse = shell_exec($cmdUpload);
$uploadData = json_decode($uploadResponse, true);

if (!isset($uploadData['upload_url'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al subir audio a AssemblyAI.',
        'debug'   => $uploadData
    ]);
    exit;
}
$uploadUrl = $uploadData['upload_url'];

// === Iniciar transcripción
$transcriptionRequest = [
    'audio_url'      => $uploadUrl,
    'language_code'  => 'es',
    'format_text'    => true,
    'disfluencies'   => false,
];

$ch = curl_init('https://api.assemblyai.com/v2/transcript');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authorization: ' . $assemblyApiKey,
    'content-type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transcriptionRequest));
$transcriptionResponse = curl_exec($ch);
if ($transcriptionResponse === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error cURL al iniciar transcripción: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

$transcriptionData = json_decode($transcriptionResponse, true);
if (!isset($transcriptionData['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Error al iniciar transcripción en AssemblyAI.', 'debug' => $transcriptionData]);
    exit;
}
$transcriptionId = $transcriptionData['id'];

// === Polling (60s = 10 intentos x 6s)
$attempts = 10;
$interval = 6;
$text = '';
for ($i = 0; $i < $attempts; $i++) {
    sleep($interval);
    $ch = curl_init("https://api.assemblyai.com/v2/transcript/$transcriptionId");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['authorization: ' . $assemblyApiKey]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $statusResponse = curl_exec($ch);
    if ($statusResponse === false) {
        $err = curl_error($ch);
        curl_close($ch);
        echo json_encode(['status' => 'error', 'message' => 'Error cURL al consultar transcripción: ' . $err]);
        exit;
    }
    curl_close($ch);

    $statusData = json_decode($statusResponse, true);
    if (!empty($statusData['status']) && $statusData['status'] === 'completed') {
        $text = quitarTildes(trim((string)$statusData['text']));
        break;
    } elseif (!empty($statusData['status']) && $statusData['status'] === 'failed') {
        echo json_encode(['status' => 'error', 'message' => 'La transcripción falló en AssemblyAI.', 'debug' => $statusData]);
        exit;
    }
}

if (!$text || strlen($text) < 5) {
    echo json_encode(['status' => 'error', 'message' => 'Texto transcrito vacío o inválido.']);
    exit;
}

// === Pasar texto a proceso_gpt.php
$gptUrl = 'http://localhost/funciones/proceso_gpt.php';
$payload = [
    'texto'          => $text,
    'plantilla_base' => $_POST['plantilla_base'] ?? '',
    'plantilla_id'   => $_POST['plantilla_id'] ?? '',
    'paciente'       => $_POST['paciente'] ?? '',
    'especie'        => $_POST['especie'] ?? '',
    'raza'           => $_POST['raza'] ?? '',
    'edad'           => $_POST['edad'] ?? '',
    'sexo'           => $_POST['sexo'] ?? '',
    'tipo_estudio'   => $_POST['tipo_estudio'] ?? '',
    'motivo'         => $_POST['motivo_examen'] ?? '',
    'audio_relative_path' => $_POST['relative_path'] ?? '',
];

$ch = curl_init($gptUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
$responseGPT = curl_exec($ch);
if ($responseGPT === false) {
    $err = curl_error($ch);
    curl_close($ch);
    echo json_encode(['status' => 'error', 'message' => 'Error cURL al llamar proceso_gpt: ' . $err]);
    exit;
}
curl_close($ch);

echo $responseGPT;
