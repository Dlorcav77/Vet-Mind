<?php
// admin/certificado/pdf/previewPDF.php

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../../../vendor/autoload.php");
require_once(__DIR__ . "/funcionesCertificado.php");

use Dompdf\Dompdf;

$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? 'ingresar'));

if (!in_array($action, ['ingresar', 'modificar'], true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Acción no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

credenciales('certificado', $action);

function normalizarImagenExistentePreview($ruta): ?string
{
    $ruta = trim((string)$ruta);

    if ($ruta === '') {
        return null;
    }

    $ruta = str_replace('\\', '/', $ruta);
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = ltrim($ruta, '/');

    $prefix = 'uploads/certificados/img/';

    if (strpos($ruta, $prefix) !== 0) {
        return null;
    }

    $nombre = basename($ruta);

    if ($nombre === '' || $nombre === '.' || $nombre === '..') {
        return null;
    }

    return $prefix . $nombre;
}

function normalizarImagenesExistentesPreview($imagenes): array
{
    if (!is_array($imagenes)) {
        return [];
    }

    $resultado = [];

    foreach ($imagenes as $imagen) {
        $normalizada = normalizarImagenExistentePreview($imagen);

        if ($normalizada !== null) {
            $resultado[] = $normalizada;
        }
    }

    return array_values(array_unique($resultado));
}

$imagenesTemporalesPreview = [];
$tmpFile = null;

try {
    $veterinario = (int)$usuario_id;
    $id = (int)($_POST['id'] ?? 0);
    $paciente_id = (int)($_POST['paciente_id'] ?? 0);
    $fecha_examen = $_POST['fecha_examen'] ?? date('Y-m-d');
    $motivo = trim((string)($_POST['motivo_examen'] ?? ''));
    $descripcion = trim((string)($_POST['contenido_html'] ?? ''));
    $medico_solicitante = trim((string)($_POST['medico_solicitante'] ?? ''));
    $recinto = trim((string)($_POST['recinto'] ?? ''));
    $plantilla_informe_id = (int)($_POST['plantilla_informe_id'] ?? 0);
    $configuracion_informe_id = (int)($_POST['configuracion_informe_id'] ?? 0);
    $modo_manual = isset($_POST['toggle_manual']) && (string)$_POST['toggle_manual'] === '1';

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
            return trim((string)($_POST["manual_$campo"] ?? ''));
        };

        $paciente = [
            'paciente' => $manual('paciente'),
            'especie' => $manual('especie'),
            'raza' => $manual('raza'),
            'propietario' => $manual('propietario'),
            'edad' => $manual('edad'),
            'sexo' => $manual('sexo'),
            'fecha_nacimiento' => $manual('fecha_nacimiento'),
            'n_chip' => $manual('n_chip'),
            'codigo_paciente' => $manual('codigo_paciente'),
            'N_ficha' => $manual('N_ficha'),
            'm_tratante' => $manual('m_tratante')
        ];
    }

    if ($veterinario <= 0) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesión inválida.'
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
        $nombrePaciente = trim((string)($paciente['paciente'] ?? ''));

        if ($nombrePaciente === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'En modo manual debes ingresar al menos el nombre del paciente.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    /*
     * Validar diseño de informe.
     */
    $stmtConfig = $mysqli->prepare(
        "SELECT id
         FROM configuracion_informes
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmtConfig) {
        error_log('[previewPDF][config][prepare] ' . $mysqli->error);
        throw new RuntimeException('No se pudo validar el diseño del informe.');
    }

    $stmtConfig->bind_param('ii', $configuracion_informe_id, $veterinario);

    if (!$stmtConfig->execute()) {
        error_log('[previewPDF][config][execute] ' . $stmtConfig->error);
        $stmtConfig->close();
        throw new RuntimeException('No se pudo validar el diseño del informe.');
    }

    $existeConfig = $stmtConfig->get_result()->num_rows > 0;
    $stmtConfig->close();

    if (!$existeConfig) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Diseño de informe no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /*
     * Validar plantilla/tipo de examen.
     */
    $stmtPlantilla = $mysqli->prepare(
        "SELECT id
         FROM plantilla_informe
         WHERE id = ?
           AND veterinario_id = ?
           AND deleted_at IS NULL
         LIMIT 1"
    );

    if (!$stmtPlantilla) {
        error_log('[previewPDF][plantilla][prepare] ' . $mysqli->error);
        throw new RuntimeException('No se pudo validar el tipo de examen.');
    }

    $stmtPlantilla->bind_param('ii', $plantilla_informe_id, $veterinario);

    if (!$stmtPlantilla->execute()) {
        error_log('[previewPDF][plantilla][execute] ' . $stmtPlantilla->error);
        $stmtPlantilla->close();
        throw new RuntimeException('No se pudo validar el tipo de examen.');
    }

    $existePlantilla = $stmtPlantilla->get_result()->num_rows > 0;
    $stmtPlantilla->close();

    if (!$existePlantilla) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Tipo de examen no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /*
     * Validar paciente cuando no es ingreso manual.
     */
    if (!$modo_manual) {
        $stmtPaciente = $mysqli->prepare(
            "SELECT id
             FROM pacientes
             WHERE id = ? AND veterinario_id = ?
             LIMIT 1"
        );

        if (!$stmtPaciente) {
            error_log('[previewPDF][paciente][prepare] ' . $mysqli->error);
            throw new RuntimeException('No se pudo validar el paciente.');
        }

        $stmtPaciente->bind_param('ii', $paciente_id, $veterinario);

        if (!$stmtPaciente->execute()) {
            error_log('[previewPDF][paciente][execute] ' . $stmtPaciente->error);
            $stmtPaciente->close();
            throw new RuntimeException('No se pudo validar el paciente.');
        }

        $existePaciente = $stmtPaciente->get_result()->num_rows > 0;
        $stmtPaciente->close();

        if (!$existePaciente) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Paciente no encontrado o sin permisos.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    /*
     * En modificar, validar también el certificado y obtener
     * las imágenes realmente asociadas a ese certificado.
     */
    $imagenesGuardadasCertificado = [];

    if ($action === 'modificar') {
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Certificado inválido.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmtCert = $mysqli->prepare(
            "SELECT imagenes_json
             FROM certificados
             WHERE id = ? AND veterinario_id = ?
             LIMIT 1"
        );

        if (!$stmtCert) {
            error_log('[previewPDF][certificado][prepare] ' . $mysqli->error);
            throw new RuntimeException('No se pudo validar el certificado.');
        }

        $stmtCert->bind_param('ii', $id, $veterinario);

        if (!$stmtCert->execute()) {
            error_log('[previewPDF][certificado][execute] ' . $stmtCert->error);
            $stmtCert->close();
            throw new RuntimeException('No se pudo validar el certificado.');
        }

        $rowCert = $stmtCert->get_result()->fetch_assoc();
        $stmtCert->close();

        if (!$rowCert) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Certificado no encontrado o sin permisos.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (!empty($rowCert['imagenes_json'])) {
            $tmpGuardadas = json_decode($rowCert['imagenes_json'], true);

            if (is_array($tmpGuardadas)) {
                $imagenesGuardadasCertificado = normalizarImagenesExistentesPreview($tmpGuardadas);
            }
        }
    }

    /*
     * Solo aceptar como antiguas las imágenes que realmente
     * pertenecen al certificado que se está modificando.
     */
    $imagenesAntiguasValidadas = [];

    if (
        $action === 'modificar' &&
        !empty($_POST['imagenes_antiguas'])
    ) {
        $solicitadas = json_decode((string)$_POST['imagenes_antiguas'], true);

        if (is_array($solicitadas)) {
            $solicitadas = normalizarImagenesExistentesPreview($solicitadas);

            $imagenesAntiguasValidadas = array_values(
                array_intersect(
                    $solicitadas,
                    $imagenesGuardadasCertificado
                )
            );
        }
    }

    $previewDir = __DIR__ . '/../../../uploads/tmp/informe/';
    $previewImgDir = __DIR__ . '/../../../uploads/tmp/img/';

    if (!is_dir($previewDir) && !mkdir($previewDir, 0775, true) && !is_dir($previewDir)) {
        throw new RuntimeException('No se pudo crear el directorio temporal de informes.');
    }

    if (!is_dir($previewImgDir) && !mkdir($previewImgDir, 0775, true) && !is_dir($previewImgDir)) {
        throw new RuntimeException('No se pudo crear el directorio temporal de imágenes.');
    }

    if (!is_writable($previewDir) || !is_writable($previewImgDir)) {
        throw new RuntimeException('Los directorios temporales no tienen permisos de escritura.');
    }

    /*
     * Limpiar solamente temporales antiguos del veterinario actual.
     */
    foreach (glob($previewDir . 'preview_' . $veterinario . '_*.pdf') ?: [] as $oldFile) {
        if (is_file($oldFile) && filemtime($oldFile) < (time() - 60 * 60)) {
            @unlink($oldFile);
        }
    }

    foreach (glob($previewImgDir . 'previmg_' . $veterinario . '_*') ?: [] as $oldImg) {
        if (is_file($oldImg) && filemtime($oldImg) < (time() - 60 * 60)) {
            @unlink($oldImg);
        }
    }

    /*
     * Límite total de imágenes.
     */
    $limiteImagenes = 40;
    $cantidadImagenesAntiguas = count($imagenesAntiguasValidadas);
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

    $imagenes = $imagenesAntiguasValidadas;

    /*
     * Procesar imágenes nuevas.
     */
    if (!empty($_FILES['imagenes']['name'][0])) {
        foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
            $error = (int)($_FILES['imagenes']['error'][$key] ?? UPLOAD_ERR_NO_FILE);

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Una de las imágenes no pudo ser recibida correctamente.');
            }

            if (empty($tmpName) || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Una de las imágenes recibidas no es válida.');
            }

            $size = (int)($_FILES['imagenes']['size'][$key] ?? 0);

            if ($size <= 0 || $size > 20 * 1024 * 1024) {
                throw new RuntimeException('Cada imagen puede pesar como máximo 20 MB.');
            }

            $infoImagen = @getimagesize($tmpName);

            if ($infoImagen === false) {
                throw new RuntimeException('Uno de los archivos recibidos no es una imagen válida.');
            }

            $ancho = (int)($infoImagen[0] ?? 0);
            $alto = (int)($infoImagen[1] ?? 0);
            $mime = (string)($infoImagen['mime'] ?? '');

            if ($ancho <= 0 || $alto <= 0 || ($ancho * $alto) > 25000000) {
                throw new RuntimeException('Una de las imágenes supera la resolución máxima permitida.');
            }

            $extensionesMime = [
                'image/jpeg' => '.jpg',
                'image/png' => '.png',
                'image/webp' => '.webp'
            ];

            if (!isset($extensionesMime[$mime])) {
                throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');
            }

            $tmpPreviewName = 'previmg_' . $veterinario . '_' . bin2hex(random_bytes(12)) . $extensionesMime[$mime];
            $destinoFisico = $previewImgDir . $tmpPreviewName;

            if (!move_uploaded_file($tmpName, $destinoFisico)) {
                throw new RuntimeException('No se pudo guardar una de las imágenes temporales.');
            }

            @chmod($destinoFisico, 0644);

            $rutaPreviewImg = 'uploads/tmp/img/' . $tmpPreviewName;

            $imagenes[] = $rutaPreviewImg;
            $imagenesTemporalesPreview[] = $rutaPreviewImg;
        }
    }

    /*
     * Generar HTML del informe.
     */
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

    /*
     * Generar PDF temporal.
     */
    $pdf = new Dompdf();

    $options = $pdf->getOptions();
    $options->set('isRemoteEnabled', false);
    $pdf->setOptions($options);

    $pdf->loadHtml($html);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $pdfOutput = $pdf->output();

    if ($pdfOutput === '') {
        throw new RuntimeException('No se pudo generar el PDF temporal.');
    }

    $tmpFile = $previewDir . 'preview_' . $veterinario . '_' . bin2hex(random_bytes(12)) . '.pdf';

    if (file_put_contents($tmpFile, $pdfOutput, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar el PDF temporal.');
    }

    @chmod($tmpFile, 0644);

    $pdfUrl = '/uploads/tmp/informe/' . basename($tmpFile);

    echo json_encode([
        'status' => 'success',
        'pdfUrl' => $pdfUrl,
        'pdf' => basename($tmpFile),
        'imagenesTemporales' => $imagenesTemporalesPreview
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;

} catch (Throwable $e) {
    /*
     * Limpiar únicamente archivos creados durante esta petición.
     */
    foreach ($imagenesTemporalesPreview as $rutaTemporal) {
        $nombre = basename((string)$rutaTemporal);
        $rutaFisica = __DIR__ . '/../../../uploads/tmp/img/' . $nombre;

        if (
            strpos($nombre, 'previmg_' . (int)$usuario_id . '_') === 0 &&
            is_file($rutaFisica)
        ) {
            @unlink($rutaFisica);
        }
    }

    if (
        $tmpFile !== null &&
        is_file($tmpFile) &&
        strpos(basename($tmpFile), 'preview_' . (int)$usuario_id . '_') === 0
    ) {
        @unlink($tmpFile);
    }

    error_log('[previewPDF] ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno al generar la vista previa.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}