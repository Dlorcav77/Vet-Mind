<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if ($action !== 'eliminar') {
    $veterinario_id = $_POST['veterinario_id'];
    $tipo_examen_id = intval($_POST['tipo_examen_id']);
    $nombre         = trim($_POST['nombre']);
    $contenido      = trim($_POST['contenido']);
    $estado         = $_POST['estado'];

    validar_length("Nombre", $nombre, 100);
    validar_length("Estado", $estado, 10);
    // validar_length("Contenido", $contenido, 65535);
}

if ($action === 'eliminar' && !empty($id)) {
    $delete_query = "UPDATE plantilla_informe SET deleted_at = NOW() WHERE id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Eliminación de plantilla_informe ID: $id");
        echo json_encode(['status' => 'success', 'message' => 'Plantilla eliminada exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la plantilla.']);
    }
    exit;
}

if ($action === 'modificar') {
    $sel = "SELECT id FROM plantilla_informe WHERE nombre = ? AND veterinario_id = ? AND id != ? AND deleted_at IS NULL";
    $stmt = $mysqli->prepare($sel);
    $stmt->bind_param('sii', $nombre, $veterinario_id, $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe una plantilla con este nombre.']);
        exit;
    }

    $update_query = "UPDATE plantilla_informe 
                     SET tipo_examen_id = ?, nombre = ?, contenido = ?, estado = ?, updated_at = NOW()
                     WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('isssi', $tipo_examen_id, $nombre, $contenido, $estado, $id);

    if ($stmt->execute()) {
        logg("Modificación de plantilla_informe ID: $id por veterinario: $veterinario_id");
        echo json_encode(['status' => 'success', 'message' => 'Plantilla actualizada exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la plantilla.']);
    }
    exit;
}

if ($action === 'ingresar') {
    $sel = "SELECT id, deleted_at FROM plantilla_informe WHERE nombre = ? AND veterinario_id = ?";
    $stmt = $mysqli->prepare($sel);
    $stmt->bind_param('si', $nombre, $veterinario_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (!is_null($row['deleted_at'])) {
            // Reactivar
            $id = $row['id'];
            $reactivar_query = "UPDATE plantilla_informe 
                                SET tipo_examen_id = ?, contenido = ?, estado = ?, deleted_at = NULL, updated_at = NOW()
                                WHERE id = ?";
            $stmt = $mysqli->prepare($reactivar_query);
            $stmt->bind_param('issi', $tipo_examen_id, $contenido, $estado, $id);

            if ($stmt->execute()) {
                logg("Reactivación de plantilla_informe ID: $id por veterinario: $veterinario_id");
                echo json_encode(['status' => 'success', 'message' => 'Plantilla reactivada exitosamente.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al reactivar la plantilla eliminada.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ya existe una plantilla con este nombre.']);
        }
        exit;
    }
// print"------ $id_usu";
    $insert_query = "INSERT INTO plantilla_informe (veterinario_id, tipo_examen_id, nombre, contenido, estado) 
                     VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($insert_query);
    $stmt->bind_param('iisss', $veterinario_id, $tipo_examen_id, $nombre, $contenido, $estado);

    if ($stmt->execute()) {
        logg("Inserción de plantilla_informe: $nombre por veterinario: $veterinario_id");
        echo json_encode(['status' => 'success', 'message' => 'Plantilla ingresada exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar la plantilla.']);
    }
}
?>
