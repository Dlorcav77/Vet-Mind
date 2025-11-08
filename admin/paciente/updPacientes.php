<?php
require_once("../config.php");

$mysqli = conn();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

// ✅ helper: resuelve especie/raza texto desde raza_id (o null si vacío)
function resolver_especie_y_raza_por_id(mysqli $db, $raza_id) {
    if (!$raza_id) return [null, null];
    $sql = "SELECT r.nombre AS raza, e.nombre AS especie
              FROM razas r
              JOIN especies e ON e.id = r.especie_id
             WHERE r.id = ? LIMIT 1";
    if ($st = $db->prepare($sql)) {
        $st->bind_param('i', $raza_id);
        $st->execute();
        $rs = $st->get_result();
        if ($row = $rs->fetch_assoc()) {
            return [$row['especie'] ?? null, $row['raza'] ?? null];
        }
    }
    return [null, null];
}

if ($action !== 'eliminar') {
    $veterinario_id   = intval($_POST['veterinario_id']);
    $tutor_id         = intval($_POST['tutor_id']);
    $nombre           = trim($_POST['nombre']);
    // ❌ ya no viene especie por POST
    // $especie        = trim($_POST['especie']);

    // ✅ ahora 'raza' es el ID seleccionado en el <select>
    $raza_id_post     = isset($_POST['raza']) && $_POST['raza'] !== '' ? intval($_POST['raza']) : null;

    // ✅ resolvemos especie/raza (texto) desde BD (o null si no se seleccionó)
    list($especie, $raza) = resolver_especie_y_raza_por_id($mysqli, $raza_id_post);

    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $n_chip           = trim($_POST['n_chip'] ?? '');
    $sexo             = trim($_POST['sexo'] ?? '');

    // Validaciones básicas
    validar_length("Nombre", $nombre, 100);
    // ❌ quitamos validar_length de especie por POST
    // validar_length("Especie", $especie, 20);

    // ✅ valida sexo contra catálogo permitido (evita typos)
    $sexos_validos = ['Macho','Macho Castrado','Hembra','Hembra Esterilizada','Otro'];
    if ($sexo !== '' && !in_array($sexo, $sexos_validos, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Sexo inválido.']);
        exit;
    }

    // ✅ raza es opcional; si viene, ya está normalizada a texto por resolver_especie_y_raza_por_id()
    if ($raza !== null) validar_length("Raza", $raza, 100, true);
    if ($especie !== null) validar_length("Especie", $especie, 20, true);

    if ($fecha_nacimiento !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
            echo json_encode(['status' => 'error', 'message' => 'Fecha de nacimiento inválida (formato esperado: YYYY-MM-DD).']);
            exit;
        }
        if (strtotime($fecha_nacimiento) > time()) {
            echo json_encode(['status' => 'error', 'message' => 'La fecha de nacimiento no puede ser futura.']);
            exit;
        }
    } else {
        $fecha_nacimiento = NULL;
    }

    if ($n_chip !== '') {
        if (!preg_match('/^[0-9]{10,15}$/', $n_chip)) {
            echo json_encode(['status' => 'error', 'message' => 'El número de chip debe tener entre 10 y 15 dígitos numéricos.']);
            exit;
        }
    }
}

// Soft delete
if ($action === 'eliminar' && !empty($id)) {
    $delete_query = "DELETE FROM pacientes WHERE id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Eliminación de paciente ID: $id");
        echo json_encode(['status' => 'success', 'message' => 'Paciente eliminado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el paciente.']);
    }
    exit;
}

// Modificar
if ($action === 'modificar') {

    // ✅ Duplicidad por nombre + tutor (+ especie si la pudimos resolver)
    if ($especie !== null) {
        $sel = "SELECT id FROM pacientes WHERE nombre = ? AND tutor_id = ? AND especie = ? AND id != ?";
        $stmt = $mysqli->prepare($sel);
        $stmt->bind_param('sisi', $nombre, $tutor_id, $especie, $id);
    } else {
        $sel = "SELECT id FROM pacientes WHERE nombre = ? AND tutor_id = ? AND id != ?";
        $stmt = $mysqli->prepare($sel);
        $stmt->bind_param('sii', $nombre, $tutor_id, $id);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe un paciente con ese nombre para este tutor' . ($especie ? " (especie $especie)" : "") . '.']);
        exit;
    }

    // ✅ guardamos especie/raza como TEXTO (compat con tu esquema actual)
    $update_query = "UPDATE pacientes 
                        SET nombre = ?, n_chip = ?, especie = ?, raza = ?, fecha_nacimiento = ?, sexo = ?, updated_at = NOW() 
                      WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    // si especie/raza pueden ser null, usa 's' y pasa null (mysqli lo acepta si tienes MYSQLI_REPORT_STRICT desactivado)
    $stmt->bind_param('ssssssi', $nombre, $n_chip, $especie, $raza, $fecha_nacimiento, $sexo, $id);

    if ($stmt->execute()) {
        logg("Modificación de paciente ID: $id, Nombre: $nombre");
        echo json_encode(['status' => 'success', 'message' => 'Paciente actualizado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el paciente.']);
    }
    exit;
}

// Ingresar
if ($action === 'ingresar') {

    // ✅ Duplicidad por nombre + tutor (+ especie si se resolvió)
    if ($especie !== null) {
        $sel = "SELECT id FROM pacientes WHERE nombre = ? AND especie = ? AND tutor_id = ?";
        $stmt = $mysqli->prepare($sel);
        $stmt->bind_param('ssi', $nombre, $especie, $tutor_id);
    } else {
        $sel = "SELECT id FROM pacientes WHERE nombre = ? AND tutor_id = ?";
        $stmt = $mysqli->prepare($sel);
        $stmt->bind_param('si', $nombre, $tutor_id);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe un paciente con ese nombre para este tutor' . ($especie ? " (especie $especie)" : "") . '.']);
        exit;
    }

    // ✅ guardamos especie/raza texto
    $ins = "INSERT INTO pacientes (veterinario_id, tutor_id, nombre, n_chip, especie, raza, fecha_nacimiento, sexo, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('iissssss', $veterinario_id, $tutor_id, $nombre, $n_chip, $especie, $raza, $fecha_nacimiento, $sexo);

    if ($stmt->execute()) {
        logg("Inserción de paciente: $nombre, Tutor ID: $tutor_id");
        echo json_encode(['status' => 'success', 'message' => 'Paciente ingresado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el paciente.']);
    }
}
?>
