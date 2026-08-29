<?php
// admin/certificado/updCertificados.php

require_once("../config.php");
require_once("../../vendor/autoload.php");
require_once(__DIR__ . "/pdf/funcionesCertificado.php");
require_once(__DIR__ . "/../../funciones/GPT/lib/ia_store.php");
require_once(__DIR__ . "/../../funciones/GPT/lib/stt_store.php");

use Dompdf\Dompdf;

header('Content-Type: application/json; charset=utf-8');

$pdfDir = "../../uploads/certificados/informes/";
$mysqli = conn();

if (!$mysqli) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? ''));
$id = (int)($_POST['id'] ?? 0);
$veterinario = (int)($_SESSION['usuario_id'] ?? 0);

if ($veterinario <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!in_array($action, ['ingresar', 'modificar', 'eliminar'], true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Acción no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

switch ($action) {
    case 'ingresar':
        credenciales('certificado', 'ingresar');
        break;
    case 'modificar':
        credenciales('certificado', 'modificar');
        break;
    case 'eliminar':
        credenciales('certificado', 'eliminar');
        break;
}

if ($action === 'modificar' || $action === 'eliminar') {
    if ($id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Certificado inválido.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtOwner = $mysqli->prepare(
        "SELECT id
         FROM certificados
         WHERE id = ?
           AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmtOwner) {
        error_log('[updCertificados][ownership] ' . $mysqli->error);
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo validar el certificado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtOwner->bind_param('ii', $id, $veterinario);
    $stmtOwner->execute();
    $resOwner = $stmtOwner->get_result();
    $stmtOwner->close();

    if ($resOwner->num_rows === 0) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Certificado no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$transaccionActiva = false;

function rollbackCertificadoSiActivo($mysqli, &$transaccionActiva)
{
    if (!$transaccionActiva) {
        return;
    }

    $mysqli->rollback();
    $transaccionActiva = false;
}

function normalizarRutaImagenCertificado($ruta)
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

    $nombreArchivo = basename($ruta);

    if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..') {
        return null;
    }

    return $prefix . $nombreArchivo;
}

function normalizarListaImagenesCertificado($imagenes)
{
    $normalizadas = [];

    if (!is_array($imagenes)) {
        return $normalizadas;
    }

    foreach ($imagenes as $img) {
        $rutaNormalizada = normalizarRutaImagenCertificado($img);

        if ($rutaNormalizada !== null) {
            $normalizadas[] = $rutaNormalizada;
        }
    }

    return array_values(array_unique($normalizadas));
}

function comprimirImagenCertificado($rutaArchivo, $maxLado = 1600, $calidad = 82)
{
    if (!file_exists($rutaArchivo)) {
        return $rutaArchivo;
    }

    $info = @getimagesize($rutaArchivo);

    if ($info === false) {
        return $rutaArchivo;
    }

    $ancho = (int)$info[0];
    $alto = (int)$info[1];
    $tipo = $info[2];

    if ($ancho <= 0 || $alto <= 0) {
        return $rutaArchivo;
    }

    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($rutaArchivo);
            break;
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($rutaArchivo);
            break;
        case IMAGETYPE_WEBP:
            $src = @imagecreatefromwebp($rutaArchivo);
            break;
        default:
            return $rutaArchivo;
    }

    if (!$src) {
        return $rutaArchivo;
    }

    $ladoMayor = max($ancho, $alto);

    if ($ladoMayor > $maxLado) {
        $escala = $maxLado / $ladoMayor;
        $nuevoAncho = max(1, (int)round($ancho * $escala));
        $nuevoAlto = max(1, (int)round($alto * $escala));
    } else {
        $nuevoAncho = $ancho;
        $nuevoAlto = $alto;
    }

    $dst = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

    imagefilledrectangle($dst, 0, 0, $nuevoAncho, $nuevoAlto, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
    imagedestroy($src);

    $dir = dirname($rutaArchivo);
    $base = pathinfo($rutaArchivo, PATHINFO_FILENAME);
    $rutaJpg = $dir . '/' . $base . '.jpg';

    $ok = @imagejpeg($dst, $rutaJpg, $calidad);
    imagedestroy($dst);

    if (!$ok) {
        return $rutaArchivo;
    }

    if ($rutaJpg !== $rutaArchivo && file_exists($rutaArchivo)) {
        @unlink($rutaArchivo);
    }

    @chmod($rutaJpg, 0644);

    return $rutaJpg;
}

function eliminarImagenCertificadoFisica($ruta)
{
    $rutaNormalizada = normalizarRutaImagenCertificado($ruta);

    if ($rutaNormalizada === null) {
        return false;
    }

    $baseUploads = realpath("../../uploads/certificados/img");

    if ($baseUploads === false) {
        return false;
    }

    $archivo = realpath("../../" . $rutaNormalizada);

    if ($archivo === false || !file_exists($archivo)) {
        return false;
    }

    if (strpos($archivo, $baseUploads . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    return @unlink($archivo);
}

function limpiarArchivosNuevosCertificado(array $imagenesNuevas, ?string $pdfFisico = null): void
{
    foreach ($imagenesNuevas as $imagen) {
        eliminarImagenCertificadoFisica($imagen);
    }

    if ($pdfFisico !== null && is_file($pdfFisico)) {
        @unlink($pdfFisico);
    }
}

function normalizarRutaAudioTmpCertificado($ruta, $veterinario)
{
    $ruta = trim((string)$ruta);

    if ($ruta === '') {
        return null;
    }

    $ruta = str_replace('\\', '/', $ruta);
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = ltrim($ruta, '/');

    $prefix = 'uploads/tmp/audio/';

    if (strpos($ruta, $prefix) !== 0) {
        return null;
    }

    $nombreArchivo = basename($ruta);

    if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..') {
        return null;
    }

    $prefijoEsperado = 'tmp_audio_' . (int)$veterinario . '_';

    if (strpos($nombreArchivo, $prefijoEsperado) !== 0) {
        return null;
    }

    if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'wav') {
        return null;
    }

    return $prefix . $nombreArchivo;
}

function eliminarAudiosAnterioresCertificado($veterinario, $certId, $rutaNuevaFinal)
{
    $baseAudio = realpath("../../uploads/certificados/audio");

    if ($baseAudio === false) {
        return [
            'eliminados' => 0,
            'errores' => 0
        ];
    }

    $rutaNuevaReal = realpath("../../" . ltrim((string)$rutaNuevaFinal, '/'));
    $patron = "../../uploads/certificados/audio/*/*/" . (int)$veterinario . "_" . (int)$certId . "_*.wav";

    $eliminados = 0;
    $errores = 0;

    foreach (glob($patron) as $archivo) {
        $archivoReal = realpath($archivo);

        if ($archivoReal === false || !is_file($archivoReal)) {
            continue;
        }

        if ($rutaNuevaReal !== false && $archivoReal === $rutaNuevaReal) {
            continue;
        }

        if (strpos($archivoReal, $baseAudio . DIRECTORY_SEPARATOR) !== 0) {
            $errores++;
            continue;
        }

        if (@unlink($archivoReal)) {
            $eliminados++;
        } else {
            $errores++;
        }
    }

    return [
        'eliminados' => $eliminados,
        'errores' => $errores
    ];
}

function eliminarAudiosCertificadoFisicos($veterinario, $certId)
{
    $baseAudio = realpath("../../uploads/certificados/audio");

    if ($baseAudio === false) {
        return [
            'eliminados' => 0,
            'errores' => 0
        ];
    }

    $patron = "../../uploads/certificados/audio/*/*/" . (int)$veterinario . "_" . (int)$certId . "_*.wav";

    $eliminados = 0;
    $errores = 0;

    foreach (glob($patron) as $archivo) {
        $archivoReal = realpath($archivo);

        if ($archivoReal === false || !is_file($archivoReal)) {
            continue;
        }

        if (strpos($archivoReal, $baseAudio . DIRECTORY_SEPARATOR) !== 0) {
            $errores++;
            continue;
        }

        if (@unlink($archivoReal)) {
            $eliminados++;
        } else {
            $errores++;
        }
    }

    return [
        'eliminados' => $eliminados,
        'errores' => $errores
    ];
}

function moverAudioTemporalCertificado($rutaAudioTmp, $veterinario, $certId)
{
    $rutaNormalizada = normalizarRutaAudioTmpCertificado($rutaAudioTmp, $veterinario);

    if ($rutaNormalizada === null) {
        return [
            'status' => 'error',
            'message' => 'Audio temporal inválido o no permitido.',
            'origen' => (string)$rutaAudioTmp,
            'destino' => null
        ];
    }

    $baseTmp = realpath("../../uploads/tmp/audio");

    if ($baseTmp === false) {
        return [
            'status' => 'error',
            'message' => 'No existe el directorio temporal de audio.',
            'origen' => $rutaNormalizada,
            'destino' => null
        ];
    }

    $origenReal = realpath("../../" . $rutaNormalizada);

    if ($origenReal === false || !is_file($origenReal)) {
        return [
            'status' => 'error',
            'message' => 'El audio temporal no existe.',
            'origen' => $rutaNormalizada,
            'destino' => null
        ];
    }

    if (strpos($origenReal, $baseTmp . DIRECTORY_SEPARATOR) !== 0) {
        return [
            'status' => 'error',
            'message' => 'El audio temporal está fuera del directorio permitido.',
            'origen' => $rutaNormalizada,
            'destino' => null
        ];
    }

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $year = $now->format('Y');
    $month = $now->format('m');
    $day = $now->format('d');
    $hmsms = $now->format('Hisv');

    $destinoDirRel = "uploads/certificados/audio/{$year}/{$month}";
    $destinoDir = "../../" . $destinoDirRel;

    if (!is_dir($destinoDir)) {
        if (!mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
            return [
                'status' => 'error',
                'message' => 'No se pudo crear el directorio final de audio.',
                'origen' => $rutaNormalizada,
                'destino' => null
            ];
        }
    }

    $nombreFinal = (int)$veterinario . "_" . (int)$certId . "_" . $day . "_" . $hmsms . ".wav";
    $destinoRel = $destinoDirRel . "/" . $nombreFinal;
    $destinoFisico = $destinoDir . "/" . $nombreFinal;

    $movido = @rename($origenReal, $destinoFisico);

    if (!$movido) {
        $copiado = @copy($origenReal, $destinoFisico);

        if ($copiado) {
            @unlink($origenReal);
            $movido = true;
        }
    }

    if (!$movido) {
        return [
            'status' => 'error',
            'message' => 'No se pudo mover el audio temporal al directorio final.',
            'origen' => $rutaNormalizada,
            'destino' => $destinoRel
        ];
    }

    @chmod($destinoFisico, 0644);

    $limpiezaPrevios = eliminarAudiosAnterioresCertificado($veterinario, $certId, $destinoRel);

    return [
        'status' => 'success',
        'message' => 'Audio asociado al certificado correctamente.',
        'origen' => $rutaNormalizada,
        'destino' => $destinoRel,
        'filename' => $nombreFinal,
        'anteriores_eliminados' => $limpiezaPrevios['eliminados'],
        'anteriores_errores' => $limpiezaPrevios['errores']
    ];
}


if ($action === 'eliminar') {
    /*
     * Primero obtenemos las rutas de los archivos,
     * pero todavía NO eliminamos nada físicamente.
     */
    $sel = $mysqli->prepare(
        "SELECT archivo_pdf, imagenes_json
         FROM certificados
         WHERE id = ?
           AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$sel) {
        error_log(
            '[updCertificados][eliminar][select_prepare] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando eliminación.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $sel->bind_param(
        'ii',
        $id,
        $veterinario
    );

    if (!$sel->execute()) {
        error_log(
            '[updCertificados][eliminar][select_execute] ' .
            $sel->error
        );

        $sel->close();

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo preparar la eliminación.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $cert = $sel
        ->get_result()
        ->fetch_assoc();

    $sel->close();

    if (!$cert) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Certificado no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /*
     * Primero eliminamos el registro de BD.
     * Si esto falla, conservamos todos los archivos.
     */
    $del = $mysqli->prepare(
        "DELETE FROM certificados
         WHERE id = ?
           AND veterinario_id = ?"
    );

    if (!$del) {
        error_log(
            '[updCertificados][eliminar][delete_prepare] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando borrado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $del->bind_param(
        'ii',
        $id,
        $veterinario
    );

    if (!$del->execute()) {
        error_log(
            '[updCertificados][eliminar][delete_execute] ' .
            $del->error
        );

        $del->close();

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo eliminar el certificado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($del->affected_rows === 0) {
        $del->close();

        echo json_encode([
            'status' => 'error',
            'message' => 'Certificado no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $del->close();

    /*
     * La BD ya confirmó la eliminación.
     * Recién ahora limpiamos los archivos físicos.
     */

    if (!empty($cert['archivo_pdf'])) {
        $basePdf = realpath($pdfDir);

        $pdfPath = realpath(
            "../../" . ltrim((string)$cert['archivo_pdf'], '/')
        );

        if (
            $basePdf !== false &&
            $pdfPath !== false &&
            is_file($pdfPath) &&
            strpos(
                $pdfPath,
                $basePdf . DIRECTORY_SEPARATOR
            ) === 0
        ) {
            if (!@unlink($pdfPath)) {
                error_log(
                    '[updCertificados][eliminar][pdf] No se pudo eliminar: ' .
                    $pdfPath
                );
            }
        }
    }

    if (!empty($cert['imagenes_json'])) {
        $imagenesEliminar = json_decode(
            $cert['imagenes_json'],
            true
        );

        if (is_array($imagenesEliminar)) {
            foreach ($imagenesEliminar as $img) {
                eliminarImagenCertificadoFisica($img);
            }
        }
    }

    $resultadoAudios = eliminarAudiosCertificadoFisicos(
        $veterinario,
        $id
    );

    if (($resultadoAudios['errores'] ?? 0) > 0) {
        error_log(
            '[updCertificados][eliminar][audio] ' .
            'No se pudieron eliminar todos los audios del certificado ID ' .
            $id
        );
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Certificado eliminado correctamente.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

$paciente_id             = intval($_POST['paciente_id'] ?? 0);
$fecha_examen            = trim((string)($_POST['fecha_examen'] ?? date('Y-m-d')));
$descripcion             = trim($_POST['contenido_html'] ?? '');
$medico_solicitante      = trim($_POST['medico_solicitante'] ?? '');
$motivo                  = trim($_POST['motivo_examen'] ?? '');
$plantilla_informe_id    = intval($_POST['plantilla_informe_id'] ?? 0);
$configuracion_informe_id = intval($_POST['configuracion_informe_id'] ?? 0);
$modo_manual             = isset($_POST['toggle_manual']) && $_POST['toggle_manual'] == '1';
$borrador_id = (int)($_POST['borrador_id'] ?? 0);

$borrador_scope_key = (
    $action === 'modificar' && $id > 0
)
    ? 'modificar:' . $id
    : 'nuevo';

$audio_tmp = trim((string)($_POST['audio_tmp'] ?? ''));

$fechaExamenDt = DateTime::createFromFormat(
    'Y-m-d',
    $fecha_examen
);

if (
    !$fechaExamenDt ||
    $fechaExamenDt->format('Y-m-d') !== $fecha_examen
) {
    echo json_encode([
        'status' => 'error',
        'message' => 'La fecha del examen no es válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

validar_length(
    "Médico solicitante",
    $medico_solicitante,
    255,
    true
);

validar_length(
    "Motivo",
    $motivo,
    255,
    true
);


if (!$modo_manual) {
    if ($paciente_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Debes seleccionar un paciente válido.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtPaciente = $mysqli->prepare(
        "SELECT id
         FROM pacientes
         WHERE id = ?
           AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmtPaciente) {
        error_log('[updCertificados][paciente] ' . $mysqli->error);

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo validar el paciente.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtPaciente->bind_param('ii', $paciente_id, $veterinario);
    $stmtPaciente->execute();
    $resPaciente = $stmtPaciente->get_result();
    $stmtPaciente->close();

    if ($resPaciente->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'El paciente no existe o no pertenece al veterinario actual.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($plantilla_informe_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Debes seleccionar un tipo de examen válido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmtPlantilla = $mysqli->prepare(
    "SELECT id
     FROM plantilla_informe
     WHERE id = ?
       AND veterinario_id = ?
       AND deleted_at IS NULL
     LIMIT 1"
);

if (!$stmtPlantilla) {
    error_log('[updCertificados][plantilla] ' . $mysqli->error);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo validar el tipo de examen.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmtPlantilla->bind_param('ii', $plantilla_informe_id, $veterinario);
$stmtPlantilla->execute();
$resPlantilla = $stmtPlantilla->get_result();
$stmtPlantilla->close();

if ($resPlantilla->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'El tipo de examen no existe o no pertenece al veterinario actual.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($configuracion_informe_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Debes seleccionar una plantilla de diseño válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmtConfiguracion = $mysqli->prepare(
    "SELECT id
     FROM configuracion_informes
     WHERE id = ?
       AND veterinario_id = ?
     LIMIT 1"
);

if (!$stmtConfiguracion) {
    error_log('[updCertificados][configuracion] ' . $mysqli->error);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo validar la plantilla de diseño.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmtConfiguracion->bind_param('ii', $configuracion_informe_id, $veterinario);
$stmtConfiguracion->execute();
$resConfiguracion = $stmtConfiguracion->get_result();
$stmtConfiguracion->close();

if ($resConfiguracion->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'La plantilla de diseño no existe o no pertenece al veterinario actual.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$es_destacado = (
    isset($_POST['es_destacado']) &&
    (string)$_POST['es_destacado'] === '1'
) ? 1 : 0;

$destacado_titulo = trim((string)($_POST['destacado_titulo'] ?? ''));

if ($es_destacado !== 1 || $destacado_titulo === '') {
    $destacado_titulo = null;
}

if ($destacado_titulo !== null) {
    validar_length("Título destacado", $destacado_titulo, 255, true);
}

$recinto                 = trim($_POST['recinto'] ?? '');

if ($recinto === '' && $configuracion_informe_id > 0) {
    $stmtRecintoDef = $mysqli->prepare("
        SELECT recinto_default
        FROM configuracion_informes
        WHERE id = ? AND veterinario_id = ?
        LIMIT 1
    ");

    if ($stmtRecintoDef) {
        $stmtRecintoDef->bind_param("ii", $configuracion_informe_id, $veterinario);
        $stmtRecintoDef->execute();
        $rowRecintoDef = $stmtRecintoDef->get_result()->fetch_assoc();

        if (is_array($rowRecintoDef) && trim((string)($rowRecintoDef['recinto_default'] ?? '')) !== '') {
            $recinto = trim((string)$rowRecintoDef['recinto_default']);
        }
    }
}

validar_length("Recinto", $recinto, 255, true);

if ($descripcion === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan datos obligatorios.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$guardarMascota = isset($_POST['guardar_mascota']) && $_POST['guardar_mascota'] == '1';
$tutorExistenteId = intval($_POST['tutor_existente_id'] ?? 0);

$manual = [];
foreach ($_POST as $k => $v) {
    if (strpos($k, 'manual_') === 0) {
        $manual[substr($k, 7)] = trim((string)$v);
    }
}

$manual_extra_data = [];

foreach ($manual as $campoManual => $valorManual) {
    $valorManual = trim((string)$valorManual);

    if ($valorManual === '') {
        continue;
    }

    $manual_extra_data[$campoManual] = $valorManual;
}

if ($modo_manual) {
    $manualPaciente = trim((string)($manual['paciente'] ?? ''));
    $manualPropietario = trim((string)($manual['propietario'] ?? ''));

    if ($manualPaciente === '' || $manualPropietario === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'En ingreso manual, Paciente y Propietario son obligatorios.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    validar_length("Propietario", $manualPropietario, 150);
    validar_length("Paciente", $manualPaciente, 100);
    validar_length("Código paciente", $manual['codigo_paciente'] ?? '', 30, true);
    validar_length("Chip", $manual['n_chip'] ?? '', 30, true);
    validar_length("Especie", $manual['especie'] ?? '', 20, true);
    validar_length("Raza", $manual['raza'] ?? '', 100, true);
    validar_length("Sexo", $manual['sexo'] ?? '', 20, true);
}

$prev_manual_data = null;
if ($action === 'modificar' && $id > 0) {
    $q = $mysqli->prepare("SELECT manual_data FROM certificados WHERE id = ? AND veterinario_id = ?");
    if ($q) {
        $q->bind_param("ii", $id, $veterinario);
        $q->execute();
        $r = $q->get_result();
        if ($rowPrev = $r->fetch_assoc()) {
            $prev_manual_data = $rowPrev['manual_data'];
        }
    }
}

$manual_data = !empty($manual_extra_data)
    ? json_encode($manual_extra_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : null;

if ($modo_manual && $guardarMascota && !empty($manual)) {
    if (!$mysqli->begin_transaction()) {
        error_log(
            '[updCertificados][transaccion][begin] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo iniciar la transacción de guardado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $transaccionActiva = true;

    $tutorNombre = trim((string)($manual['propietario'] ?? ''));

    $tutorId = 0;

    /*
    * Si el médico seleccionó explícitamente un tutor existente,
    * comprobamos en servidor que realmente exista y que pertenezca
    * al veterinario actual.
    *
    * Nunca confiamos solamente en el ID recibido desde JavaScript.
    */
    if ($tutorExistenteId > 0) {
        $stmtTutorExistente = $mysqli->prepare("
            SELECT
                id,
                nombre_completo
            FROM tutores
            WHERE
                id = ?
                AND veterinario_id = ?
            LIMIT 1
        ");

        if (!$stmtTutorExistente) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            error_log(
                '[updCertificados][tutor_existente][prepare] ' .
                $mysqli->error
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Error preparando validación del tutor seleccionado.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmtTutorExistente->bind_param(
            "ii",
            $tutorExistenteId,
            $veterinario
        );

        if (!$stmtTutorExistente->execute()) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            error_log(
                '[updCertificados][tutor_existente][execute] ' .
                $stmtTutorExistente->error
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo validar el tutor seleccionado.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $resTutorExistente =
            $stmtTutorExistente->get_result();

        $tutorExistente =
            $resTutorExistente->fetch_assoc();

        if (!$tutorExistente) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'El tutor seleccionado no existe o no pertenece al veterinario actual.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $tutorId = (int)$tutorExistente['id'];

    } else {
        /*
        * No se seleccionó ningún tutor existente.
        *
        * El médico continuó escribiendo el propietario manualmente,
        * por lo tanto se considera explícitamente un tutor nuevo.
        */
        $stmtTutorNuevo = $mysqli->prepare("
            INSERT INTO tutores
                (
                    nombre_completo,
                    veterinario_id
                )
            VALUES
                (?, ?)
        ");

        if (!$stmtTutorNuevo) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            error_log(
                '[updCertificados][tutor_nuevo][prepare] ' .
                $mysqli->error
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Error preparando creación de tutor.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmtTutorNuevo->bind_param(
            "si",
            $tutorNombre,
            $veterinario
        );

        if (!$stmtTutorNuevo->execute()) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            error_log(
                '[updCertificados][tutor_nuevo][execute] ' .
                $stmtTutorNuevo->error
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo crear el tutor.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $tutorId = (int)$stmtTutorNuevo->insert_id;

        if ($tutorId <= 0) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se obtuvo el ID del tutor creado.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    $nombreMascota = trim((string)($manual['paciente'] ?? ''));
    $codigoPaciente = trim((string)($manual['codigo_paciente'] ?? ''));
    $especie = trim((string)($manual['especie'] ?? ''));
    $raza = trim((string)($manual['raza'] ?? ''));
    $sexo = trim((string)($manual['sexo'] ?? ''));
    $n_chip = trim((string)($manual['n_chip'] ?? ''));

    $fecha_nacimiento_raw = trim(
        (string)($manual['fecha_nacimiento'] ?? '')
    );

    $fecha_nacimiento = null;

    if ($fecha_nacimiento_raw !== '') {
        $dt = DateTime::createFromFormat(
            'Y-m-d',
            $fecha_nacimiento_raw
        );

        if (
            $dt &&
            $dt->format('Y-m-d') === $fecha_nacimiento_raw
        ) {
            $fecha_nacimiento = $fecha_nacimiento_raw;
        }
    }

    $stmt = $mysqli->prepare("
        INSERT INTO pacientes
            (
                nombre,
                codigo_paciente,
                especie,
                raza,
                sexo,
                fecha_nacimiento,
                tutor_id,
                veterinario_id,
                n_chip
            )
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        error_log(
            '[updCertificados][paciente_nuevo][prepare] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando creación de paciente.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param(
        "ssssssiis",
        $nombreMascota,
        $codigoPaciente,
        $especie,
        $raza,
        $sexo,
        $fecha_nacimiento,
        $tutorId,
        $veterinario,
        $n_chip
    );

    if (!$stmt->execute()) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        error_log(
            '[updCertificados][paciente_nuevo][execute] ' .
            $stmt->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo crear el paciente.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $paciente_id = (int)$stmt->insert_id;

    if ($paciente_id <= 0) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'No se obtuvo el ID del paciente creado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /*
     * El paciente quedó guardado en las tablas reales.
     * El certificado queda asociado mediante paciente_id,
     * por lo que no necesitamos duplicar estos datos en manual_data.
     */
    $manual_data = null;

} elseif ($modo_manual && !empty($manual)) {
    /*
     * Ingreso manual sin "Guardar":
     * no se crean tutor ni paciente.
     */
    $paciente_id = null;

    $manual_data = json_encode(
        $manual,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

$imagenesNuevas = [];
$imagenes = [];

if (
    $action === 'modificar' &&
    $id > 0 &&
    !empty($_POST['imagenes_antiguas'])
) {
    $imgsSolicitadas = json_decode(
        (string)$_POST['imagenes_antiguas'],
        true
    );

    if (is_array($imgsSolicitadas)) {
        $imgsSolicitadas = normalizarListaImagenesCertificado(
            $imgsSolicitadas
        );

        $stmtImgs = $mysqli->prepare(
            "SELECT imagenes_json
             FROM certificados
             WHERE id = ?
               AND veterinario_id = ?
             LIMIT 1"
        );

        if (!$stmtImgs) {
            error_log(
                '[updCertificados][imagenes_antiguas] ' .
                $mysqli->error
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudieron validar las imágenes existentes.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmtImgs->bind_param(
            'ii',
            $id,
            $veterinario
        );

        $stmtImgs->execute();

        $rowImgs = $stmtImgs
            ->get_result()
            ->fetch_assoc();

        $stmtImgs->close();

        $imgsGuardadas = [];

        if (
            is_array($rowImgs) &&
            !empty($rowImgs['imagenes_json'])
        ) {
            $imgsGuardadasRaw = json_decode(
                $rowImgs['imagenes_json'],
                true
            );

            if (is_array($imgsGuardadasRaw)) {
                $imgsGuardadas = normalizarListaImagenesCertificado(
                    $imgsGuardadasRaw
                );
            }
        }

        $imagenes = array_values(
            array_intersect(
                $imgsSolicitadas,
                $imgsGuardadas
            )
        );
    }
}

if (
    isset($_FILES['imagenes']) &&
    isset($_FILES['imagenes']['name']) &&
    is_array($_FILES['imagenes']['name']) &&
    !empty($_FILES['imagenes']['name'][0])
) {
    $imgDir = "../../uploads/certificados/img/";

    if (!is_dir($imgDir)) {
        if (!mkdir($imgDir, 0775, true) && !is_dir($imgDir)) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            error_log(
                '[updCertificados][imagenes] No se pudo crear el directorio: ' .
                $imgDir
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo preparar el directorio de imágenes.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    $maxBytesImagen = 20 * 1024 * 1024; // 20 MB
    $maxPixelesImagen = 25000000;       // 25 megapíxeles

    foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
        $uploadError = (int)($_FILES['imagenes']['error'][$key] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Una de las imágenes no pudo ser subida correctamente.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (
            empty($tmpName) ||
            !is_uploaded_file($tmpName)
        ) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Se recibió una imagen inválida.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $tamanoArchivo = (int)($_FILES['imagenes']['size'][$key] ?? 0);

        if (
            $tamanoArchivo <= 0 ||
            $tamanoArchivo > $maxBytesImagen
        ) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Cada imagen debe pesar como máximo 20 MB.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $infoImagen = @getimagesize($tmpName);

        if ($infoImagen === false) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Uno de los archivos enviados no es una imagen válida.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $anchoImagen = (int)($infoImagen[0] ?? 0);
        $altoImagen = (int)($infoImagen[1] ?? 0);
        $tipoImagen = (int)($infoImagen[2] ?? 0);

        if (
            $anchoImagen <= 0 ||
            $altoImagen <= 0 ||
            ($anchoImagen * $altoImagen) > $maxPixelesImagen
        ) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'La imagen tiene dimensiones demasiado grandes.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $extensionesPermitidas = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (!isset($extensionesPermitidas[$tipoImagen])) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'Solo se permiten imágenes JPG, PNG o WebP.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $extension = $extensionesPermitidas[$tipoImagen];

        $nombreArchivo =
            "img_{$veterinario}_" .
            bin2hex(random_bytes(12)) .
            "." .
            $extension;

        $rutaDestino = $imgDir . $nombreArchivo;

        if (!move_uploaded_file($tmpName, $rutaDestino)) {
            rollbackCertificadoSiActivo(
                $mysqli,
                $transaccionActiva
            );

            error_log(
                '[updCertificados][imagenes] No se pudo mover imagen a: ' .
                $rutaDestino
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo guardar una de las imágenes.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        @chmod($rutaDestino, 0644);

        $rutaFinal = comprimirImagenCertificado(
            $rutaDestino
        );

        $rutaImagenNueva = normalizarRutaImagenCertificado(
            "uploads/certificados/img/" .
            basename($rutaFinal)
        );

        if ($rutaImagenNueva !== null) {
            $imagenes[] = $rutaImagenNueva;
            $imagenesNuevas[] = $rutaImagenNueva;
        }
    }
}

$imagenes = array_values(array_unique($imagenes));
$imagenesJson = json_encode($imagenes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (!is_dir($pdfDir)) {
    if (!mkdir($pdfDir, 0775, true) && !is_dir($pdfDir)) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        error_log(
            '[updCertificados][pdf] No se pudo crear el directorio: ' .
            $pdfDir
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo preparar el directorio del informe.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!is_writable($pdfDir)) {
    rollbackCertificadoSiActivo(
        $mysqli,
        $transaccionActiva
    );

    error_log(
        '[updCertificados][pdf] Directorio sin permisos de escritura: ' .
        $pdfDir
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo preparar el archivo del informe.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdfFilename =
    "cert_{$veterinario}_" .
    bin2hex(random_bytes(12)) .
    ".pdf";

$pdfPathFisico = $pdfDir . $pdfFilename;

try {
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
        $manual_data,
        $plantilla_informe_id
    );

    $pdf = new Dompdf();

    $options = $pdf->getOptions();
    $options->set('isRemoteEnabled', false);
    $pdf->setOptions($options);

    $pdf->loadHtml($html);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $pdfContenido = $pdf->output();

    if ($pdfContenido === '') {
        throw new RuntimeException(
            'Dompdf generó un contenido PDF vacío.'
        );
    }

    $bytesEscritos = file_put_contents(
        $pdfPathFisico,
        $pdfContenido,
        LOCK_EX
    );

    if ($bytesEscritos === false || $bytesEscritos <= 0) {
        throw new RuntimeException(
            'No se pudo escribir el archivo PDF.'
        );
    }

    @chmod($pdfPathFisico, 0644);

} catch (Throwable $e) {
    rollbackCertificadoSiActivo($mysqli, $transaccionActiva);

    limpiarArchivosNuevosCertificado($imagenesNuevas, $pdfPathFisico);

    error_log('[updCertificados][pdf] ' . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo generar el informe PDF.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rutaPdf =
    "uploads/certificados/informes/" .
    $pdfFilename;

$pdfAnteriorEliminar = null;
$imagenesEliminarDespues = [];

if ($action === 'ingresar') {
    $stmt = $mysqli->prepare("INSERT INTO certificados 
        (
            veterinario_id,
            paciente_id,
            fecha_examen,
            contenido_html,
            archivo_pdf,
            imagenes_json,
            medico_solicitante,
            recinto,
            tipo_estudio,
            configuracion_informe_id,
            motivo,
            manual_data,
            es_destacado,
            destacado_titulo,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    if (!$stmt) {
        rollbackCertificadoSiActivo($mysqli, $transaccionActiva);
        limpiarArchivosNuevosCertificado($imagenesNuevas, $pdfPathFisico);

        error_log(
            '[updCertificados][certificado][insert_prepare] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando inserción.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param(
        "iisssssssissis",
        $veterinario,
        $paciente_id,
        $fecha_examen,
        $descripcion,
        $rutaPdf,
        $imagenesJson,
        $medico_solicitante,
        $recinto,
        $plantilla_informe_id,
        $configuracion_informe_id,
        $motivo,
        $manual_data,
        $es_destacado,
        $destacado_titulo
    );
} elseif ($action === 'modificar' && $id > 0) {
    



    $stmtPrev = $mysqli->prepare(
        "SELECT archivo_pdf, imagenes_json
        FROM certificados
        WHERE id = ?
        AND veterinario_id = ?
        LIMIT 1"
    );

    if (!$stmtPrev) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        error_log(
            '[updCertificados][modificar][prev_prepare] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo preparar la actualización del certificado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtPrev->bind_param(
        "ii",
        $id,
        $veterinario
    );

    if (!$stmtPrev->execute()) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        error_log(
            '[updCertificados][modificar][prev_execute] ' .
            $stmtPrev->error
        );

        $stmtPrev->close();

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo preparar la actualización del certificado.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $prev = $stmtPrev
        ->get_result()
        ->fetch_assoc();

    $stmtPrev->close();

    if (!$prev) {
        rollbackCertificadoSiActivo(
            $mysqli,
            $transaccionActiva
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Certificado no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /*
    * Importante:
    * todavía NO eliminamos ningún archivo.
    * Solo guardamos qué archivos habrá que eliminar
    * después de que el UPDATE haya sido exitoso.
    */
    if (!empty($prev['archivo_pdf'])) {
        $pdfAnteriorEliminar = trim(
            (string)$prev['archivo_pdf']
        );
    }

    $imagenesPrevias = [];

    if (!empty($prev['imagenes_json'])) {
        $imagenesPreviasDecode = json_decode(
            $prev['imagenes_json'],
            true
        );

        if (is_array($imagenesPreviasDecode)) {
            $imagenesPrevias = normalizarListaImagenesCertificado(
                $imagenesPreviasDecode
            );
        }
    }

    $imagenesActuales = normalizarListaImagenesCertificado(
        $imagenes
    );

    $imagenesEliminarDespues = array_values(
        array_diff(
            $imagenesPrevias,
            $imagenesActuales
        )
    );



    $tienePaciente = !empty($paciente_id);
    $llegaManualNuevo = !empty($manual_data);

    if (!$tienePaciente && !$llegaManualNuevo && $prev_manual_data !== null) {
        $manual_data = $prev_manual_data;
    }

    $stmt = $mysqli->prepare("UPDATE certificados
        SET paciente_id = ?,
            fecha_examen = ?,
            contenido_html = ?,
            archivo_pdf = ?,
            imagenes_json = ?,
            medico_solicitante = ?,
            recinto = ?,
            tipo_estudio = ?,
            configuracion_informe_id = ?,
            motivo = ?,
            manual_data = ?,
            es_destacado = ?,
            destacado_titulo = ?,
            updated_at = NOW()
        WHERE id = ?
            AND veterinario_id = ?");

    if (!$stmt) {
        rollbackCertificadoSiActivo($mysqli, $transaccionActiva);
        limpiarArchivosNuevosCertificado($imagenesNuevas, $pdfPathFisico);

        error_log(
            '[updCertificados][certificado][update_prepare] ' .
            $mysqli->error
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando actualización.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param(
        "isssssssissisii",
        $paciente_id,
        $fecha_examen,
        $descripcion,
        $rutaPdf,
        $imagenesJson,
        $medico_solicitante,
        $recinto,
        $plantilla_informe_id,
        $configuracion_informe_id,
        $motivo,
        $manual_data,
        $es_destacado,
        $destacado_titulo,
        $id,
        $veterinario
    );
} else {
    rollbackCertificadoSiActivo(
        $mysqli,
        $transaccionActiva
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Acción no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($stmt->execute()) {
    if ($transaccionActiva) {
        if (!$mysqli->commit()) {
            rollbackCertificadoSiActivo($mysqli, $transaccionActiva);
            limpiarArchivosNuevosCertificado($imagenesNuevas, $pdfPathFisico);

            error_log(
                '[updCertificados][transaccion][commit] ' .
                $mysqli->error
            );

            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo confirmar la transacción de guardado.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $transaccionActiva = false;
    }

    if ($action === 'modificar') {
        /*
        * El UPDATE ya fue exitoso y, si había transacción,
        * también quedó confirmada.
        * Recién ahora podemos eliminar archivos anteriores.
        */

        if ($pdfAnteriorEliminar !== null) {
            $basePdf = realpath($pdfDir);

            $pdfAnteriorReal = realpath(
                "../../" . ltrim($pdfAnteriorEliminar, '/')
            );

            if (
                $basePdf !== false &&
                $pdfAnteriorReal !== false &&
                is_file($pdfAnteriorReal) &&
                strpos(
                    $pdfAnteriorReal,
                    $basePdf . DIRECTORY_SEPARATOR
                ) === 0
            ) {
                @unlink($pdfAnteriorReal);
            }
        }

        foreach ($imagenesEliminarDespues as $imagenEliminar) {
            eliminarImagenCertificadoFisica(
                $imagenEliminar
            );
        }
    }

    $certId = 0;

    if ($action === 'ingresar') {
        $certId = (int)$stmt->insert_id;
    } elseif ($action === 'modificar' && $id > 0) {
        $certId = (int)$id;
    }

    $audioResultado = null;

    if ($certId > 0) {
        $rid_ia = trim((string)($_POST['rid_ia'] ?? ''));
        if ($rid_ia !== '') {
            ia_link_certificado($mysqli, $rid_ia, $certId);
        }

        $rid_revision = trim((string)($_POST['rid_revision'] ?? ''));
        if ($rid_revision !== '') {
            ia_link_certificado($mysqli, $rid_revision, $certId);
        }
        if ($audio_tmp !== '') {
            stt_link_certificado($mysqli, $audio_tmp, $certId);
        }

        if ($borrador_id > 0) {
            $stmtBorrador = $mysqli->prepare("
                UPDATE certificados_borradores
                SET estado = 'finalizado',
                    certificado_id = ?,
                    updated_at = NOW()
                WHERE id = ?
                    AND veterinario_id = ?
                    AND scope_key = ?
                    AND estado = 'activo'
            ");

            if ($stmtBorrador) {
                $stmtBorrador->bind_param(
                    "iiis",
                    $certId,
                    $borrador_id,
                    $veterinario,
                    $borrador_scope_key
                );

                $stmtBorrador->execute();
                $stmtBorrador->close();
            }
        } else {
            $stmtBorrador = $mysqli->prepare("
                UPDATE certificados_borradores
                SET estado = 'finalizado',
                    certificado_id = ?,
                    updated_at = NOW()
                WHERE veterinario_id = ?
                    AND scope_key = ?
                    AND estado = 'activo'
            ");

            if ($stmtBorrador) {
                $stmtBorrador->bind_param(
                    "iis",
                    $certId,
                    $veterinario,
                    $borrador_scope_key
                );

                $stmtBorrador->execute();
                $stmtBorrador->close();
            }
        }

        if ($audio_tmp !== '') {
            $audioResultado = moverAudioTemporalCertificado($audio_tmp, $veterinario, $certId);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Certificado guardado correctamente.',
        'rutaPdf' => $rutaPdf,
        'id' => $certId,
        'audio' => $audioResultado
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    rollbackCertificadoSiActivo($mysqli, $transaccionActiva);
    limpiarArchivosNuevosCertificado($imagenesNuevas, $pdfPathFisico);

    error_log(
        '[updCertificados][certificado][execute] ' .
        $stmt->error
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Error al guardar el certificado.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;