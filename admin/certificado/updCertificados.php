<?php
// admin/certificado/updCertificados.php

require_once("../config.php");
require_once("../../vendor/autoload.php");
require_once("funcionesCertificado.php");

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    $usuario_id = intval($_SESSION['usuario_id'] ?? 0);

    $sel = $mysqli->prepare("SELECT archivo_pdf, imagenes_json FROM certificados WHERE id = ? AND veterinario_id = ?");
    if (!$sel) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando eliminación.',
            'mysql_error' => $mysqli->error
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $sel->bind_param("ii", $id, $usuario_id);
    $sel->execute();
    $res = $sel->get_result();
    $cert = $res->fetch_assoc();

    if ($cert) {
        if (!empty($cert['archivo_pdf'])) {
            $pdfPath = realpath("../../" . $cert['archivo_pdf']);
            if ($pdfPath && file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }

        if (!empty($cert['imagenes_json'])) {
            $imagenes = json_decode($cert['imagenes_json'], true);
            if (is_array($imagenes)) {
                foreach ($imagenes as $img) {
                    $imgPath = realpath("../../" . $img);
                    if ($imgPath && file_exists($imgPath)) {
                        @unlink($imgPath);
                    }
                }
            }
        }

        $del = $mysqli->prepare("DELETE FROM certificados WHERE id = ? AND veterinario_id = ?");
        if (!$del) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error preparando borrado.',
                'mysql_error' => $mysqli->error
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $del->bind_param("ii", $id, $usuario_id);

        if ($del->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Certificado eliminado correctamente.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo eliminar el certificado (DB).',
                'mysql_error' => $del->error
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Certificado no encontrado o sin permisos.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}

$action                  = $_POST['action'] ?? '';
$id                      = intval($_POST['id'] ?? 0);
$veterinario             = intval($_POST['veterinario_id'] ?? ($_SESSION['usuario_id'] ?? 0));
$paciente_id             = intval($_POST['paciente_id'] ?? 0);
$fecha_examen            = $_POST['fecha_examen'] ?? date('Y-m-d');
$descripcion             = trim($_POST['contenido_html'] ?? '');
$medico_solicitante      = trim($_POST['medico_solicitante'] ?? '');
$motivo                  = trim($_POST['motivo_examen'] ?? '');
$recinto                 = trim($_POST['recinto'] ?? '');
$plantilla_informe_id    = intval($_POST['plantilla_informe_id'] ?? 0);
$configuracion_informe_id = intval($_POST['configuracion_informe_id'] ?? 0);
$modo_manual             = isset($_POST['toggle_manual']) && $_POST['toggle_manual'] == '1';
$borrador_id             = (int)($_POST['borrador_id'] ?? 0);
$borrador_scope_key      = trim((string)($_POST['borrador_scope_key'] ?? (($action === 'modificar' && $id > 0) ? 'modificar:' . $id : 'nuevo')));

if ($veterinario <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida o veterinario no recibido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($descripcion === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan datos obligatorios.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($configuracion_informe_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Debes seleccionar una plantilla de diseño.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($plantilla_informe_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Debes seleccionar un tipo de examen.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$guardarMascota = isset($_POST['guardar_mascota']) && $_POST['guardar_mascota'] == '1';

$manual = [];
foreach ($_POST as $k => $v) {
    if (strpos($k, 'manual_') === 0) {
        $manual[substr($k, 7)] = trim((string)$v);
    }
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

$manual_data = null;

if ($modo_manual && $guardarMascota && !empty($manual)) {
    $tutorNombre = trim($manual['propietario'] ?? '');

    $stmt = $mysqli->prepare("SELECT id FROM tutores WHERE nombre_completo = ? AND veterinario_id = ?");
    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando tutor.',
            'mysql_error' => $mysqli->error
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param("si", $tutorNombre, $veterinario);
    $stmt->execute();
    $res = $stmt->get_result();

    $tutorId = null;
    if ($row = $res->fetch_assoc()) {
        $tutorId = (int)$row['id'];
    } else {
        $stmt = $mysqli->prepare("INSERT INTO tutores (nombre_completo, veterinario_id) VALUES (?, ?)");
        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error preparando creación de tutor.',
                'mysql_error' => $mysqli->error
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $stmt->bind_param("si", $tutorNombre, $veterinario);
        if ($stmt->execute()) {
            $tutorId = (int)$stmt->insert_id;
        }
    }

    $nombreMascota  = trim($manual['paciente'] ?? '');
    $codigoPaciente = trim($manual['codigo_paciente'] ?? '');
    $especie        = trim($manual['especie'] ?? '');
    $raza           = trim($manual['raza'] ?? '');
    $sexo           = trim($manual['sexo'] ?? '');
    $n_chip         = trim($manual['n_chip'] ?? '');

    $fecha_nacimiento_raw = trim($manual['fecha_nacimiento'] ?? '');
    $fecha_nacimiento = null;
    if ($fecha_nacimiento_raw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento_raw);
        if ($dt && $dt->format('Y-m-d') === $fecha_nacimiento_raw) {
            $fecha_nacimiento = $fecha_nacimiento_raw;
        }
    }

    $stmt = $mysqli->prepare("SELECT id FROM pacientes WHERE nombre = ? AND tutor_id = ? AND veterinario_id = ?");
    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando búsqueda de paciente.',
            'mysql_error' => $mysqli->error
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param("sii", $nombreMascota, $tutorId, $veterinario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $paciente_id = (int)$row['id'];
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO pacientes
                (nombre, codigo_paciente, especie, raza, sexo, fecha_nacimiento, tutor_id, veterinario_id, n_chip)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error preparando creación de paciente.',
                'mysql_error' => $mysqli->error
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

        if ($stmt->execute()) {
            $paciente_id = (int)$stmt->insert_id;
        }
    }
} elseif ($modo_manual && !empty($manual)) {
    $paciente_id = null;
    $manual_data = json_encode($manual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$imagenes = [];
if (!empty($_POST['imagenes_antiguas'])) {
    $imgsAntiguas = json_decode($_POST['imagenes_antiguas'], true);
    if (is_array($imgsAntiguas)) {
        $imagenes = $imgsAntiguas;
    }
}

if (!empty($_FILES['imagenes']['name'][0])) {
    $imgDir = "../../uploads/certificados/img/";

    if (!is_dir($imgDir)) {
        mkdir($imgDir, 0777, true);
    }

    foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmpName) {
        if (!empty($tmpName) && is_uploaded_file($tmpName)) {
            $nombreArchivo = "img_{$veterinario}_" . uniqid() . basename($_FILES['imagenes']['name'][$key]);
            $rutaDestino = $imgDir . $nombreArchivo;

            if (move_uploaded_file($tmpName, $rutaDestino)) {
                $imagenes[] = "uploads/certificados/img/" . $nombreArchivo;
            }
        }
    }
}

$imagenesJson = json_encode($imagenes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0777, true);
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
    $manual_data
);

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

if ($action === 'ingresar') {
    $stmt = $mysqli->prepare("INSERT INTO certificados 
        (veterinario_id, paciente_id, fecha_examen, contenido_html, archivo_pdf, imagenes_json, medico_solicitante, recinto, tipo_estudio, configuracion_informe_id, motivo, manual_data, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando inserción.',
            'mysql_error' => $mysqli->error
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param(
        "iisssssssiss",
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
        $manual_data
    );
} elseif ($action === 'modificar' && $id > 0) {
    $stmtPrev = $mysqli->prepare("SELECT archivo_pdf, imagenes_json FROM certificados WHERE id = ? AND veterinario_id = ?");
    if ($stmtPrev) {
        $stmtPrev->bind_param("ii", $id, $veterinario);
        $stmtPrev->execute();
        $res = $stmtPrev->get_result();
        $prev = $res->fetch_assoc();

        if (!empty($prev['archivo_pdf'])) {
            $rutaAnterior = realpath("../../" . $prev['archivo_pdf']);
            if ($rutaAnterior && file_exists($rutaAnterior)) {
                @unlink($rutaAnterior);
            }
        }
    }

    $tienePaciente = !empty($paciente_id);
    $llegaManualNuevo = !empty($manual_data);

    if (!$tienePaciente && !$llegaManualNuevo && $prev_manual_data !== null) {
        $manual_data = $prev_manual_data;
    }

    $stmt = $mysqli->prepare("UPDATE certificados
        SET fecha_examen = ?, contenido_html = ?, archivo_pdf = ?, imagenes_json = ?, medico_solicitante = ?, recinto = ?, tipo_estudio = ?, configuracion_informe_id = ?, motivo = ?, manual_data = ?, updated_at = NOW()
        WHERE id = ? AND veterinario_id = ?");

    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error preparando actualización.',
            'mysql_error' => $mysqli->error
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param(
        "sssssssissii",
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
        $id,
        $veterinario
    );
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Acción no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($stmt->execute()) {
    $certId = 0;

    if ($action === 'ingresar') {
        $certId = (int)$stmt->insert_id;
    } elseif ($action === 'modificar' && $id > 0) {
        $certId = (int)$id;
    }

    if ($certId > 0) {
        if ($borrador_id > 0) {
            $stmtBorrador = $mysqli->prepare("
                UPDATE certificados_borradores
                SET estado = 'finalizado',
                    certificado_id = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND veterinario_id = ?
            ");

            if ($stmtBorrador) {
                $stmtBorrador->bind_param("iii", $certId, $borrador_id, $veterinario);
                $stmtBorrador->execute();
            }
        } elseif ($borrador_scope_key !== '') {
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
                $stmtBorrador->bind_param("iis", $certId, $veterinario, $borrador_scope_key);
                $stmtBorrador->execute();
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Certificado guardado correctamente.',
        'rutaPdf' => $rutaPdf,
        'id' => $certId
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al guardar el certificado.',
        'mysql_error' => $stmt->error
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;