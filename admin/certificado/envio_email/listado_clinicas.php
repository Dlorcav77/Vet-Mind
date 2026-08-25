<?php

// admin/certificado/envio_email/listado_clinicas.php

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

$out = [
    'status' => 'success',
    'clinicas' => []
];

$stmt = $mysqli->prepare("
    SELECT id, nombre_clinica, correo
    FROM clinicas
    WHERE veterinario_id = ?
      AND correo <> ''
    ORDER BY nombre_clinica ASC
");

$stmt->bind_param('i', $usuario_id);
$stmt->execute();

$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $out['clinicas'][] = $r;
}

echo json_encode(
    $out,
    JSON_UNESCAPED_UNICODE
);