<?php
require_once("../config.php");

$mysqli = conn();

global $usuario_id;

$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

function tutor_pertenece_al_veterinario(mysqli $db, int $tutor_id, int $veterinario_id): bool {
    $sql = "SELECT id FROM tutores WHERE id = ? AND veterinario_id = ? LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param('ii', $tutor_id, $veterinario_id);
    $st->execute();
    $rs = $st->get_result();
    return ($rs && $rs->num_rows > 0);
}

function obtener_pacientes_resumen(mysqli $db, int $tutor_id): array {
    // Traemos lista simple para mostrar en confirmación
    $sql = "SELECT id, nombre
              FROM pacientes
             WHERE tutor_id = ?
             ORDER BY nombre ASC";
    $st = $db->prepare($sql);
    $st->bind_param('i', $tutor_id);
    $st->execute();
    $rs = $st->get_result();

    $out = [];
    while ($row = $rs->fetch_assoc()) {
        $out[] = [
            'id' => (int)($row['id'] ?? 0),
            'nombre' => (string)($row['nombre'] ?? '')
        ];
    }
    return $out;
}

function contar_pacientes_desde_lista(array $pacientes): int {
    return count($pacientes);
}


function contar_informes_por_tutor(mysqli $db, int $tutor_id): int {
    // certificados.paciente_id puede ser NULL, así que contamos solo los que matchean pacientes del tutor
    $sql = "SELECT COUNT(*) AS c
              FROM certificados c
              JOIN pacientes p ON p.id = c.paciente_id
             WHERE p.tutor_id = ?";
    $st = $db->prepare($sql);
    $st->bind_param('i', $tutor_id);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

/* ===========================
   PRE-ELIMINAR (solo consulta)
   =========================== */
if ($action === 'pre_eliminar') {

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tutor inválido.']);
        exit;
    }

    if (!tutor_pertenece_al_veterinario($mysqli, $id, (int)$usuario_id)) {
        echo json_encode(['status' => 'error', 'message' => 'No tienes permiso para eliminar este tutor.']);
        exit;
    }

    $pacientes_list = obtener_pacientes_resumen($mysqli, $id);
    $pacientes_count = contar_pacientes_desde_lista($pacientes_list);
    $informes  = contar_informes_por_tutor($mysqli, $id);

    echo json_encode([
        'status' => 'success',
        'pacientes_count' => $pacientes_count,
        'informes_count'  => $informes,
        'pacientes'       => $pacientes_list
    ]);
    exit;
}


/* ===========================
   VALIDACIONES (no eliminar)
   =========================== */
if ($action !== 'eliminar') {
    $veterinario_id  = intval($_POST['veterinario_id'] ?? 0);
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $rut             = trim($_POST['rut'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $direccion       = trim($_POST['direccion'] ?? '');

    validar_length("Nombre completo", $nombre_completo, 150);
    validar_length("Rut", $rut, 12, true);
    validar_length("Teléfono", $telefono, 20, true);
    validar_length("Email", $email, 100, true);
    validar_length("Dirección", $direccion, 200, true);
}

/* ===========================
   ELIMINAR
   =========================== */
if ($action === 'eliminar') {

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tutor inválido.']);
        exit;
    }

    if (!tutor_pertenece_al_veterinario($mysqli, $id, (int)$usuario_id)) {
        echo json_encode(['status' => 'error', 'message' => 'No tienes permiso para eliminar este tutor.']);
        exit;
    }

    $mysqli->begin_transaction();

    try {
        $pacientes_list = obtener_pacientes_resumen($mysqli, $id);
        $pacientes = contar_pacientes_desde_lista($pacientes_list);
        $informes  = contar_informes_por_tutor($mysqli, $id);

        if ($informes > 0) {
            $mysqli->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => "No se puede eliminar. Hay $informes informe(s) asociado(s) a las mascotas de este tutor."
            ]);
            exit;
        }

        // eliminar pacientes del tutor
        $del_p = $mysqli->prepare("DELETE FROM pacientes WHERE tutor_id = ?");
        $del_p->bind_param('i', $id);
        if (!$del_p->execute()) {
            throw new Exception('Error al eliminar pacientes.');
        }

        // eliminar tutor
        $del_t = $mysqli->prepare("DELETE FROM tutores WHERE id = ? AND veterinario_id = ?");
        $del_t->bind_param('ii', $id, $usuario_id);
        if (!$del_t->execute()) {
            throw new Exception('Error al eliminar tutor.');
        }

        $mysqli->commit();

        logg("Eliminación de tutor ID: $id (pacientes eliminados: $pacientes)");
        echo json_encode([
            'status' => 'success',
            'message' => "Tutor eliminado exitosamente. Se eliminaron $pacientes mascota(s) asociada(s)."
        ]);
        exit;

    } catch (Throwable $e) {
        $mysqli->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ===========================
   MODIFICAR
   =========================== */
if ($action === 'modificar') {

    $veterinario_id  = intval($_POST['veterinario_id'] ?? 0);
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $rut             = trim($_POST['rut'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $direccion       = trim($_POST['direccion'] ?? '');

    $update_query = "UPDATE tutores
                     SET nombre_completo = ?, rut = ?, telefono = ?, email = ?, direccion = ?, updated_at = NOW()
                     WHERE id = ? AND veterinario_id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('sssssii', $nombre_completo, $rut, $telefono, $email, $direccion, $id, $veterinario_id);

    if ($stmt->execute()) {
        logg("Modificación de tutor ID: $id, RUT: $rut");
        echo json_encode(['status' => 'success', 'message' => 'Tutor actualizado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el tutor.']);
    }
    exit;
}

/* ===========================
   INGRESAR
   =========================== */
if ($action === 'ingresar') {

    $veterinario_id  = intval($_POST['veterinario_id'] ?? 0);
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $rut             = trim($_POST['rut'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $direccion       = trim($_POST['direccion'] ?? '');

    $ins = "INSERT INTO tutores (veterinario_id, nombre_completo, rut, telefono, email, direccion)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($ins);
    $stmt->bind_param('isssss', $veterinario_id, $nombre_completo, $rut, $telefono, $email, $direccion);

    if ($stmt->execute()) {
        logg("Inserción de tutor: $nombre_completo, RUT: $rut");
        echo json_encode(['status' => 'success', 'message' => 'Tutor ingresado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar el tutor.']);
    }
    exit;
}
?>
