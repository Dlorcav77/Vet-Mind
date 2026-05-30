<?php
// admin/certificado/previewPDF.php

require_once("../config.php");
require_once("../../vendor/autoload.php");
require_once("funcionesCertificado.php");

use Dompdf\Dompdf;

$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

try {
    $veterinario              = intval($_POST['veterinario_id'] ?? 0);
    $paciente_id              = intval($_POST['paciente_id'] ?? 0);
    $fecha_examen             = $_POST['fecha_examen'] ?? date('Y-m-d');
    $motivo                   = trim($_POST['motivo_examen'] ?? '');
    $descripcion              = trim($_POST['contenido_html'] ?? '');
    $medico_solicitante       = trim($_POST['medico_solicitante'] ?? '');
    $recinto                  = trim($_POST['recinto'] ?? '');
    $plantilla_informe_id     = intval($_POST['plantilla_informe_id'] ?? 0);
    $configuracion_informe_id = intval($_POST['configuracion_informe_id'] ?? 0);
    $modo_manual              = isset($_POST['toggle_manual']) && $_POST['toggle_manual'] == '1';

    $manual_data_preview = [];

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'manual_') !== 0) {
            continue;
        }

        $campoManual = substr($key, 7);

        if ($campoManual === '') {
            continue;
        }

        $manual_data_preview[$campoManual] = is_array($value)
            ? $value
            : trim((string)$value);
    }

    $paciente = null;

    if ($modo_manual) {
        $manual = function ($campo) {
            return trim($_POST["manual_$campo"] ?? '');
        };

        $paciente = [
            'paciente'         => $manual('paciente'),
            'especie'          => $manual('especie'),
            'raza'             => $manual('raza'),
            'propietario'      => $manual('propietario'),
            'edad'             => $manual('edad'),
            'sexo'             => $manual('sexo'),
            'fecha_nacimiento' => $manual('fecha_nacimiento'),
            'n_chip'           => $manual('n_chip'),
            'codigo_paciente'  => $manual('codigo_paciente'),
            'N_ficha'          => $manual('N_ficha'),
            'm_tratante'       => $manual('m_tratante'),
        ];
    }

    if ($veterinario <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Falta el veterinario del formulario.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($configuracion_informe_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Debes seleccionar una plantilla de diseño.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($plantilla_informe_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Debes seleccionar un tipo de examen.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($descripcion === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'El contenido del informe está vacío.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!$modo_manual && $paciente_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Debe seleccionar un paciente o ingresar los datos manualmente.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($modo_manual) {
        $nombrePaciente = trim($paciente['paciente'] ?? '');

        if ($nombrePaciente === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'En modo manual debes ingresar al menos el nombre del paciente.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    $previewDir = __DIR__ . '/../../uploads/tmp/informe/';
    $previewImgDir = __DIR__ . '/../../uploads/tmp/img/';

    $limiteImagenes = 24;
    $cantidadImagenesAntiguas = 0;

    if (!empty($_POST['imagenes_antiguas'])) {
        $tmpAntiguas = json_decode($_POST['imagenes_antiguas'], true);

        if (is_array($tmpAntiguas)) {
            $cantidadImagenesAntiguas = count($tmpAntiguas);
        }
    }

    $cantidadImagenesNuevas = 0;

    if (!empty($_FILES['imagenes']['name']) && is_array($_FILES['imagenes']['name'])) {
        foreach ($_FILES['imagenes']['name'] as $nombreTmp) {
            if (!empty($nombreTmp)) {
                $cantidadImagenesNuevas++;
            }
        }
    }

    $cantidadTotalImagenes = $cantidadImagenesAntiguas + $cantidadImagenesNuevas;

    if ($cantidadTotalImagenes > $limiteImagenes) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => "Se permiten como máximo {$limiteImagenes} imágenes para la vista previa."
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!is_dir($previewDir)) {
        mkdir($previewDir, 0777, true);
    }

    if (!is_dir($previewImgDir)) {
        mkdir($previewImgDir, 0777, true);
    }

    foreach (glob($previewDir . 'preview_*.pdf') as $oldFile) {
        if (filemtime($oldFile) < (time() - 60 * 60)) {
            @unlink($oldFile);
        }
    }

    foreach (glob($previewImgDir . 'previmg_*') as $oldImg) {
        if (filemtime($oldImg) < (time() - 60 * 60)) {
            @unlink($oldImg);
        }
    }

    $imagenes = [];
    $imagenesTemporalesPreview = [];

    if (!empty($_POST['imagenes_antiguas'])) {
        $imgsAntiguas = json_decode($_POST['imagenes_antiguas'], true);

        if (is_array($imgsAntiguas)) {
            foreach ($imgsAntiguas as $img) {
                if (!empty($img)) {
                    $imagenes[] = ltrim($img, '/');
                }
            }
        }
    }

    if (!empty($_FILES['imagenes']['name'][0])) {
        foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
            if (!empty($tmpName) && is_uploaded_file($tmpName)) {
                $originalName = $_FILES['imagenes']['name'][$key] ?? ('imagen_' . $key);
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $ext = $ext ? '.' . strtolower($ext) : '.jpg';

                $tmpPreviewName = 'previmg_' . $veterinario . '_' . uniqid('', true) . $ext;
                $destinoFisico = $previewImgDir . $tmpPreviewName;

                if (move_uploaded_file($tmpName, $destinoFisico)) {
                    $rutaPreviewImg = 'uploads/tmp/img/' . $tmpPreviewName;

                    $imagenes[] = $rutaPreviewImg;
                    $imagenesTemporalesPreview[] = $rutaPreviewImg;
                }
            }
        }
    }

    $html = buildInformeHtml(
        $veterinario,
        $configuracion_informe_id,
        $paciente_id,
        $fecha_examen,
        $motivo,
        $descripcion,
        $imagenes,
        $recinto,
        $medico_solicitante,
        $modo_manual ? $paciente : $manual_data_preview,
        $plantilla_informe_id
    );

    $pdf = new Dompdf();
    $options = $pdf->getOptions();
    $options->set('isRemoteEnabled', true);
    $pdf->setOptions($options);
    $pdf->loadHtml($html);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $tmpFile = $previewDir . uniqid('preview_', true) . '.pdf';
    file_put_contents($tmpFile, $pdf->output());

    $pdfUrl = '/uploads/tmp/informe/' . basename($tmpFile);

    echo json_encode([
        'status' => 'success',
        'pdfUrl' => $pdfUrl,
        'pdf' => basename($tmpFile),
        'imagenesTemporales' => $imagenesTemporalesPreview
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno al generar la vista previa: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}