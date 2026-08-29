<?php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();

function jexit(string $status, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function tutor_pertenece_al_veterinario(mysqli $db, int $tutorId, int $veterinarioId): bool
{
    $stmt = $db->prepare("SELECT id FROM tutores WHERE id = ? AND veterinario_id = ? LIMIT 1");
    $stmt->bind_param('ii', $tutorId, $veterinarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    return $res->num_rows > 0;
}

function obtener_pacientes_resumen(mysqli $db, int $tutorId): array
{
    $stmt = $db->prepare(
        "SELECT id, nombre
         FROM pacientes
         WHERE tutor_id = ?
         ORDER BY nombre ASC"
    );
    $stmt->bind_param('i', $tutorId);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];

    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)($row['id'] ?? 0),
            'nombre' => (string)($row['nombre'] ?? '')
        ];
    }

    $stmt->close();
    return $out;
}

function contar_informes_por_tutor(mysqli $db, int $tutorId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS c
         FROM certificados c
         JOIN pacientes p ON p.id = c.paciente_id
         WHERE p.tutor_id = ?"
    );
    $stmt->bind_param('i', $tutorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return (int)($row['c'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jexit('error', 'Método no permitido.');
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? ''));
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$veterinarioId = (int)$usuario_id;

if (!in_array($action, ['ingresar', 'modificar', 'pre_eliminar', 'eliminar'], true)) {
    jexit('error', 'Acción inválida.');
}

switch ($action) {
    case 'ingresar':
        credenciales('tutor', 'ingresar');
        break;

    case 'modificar':
        credenciales('tutor', 'modificar');
        break;

    case 'pre_eliminar':
    case 'eliminar':
        credenciales('tutor', 'eliminar');
        break;
}

if ($action === 'pre_eliminar') {
    if ($id <= 0) {
        jexit('error', 'Tutor inválido.');
    }

    if (!tutor_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
        jexit('error', 'No tienes permiso para eliminar este tutor.');
    }

    $pacientes = obtener_pacientes_resumen($mysqli, $id);
    $informes = contar_informes_por_tutor($mysqli, $id);

    jexit('success', '', [
        'pacientes_count' => count($pacientes),
        'informes_count' => $informes,
        'pacientes' => $pacientes
    ]);
}

if ($action === 'eliminar') {
    if ($id <= 0) {
        jexit('error', 'Tutor inválido.');
    }

    if (!tutor_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
        jexit('error', 'No tienes permiso para eliminar este tutor.');
    }

    $mysqli->begin_transaction();

    try {
        $pacientesLista = obtener_pacientes_resumen($mysqli, $id);
        $pacientes = count($pacientesLista);
        $informes = contar_informes_por_tutor($mysqli, $id);

        if ($informes > 0) {
            $mysqli->rollback();
            jexit('error', "No se puede eliminar. Hay $informes informe(s) asociado(s) a las mascotas de este tutor.");
        }

        $stmt = $mysqli->prepare("DELETE FROM pacientes WHERE tutor_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("DELETE FROM tutores WHERE id = ? AND veterinario_id = ?");
        $stmt->bind_param('ii', $id, $veterinarioId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new RuntimeException('Tutor inexistente o no autorizado.');
        }

        $stmt->close();
        $mysqli->commit();

        logg("Eliminación de tutor ID: $id (pacientes eliminados: $pacientes)");
        jexit('success', "Tutor eliminado exitosamente. Se eliminaron $pacientes mascota(s) asociada(s).");
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log('[updTutores][eliminar] ' . $e->getMessage());
        jexit('error', 'No se pudo eliminar el tutor.');
    }
}

$nombreCompleto = trim((string)($_POST['nombre_completo'] ?? ''));
$rut = trim((string)($_POST['rut'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$direccion = trim((string)($_POST['direccion'] ?? ''));

validar_length("Nombre completo", $nombreCompleto, 150);
validar_length("Rut", $rut, 12, true);
validar_length("Teléfono", $telefono, 20, true);
validar_length("Email", $email, 100, true);
validar_length("Dirección", $direccion, 200, true);

if ($action === 'modificar') {
    if ($id <= 0) {
        jexit('error', 'Tutor inválido.');
    }

    if (!tutor_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
        jexit('error', 'No tienes permiso para modificar este tutor.');
    }

    try {
        $stmt = $mysqli->prepare(
            "UPDATE tutores
             SET nombre_completo = ?, rut = ?, telefono = ?, email = ?, direccion = ?, updated_at = NOW()
             WHERE id = ? AND veterinario_id = ?"
        );
        $stmt->bind_param('sssssii', $nombreCompleto, $rut, $telefono, $email, $direccion, $id, $veterinarioId);
        $stmt->execute();
        $stmt->close();

        logg("Modificación de tutor ID: $id, RUT: $rut");
        jexit('success', 'Tutor actualizado exitosamente.');
    } catch (Throwable $e) {
        error_log('[updTutores][modificar] ' . $e->getMessage());
        jexit('error', 'Error al actualizar el tutor.');
    }
}

if ($action === 'ingresar') {
    try {
        $stmt = $mysqli->prepare(
            "INSERT INTO tutores (veterinario_id, nombre_completo, rut, telefono, email, direccion)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isssss', $veterinarioId, $nombreCompleto, $rut, $telefono, $email, $direccion);
        $stmt->execute();
        $stmt->close();

        logg("Inserción de tutor: $nombreCompleto, RUT: $rut");
        jexit('success', 'Tutor ingresado exitosamente.');
    } catch (Throwable $e) {
        error_log('[updTutores][ingresar] ' . $e->getMessage());
        jexit('error', 'Error al ingresar el tutor.');
    }
}