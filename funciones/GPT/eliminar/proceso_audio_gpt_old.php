<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Santiago');

// ahora estamos en /funciones/GPT
$ROOT_DIR = dirname(__DIR__, 2);   // /
$FUNC_DIR = dirname(__DIR__);      // /funciones

require_once($ROOT_DIR . "/configP.php");

session_start();

$logDir = $FUNC_DIR . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$T0 = microtime(true); // ⏱ inicio total

$userId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit;
}

// 📁 Base y fecha
$baseDir = $ROOT_DIR . '/uploads/grabaciones';
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta grabaciones']);
        exit;
    }
}

$now   = new DateTime('now', new DateTimeZone('America/Santiago'));
$year  = $now->format('Y');
$month = $now->format('m');
$day   = $now->format('d');
$hmsms = $now->format('Hisv'); // HHMMSSmmm

$uploadDir = $baseDir . '/' . $year . '/' . $month;
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta destino']);
        exit;
    }
}

// 🔒 API Key AssemblyAI
$assemblyApiKey = $ASSEM_API_KEY ?? '';
if (!$assemblyApiKey) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de AssemblyAI no configurada.']);
    exit;
}

$audioPath = '';

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

// 🟣 recibir audio
if (isset($_FILES['audio'])) {
    $tmpFile = $_FILES['audio']['tmp_name'];
    $originalExt = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
    if ($originalExt === '') { $originalExt = 'webm'; }

    // === Validaciones ===
    define('MAX_AUDIO_BYTES', 25 * 1024 * 1024); // 25 MB

    $size = (int)($_FILES['audio']['size'] ?? 0);
    if ($size <= 0 && is_file($tmpFile)) {
        $size = filesize($tmpFile);
    }
    if ($size > MAX_AUDIO_BYTES) {
        @unlink($tmpFile);
        echo json_encode(['status' => 'error', 'message' => 'Archivo demasiado grande (máx 25 MB).']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpFile) ?: '';

    $allowedMimes = [
        'audio/wav', 'audio/x-wav',
        'audio/webm', 'video/webm',
        'audio/ogg',
        'audio/mpeg',
        'audio/mp4',
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
            " | mime:$mime | nombre:" . ($_FILES['audio']['name'] ?? '-') . "\n",
            FILE_APPEND
        );
        echo json_encode(['status' => 'error', 'message' => 'Formato de audio no permitido. Usa WAV, WEBM, MP3, OGG o M4A.']);
        exit;
    }

    $tempName = preg_replace('/[^a-zA-Z0-9_\-]/', '', uniqid('upload_', true)) . '.' . $originalExt;
    $tempPath = $uploadDir . '/' . $tempName;

    if (!move_uploaded_file($tmpFile, $tempPath)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo de audio subido']);
        exit;
    }

    $convertedName = $userId . '_' . $day . '_' . $hmsms . '.wav';
    $convertedPath = $uploadDir . '/' . $convertedName;

    $ffmpegPath = trim(shell_exec('command -v ffmpeg 2>/dev/null') ?? '');
    if ($ffmpegPath === '') {
        echo json_encode(['status' => 'error', 'message' => 'FFmpeg no está instalado o no está en $PATH.']);
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
        unlink($tempPath);
        echo json_encode(['status' => 'error', 'message' => 'Error al convertir el audio a WAV. Revisa ' . $logDir . '/ffmpeg_error.log']);
        exit;
    }

    unlink($tempPath);
    $audioPath = $convertedPath;

} elseif (isset($_POST['audio_filename']) || isset($_POST['audio_url'])) {
    $input = $_POST['audio_filename'] ?? $_POST['audio_url'];
    $rel   = sanitizeRelativeAudioPath($input);

    if ($rel === '' || $rel === '.') {
        echo json_encode(['status' => 'error', 'message' => 'Ruta de audio inválida']);
        exit;
    }

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

if (!file_exists($audioPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Archivo de audio no encontrado']);
    exit;
}

/**
 * ⏱ SUBIDA A ASSEMBLY
 */
$T_upload_start = microtime(true);

$curlCmd = "curl -s --request POST " .
           "--url https://api.assemblyai.com/v2/upload " .
           "--header " . escapeshellarg("authorization: $assemblyApiKey") . " " .
           "--header 'content-type: application/octet-stream' " .
           "--data-binary @" . escapeshellarg($audioPath) . " " .
           "--connect-timeout 10 --max-time 60";

$uploadResponse = shell_exec($curlCmd);

$T_upload_end = microtime(true);

$uploadData = json_decode($uploadResponse, true);

if (!is_array($uploadData) || !isset($uploadData['upload_url'])) {
    file_put_contents(
        $logDir . '/assembly_upload_error.log',
        date('c') . " | userId:$userId | resp:" . substr((string)$uploadResponse, 0, 4000) . "\n",
        FILE_APPEND
    );
    $detail = is_array($uploadData) && isset($uploadData['error']) ? (' Detalle: ' . $uploadData['error']) : '';
    echo json_encode(['status' => 'error', 'message' => 'Error al subir audio a AssemblyAI.' . $detail]);
    exit;
}
$uploadUrl = $uploadData['upload_url'];

/**
 * ⏱ POLLING DE TRANSCRIPCIÓN
 */
$T_poll_start = microtime(true);

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
if ($httpCode >= 400 || !is_array($transcriptionData) || !isset($transcriptionData['id'])) {
    file_put_contents(
        $logDir . '/assembly_transcript_error.log',
        date('c') . " | userId:$userId | http:$httpCode | resp:" . substr((string)$transcriptionResponse, 0, 4000) . "\n",
        FILE_APPEND
    );
    $detail = is_array($transcriptionData) && isset($transcriptionData['error']) ? (' Detalle: ' . $transcriptionData['error']) : '';
    echo json_encode(['status' => 'error', 'message' => 'Error al iniciar transcripción en AssemblyAI.' . $detail]);
    exit;
}
$transcriptionId = $transcriptionData['id'];

$status = '';
$text = '';
$delays = [2, 3, 5, 8, 8, 8, 8, 8, 8, 8, 8, 8]; // ~60s total aprox

for ($i = 0; $i < count($delays); $i++) {
    $ch = curl_init("https://api.assemblyai.com/v2/transcript/$transcriptionId");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'authorization: ' . $assemblyApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $statusResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $statusData = json_decode($statusResponse, true);
    if ($httpCode >= 400 || !is_array($statusData) || !isset($statusData['status'])) {
        sleep($delays[$i]);
        continue;
    }

    if ($statusData['status'] === 'completed') {
        $text = trim((string)$statusData['text']);
        $text = quitarTildes($text);
        break;
    }

    if ($statusData['status'] === 'failed') {
        $detail = isset($statusData['error']) ? (' Detalle: ' . $statusData['error']) : '';
        echo json_encode(['status' => 'error', 'message' => 'La transcripción falló en AssemblyAI.' . $detail]);
        exit;
    }

    sleep($delays[$i]);
}

$T_poll_end = microtime(true);

if (!$text || strlen($text) < 5) {
    echo json_encode(['status' => 'error', 'message' => 'Texto transcrito vacío o inválido (timeout o contenido muy corto).']);
    exit;
}

/**
 * ⏱ LLAMADA A GPT LOCAL
 */
$T_gpt_start = microtime(true);

$ch = curl_init('http://localhost/funciones/GPT/proceso_gpt.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'texto'         => $text,
    'plantilla_base'=> $_POST['plantilla_base'] ?? '',
    'plantilla_id'  => $_POST['plantilla_id'] ?? '',
    'paciente'      => $_POST['paciente'] ?? '',
    'especie'       => $_POST['especie'] ?? '',
    'raza'          => $_POST['raza'] ?? '',
    'edad'          => $_POST['edad'] ?? '',
    'sexo'          => $_POST['sexo'] ?? '',
    'tipo_estudio'  => $_POST['tipo_estudio'] ?? '',
    'motivo'        => $_POST['motivo_examen'] ?? ''
]));
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$responseGPT = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errNo    = curl_errno($ch);
$errMsg   = curl_error($ch);
curl_close($ch);

$T_gpt_end = microtime(true);

$T_end = microtime(true);

// guardar tiempos en log
file_put_contents(
    $logDir . '/tiempos_proceso_audio.log',
    date('c')
    . ' | upload=' . round($T_upload_end - $T_upload_start, 3) . 's'
    . ' | poll='   . round($T_poll_end - $T_poll_start, 3) . 's'
    . ' | gpt='    . round($T_gpt_end - $T_gpt_start, 3) . 's'
    . ' | total='  . round($T_end - $T0, 3) . "s\n",
    FILE_APPEND
);

if ($errNo) {
    file_put_contents(
        $logDir . '/proceso_gpt_error.log',
        date('c') . " | userId:$userId | curl_err:$errNo $errMsg\n",
        FILE_APPEND
    );
    echo json_encode(['status' => 'error', 'message' => 'Error cURL al llamar proceso_gpt.']);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300 || !$responseGPT) {
    file_put_contents(
        $logDir . '/proceso_gpt_error.log',
        date('c') . " | userId:$userId | http:$httpCode | body:" . substr((string)$responseGPT, 0, 2000) . "\n",
        FILE_APPEND
    );
    echo json_encode(['status' => 'error', 'message' => 'Error al llamar proceso_gpt (HTTP ' . $httpCode . ').']);
    exit;
}

// ✅ Devolver respuesta de GPT tal cual
echo $responseGPT;


/**
 * util
 */
function quitarTildes($string) {
    $originales = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú'];
    $sinTildes  = ['a','e','i','o','u','A','E','I','O','U'];
    return str_replace($originales, $sinTildes, $string);
}
