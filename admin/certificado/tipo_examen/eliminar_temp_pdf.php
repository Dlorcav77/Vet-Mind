<?php
// admin/certificado/tipo_examen/eliminar_temp_pdf.php

header('Content-Type: application/json; charset=utf-8');

function eliminarArchivoTemporalPreviewSeguro($baseDir, $nombreArchivo)
{
    $nombreArchivo = basename((string)$nombreArchivo);

    if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..') {
        return [
            'ok' => false,
            'message' => 'Nombre inválido.'
        ];
    }

    $baseReal = realpath($baseDir);

    if ($baseReal === false || !is_dir($baseReal)) {
        return [
            'ok' => false,
            'message' => 'Directorio base inválido.'
        ];
    }

    $ruta = $baseReal . DIRECTORY_SEPARATOR . $nombreArchivo;
    $rutaReal = realpath($ruta);

    if ($rutaReal === false || !file_exists($rutaReal)) {
        return [
            'ok' => true,
            'message' => 'Archivo no existe.'
        ];
    }

    if (strpos($rutaReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
        return [
            'ok' => false,
            'message' => 'Ruta fuera del directorio permitido.'
        ];
    }

    if (!is_file($rutaReal)) {
        return [
            'ok' => false,
            'message' => 'No es archivo.'
        ];
    }

    if (@unlink($rutaReal)) {
        return [
            'ok' => true,
            'message' => 'Archivo eliminado.'
        ];
    }

    return [
        'ok' => false,
        'message' => 'No se pudo eliminar archivo.'
    ];
}

function normalizarRutaImagenPreview($rutaImagen)
{
    $rutaImagen = trim((string)$rutaImagen);

    if ($rutaImagen === '') {
        return null;
    }

    $rutaImagen = str_replace('\\', '/', $rutaImagen);
    $rutaImagen = preg_replace('#/+#', '/', $rutaImagen);
    $rutaImagen = ltrim($rutaImagen, '/');

    if (strpos($rutaImagen, 'uploads/tmp/img/') !== 0) {
        return null;
    }

    $nombreImagen = basename($rutaImagen);

    if ($nombreImagen === '' || $nombreImagen === '.' || $nombreImagen === '..') {
        return null;
    }

    if (strpos($nombreImagen, 'previmg_') !== 0) {
        return null;
    }

    return [
        'ruta' => 'uploads/tmp/img/' . $nombreImagen,
        'nombre' => $nombreImagen
    ];
}

$nombrePDF = trim((string)($_POST['pdf'] ?? ''));

if ($nombrePDF === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nombre de PDF no proporcionado.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

if ($documentRoot === '') {
    $documentRoot = realpath(__DIR__ . '/../../../..');
}

if ($documentRoot === false || $documentRoot === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo resolver DOCUMENT_ROOT.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$previewDir = $documentRoot . '/uploads/tmp/informe';
$previewImgDir = $documentRoot . '/uploads/tmp/img';

$resultadoPdf = eliminarArchivoTemporalPreviewSeguro($previewDir, $nombrePDF);

$imagenes = $_POST['imagenes'] ?? [];
$resultadosImagenes = [];
$imagenesEliminadas = 0;
$imagenesErrores = 0;

if (is_string($imagenes)) {
    $decode = json_decode($imagenes, true);
    $imagenes = is_array($decode) ? $decode : [];
}

if (!is_array($imagenes)) {
    $imagenes = [];
}

foreach ($imagenes as $imagen) {
    $normalizada = normalizarRutaImagenPreview($imagen);

    if ($normalizada === null) {
        $imagenesErrores++;

        $resultadosImagenes[] = [
            'imagen' => (string)$imagen,
            'ok' => false,
            'message' => 'Ruta de imagen no permitida.'
        ];

        continue;
    }

    $resultadoImagen = eliminarArchivoTemporalPreviewSeguro($previewImgDir, $normalizada['nombre']);

    if (!empty($resultadoImagen['ok'])) {
        $imagenesEliminadas++;
    } else {
        $imagenesErrores++;
    }

    $resultadosImagenes[] = [
        'imagen' => $normalizada['ruta'],
        'ok' => !empty($resultadoImagen['ok']),
        'message' => $resultadoImagen['message'] ?? ''
    ];
}

$status = (!empty($resultadoPdf['ok']) && $imagenesErrores === 0) ? 'success' : 'partial';

echo json_encode([
    'status' => $status,
    'message' => 'Limpieza de vista previa ejecutada.',
    'pdf' => [
        'archivo' => basename($nombrePDF),
        'ok' => !empty($resultadoPdf['ok']),
        'message' => $resultadoPdf['message'] ?? ''
    ],
    'imagenes' => [
        'recibidas' => count($imagenes),
        'eliminadas' => $imagenesEliminadas,
        'errores' => $imagenesErrores,
        'detalle' => $resultadosImagenes
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;