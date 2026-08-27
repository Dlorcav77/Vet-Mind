<?php
// funciones/GPT/transcribir_audio_openai_4o.php
// Variante de transcripción usando OpenAI gpt-4o-transcribe.
// Se invoca desde transcribir_audio.php cuando $motor_stt === 'openai_4o'.
// Flujo: recibir/subir audio -> convertir a WAV -> /v1/audio/transcriptions.

if (
    !defined('VETMIND_STT_DISPATCH')
    || VETMIND_STT_DISPATCH !== true
) {
    http_response_code(403);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode([
        'status'  => 'error',
        'message' => 'Acceso directo no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

$ROOT_DIR = dirname(__DIR__, 3);

require_once(
    $ROOT_DIR . "/configP.php"
);

require_once(
    $ROOT_DIR
    . "/funciones/session/funcionesSesion.php"
);

iniciarSesionSegura();

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

$openaiApiKey = $OPENAI_API_KEY ?? getenv('OPENAI_API_KEY') ?: '';

if (!$openaiApiKey) {
    echo json_encode([
        'status' => 'error',
        'message' => 'API Key de OpenAI no configurada.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizarRutaAudioTmpOpenAI4o($ruta, $userId)
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

function resolverAudioTmpFisicoOpenAI4o($rootDir, $rutaAudioTmp, $userId)
{
    $rutaNormalizada = normalizarRutaAudioTmpOpenAI4o($rutaAudioTmp, $userId);

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

function guardarAudioSubidoParaTranscripcionOpenAI4o($rootDir, $userId)
{
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        return [
            'status' => 'error',
            'message' => 'No se recibió ningún archivo de audio válido.',
            'path' => '',
            'audio_tmp' => ''
        ];
    }

    $maxBytes = 25 * 1024 * 1024;

    $tmpFile = $_FILES['audio']['tmp_name'];
    $size = (int)($_FILES['audio']['size'] ?? 0);

    if ($size <= 0 && is_file($tmpFile)) {
        $size = filesize($tmpFile);
    }

    if ($size > $maxBytes) {
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

    return [
        'status' => 'success',
        'message' => 'Audio subido temporalmente.',
        'path' => $rutaConvertida,
        'audio_tmp' => 'uploads/tmp/audio/' . $nombreConvertido
    ];
}

$audioPath = '';
$audioTmpRespuesta = '';

if (isset($_FILES['audio'])) {
    $resultadoUpload = guardarAudioSubidoParaTranscripcionOpenAI4o($ROOT_DIR, $userId);

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
    $audioPath = resolverAudioTmpFisicoOpenAI4o($ROOT_DIR, $audioTmpRespuesta, $userId);
} elseif (!empty($_POST['audio_url'])) {
    $audioTmpRespuesta = trim((string)$_POST['audio_url']);
    $audioPath = resolverAudioTmpFisicoOpenAI4o($ROOT_DIR, $audioTmpRespuesta, $userId);
} elseif (!empty($_POST['audio_filename'])) {
    $audioTmpRespuesta = 'uploads/tmp/audio/' . basename((string)$_POST['audio_filename']);
    $audioPath = resolverAudioTmpFisicoOpenAI4o($ROOT_DIR, $audioTmpRespuesta, $userId);
}

if (!$audioPath || !file_exists($audioPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Archivo de audio no encontrado.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$postFields = [
    'model' => 'gpt-4o-transcribe',
    'file' => new CURLFile($audioPath, 'audio/wav', basename($audioPath)),
    'language' => 'es',
    'response_format' => 'json',
];

// Keyterms opcionales: solo si la llamada pide usar_keyterms=1.
// OpenAI los recibe vía 'prompt' (texto de contexto). Lista en lib/stt_keyterms.php.
if (!empty($_POST['usar_keyterms']) && (string)$_POST['usar_keyterms'] === '1') {
    $kwFile = dirname(__DIR__) . '/lib/stt_keyterms.php';
    if (is_file($kwFile)) {
        $keyterms = require($kwFile);
        if (is_array($keyterms) && !empty($keyterms)) {
            $postFields['prompt'] =
                'Transcribe en español. Es un dictado clínico veterinario, principalmente ecográfico. '
              . 'Respeta términos médicos y veterinarios como ' . implode(', ', $keyterms) . '.';
        }
    }
}

$ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $openaiApiKey,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

$data = json_decode((string)$response, true);

if ($curlErr !== '' || $httpCode >= 400 || !is_array($data)) {
    file_put_contents(
        $logDir . '/openai_4o_transcribe_error.log',
        date('c') . " | user:$userId | http:$httpCode | err:$curlErr | resp:" . substr((string)$response, 0, 2000) . "\n",
        FILE_APPEND
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Error al transcribir audio con OpenAI.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$text = trim((string)($data['text'] ?? ''));

if ($text === '') {
    file_put_contents(
        $logDir . '/openai_4o_transcribe_empty.log',
        date('c') . " | user:$userId | http:$httpCode | resp:" . substr((string)$response, 0, 2000) . "\n",
        FILE_APPEND
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Texto transcrito vacío.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'success',
    'texto' => $text,
    'audio_tmp' => $audioTmpRespuesta
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;