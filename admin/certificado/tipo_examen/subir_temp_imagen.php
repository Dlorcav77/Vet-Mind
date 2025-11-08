<?php
###########################################
require_once("../../config.php");
###########################################

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['imagen'])) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió la imagen']);
    exit;
}

$archivo = $_FILES['imagen'];
$nombreTemp = $archivo['tmp_name'];
$nombreArchivo = 'temp_' . time() . '_' . rand(1000,9999) . '.jpg';

// 👇 Ruta absoluta en disco
$carpetaDestino = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tmp/';
$destino = $carpetaDestino . $nombreArchivo;

// Crear carpeta si no existe
// if (!is_dir($carpetaDestino)) {
//     if (!mkdir($carpetaDestino, 0777, true)) {
//         echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta tmp']);
//         exit;
//     }
// }

// Mover el archivo subido
if (move_uploaded_file($nombreTemp, $destino)) {
    // ✅ URL pública para usar en el frontend
    $url = '/uploads/tmp/' . $nombreArchivo;
    echo json_encode(['status' => 'success', 'url' => $url]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al mover la imagen temporal']);
}
