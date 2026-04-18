<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['pdf']) || empty($_POST['pdf'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nombre de PDF no proporcionado'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$nombrePDF = basename((string)$_POST['pdf']);
$rutaTemporal = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/tmp_previews/' . $nombrePDF;

error_log("Eliminando PDF temporal: $rutaTemporal");

if (file_exists($rutaTemporal)) {
    if (unlink($rutaTemporal)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'PDF temporal eliminado correctamente'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo eliminar el PDF temporal'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Archivo no encontrado en el servidor'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;