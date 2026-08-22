<?php
// admin/certificado/pacientes/buscar_codigo.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$codigo = trim((string)($_GET['q'] ?? ''));

if ($usuario_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($codigo === '') {
    echo json_encode([
        'status' => 'success',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT
        p.id AS paciente_id,
        p.nombre AS paciente,
        p.codigo_paciente,
        p.especie,
        p.raza,
        p.sexo,
        p.fecha_nacimiento,
        p.n_chip,
        p.tutor_id,
        t.nombre_completo AS propietario,
        t.rut AS tutor_rut
    FROM pacientes p
    LEFT JOIN tutores t
        ON t.id = p.tutor_id
        AND t.veterinario_id = p.veterinario_id
    WHERE p.codigo_paciente = ?
      AND p.veterinario_id = ?
    ORDER BY
        p.nombre ASC,
        p.id ASC
    LIMIT 20
");

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error preparando búsqueda.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt->bind_param(
    "si",
    $codigo,
    $usuario_id
);

$stmt->execute();

$res = $stmt->get_result();

$matches = [];

while ($row = $res->fetch_assoc()) {
    $matches[] = [
        'paciente_id' => (int)($row['paciente_id'] ?? 0),
        'paciente' => trim((string)($row['paciente'] ?? '')),
        'codigo_paciente' => trim((string)($row['codigo_paciente'] ?? '')),
        'especie' => trim((string)($row['especie'] ?? '')),
        'raza' => trim((string)($row['raza'] ?? '')),
        'sexo' => trim((string)($row['sexo'] ?? '')),
        'fecha_nacimiento' => trim((string)($row['fecha_nacimiento'] ?? '')),
        'n_chip' => trim((string)($row['n_chip'] ?? '')),
        'propietario' => trim((string)($row['propietario'] ?? '')),
        'tutor_rut' => trim((string)($row['tutor_rut'] ?? ''))
    ];
}

echo json_encode([
    'status' => 'success',
    'matches' => $matches
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;