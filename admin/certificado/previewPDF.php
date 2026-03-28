<?php
require_once("../config.php");
require_once("../../vendor/autoload.php");
require_once("funcionesCertificado.php");

$mysqli = conn();

header('Content-Type: text/plain; charset=utf-8');

// 1. Recibir datos del formulario
$veterinario              = intval($_POST['veterinario_id'] ?? 0);
$paciente_id              = intval($_POST['paciente_id'] ?? 0);
$fecha_examen             = $_POST['fecha_examen'] ?? date('Y-m-d');
$motivo                   = trim($_POST['motivo_examen'] ?? '');
$descripcion              = trim($_POST['contenido_html'] ?? '');
$medico_solicitante       = trim($_POST['medico_solicitante'] ?? '');
$recinto                  = trim($_POST['recinto'] ?? '');
$plantilla_informe_id     = intval($_POST['plantilla_informe_id'] ?? 0);
$configuracion_informe_id = intval($_POST['configuracion_informe_id'] ?? 0);

$modo_manual = isset($_POST['toggle_manual']) && $_POST['toggle_manual'] == '1';

$paciente = null;
if ($modo_manual) {
    $manual = function($campo) {
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

// 2. Validar campos mínimos
if (empty($veterinario) || empty($descripcion)) {
    http_response_code(400);
    echo "Faltan datos obligatorios para la vista previa.";
    exit;
}

if ($configuracion_informe_id <= 0) {
    http_response_code(400);
    echo "Debes seleccionar una plantilla de diseño.";
    exit;
}

if (!$modo_manual && empty($paciente_id)) {
    http_response_code(400);
    echo "Debe seleccionar un paciente o ingresar los datos manualmente.";
    exit;
}

// 3. Procesar imágenes SOLO para vista previa
$imagenes = [];
if (!empty($_FILES['imagenes']['name'][0])) {
    foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
        if (!empty($tmpName) && is_uploaded_file($tmpName)) {
            $data = base64_encode(file_get_contents($tmpName));
            $mime = mime_content_type($tmpName);
            $imagenes[] = "data:$mime;base64,$data";
        }
    }
}

// 4. Generar HTML usando la plantilla seleccionada
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

// 5. Crear PDF temporal
use Dompdf\Dompdf;

$pdf = new Dompdf();
$options = $pdf->getOptions();
$options->set('isRemoteEnabled', true);
$pdf->setOptions($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

$previewDir = __DIR__ . '/../../uploads/tmp_previews/';
if (!is_dir($previewDir)) {
    mkdir($previewDir, 0777, true);
}

// Limpieza opcional de previews viejos
foreach (glob($previewDir . 'preview_*.pdf') as $oldFile) {
    if (filemtime($oldFile) < (time() - 60 * 60)) {
        unlink($oldFile);
    }
}

$tmpFile = $previewDir . uniqid('preview_', true) . '.pdf';
file_put_contents($tmpFile, $pdf->output());

$pdfUrl = '/uploads/tmp_previews/' . basename($tmpFile);
echo $pdfUrl;
exit;