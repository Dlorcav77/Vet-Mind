<?php
header('Content-Type: application/json');

// 📌 Validar que venga el nombre de la imagen
if (!isset($_POST['imagen']) || empty($_POST['imagen'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nombre de imagen no proporcionado'
    ]);
    exit;
}

$nombreImagen = basename($_POST['imagen']); // 🔒 Solo el nombre, evita path traversal
$rutaTemporal = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tmp/' . $nombreImagen;

error_log("Eliminando imagen temporal: $rutaTemporal");

// 🗑️ Verificar si existe y eliminar
if (file_exists($rutaTemporal)) {
    if (unlink($rutaTemporal)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Imagen temporal eliminada correctamente'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo eliminar la imagen temporal'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Archivo no encontrado en el servidor'
    ]);
}
