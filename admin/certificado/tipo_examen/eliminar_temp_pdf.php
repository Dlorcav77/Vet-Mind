<?php
// admin/certificado/tipo_examen/eliminar_temp_pdf.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

validarTokenCsrf();

$veterinario = (int)$usuario_id;

if ($veterinario <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function eliminarArchivoTemporalPreviewSeguro($baseDir, $nombreArchivo)
{
    $nombreArchivo = basename((string)$nombreArchivo);

    if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..') {
        return ['ok' => false, 'message' => 'Nombre inválido.'];
    }

    $baseReal = realpath($baseDir);

    if ($baseReal === false || !is_dir($baseReal)) {
        return ['ok' => false, 'message' => 'Directorio base inválido.'];
    }

    $ruta = $baseReal . DIRECTORY_SEPARATOR . $nombreArchivo;
    $rutaReal = realpath($ruta);

    if ($rutaReal === false || !file_exists($rutaReal)) {
        return ['ok' => true, 'message' => 'Archivo no existe.'];
    }

    if (strpos($rutaReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
        return ['ok' => false, 'message' => 'Ruta fuera del directorio permitido.'];
    }

    if (!is_file($rutaReal)) {
        return ['ok' => false, 'message' => 'No es archivo.'];
    }

    if (@unlink($rutaReal)) {
        return ['ok' => true, 'message' => 'Archivo eliminado.'];
    }

    return ['ok' => false, 'message' => 'No se pudo eliminar archivo.'];
}

function normalizarPdfPreview($nombrePDF, $veterinario)
{
    $nombrePDF = basename(trim((string)$nombrePDF));

    if ($nombrePDF === '' || $nombrePDF === '.' || $nombrePDF === '..') {
        return null;
    }

    $prefijoEsperado = 'preview_' . (int)$veterinario . '_';

    if (strpos($nombrePDF, $prefijoEsperado) !== 0) {
        return null;
    }

    if (strtolower(pathinfo($nombrePDF, PATHINFO_EXTENSION)) !== 'pdf') {
        return null;
    }

    return $nombrePDF;
}

function normalizarRutaImagenPreview($rutaImagen, $veterinario)
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
    $prefijoEsperado = 'previmg_' . (int)$veterinario . '_';

    if (strpos($nombreImagen, $prefijoEsperado) !== 0) {
        return null;
    }

    return [
        'ruta' => 'uploads/tmp/img/' . $nombreImagen,
        'nombre' => $nombreImagen
    ];
}

$nombrePDF = normalizarPdfPreview($_POST['pdf'] ?? '', $veterinario);

if ($nombrePDF === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'PDF temporal inválido o no permitido.'
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

if (is_string($imagenes)) {
    $decode = json_decode($imagenes, true);
    $imagenes = is_array($decode) ? $decode : [];
}

if (!is_array($imagenes)) {
    $imagenes = [];
}

$resultadosImagenes = [];
$imagenesEliminadas = 0;
$imagenesErrores = 0;

foreach ($imagenes as $imagen) {
    $normalizada = normalizarRutaImagenPreview($imagen, $veterinario);

    if ($normalizada === null) {
        $imagenesErrores++;
        $resultadosImagenes[] = [
            'imagen' => (string)$imagen,
            'ok' => false,
            'message' => 'Ruta de imagen no permitida.'
        ];
        continue;
    }

    $resultadoImagen = eliminarArchivoTemporalPreviewSeguro(
        $previewImgDir,
        $normalizada['nombre']
    );

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

$status = (!empty($resultadoPdf['ok']) && $imagenesErrores === 0)
    ? 'success'
    : 'partial';

echo json_encode([
    'status' => $status,
    'message' => 'Limpieza de vista previa ejecutada.',
    'pdf' => [
        'archivo' => $nombrePDF,
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