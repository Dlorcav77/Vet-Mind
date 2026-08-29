<?php
// admin/raza_parametros/componentes/crud/add_raza.php

declare(strict_types=1);

require_once('../../../config.php');

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$mysqli->set_charset('utf8mb4');

function jexit(string $status, string $message, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jexit('error', 'Método no permitido.', [], 405);
}

validarTokenCsrf();

$especie_id = isset($_POST['especie_id']) ? (int)$_POST['especie_id'] : 0;
$nombre = trim((string)($_POST['nombre_raza'] ?? ''));
$tamano = trim((string)($_POST['tamano_raza'] ?? ''));
$raza_id = isset($_POST['raza_id']) ? (int)$_POST['raza_id'] : 0;

if ($raza_id > 0) {
    credenciales('raza_parametros', 'modificar');
} else {
    credenciales('raza_parametros', 'ingresar');
}

if ($nombre === '' || $especie_id <= 0) {
    jexit('error', 'Datos incompletos.');
}

$tamano = $tamano !== '' ? $tamano : null;

if ($raza_id > 0) {
    $stmt = $mysqli->prepare(
        "UPDATE razas
         SET nombre = ?, tamano = ?
         WHERE id = ?"
    );

    if (!$stmt) {
        error_log('[add_raza] Error prepare update: ' . $mysqli->error);
        jexit('error', 'No se pudo preparar la actualización.', [], 500);
    }

    $stmt->bind_param('ssi', $nombre, $tamano, $raza_id);

    if (!$stmt->execute()) {
        error_log('[add_raza] Error execute update: ' . $stmt->error);
        jexit('error', 'Error al actualizar.', [], 500);
    }

    jexit('ok', 'Raza actualizada correctamente.');
}

$stmt = $mysqli->prepare(
    "INSERT INTO razas (especie_id, nombre, tamano, activo)
     VALUES (?, ?, ?, 1)"
);

if (!$stmt) {
    error_log('[add_raza] Error prepare insert: ' . $mysqli->error);
    jexit('error', 'No se pudo preparar el registro.', [], 500);
}

$stmt->bind_param('iss', $especie_id, $nombre, $tamano);

if (!$stmt->execute()) {
    error_log('[add_raza] Error execute insert: ' . $stmt->error);
    jexit('error', 'Error al guardar. ¿Ya existe?', [], 500);
}

jexit('ok', 'Raza agregada correctamente.', ['insert_id' => $stmt->insert_id]);