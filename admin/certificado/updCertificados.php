<?php
require_once("../config.php");
require_once("../../vendor/autoload.php");
require_once("funcionesCertificado.php");
$pdfDir = "../../uploads/certificados/informes/";

use Dompdf\Dompdf;
$mysqli = conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    $usuario_id = $_SESSION['usuario_id'] ?? 0;

    // Validar que el certificado es del veterinario actual (extra seguro)
    $sel = $mysqli->prepare("SELECT archivo_pdf, imagenes_json FROM certificados WHERE id = ? AND veterinario_id = ?");
    $sel->bind_param("ii", $id, $usuario_id);
    $sel->execute();
    $res = $sel->get_result();
    $cert = $res->fetch_assoc();

    if ($cert) {
        // Elimina PDF si existe
        if (!empty($cert['archivo_pdf'])) {
            $pdfPath = realpath("../../" . $cert['archivo_pdf']);
            if ($pdfPath && file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
        // Elimina imágenes si existen
        if (!empty($cert['imagenes_json'])) {
            $imagenes = json_decode($cert['imagenes_json'], true);
            if (is_array($imagenes)) {
                foreach ($imagenes as $img) {
                    $imgPath = realpath("../../" . $img);
                    if ($imgPath && file_exists($imgPath)) {
                        unlink($imgPath);
                    }
                }
            }
        }

        // Elimina registro
        $del = $mysqli->prepare("DELETE FROM certificados WHERE id = ? AND veterinario_id = ?");
        $del->bind_param("ii", $id, $usuario_id);
        if ($del->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Certificado eliminado correctamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el certificado (DB).']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Certificado no encontrado o sin permisos.']);
    }
    exit; 
}

$action       = $_POST['action'] ?? '';
$id           = intval($_POST['id'] ?? 0);
$veterinario  = intval($_POST['veterinario_id'] ?? ($_SESSION['usuario_id'] ?? 0));
$paciente_id  = intval($_POST['paciente_id'] ?? 0);
$fecha_examen = $_POST['fecha_examen'] ?? date('Y-m-d');
$motivo       = trim($_POST['motivo_examen'] ?? '');
$descripcion  = trim($_POST['contenido_html'] ?? '');
$medico_solicitante   = trim($_POST['medico_solicitante'] ?? '');
$motivo               = trim($_POST['motivo_examen'] ?? '');
$recinto              = trim($_POST['recinto'] ?? '');
$plantilla_informe_id = intval($_POST['plantilla_informe_id']);
$configuracion_informe_id = intval($_POST['configuracion_informe_id'] ?? 0);
$modo_manual          = isset($_POST['toggle_manual']) && $_POST['toggle_manual'] == '1';


if (empty($descripcion)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios.']);
    exit;
}

if ($configuracion_informe_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Debes seleccionar una plantilla de diseño.']);
    exit;
}

$guardarMascota = isset($_POST['guardar_mascota']) && $_POST['guardar_mascota'] == '1';
$manual = [];
foreach ($_POST as $k => $v) {
    if (strpos($k, 'manual_') === 0) {
        $manual[substr($k, 7)] = trim($v);
    }
}

$prev_manual_data = null;
if ($action === 'modificar' && $id > 0) {
    $q = $mysqli->prepare("SELECT manual_data FROM certificados WHERE id = ? AND veterinario_id = ?");
    $q->bind_param("ii", $id, $veterinario);
    $q->execute();
    $r = $q->get_result();
    if ($rowPrev = $r->fetch_assoc()) {
        $prev_manual_data = $rowPrev['manual_data']; // puede ser NULL o JSON
    }
}

$manual_data = null;
if ($modo_manual && $guardarMascota && !empty($manual)) {

    // ---------- Tutor ----------
    $tutorNombre = trim($manual['propietario'] ?? '');

    $stmt = $mysqli->prepare("SELECT id FROM tutores WHERE nombre_completo = ? AND veterinario_id = ?");
    $stmt->bind_param("si", $tutorNombre, $veterinario);
    $stmt->execute();
    $res = $stmt->get_result();

    $tutorId = null;
    if ($row = $res->fetch_assoc()) {
        $tutorId = (int)$row['id'];
    } else {
        $stmt = $mysqli->prepare("INSERT INTO tutores (nombre_completo, veterinario_id) VALUES (?, ?)");
        $stmt->bind_param("si", $tutorNombre, $veterinario);
        if ($stmt->execute()) {
            $tutorId = (int)$stmt->insert_id;
        }
    }

    // ---------- Paciente ----------
    $nombreMascota   = trim($manual['paciente'] ?? '');
    $codigoPaciente  = trim($manual['codigo_paciente'] ?? ''); // 🆕 si viene desde el formulario manual
    $especie         = trim($manual['especie'] ?? '');
    $raza            = trim($manual['raza'] ?? '');
    $sexo            = trim($manual['sexo'] ?? '');
    $n_chip          = trim($manual['n_chip'] ?? '');

    // ✅ Fecha nacimiento opcional: si viene vacío o inválido => NULL
    $fecha_nacimiento_raw = trim($manual['fecha_nacimiento'] ?? '');
    $fecha_nacimiento = null;
    if ($fecha_nacimiento_raw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento_raw);
        if ($dt && $dt->format('Y-m-d') === $fecha_nacimiento_raw) {
            $fecha_nacimiento = $fecha_nacimiento_raw;
        }
    }

    // Buscar si ya existe ese paciente para ese tutor
    $stmt = $mysqli->prepare("SELECT id FROM pacientes WHERE nombre = ? AND tutor_id = ? AND veterinario_id = ?");
    $stmt->bind_param("sii", $nombreMascota, $tutorId, $veterinario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $paciente_id = (int)$row['id'];
    } else {
        // 🆕 Incluye codigo_paciente si existe en tu tabla
        $stmt = $mysqli->prepare("
            INSERT INTO pacientes
                (nombre, codigo_paciente, especie, raza, sexo, fecha_nacimiento, tutor_id, veterinario_id, n_chip)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssiis",
            $nombreMascota,
            $codigoPaciente,
            $especie,
            $raza,
            $sexo,
            $fecha_nacimiento,   // 👈 NULL si viene vacío
            $tutorId,
            $veterinario,
            $n_chip
        );

        if ($stmt->execute()) {
            $paciente_id = (int)$stmt->insert_id;
        }
    }
} elseif ($modo_manual && !empty($manual)) {
    $paciente_id = null; // se guarda paciente inline, sin persistir
    $manual_data = json_encode($manual, JSON_UNESCAPED_UNICODE);
}

// 📷 Manejar imágenes
$imagenes = [];
if (!empty($_POST['imagenes_antiguas'])) {
    $imgsAntiguas = json_decode($_POST['imagenes_antiguas'], true);
    if (is_array($imgsAntiguas)) {
        $imagenes = $imgsAntiguas; // Empezamos con estas
    }
}
if (!empty($_FILES['imagenes']['name'][0])) {
    $imgDir = "../../uploads/certificados/img/";

    if (!is_dir($imgDir)) {
        mkdir($imgDir, 0777, true);
    }

    foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
        // $nombreArchivo = uniqid('img_') . $veterinario ."_" . basename($_FILES['imagenes']['name'][$key]);
        $nombreArchivo = "img_{$veterinario}_" . uniqid() . basename($_FILES['imagenes']['name'][$key]);

        $rutaDestino = $imgDir . $nombreArchivo;
        if (move_uploaded_file($tmpName, $rutaDestino)) {
            $imagenes[] = "uploads/certificados/img/" . $nombreArchivo;
        }
    }
}

$imagenesJson = json_encode($imagenes);
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
    $manual_data
);

// 📄 Generar PDF
$pdf = new Dompdf();
$options = $pdf->getOptions();
$options->set('isRemoteEnabled', true);
$pdf->setOptions($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdfFilename = "cert_{$veterinario}_" . uniqid() . ".pdf";
file_put_contents($pdfDir . $pdfFilename, $pdf->output());
$rutaPdf = "uploads/certificados/informes/" . $pdfFilename;

// 🔥 Insertar o Actualizar en DB
if ($action === 'ingresar') {
    $stmt = $mysqli->prepare("INSERT INTO certificados 
        (veterinario_id, paciente_id, fecha_examen, contenido_html, archivo_pdf, imagenes_json, medico_solicitante, recinto, tipo_estudio, configuracion_informe_id, motivo, manual_data, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("iisssssssiss", $veterinario, $paciente_id, $fecha_examen, $descripcion, $rutaPdf, $imagenesJson, $medico_solicitante, $recinto, $plantilla_informe_id, $configuracion_informe_id, $motivo, $manual_data);
} elseif ($action === 'modificar' && $id > 0) {
    // Obtener archivo anterior e imágenes
    $stmt = $mysqli->prepare("SELECT archivo_pdf, imagenes_json FROM certificados WHERE id = ? AND veterinario_id = ?");
    $stmt->bind_param("ii", $id, $veterinario);
    $stmt->execute();
    $res = $stmt->get_result();
    $prev = $res->fetch_assoc();

    // Eliminar PDF anterior si existe
    if (!empty($prev['archivo_pdf'])) {
        $rutaAnterior = realpath("../../" . $prev['archivo_pdf']);
        if ($rutaAnterior && file_exists($rutaAnterior)) {
            unlink($rutaAnterior);
        }
    }

    $tienePaciente    = !empty($paciente_id);
    $llegaManualNuevo = !empty($manual_data);

    // 🔒 Fallback: si no hay paciente DB y no llegó manual nuevo, conserva el anterior
    if ($action === 'modificar' && !$tienePaciente && !$llegaManualNuevo && $prev_manual_data !== null) {
        $manual_data = $prev_manual_data;
    }

    // Actualizar registro con nuevo PDF y nuevas imágenes
    $stmt = $mysqli->prepare("UPDATE certificados
        SET fecha_examen = ?, contenido_html = ?, archivo_pdf = ?, imagenes_json = ?, medico_solicitante = ?, recinto = ?, tipo_estudio = ?, configuracion_informe_id = ?, motivo = ?, manual_data = ?, updated_at = NOW()
        WHERE id = ? AND veterinario_id = ?");
    $stmt->bind_param("sssssssissii", $fecha_examen, $descripcion, $rutaPdf, $imagenesJson, $medico_solicitante, $recinto, $plantilla_informe_id, $configuracion_informe_id, $motivo, $manual_data, $id, $veterinario);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    exit;
}

if ($stmt->execute()) {

    // id del certificado (ingresar: insert_id, modificar: ya viene)
    $certId = 0;
    if ($action === 'ingresar') {
        $certId = (int)$stmt->insert_id;
    } elseif ($action === 'modificar' && $id > 0) {
        $certId = (int)$id;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Certificado guardado correctamente.",
        "rutaPdf" => $rutaPdf,
        "id"      => $certId
    ]);
} else {
    echo json_encode([
        'status' => 'info',
        'message' => 'Error al guardar el certificado.',
        'mysql_error' => $stmt->error
    ]);
}


exit;
