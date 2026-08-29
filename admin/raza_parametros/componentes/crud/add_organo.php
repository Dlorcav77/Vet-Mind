<?php
// admin/raza_parametros/componentes/crud/add_organo.php
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

function num_or_null(?string $v): ?string
{
    if ($v === null) return null;

    $v = trim($v);
    if ($v === '') return null;

    $v = str_replace(',', '.', $v);
    if (!is_numeric($v)) return null;

    return $v;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jexit('error', 'Método no permitido.', [], 405);
}

validarTokenCsrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    credenciales('raza_parametros', 'modificar');
} else {
    credenciales('raza_parametros', 'ingresar');
}

$organo = trim((string)($_POST['organo'] ?? ''));
$especie_id = isset($_POST['especie_id']) ? (int)$_POST['especie_id'] : 0;
$tamano = isset($_POST['tamano']) ? trim((string)$_POST['tamano']) : null;
$etapa = trim((string)($_POST['etapa'] ?? ''));
$unidad = trim((string)($_POST['unidad'] ?? 'cm'));

$tamano_min = num_or_null(isset($_POST['tamano_min']) ? (string)$_POST['tamano_min'] : null);
$tamano_max = num_or_null(isset($_POST['tamano_max']) ? (string)$_POST['tamano_max'] : null);
$tamano_min_error = num_or_null(isset($_POST['tamano_min_error']) ? (string)$_POST['tamano_min_error'] : null);
$tamano_max_error = num_or_null(isset($_POST['tamano_max_error']) ? (string)$_POST['tamano_max_error'] : null);

if ($organo === '') jexit('error', 'Falta el nombre del órgano.');
if ($especie_id <= 0) jexit('error', 'Falta la especie.');
if ($tamano_min === null) jexit('error', 'Falta el valor mínimo.');
if ($tamano_max === null) jexit('error', 'Falta el valor máximo.');
if ($unidad === '') jexit('error', 'Falta la unidad.');

if ($tamano === '') {
    $tamano = null;
}

$dupSql = "SELECT 1
           FROM organos_parametros
           WHERE especie_id = ?
             AND LOWER(TRIM(organo)) = LOWER(TRIM(?))
             AND (tamano <=> ?)";

$types = 'iss';
$args = [$especie_id, $organo, $tamano];

if ($id > 0) {
    $dupSql .= " AND id <> ?";
    $types .= 'i';
    $args[] = $id;
}

$dupStmt = $mysqli->prepare($dupSql);

if (!$dupStmt) {
    error_log('[add_organo] Error prepare duplicado: ' . $mysqli->error);
    jexit('error', 'No se pudo validar el parámetro.', [], 500);
}

if (!$dupStmt->bind_param($types, ...$args)) {
    error_log('[add_organo] Error bind duplicado: ' . $dupStmt->error);
    jexit('error', 'No se pudo validar el parámetro.', [], 500);
}

if (!$dupStmt->execute()) {
    error_log('[add_organo] Error execute duplicado: ' . $dupStmt->error);
    jexit('error', 'No se pudo validar el parámetro.', [], 500);
}

$dupStmt->store_result();

if ($dupStmt->num_rows > 0) {
    $dupStmt->close();
    jexit('error', 'Ya existe este órgano para la especie y tamaño seleccionados.');
}

$dupStmt->close();

if ($id > 0) {
    $sql = "UPDATE organos_parametros
            SET organo = ?, especie_id = ?, tamano = ?, etapa = ?,
                tamano_min = ?, tamano_max = ?,
                tamano_min_critico = ?, tamano_max_critico = ?,
                unidad = ?
            WHERE id = ?";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log('[add_organo] Error prepare update: ' . $mysqli->error);
        jexit('error', 'No se pudo preparar la actualización.', [], 500);
    }

    if (!$stmt->bind_param(
        'sisssssssi',
        $organo,
        $especie_id,
        $tamano,
        $etapa,
        $tamano_min,
        $tamano_max,
        $tamano_min_error,
        $tamano_max_error,
        $unidad,
        $id
    )) {
        error_log('[add_organo] Error bind update: ' . $stmt->error);
        jexit('error', 'No se pudo preparar la actualización.', [], 500);
    }

    if (!$stmt->execute()) {
        error_log('[add_organo] Error execute update: ' . $stmt->error);
        jexit('error', 'No se pudo actualizar el parámetro.', [], 500);
    }

    jexit('ok', 'Parámetro actualizado correctamente.');
}

$sql = "INSERT INTO organos_parametros
            (organo, especie_id, tamano, etapa, tamano_min, tamano_max, tamano_min_critico, tamano_max_critico, unidad)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log('[add_organo] Error prepare insert: ' . $mysqli->error);
    jexit('error', 'No se pudo preparar el registro.', [], 500);
}

if (!$stmt->bind_param(
    'sisssssss',
    $organo,
    $especie_id,
    $tamano,
    $etapa,
    $tamano_min,
    $tamano_max,
    $tamano_min_error,
    $tamano_max_error,
    $unidad
)) {
    error_log('[add_organo] Error bind insert: ' . $stmt->error);
    jexit('error', 'No se pudo preparar el registro.', [], 500);
}

if (!$stmt->execute()) {
    error_log('[add_organo] Error execute insert: ' . $stmt->error);
    jexit('error', 'No se pudo agregar el parámetro.', [], 500);
}

jexit('ok', 'Parámetro agregado correctamente.', ['insert_id' => $stmt->insert_id]);