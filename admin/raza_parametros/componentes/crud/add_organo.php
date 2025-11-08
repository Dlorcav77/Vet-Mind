<?php
declare(strict_types=1);

require_once("../../../../funciones/conn/conn.php");
$mysqli = conn();
$mysqli->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');

function jexit(string $status, string $message, array $extra = []): void {
  echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
  exit;
}

// ===== Helpers =====
function num_or_null(?string $v): ?string {
  if ($v === null) return null;
  $v = trim($v);
  if ($v === '') return null;
  $v = str_replace(',', '.', $v);
  if (!is_numeric($v)) return null;
  return $v;
}

$id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$organo       = trim($_POST['organo'] ?? '');
$especie_id   = isset($_POST['especie_id']) ? (int)$_POST['especie_id'] : 0;
$tamano       = isset($_POST['tamano']) ? trim((string)$_POST['tamano']) : null; // puede ser null
$etapa        = trim($_POST['etapa']  ?? '');
$unidad       = trim($_POST['unidad'] ?? 'cm');

$tamano_min       = num_or_null($_POST['tamano_min']       ?? null);
$tamano_max       = num_or_null($_POST['tamano_max']       ?? null);
$tamano_min_error = num_or_null($_POST['tamano_min_error'] ?? null);
$tamano_max_error = num_or_null($_POST['tamano_max_error'] ?? null);

// Validaciones mínimas
if ($organo === '')       jexit('error', 'Falta el nombre del órgano.');
if ($especie_id <= 0)     jexit('error', 'Falta la especie.');
if ($tamano_min === null) jexit('error', 'Falta el valor mínimo.');
if ($tamano_max === null) jexit('error', 'Falta el valor máximo.');
if ($unidad === '')       jexit('error', 'Falta la unidad.');

// Si no es Canino y no seleccionaste tamaño, puedes dejarlo null
if ($tamano === '') $tamano = null;

/**
 * === Verificación de duplicado: (especie_id + órgano) único ===
 * - Case-insensitive y sin espacios laterales
 * - En UPDATE excluimos el propio id
 */
$dupSql = "SELECT 1
           FROM organos_parametros
           WHERE especie_id = ?
             AND LOWER(TRIM(organo)) = LOWER(TRIM(?))
             AND (tamano <=> ?)";

$types = 'iss';
$args  = [$especie_id, $organo, $tamano]; // $tamano puede ser NULL

if ($id > 0) {
  $dupSql .= " AND id <> ?";
  $types  .= 'i';
  $args[]  = $id;
}

$dupStmt = $mysqli->prepare($dupSql);
if (!$dupStmt) jexit('error', 'Error al preparar validación de duplicado: ' . $mysqli->error);
if (!$dupStmt->bind_param($types, ...$args)) jexit('error', 'Error al bindear validación: ' . $dupStmt->error);
if (!$dupStmt->execute()) jexit('error', 'Error al ejecutar validación: ' . $dupStmt->error);
$dupStmt->store_result();

if ($dupStmt->num_rows > 0) {
  jexit('error', 'Ya existe este órgano para la especie y tamaño seleccionados.');
}
$dupStmt->close();

// INSERT vs UPDATE
if ($id > 0) {
  $sql = "UPDATE organos_parametros
          SET organo = ?, especie_id = ?, tamano = ?, etapa = ?,
              tamano_min = ?, tamano_max = ?,
              tamano_min_critico = ?, tamano_max_critico = ?,
              unidad = ?
          WHERE id = ?";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) jexit('error', 'Error al preparar (update): ' . $mysqli->error);

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
    jexit('error', 'Error al bindear (update): ' . $stmt->error);
  }

  if (!$stmt->execute()) jexit('error', 'Error al actualizar: ' . $stmt->error);
  jexit('ok', 'Parámetro actualizado correctamente.');

} else {
  $sql = "INSERT INTO organos_parametros
            (organo, especie_id, tamano, etapa, tamano_min, tamano_max, tamano_min_critico, tamano_max_critico, unidad)
          VALUES (?,?,?,?,?,?,?,?,?)";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) jexit('error', 'Error al preparar (insert): ' . $mysqli->error);

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
    jexit('error', 'Error al bindear (insert): ' . $stmt->error);
  }

  if (!$stmt->execute()) jexit('error', 'Error al insertar: ' . $stmt->error);
  jexit('ok', 'Parámetro agregado correctamente.', ['insert_id' => $stmt->insert_id]);
}
