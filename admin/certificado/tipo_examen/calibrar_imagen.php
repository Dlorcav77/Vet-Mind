<?php

require_once("../../config.php");

configurarErroresAplicacion(true);

header(
    'Content-Type: application/json; charset=utf-8'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_POST['imagen'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se recibió la imagen'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$imagenUrl = (string)$_POST['imagen'];
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);

if (!$documentRoot) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo resolver DOCUMENT_ROOT'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rutaUrl = parse_url($imagenUrl, PHP_URL_PATH);
$imagenPath = $documentRoot . $rutaUrl;

if (!file_exists($imagenPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Imagen no encontrada: ' . $imagenPath
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pythonScript = $documentRoot . "/funciones/auto_calibrar.py";
if (!file_exists($pythonScript)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No existe auto_calibrar.py en ' . $pythonScript
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$cmd = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($imagenPath) . " 2>&1";
$output = shell_exec($cmd);

@file_put_contents(
    $documentRoot . '/debug_py.log',
    "CMD: $cmd\nOUTPUT:\n" . (string)$output . "\n\n",
    FILE_APPEND
);

if ($output === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al ejecutar auto_calibrar.py'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ocrTextos = "";
if (preg_match('/Valores OCR detectados.*?\[(.*?)\]/s', $output, $match)) {
    $ocrTextos = '[' . $match[1] . ']';
}

if (preg_match('/(\d+\.\d+)\s*$/', trim($output), $match)) {
    $pxPorCm = (float)$match[1];

    echo json_encode([
        'status' => 'success',
        'pxPorCm' => $pxPorCm,
        'ocr' => $ocrTextos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'No se pudo detectar la escala automáticamente',
    'debug' => $output
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;