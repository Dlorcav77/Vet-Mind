<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['imagen']) || empty($_POST['imagen'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nombre de imagen no proporcionado'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$nombreImagen = basename((string)$_POST['imagen']);
$rutaTemporal = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/tmp/' . $nombreImagen;

error_log("Eliminando imagen temporal: $rutaTemporal");

if (file_exists($rutaTemporal)) {
    if (unlink($rutaTemporal)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Imagen temporal eliminada correctamente'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo eliminar la imagen temporal'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Archivo no encontrado en el servidor'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;