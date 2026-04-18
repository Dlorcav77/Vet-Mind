<?php
require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_FILES['imagen']) || empty($_FILES['imagen']['tmp_name'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se recibió ninguna imagen.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!empty($_FILES['imagen']['error']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al subir la imagen.',
        'upload_error' => (int)$_FILES['imagen']['error']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tmpPath = $_FILES['imagen']['tmp_name'];
$nombreOriginal = $_FILES['imagen']['name'] ?? 'imagen';
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

$extPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];
if (!in_array($extension, $extPermitidas, true)) {
    $extension = 'png';
}

$directorioTmp = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/tmp/';

if (!is_dir($directorioTmp)) {
    if (!mkdir($directorioTmp, 0775, true) && !is_dir($directorioTmp)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo crear el directorio temporal.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$nombreArchivo = 'tmp_img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$rutaDestino = $directorioTmp . $nombreArchivo;

if (!move_uploaded_file($tmpPath, $rutaDestino)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo guardar la imagen temporal.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$urlPublica = '/uploads/tmp/' . $nombreArchivo;

echo json_encode([
    'status' => 'success',
    'url' => $urlPublica,
    'filename' => $nombreArchivo
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;