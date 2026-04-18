<?php
###########################################
require_once("../../config.php");
###########################################

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');   // no imprimir errores al output
ini_set('log_errors', '1');       // loguéalos
error_reporting(E_ALL);   

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (empty($_POST['imagen'])) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió la imagen']);
    exit;
}

$imagenUrl = $_POST['imagen'];

// 👇 Cambiar la ruta para apuntar bien a /var/www/html/uploads/tmp
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT']); // /var/www/html
$imagenPath = $documentRoot . parse_url($imagenUrl, PHP_URL_PATH);

if (!file_exists($imagenPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Imagen no encontrada: ' . $imagenPath]);
    exit;
}

// Ejecutar el script Python
$cmd = "python3 " . escapeshellarg($documentRoot . "/funciones/auto_calibrar.py") . " " . escapeshellarg($imagenPath) . " 2>&1";
$output = shell_exec($cmd);
file_put_contents('/var/www/html/debug_py.log', "CMD: $cmd\nOUTPUT:\n" . $output . "\n\n", FILE_APPEND);


if ($output === null) {
    echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar auto_calibrar.py']);
    exit;
}

// Buscar OCR detectados
$ocrTextos = "";
if (preg_match('/Valores OCR detectados.*?\[(.*?)\]/s', $output, $match)) {
    $ocrTextos = '[' . $match[1] . ']';
}

// Buscar pxPorCm en la salida
if (preg_match('/(\d+\.\d+)\s*$/', $output, $match)) {
    $pxPorCm = floatval($match[1]);
    echo json_encode([
        'status' => 'success',
        'pxPorCm' => $pxPorCm,
        'ocr' => $ocrTextos
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo detectar la escala automáticamente', 'debug' => $output]);
}
