<?php
// admin/certificado/pacientes/buscar_tutor.php

require_once(__DIR__ . "/../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

if (!$mysqli) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$veterinarioId = intval($_SESSION['usuario_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

if ($veterinarioId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strlen($q) < 3) {
    echo json_encode([
        'status' => 'success',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/*
 * Buscamos tutores pertenecientes al veterinario actual.
 *
 * Se puede encontrar un tutor mediante:
 * - nombre del tutor
 * - RUT del tutor
 * - nombre de una mascota
 * - N° de chip de una mascota
 * - código/ficha de una mascota
 *
 * EXISTS evita que el tutor aparezca repetido por cada mascota.
 */
$stmtTutores = $mysqli->prepare("
    SELECT
        t.id,
        t.nombre_completo,
        t.rut
    FROM tutores t
    WHERE
        t.veterinario_id = ?
        AND (
            t.nombre_completo LIKE CONCAT('%', ?, '%')
            OR t.rut LIKE CONCAT('%', ?, '%')
            OR EXISTS (
                SELECT 1
                FROM pacientes p
                WHERE
                    p.tutor_id = t.id
                    AND p.veterinario_id = ?
                    AND (
                        p.nombre LIKE CONCAT('%', ?, '%')
                        OR p.n_chip LIKE CONCAT('%', ?, '%')
                        OR p.codigo_paciente LIKE CONCAT('%', ?, '%')
                    )
            )
        )
    ORDER BY
        CASE
            WHEN t.nombre_completo LIKE CONCAT(?, '%') THEN 0
            ELSE 1
        END,
        t.nombre_completo ASC
    LIMIT 10
");

if (!$stmtTutores) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo preparar la búsqueda de tutores.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmtTutores->bind_param(
    "ississss",
    $veterinarioId,
    $q,
    $q,
    $veterinarioId,
    $q,
    $q,
    $q,
    $q
);

if (!$stmtTutores->execute()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo ejecutar la búsqueda de tutores.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$resTutores = $stmtTutores->get_result();

$stmtMascotas = $mysqli->prepare("
    SELECT
        id,
        nombre,
        codigo_paciente,
        especie,
        raza,
        n_chip
    FROM pacientes
    WHERE
        tutor_id = ?
        AND veterinario_id = ?
    ORDER BY nombre ASC
");

if (!$stmtMascotas) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo preparar la búsqueda de mascotas.',
        'matches' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$matches = [];

while ($tutor = $resTutores->fetch_assoc()) {
    $tutorId = intval($tutor['id']);

    $stmtMascotas->bind_param(
        "ii",
        $tutorId,
        $veterinarioId
    );

    if (!$stmtMascotas->execute()) {
        continue;
    }

    $resMascotas = $stmtMascotas->get_result();

    $mascotas = [];

    while ($mascota = $resMascotas->fetch_assoc()) {
        $mascotas[] = [
            'paciente_id' => intval($mascota['id']),
            'nombre' => (string)($mascota['nombre'] ?? ''),
            'codigo_paciente' => (string)($mascota['codigo_paciente'] ?? ''),
            'especie' => (string)($mascota['especie'] ?? ''),
            'raza' => (string)($mascota['raza'] ?? ''),
            'n_chip' => (string)($mascota['n_chip'] ?? '')
        ];
    }

    $matches[] = [
        'tutor_id' => $tutorId,
        'nombre' => (string)($tutor['nombre_completo'] ?? ''),
        'rut' => (string)($tutor['rut'] ?? ''),
        'mascotas' => $mascotas
    ];
}

echo json_encode([
    'status' => 'success',
    'matches' => $matches
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;