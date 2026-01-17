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
    $nombre           = trim($_POST['nombre'] ?? '');

    // ✅ obligatorios: tutor + nombre
    if ($tutor_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tutor inválido.']);
        exit;
    }
    if ($nombre === '') {
        echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio.']);
        exit;
    }
    validar_length("Nombre", $nombre, 100);

    // ✅ código interno opcional
    $codigo_paciente  = trim($_POST['codigo_paciente'] ?? '');
    if ($codigo_paciente === '') $codigo_paciente = null;
    if ($codigo_paciente !== null) {
        validar_length("Código de paciente", $codigo_paciente, 30, true);
    }

    // ✅ raza opcional (ID en el <select>)
    $raza_id_post = (isset($_POST['raza']) && $_POST['raza'] !== '') ? intval($_POST['raza']) : null;

    // ✅ resolvemos especie/raza (texto) desde BD (o null si no se seleccionó)
    list($especie, $raza) = resolver_especie_y_raza_por_id($mysqli, $raza_id_post);

    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $n_chip           = trim($_POST['n_chip'] ?? '');
    $sexo             = trim($_POST['sexo'] ?? '');

    // ✅ sexo opcional (solo validamos si viene)
    $sexos_validos = ['Macho','Macho Castrado','Hembra','Hembra Esterilizada','Otro'];
    if ($sexo !== '' && !in_array($sexo, $sexos_validos, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Sexo inválido.']);
        exit;
    }

    // ✅ raza/especie opcional
    if ($raza !== null) validar_length("Raza", $raza, 100, true);
    if ($especie !== null) validar_length("Especie", $especie, 20, true);

    // ✅ fecha opcional (validamos si viene)
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

    // ✅ chip opcional (validamos si viene)
    if ($n_chip !== '') {
        if (!preg_match('/^[0-9]{10,15}$/', $n_chip)) {
            echo json_encode(['status' => 'error', 'message' => 'El número de chip debe tener entre 10 y 15 dígitos numéricos.']);
            exit;
        }
    }
}


// Eliminar
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

    // ✅ guardamos especie/raza como TEXTO + código opcional
    $update_query = "UPDATE pacientes
                        SET nombre = ?,
                            codigo_paciente = ?,
                            n_chip = ?,
                            especie = ?,
                            raza = ?,
                            fecha_nacimiento = ?,
                            sexo = ?,
                            updated_at = NOW()
                      WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('sssssssi', $nombre, $codigo_paciente, $n_chip, $especie, $raza, $fecha_nacimiento, $sexo, $id);

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

    // ✅ guardamos especie/raza texto + código opcional
    $ins = "INSERT INTO pacientes (veterinario_id, tutor_id, nombre, codigo_paciente, n_chip, especie, raza, fecha_nacimiento, sexo, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('iisssssss', $veterinario_id, $tutor_id, $nombre, $codigo_paciente, $n_chip, $especie, $raza, $fecha_nacimiento, $sexo);

    if ($stmt->execute()) {
        logg("Inserción de paciente: $nombre, Tutor ID: $tutor_id");
        echo json_encode(['status' => 'success', 'message' => 'Paciente ingresado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el paciente.']);
    }
}
?>
