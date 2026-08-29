<?php
require_once("../../config.php");

$mysqli = conn();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? 'ingresar'));
$id = (int)($_POST['id'] ?? 0);
$veterinario_id = (int)$usuario_id;

if (!in_array($action, ['ingresar', 'modificar', 'eliminar'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
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
        echo json_encode(['status' => 'error', 'message' => 'Informe inválido.']);
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
        error_log('[updSubirInforme][ownership][prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo validar el informe.']);
        exit;
    }

    $stmtOwner->bind_param('ii', $id, $veterinario_id);
    $stmtOwner->execute();
    $existeInforme = $stmtOwner->get_result()->num_rows > 0;
    $stmtOwner->close();

    if (!$existeInforme) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Informe no encontrado o sin permisos.']);
        exit;
    }
}

if ($action === 'eliminar') {
    $stmt = $mysqli->prepare(
        "SELECT archivo_pdf
         FROM certificados
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('[updSubirInforme][eliminar][select_prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la eliminación.']);
        exit;
    }

    $stmt->bind_param("ii", $id, $veterinario_id);

    if (!$stmt->execute()) {
        error_log('[updSubirInforme][eliminar][select_execute] ' . $stmt->error);
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la eliminación.']);
        exit;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Informe no encontrado o sin permisos.']);
        exit;
    }

    $archivoPdf = trim((string)($row['archivo_pdf'] ?? ''));

    $stmt = $mysqli->prepare(
        "DELETE FROM certificados
         WHERE id = ? AND veterinario_id = ?"
    );

    if (!$stmt) {
        error_log('[updSubirInforme][eliminar][delete_prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la eliminación.']);
        exit;
    }

    $stmt->bind_param("ii", $id, $veterinario_id);

    if (!$stmt->execute()) {
        error_log('[updSubirInforme][eliminar][delete_execute] ' . $stmt->error);
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el informe.']);
        exit;
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Informe no encontrado o sin permisos.']);
        exit;
    }

    $stmt->close();

    if ($archivoPdf !== '') {
        $baseDir = realpath(__DIR__ . '/../../../uploads/certificados/informes_subidos');

        if ($baseDir !== false) {
            $nombreArchivo = basename($archivoPdf);
            $rutaPdf = $baseDir . DIRECTORY_SEPARATOR . $nombreArchivo;
            $rutaReal = realpath($rutaPdf);

            if (
                $rutaReal !== false &&
                is_file($rutaReal) &&
                strpos($rutaReal, $baseDir . DIRECTORY_SEPARATOR) === 0
            ) {
                if (!@unlink($rutaReal)) {
                    error_log('[updSubirInforme][eliminar][pdf] No se pudo eliminar: ' . $rutaReal);
                }
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Informe manual eliminado correctamente.'
    ]);

    exit;
}

$paciente_id        = intval($_POST['paciente_id']) ?: null;
if ($paciente_id !== null) {
    $stmtPaciente = $mysqli->prepare(
        "SELECT id FROM pacientes
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmtPaciente) {
        error_log('[updSubirInforme][paciente][prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo validar el paciente.']);
        exit;
    }

    $stmtPaciente->bind_param('ii', $paciente_id, $veterinario_id);
    $stmtPaciente->execute();
    $existePaciente = $stmtPaciente->get_result()->num_rows > 0;
    $stmtPaciente->close();

    if (!$existePaciente) {
        echo json_encode(['status' => 'error', 'message' => 'El paciente no existe o no pertenece al veterinario actual.']);
        exit;
    }
}
$medico_solicitante = trim($_POST['medico_solicitante'] ?? '');
$recinto            = trim($_POST['recinto'] ?? '');
$tipo_estudio       = intval($_POST['tipo_estudio']) ?: null;
if ($tipo_estudio !== null) {
    $stmtTipo = $mysqli->prepare(
        "SELECT id FROM tipo_examen
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmtTipo) {
        error_log('[updSubirInforme][tipo_estudio][prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo validar el tipo de estudio.']);
        exit;
    }

    $stmtTipo->bind_param('ii', $tipo_estudio, $veterinario_id);

    if (!$stmtTipo->execute()) {
        error_log('[updSubirInforme][tipo_estudio][execute] ' . $stmtTipo->error);
        $stmtTipo->close();
        echo json_encode(['status' => 'error', 'message' => 'No se pudo validar el tipo de estudio.']);
        exit;
    }

    $existeTipo = $stmtTipo->get_result()->num_rows > 0;
    $stmtTipo->close();

    if (!$existeTipo) {
        echo json_encode([
            'status' => 'error',
            'message' => 'El tipo de estudio no existe o no pertenece al veterinario actual.'
        ]);
        exit;
    }
}
$fecha_examen       = $_POST['fecha_examen'] ?? null;
$guardar_mascota    = isset($_POST['guardar_mascota']);
$manual_data        = json_encode($_POST); // respaldo general\\

if (!$paciente_id && empty($_POST['manual_nombre'])) {
  echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar o ingresar un paciente.']);
  exit;
}

function guardarPdfManualSeguro(array $archivo, int $veterinario_id): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al recibir el archivo PDF.');
    }

    $tmp = (string)($archivo['tmp_name'] ?? '');

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('El archivo PDF recibido no es válido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    if ($mime !== 'application/pdf') {
        throw new RuntimeException('El archivo debe ser un PDF válido.');
    }

    $handle = fopen($tmp, 'rb');
    $firma = $handle ? fread($handle, 5) : '';
    if ($handle) fclose($handle);

    if ($firma !== '%PDF-') {
        throw new RuntimeException('El archivo no contiene una estructura PDF válida.');
    }

    $directorio = __DIR__ . '/../../../uploads/certificados/informes_subidos';

    if (!is_dir($directorio)) {
        if (!mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio de informes.');
        }
    }

    if (!is_writable($directorio)) {
        throw new RuntimeException('El directorio de informes no tiene permisos de escritura.');
    }

    $nombre = 'informe_' . $veterinario_id . '_' . bin2hex(random_bytes(12)) . '.pdf';
    $rutaFisica = $directorio . '/' . $nombre;

    if (!move_uploaded_file($tmp, $rutaFisica)) {
        throw new RuntimeException('No se pudo guardar el archivo PDF.');
    }

    @chmod($rutaFisica, 0644);

    return [
        'fisica' => $rutaFisica,
        'relativa' => 'uploads/certificados/informes_subidos/' . $nombre
    ];
}

function eliminarPdfManualSeguro(?string $ruta): void
{
    $ruta = trim((string)$ruta);

    if ($ruta === '') {
        return;
    }

    $baseDir = realpath(__DIR__ . '/../../../uploads/certificados/informes_subidos');

    if ($baseDir === false) {
        return;
    }

    $archivo = $baseDir . DIRECTORY_SEPARATOR . basename($ruta);
    $real = realpath($archivo);

    if (
        $real !== false &&
        is_file($real) &&
        strpos($real, $baseDir . DIRECTORY_SEPARATOR) === 0
    ) {
        if (!@unlink($real)) {
            error_log('[updSubirInforme][pdf] No se pudo eliminar: ' . $real);
        }
    }
}

if (!$paciente_id && $guardar_mascota) {
  $nombre     = $_POST['manual_nombre'] ?? '';
  $especie    = $_POST['manual_especie'] ?? '';
  $raza       = $_POST['manual_raza'] ?? '';
  $sexo       = $_POST['manual_sexo'] ?? '';
  $nacimiento = $_POST['manual_fecha_nacimiento'] ?? null;

  $stmt = $mysqli->prepare("INSERT INTO pacientes (nombre, especie, raza, sexo, fecha_nacimiento, veterinario_id, creado_en) VALUES (?, ?, ?, ?, ?, ?, NOW())");
  $stmt->bind_param("sssssi", $nombre, $especie, $raza, $sexo, $nacimiento, $usuario_id);
  if ($stmt->execute()) {
    $paciente_id = $stmt->insert_id;
  } else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el paciente manual.']);
    exit;
  }
}

if ($action === 'modificar') {
    $stmt = $mysqli->prepare(
        "SELECT archivo_pdf
         FROM certificados
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('[updSubirInforme][modificar][select_prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la actualización.']);
        exit;
    }

    $stmt->bind_param('ii', $id, $veterinario_id);
    $stmt->execute();
    $prev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prev) {
        echo json_encode(['status' => 'error', 'message' => 'Informe no encontrado o sin permisos.']);
        exit;
    }

    $rutaAnterior = $prev['archivo_pdf'] ?? null;
    $rutaRelativa = $rutaAnterior;
    $nuevoPdfFisico = null;

    if (
        isset($_FILES['archivo_pdf']) &&
        ($_FILES['archivo_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        try {
            $nuevoPdf = guardarPdfManualSeguro($_FILES['archivo_pdf'], $veterinario_id);
            $rutaRelativa = $nuevoPdf['relativa'];
            $nuevoPdfFisico = $nuevoPdf['fisica'];
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    $stmt = $mysqli->prepare(
        "UPDATE certificados
         SET paciente_id = ?,
             medico_solicitante = ?,
             recinto = ?,
             tipo_estudio = ?,
             fecha_examen = ?,
             archivo_pdf = ?,
             manual_data = ?
         WHERE id = ? AND veterinario_id = ?"
    );

    if (!$stmt) {
        if ($nuevoPdfFisico !== null && is_file($nuevoPdfFisico)) {
            @unlink($nuevoPdfFisico);
        }

        error_log('[updSubirInforme][modificar][prepare] ' . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la actualización.']);
        exit;
    }

    $stmt->bind_param(
        'ississsii',
        $paciente_id,
        $medico_solicitante,
        $recinto,
        $tipo_estudio,
        $fecha_examen,
        $rutaRelativa,
        $manual_data,
        $id,
        $veterinario_id
    );

    if (!$stmt->execute()) {
        if ($nuevoPdfFisico !== null && is_file($nuevoPdfFisico)) {
            @unlink($nuevoPdfFisico);
        }

        error_log('[updSubirInforme][modificar][execute] ' . $stmt->error);
        $stmt->close();

        echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el informe.']);
        exit;
    }

    $stmt->close();

    if ($nuevoPdfFisico !== null && $rutaAnterior) {
        eliminarPdfManualSeguro($rutaAnterior);
    }

    echo json_encode(['status' => 'success', 'message' => 'Informe actualizado correctamente.']);
    exit;
}

/* INGRESAR */

if (
    !isset($_FILES['archivo_pdf']) ||
    ($_FILES['archivo_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
) {
    echo json_encode(['status' => 'error', 'message' => 'Debe subir el archivo PDF.']);
    exit;
}

try {
    $nuevoPdf = guardarPdfManualSeguro($_FILES['archivo_pdf'], $veterinario_id);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$rutaRelativa = $nuevoPdf['relativa'];
$nuevoPdfFisico = $nuevoPdf['fisica'];

$stmt = $mysqli->prepare(
    "INSERT INTO certificados
        (paciente_id, medico_solicitante, recinto, tipo_estudio, fecha_examen, archivo_pdf, manual_data, veterinario_id, created_at, tipo_ingreso, motivo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'manual', 's/i')"
);

if (!$stmt) {
    if (is_file($nuevoPdfFisico)) {
        @unlink($nuevoPdfFisico);
    }

    error_log('[updSubirInforme][ingresar][prepare] ' . $mysqli->error);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar el informe.']);
    exit;
}

$stmt->bind_param(
    'ississsi',
    $paciente_id,
    $medico_solicitante,
    $recinto,
    $tipo_estudio,
    $fecha_examen,
    $rutaRelativa,
    $manual_data,
    $veterinario_id
);

if (!$stmt->execute()) {
    if (is_file($nuevoPdfFisico)) {
        @unlink($nuevoPdfFisico);
    }

    error_log('[updSubirInforme][ingresar][execute] ' . $stmt->error);
    $stmt->close();

    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el informe.']);
    exit;
}

$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Informe subido correctamente.']);
exit;
