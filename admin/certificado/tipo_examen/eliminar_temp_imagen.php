<?php
// admin/certificado/tipo_examen/eliminar_temp_imagen.php

header('Content-Type: application/json; charset=utf-8');

function normalizarRutaImagenTemporalCertificado($rutaImagen)
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

    $prefijosPermitidos = [
        'tmp_img_',
        'previmg_'
    ];

    $prefijoOk = false;

    foreach ($prefijosPermitidos as $prefijo) {
        if (strpos($nombreImagen, $prefijo) === 0) {
            $prefijoOk = true;
            break;
        }
    }

    if (!$prefijoOk) {
        return null;
    }

    return [
        'ruta' => 'uploads/tmp/img/' . $nombreImagen,
        'directorio' => 'uploads/tmp/img',
        'nombre' => $nombreImagen
    ];
}

function eliminarImagenTemporalCertificadoSeguro($documentRoot, $rutaImagen)
{
    $normalizada = normalizarRutaImagenTemporalCertificado($rutaImagen);

    if ($normalizada === null) {
        return [
            'ok' => false,
            'imagen' => (string)$rutaImagen,
            'message' => 'Ruta de imagen no permitida.'
        ];
    }

    $baseDir = rtrim($documentRoot, '/') . '/' . $normalizada['directorio'];
    $baseReal = realpath($baseDir);

    if ($baseReal === false || !is_dir($baseReal)) {
        return [
            'ok' => false,
            'imagen' => $normalizada['ruta'],
            'message' => 'Directorio base inválido.'
        ];
    }

    $rutaFisica = $baseReal . DIRECTORY_SEPARATOR . $normalizada['nombre'];
    $rutaReal = realpath($rutaFisica);

    if ($rutaReal === false || !file_exists($rutaReal)) {
        return [
            'ok' => true,
            'imagen' => $normalizada['ruta'],
            'message' => 'Archivo no existe.'
        ];
    }

    if (strpos($rutaReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
        return [
            'ok' => false,
            'imagen' => $normalizada['ruta'],
            'message' => 'Ruta fuera del directorio permitido.'
        ];
    }

    if (!is_file($rutaReal)) {
        return [
            'ok' => false,
            'imagen' => $normalizada['ruta'],
            'message' => 'No es archivo.'
        ];
    }

    if (@unlink($rutaReal)) {
        return [
            'ok' => true,
            'imagen' => $normalizada['ruta'],
            'message' => 'Imagen temporal eliminada.'
        ];
    }

    return [
        'ok' => false,
        'imagen' => $normalizada['ruta'],
        'message' => 'No se pudo eliminar la imagen temporal.'
    ];
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

$imagenes = [];

if (!empty($_POST['imagenes'])) {
    if (is_array($_POST['imagenes'])) {
        $imagenes = $_POST['imagenes'];
    } else {
        $decode = json_decode((string)$_POST['imagenes'], true);
        $imagenes = is_array($decode) ? $decode : [];
    }
}

if (!empty($_POST['imagen'])) {
    $imagenes[] = $_POST['imagen'];
}

$imagenes = array_values(array_filter($imagenes, function ($img) {
    return trim((string)$img) !== '';
}));

if (empty($imagenes)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se recibieron imágenes temporales para eliminar.',
        'imagenes' => [
            'recibidas' => 0,
            'eliminadas' => 0,
            'errores' => 0,
            'detalle' => []
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$resultados = [];
$eliminadas = 0;
$errores = 0;

foreach ($imagenes as $imagen) {
    $resultado = eliminarImagenTemporalCertificadoSeguro($documentRoot, $imagen);

    if (!empty($resultado['ok'])) {
        $eliminadas++;
    } else {
        $errores++;
    }

    $resultados[] = $resultado;
}

$status = $errores === 0 ? 'success' : 'partial';

echo json_encode([
    'status' => $status,
    'message' => 'Limpieza de imágenes temporales ejecutada.',
    'imagenes' => [
        'recibidas' => count($imagenes),
        'eliminadas' => $eliminadas,
        'errores' => $errores,
        'detalle' => $resultados
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;