<?php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

function jexit(string $status, string $message, ?string $correo = null): void
{
    $response = ['status' => $status, 'message' => $message];

    if ($correo !== null) {
        $response['correo'] = $correo;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jexit('error', 'Método no permitido.');
}

validarTokenCsrf();
credenciales('certificado', 'listar');

$id = (int)($_POST['id'] ?? 0);
$veterinarioId = (int)$usuario_id;

if ($id <= 0 || $veterinarioId <= 0) {
    jexit('error', 'Certificado inválido.');
}

$stmt = $mysqli->prepare("
    SELECT t.email
    FROM certificados c
    LEFT JOIN pacientes p ON c.paciente_id = p.id
    LEFT JOIN tutores t ON p.tutor_id = t.id
    WHERE c.id = ?
      AND c.veterinario_id = ?
    LIMIT 1
");

if (!$stmt) {
    error_log('[get_email_certificado][prepare] ' . $mysqli->error);
    jexit('error', 'No se pudo consultar el correo.');
}

$stmt->bind_param('ii', $id, $veterinarioId);

if (!$stmt->execute()) {
    error_log('[get_email_certificado][execute] ' . $stmt->error);
    $stmt->close();
    jexit('error', 'No se pudo consultar el correo.');
}

$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    jexit('error', 'Certificado no encontrado o sin permisos.');
}

$correo = trim((string)($row['email'] ?? ''));

if ($correo === '') {
    jexit('error', 'No hay correo asociado al tutor.');
}

jexit('success', 'Correo encontrado.', $correo);