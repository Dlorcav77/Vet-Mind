<?php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();

function jexit(string $status, string $message): void
{
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
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

function paciente_pertenece_al_veterinario(mysqli $db, int $pacienteId, int $veterinarioId): bool
{
    $stmt = $db->prepare("SELECT id FROM pacientes WHERE id = ? AND veterinario_id = ? LIMIT 1");
    $stmt->bind_param('ii', $pacienteId, $veterinarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    return $res->num_rows > 0;
}

function resolver_especie_y_raza_por_id(mysqli $db, ?int $razaId): array
{
    if (!$razaId) return [null, null];

    $stmt = $db->prepare(
        "SELECT r.nombre AS raza, e.nombre AS especie
         FROM razas r
         JOIN especies e ON e.id = r.especie_id
         WHERE r.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $razaId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) return [null, null];

    return [$row['especie'] ?? null, $row['raza'] ?? null];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jexit('error', 'Método no permitido.');
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? ''));
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$veterinarioId = (int)$usuario_id;

if (!in_array($action, ['ingresar', 'modificar', 'eliminar'], true)) {
    jexit('error', 'Acción inválida.');
}

switch ($action) {
    case 'ingresar':
        credenciales('tutor', 'ingresar');
        break;
    case 'modificar':
        credenciales('tutor', 'modificar');
        break;
    case 'eliminar':
        credenciales('tutor', 'eliminar');
        break;
}

try {
    if ($action === 'eliminar') {
        if ($id <= 0) {
            jexit('error', 'Paciente inválido.');
        }

        if (!paciente_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
            jexit('error', 'No tienes permiso para eliminar este paciente.');
        }

        $stmt = $mysqli->prepare("DELETE FROM pacientes WHERE id = ? AND veterinario_id = ?");
        $stmt->bind_param('ii', $id, $veterinarioId);
        $stmt->execute();
        $stmt->close();

        logg("Eliminación de paciente ID: $id");
        jexit('success', 'Paciente eliminado exitosamente.');
    }

    $tutorId = isset($_POST['tutor_id']) ? (int)$_POST['tutor_id'] : 0;
    $nombre = trim((string)($_POST['nombre'] ?? ''));

    if ($tutorId <= 0) {
        jexit('error', 'Tutor inválido.');
    }

    if (!tutor_pertenece_al_veterinario($mysqli, $tutorId, $veterinarioId)) {
        jexit('error', 'No tienes permiso para utilizar este tutor.');
    }

    if ($nombre === '') {
        jexit('error', 'El nombre es obligatorio.');
    }

    validar_length("Nombre", $nombre, 100);

    $codigoPaciente = trim((string)($_POST['codigo_paciente'] ?? ''));
    $codigoPaciente = $codigoPaciente !== '' ? $codigoPaciente : null;

    if ($codigoPaciente !== null) {
        validar_length("Código de paciente", $codigoPaciente, 30, true);
    }

    $razaId = isset($_POST['raza']) && $_POST['raza'] !== ''
        ? (int)$_POST['raza']
        : null;

    [$especie, $raza] = resolver_especie_y_raza_por_id($mysqli, $razaId);

    $fechaNacimiento = trim((string)($_POST['fecha_nacimiento'] ?? ''));
    $nChip = trim((string)($_POST['n_chip'] ?? ''));
    $sexo = trim((string)($_POST['sexo'] ?? ''));

    $sexosValidos = ['Macho', 'Macho Castrado', 'Hembra', 'Hembra Esterilizada', 'Otro'];

    if ($sexo !== '' && !in_array($sexo, $sexosValidos, true)) {
        jexit('error', 'Sexo inválido.');
    }

    if ($raza !== null) validar_length("Raza", $raza, 100, true);
    if ($especie !== null) validar_length("Especie", $especie, 20, true);

    if ($fechaNacimiento !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento)) {
            jexit('error', 'Fecha de nacimiento inválida (formato esperado: YYYY-MM-DD).');
        }

        if (strtotime($fechaNacimiento) > time()) {
            jexit('error', 'La fecha de nacimiento no puede ser futura.');
        }
    } else {
        $fechaNacimiento = null;
    }

    if ($nChip !== '' && !preg_match('/^[0-9]{10,15}$/', $nChip)) {
        jexit('error', 'El número de chip debe tener entre 10 y 15 dígitos numéricos.');
    }

    if ($action === 'modificar') {
        if ($id <= 0) {
            jexit('error', 'Paciente inválido.');
        }

        if (!paciente_pertenece_al_veterinario($mysqli, $id, $veterinarioId)) {
            jexit('error', 'No tienes permiso para modificar este paciente.');
        }

        if ($especie !== null) {
            $stmt = $mysqli->prepare(
                "SELECT id FROM pacientes
                 WHERE nombre = ? AND tutor_id = ? AND especie = ? AND id != ?
                 LIMIT 1"
            );
            $stmt->bind_param('sisi', $nombre, $tutorId, $especie, $id);
        } else {
            $stmt = $mysqli->prepare(
                "SELECT id FROM pacientes
                 WHERE nombre = ? AND tutor_id = ? AND id != ?
                 LIMIT 1"
            );
            $stmt->bind_param('sii', $nombre, $tutorId, $id);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'Ya existe un paciente con ese nombre para este tutor' . ($especie ? " (especie $especie)" : "") . '.');
        }

        $stmt = $mysqli->prepare(
            "UPDATE pacientes
             SET nombre = ?,
                 codigo_paciente = ?,
                 n_chip = ?,
                 especie = ?,
                 raza = ?,
                 fecha_nacimiento = ?,
                 sexo = ?,
                 updated_at = NOW()
             WHERE id = ?
               AND veterinario_id = ?"
        );
        $stmt->bind_param(
            'sssssssii',
            $nombre,
            $codigoPaciente,
            $nChip,
            $especie,
            $raza,
            $fechaNacimiento,
            $sexo,
            $id,
            $veterinarioId
        );
        $stmt->execute();
        $stmt->close();

        logg("Modificación de paciente ID: $id, Nombre: $nombre");
        jexit('success', 'Paciente actualizado exitosamente.');
    }

    if ($action === 'ingresar') {
        if ($especie !== null) {
            $stmt = $mysqli->prepare(
                "SELECT id FROM pacientes
                 WHERE nombre = ? AND especie = ? AND tutor_id = ?
                 LIMIT 1"
            );
            $stmt->bind_param('ssi', $nombre, $especie, $tutorId);
        } else {
            $stmt = $mysqli->prepare(
                "SELECT id FROM pacientes
                 WHERE nombre = ? AND tutor_id = ?
                 LIMIT 1"
            );
            $stmt->bind_param('si', $nombre, $tutorId);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if ($res->num_rows > 0) {
            jexit('error', 'Ya existe un paciente con ese nombre para este tutor' . ($especie ? " (especie $especie)" : "") . '.');
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO pacientes
                (veterinario_id, tutor_id, nombre, codigo_paciente, n_chip, especie, raza, fecha_nacimiento, sexo, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param(
            'iisssssss',
            $veterinarioId,
            $tutorId,
            $nombre,
            $codigoPaciente,
            $nChip,
            $especie,
            $raza,
            $fechaNacimiento,
            $sexo
        );
        $stmt->execute();
        $stmt->close();

        logg("Inserción de paciente: $nombre, Tutor ID: $tutorId");
        jexit('success', 'Paciente ingresado exitosamente.');
    }
} catch (Throwable $e) {
    error_log('[updPacientes] ' . $e->getMessage());
    jexit('error', 'No se pudo completar la operación.');
}