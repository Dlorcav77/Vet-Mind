<?php
// admin/certificado/tipo_examen/calibrar_imagen.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

function responderCalibracion(string $status, string $message, array $extra = []): void
{
    echo json_encode(
        array_merge([
            'status' => $status,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderCalibracion('error', 'Método no permitido.');
}

validarTokenCsrf();
credenciales('certificado', 'listar');

$veterinario = (int)$usuario_id;

if ($veterinario <= 0) {
    http_response_code(401);
    responderCalibracion('error', 'Sesión inválida.');
}

$imagenUrl = trim((string)($_POST['imagen'] ?? ''));

if ($imagenUrl === '') {
    responderCalibracion('error', 'No se recibió la imagen.');
}

$rutaUrl = parse_url($imagenUrl, PHP_URL_PATH);

if (!is_string($rutaUrl) || $rutaUrl === '') {
    responderCalibracion('error', 'Ruta de imagen inválida.');
}

$rutaUrl = str_replace('\\', '/', $rutaUrl);
$rutaUrl = preg_replace('#/+#', '/', $rutaUrl);

$prefijoUrl = '/uploads/tmp/img/';

if (strpos($rutaUrl, $prefijoUrl) !== 0) {
    http_response_code(403);
    responderCalibracion('error', 'La imagen no pertenece al directorio temporal permitido.');
}

$nombreImagen = basename($rutaUrl);
$prefijoUsuario = 'tmp_img_' . $veterinario . '_';

if (
    $nombreImagen === '' ||
    $nombreImagen === '.' ||
    $nombreImagen === '..' ||
    strpos($nombreImagen, $prefijoUsuario) !== 0
) {
    http_response_code(403);
    responderCalibracion('error', 'La imagen temporal no pertenece al usuario actual.');
}

$documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));

if ($documentRoot === false) {
    error_log('[calibrar_imagen] No se pudo resolver DOCUMENT_ROOT.');
    responderCalibracion('error', 'No se pudo preparar la calibración.');
}

$directorioTmp = realpath($documentRoot . '/uploads/tmp/img');

if ($directorioTmp === false || !is_dir($directorioTmp)) {
    error_log('[calibrar_imagen] Directorio temporal inválido.');
    responderCalibracion('error', 'No se pudo preparar la calibración.');
}

$imagenPath = realpath($directorioTmp . DIRECTORY_SEPARATOR . $nombreImagen);

if (
    $imagenPath === false ||
    !is_file($imagenPath) ||
    strpos($imagenPath, $directorioTmp . DIRECTORY_SEPARATOR) !== 0
) {
    responderCalibracion('error', 'Imagen temporal no encontrada.');
}

$tamano = filesize($imagenPath);

if ($tamano === false || $tamano <= 0 || $tamano > 20 * 1024 * 1024) {
    responderCalibracion('error', 'La imagen temporal tiene un tamaño no permitido.');
}

$infoImagen = @getimagesize($imagenPath);

if ($infoImagen === false) {
    responderCalibracion('error', 'El archivo temporal no es una imagen válida.');
}

$ancho = (int)($infoImagen[0] ?? 0);
$alto = (int)($infoImagen[1] ?? 0);
$mime = (string)($infoImagen['mime'] ?? '');

$mimesPermitidos = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/bmp',
    'image/x-ms-bmp'
];

if (
    $ancho <= 0 ||
    $alto <= 0 ||
    ($ancho * $alto) > 25000000 ||
    !in_array($mime, $mimesPermitidos, true)
) {
    responderCalibracion('error', 'La imagen temporal no tiene un formato o resolución permitidos.');
}

$pythonScript = realpath($documentRoot . '/funciones/auto_calibrar.py');

if (
    $pythonScript === false ||
    !is_file($pythonScript) ||
    strpos($pythonScript, $documentRoot . DIRECTORY_SEPARATOR) !== 0
) {
    error_log('[calibrar_imagen] No se encontró auto_calibrar.py.');
    responderCalibracion('error', 'No se pudo preparar el motor de calibración.');
}

$cmd = 'python3 '
    . escapeshellarg($pythonScript)
    . ' '
    . escapeshellarg($imagenPath)
    . ' 2>&1';

$output = shell_exec($cmd);

if ($output === null) {
    error_log('[calibrar_imagen] shell_exec devolvió NULL.');
    responderCalibracion('error', 'No se pudo ejecutar la calibración automática.');
}

$ocrTextos = '';

if (preg_match('/Valores OCR detectados.*?\[(.*?)\]/s', $output, $match)) {
    $ocrTextos = '[' . $match[1] . ']';
}

if (preg_match('/(\d+(?:\.\d+)?)\s*$/', trim($output), $match)) {
    $pxPorCm = (float)$match[1];

    if ($pxPorCm <= 0 || !is_finite($pxPorCm)) {
        error_log('[calibrar_imagen] Resultado de calibración inválido.');
        responderCalibracion('error', 'La escala detectada no es válida.');
    }

    responderCalibracion(
        'success',
        'Calibración realizada correctamente.',
        [
            'pxPorCm' => $pxPorCm,
            'ocr' => $ocrTextos
        ]
    );
}

error_log(
    '[calibrar_imagen][python] No se pudo interpretar la salida: ' .
    substr((string)$output, 0, 2000)
);

responderCalibracion(
    'error',
    'No se pudo detectar la escala automáticamente.'
);