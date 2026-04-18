<?php
require_once("../config.php");
require_once("../../vendor/autoload.php");
require_once("funcionesCertificado.php");

use Dompdf\Dompdf;

$mysqli = conn();

header('Content-Type: text/plain; charset=utf-8');

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
        ];
    }

    if ($veterinario <= 0) {
        http_response_code(400);
        echo "Falta el veterinario del formulario.";
        exit;
    }

    if ($configuracion_informe_id <= 0) {
        http_response_code(400);
        echo "Debes seleccionar una plantilla de diseño.";
        exit;
    }

    if ($plantilla_informe_id <= 0) {
        http_response_code(400);
        echo "Debes seleccionar un tipo de examen.";
        exit;
    }

    if ($descripcion === '') {
        http_response_code(400);
        echo "El contenido del informe está vacío.";
        exit;
    }

    if (!$modo_manual && $paciente_id <= 0) {
        http_response_code(400);
        echo "Debe seleccionar un paciente o ingresar los datos manualmente.";
        exit;
    }

    if ($modo_manual) {
        $nombrePaciente = trim($paciente['paciente'] ?? '');
        if ($nombrePaciente === '') {
            http_response_code(400);
            echo "En modo manual debes ingresar al menos el nombre del paciente.";
            exit;
        }
    }

    $previewDir = __DIR__ . '/../../uploads/tmp_previews/';
    $previewImgDir = $previewDir . 'img/';

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
        echo "Se permiten como máximo {$limiteImagenes} imágenes para la vista previa.";
        exit;
    }

    if (!is_dir($previewDir)) {
        mkdir($previewDir, 0777, true);
    }

    if (!is_dir($previewImgDir)) {
        mkdir($previewImgDir, 0777, true);
    }

    // Limpieza de previews viejos
    foreach (glob($previewDir . 'preview_*.pdf') as $oldFile) {
        if (filemtime($oldFile) < (time() - 60 * 60)) {
            @unlink($oldFile);
        }
    }

    // Limpieza de imágenes temporales viejas
    foreach (glob($previewImgDir . 'previmg_*') as $oldImg) {
        if (filemtime($oldImg) < (time() - 60 * 60)) {
            @unlink($oldImg);
        }
    }

    $imagenes = [];
    
    // Mantener imágenes antiguas si vienen al modificar
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

    // Nuevas imágenes para preview: copiar a temporales en disco, NO base64
    if (!empty($_FILES['imagenes']['name'][0])) {
        foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
            if (!empty($tmpName) && is_uploaded_file($tmpName)) {
                $originalName = $_FILES['imagenes']['name'][$key] ?? ('imagen_' . $key);
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $ext = $ext ? '.' . strtolower($ext) : '.jpg';

                $tmpPreviewName = 'previmg_' . $veterinario . '_' . uniqid('', true) . $ext;
                $destinoFisico = $previewImgDir . $tmpPreviewName;

                if (move_uploaded_file($tmpName, $destinoFisico)) {
                    $imagenes[] = 'uploads/tmp_previews/img/' . $tmpPreviewName;
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
        $modo_manual ? $paciente : null
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

    $pdfUrl = '/uploads/tmp_previews/' . basename($tmpFile);
    echo $pdfUrl;
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "Error interno al generar la vista previa: " . $e->getMessage();
    exit;
}