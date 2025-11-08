<?php
###########################################
require_once("../../config.php");
###########################################

$mysqli = conn();

// Seguridad básica
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Acceso no permitido.']);
  exit;
}

$action = $_POST['action'] ?? 'ingresar';

if ($_POST['action'] === 'eliminar') {
  $id = intval($_POST['id'] ?? 0);
  if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido.']);
    exit;
  }

  // Eliminar archivo PDF del sistema si existe
  $stmt = $mysqli->prepare("SELECT archivo_pdf FROM certificados WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();

  if ($row && !empty($row['archivo_pdf'])) {
    $rutaPdf = "../../" . $row['archivo_pdf'];
    if (file_exists($rutaPdf)) {
      unlink($rutaPdf); // Borrar el archivo
    }
  }

  // Eliminar el certificado
  $stmt = $mysqli->prepare("DELETE FROM certificados WHERE id = ?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Informe manual eliminado correctamente.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el informe.']);
  }
  exit;
}

$paciente_id        = intval($_POST['paciente_id']) ?: null;
$medico_solicitante = trim($_POST['medico_solicitante'] ?? '');
$recinto            = trim($_POST['recinto'] ?? '');
$tipo_estudio       = intval($_POST['tipo_estudio']) ?: null;
$fecha_examen       = $_POST['fecha_examen'] ?? null;
$guardar_mascota    = isset($_POST['guardar_mascota']);
$veterinario_id     = $usuario_id;
$manual_data        = json_encode($_POST); // respaldo general\\

if (!$paciente_id && empty($_POST['manual_nombre'])) {
  echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar o ingresar un paciente.']);
  exit;
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
if ($action === 'modificar' && !empty($_POST['id'])) {
  $id = intval($_POST['id']);

  // 1. Obtener ruta anterior
  $stmt = $mysqli->prepare("SELECT archivo_pdf FROM certificados WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $prev = $res->fetch_assoc();
  $rutaAnterior = $prev['archivo_pdf'] ?? null;
  $rutaRelativa = $rutaAnterior; // por defecto se mantiene

  // 2. Verificar si se subió un nuevo archivo
  if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === 0) {
    // 2.1. Eliminar archivo anterior si existe
    $rutaCompletaAnterior = "../../" . $rutaAnterior;
    if ($rutaAnterior && file_exists($rutaCompletaAnterior)) {
      unlink($rutaCompletaAnterior);
    }

    // 2.2. Subir nuevo archivo
    $pdf = $_FILES['archivo_pdf'];
    $nombreArchivo = "informe_{$veterinario_id}_" . uniqid() . ".pdf";
    $rutaFinal = "../../../uploads/certificados/informes_subidos/" . $nombreArchivo;

    if (!move_uploaded_file($pdf['tmp_name'], $rutaFinal)) {
      echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el nuevo archivo PDF.']);
      exit;
    }

    $rutaRelativa = str_replace("../../", "", $rutaFinal);
  }

  // 3. Actualizar base de datos
  $stmt = $mysqli->prepare("
    UPDATE certificados 
    SET paciente_id=?, medico_solicitante=?, recinto=?, tipo_estudio=?, fecha_examen=?, archivo_pdf=?, manual_data=? 
    WHERE id=?
  ");
  $stmt->bind_param("ississsi", $paciente_id, $medico_solicitante, $recinto, $tipo_estudio, $fecha_examen, $rutaRelativa, $manual_data, $id);
  $stmt->execute();

  echo json_encode(['status' => 'success', 'message' => 'Informe actualizado correctamente.']);
  exit;
} else {
  // ✅ Validar PDF obligatorio
  if (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Debe subir el archivo PDF.']);
    exit;
  }

  // 📥 Subir archivo
  $pdf = $_FILES['archivo_pdf'];
  $nombreArchivo = "informe_{$veterinario_id}_" . uniqid() . ".pdf";
  $rutaFinal = "../../../uploads/certificados/informes_subidos/" . $nombreArchivo;

  if (!move_uploaded_file($pdf['tmp_name'], $rutaFinal)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo PDF.']);
    exit;
  }

  $rutaRelativa = str_replace("../../", "", $rutaFinal);

  // 💾 Insertar en base de datos
  $stmt = $mysqli->prepare("
    INSERT INTO certificados 
    (paciente_id, medico_solicitante, recinto, tipo_estudio, fecha_examen, archivo_pdf, manual_data, veterinario_id, created_at, tipo_ingreso, motivo) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'manual', 's/i')
  ");

  $stmt->bind_param("ississsi", $paciente_id, $medico_solicitante, $recinto, $tipo_estudio, $fecha_examen, $rutaRelativa, $manual_data, $veterinario_id);
  $stmt->execute();

  echo json_encode(['status' => 'success', 'message' => 'Informe subido correctamente.']);
}

