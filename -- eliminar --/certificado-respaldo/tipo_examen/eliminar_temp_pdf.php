<?php
header('Content-Type: application/json');

if (!isset($_POST['pdf']) || empty($_POST['pdf'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nombre de PDF no proporcionado'
    ]);
    exit;
}

$nombrePDF = basename($_POST['pdf']); // 🔒 Evita path traversal
$rutaTemporal = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tmp_previews/' . $nombrePDF;

error_log("Eliminando imagen temporal: $rutaTemporal");

if (file_exists($rutaTemporal)) {
    if (unlink($rutaTemporal)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'PDF temporal eliminado correctamente'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo eliminar el PDF temporal'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Archivo no encontrado en el servidor'
    ]);
}
