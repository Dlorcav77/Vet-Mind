<?php
// admin/certificado/guardar/updBorradorCertificado.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

function jexitBorrador(string $status, string $message, array $extra = []): void
{
    echo json_encode(
        array_merge(['status' => $status, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (!$mysqli) {
    jexitBorrador('error', 'No se pudo conectar a la base de datos.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jexitBorrador('error', 'Método no permitido.');
}

validarTokenCsrf();

$veterinario = (int)$usuario_id;
$accionBorrador = trim((string)($_POST['action_borrador'] ?? 'guardar'));
$actionFormulario = trim((string)($_POST['action'] ?? 'ingresar'));
$certificadoId = (int)($_POST['id'] ?? 0);

if ($veterinario <= 0) {
    http_response_code(401);
    jexitBorrador('error', 'Sesión inválida.');
}

if (!in_array($accionBorrador, ['guardar', 'descartar'], true)) {
    jexitBorrador('error', 'Acción de borrador no válida.');
}

if (!in_array($actionFormulario, ['ingresar', 'modificar'], true)) {
    jexitBorrador('error', 'Acción de formulario no válida.');
}

if ($actionFormulario === 'modificar') {
    credenciales('certificado', 'modificar');

    if ($certificadoId <= 0) {
        jexitBorrador('error', 'Certificado inválido.');
    }

    $stmtCert = $mysqli->prepare(
        "SELECT id FROM certificados
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );

    if (!$stmtCert) {
        error_log('[updBorradorCertificado][certificado][prepare] ' . $mysqli->error);
        jexitBorrador('error', 'No se pudo validar el certificado.');
    }

    $stmtCert->bind_param('ii', $certificadoId, $veterinario);

    if (!$stmtCert->execute()) {
        error_log('[updBorradorCertificado][certificado][execute] ' . $stmtCert->error);
        $stmtCert->close();
        jexitBorrador('error', 'No se pudo validar el certificado.');
    }

    $existeCertificado = $stmtCert->get_result()->num_rows > 0;
    $stmtCert->close();

    if (!$existeCertificado) {
        http_response_code(403);
        jexitBorrador('error', 'Certificado no encontrado o sin permisos.');
    }

    $scopeKey = 'modificar:' . $certificadoId;
} else {
    credenciales('certificado', 'ingresar');
    $scopeKey = 'nuevo';
}

if ($accionBorrador === 'descartar') {
    $stmt = $mysqli->prepare(
        "UPDATE certificados_borradores
         SET estado = 'descartado', updated_at = NOW()
         WHERE veterinario_id = ?
           AND scope_key = ?
           AND estado = 'activo'"
    );

    if (!$stmt) {
        error_log('[updBorradorCertificado][descartar][prepare] ' . $mysqli->error);
        jexitBorrador('error', 'No se pudo preparar el descarte.');
    }

    $stmt->bind_param('is', $veterinario, $scopeKey);

    if (!$stmt->execute()) {
        error_log('[updBorradorCertificado][descartar][execute] ' . $stmt->error);
        $stmt->close();
        jexitBorrador('error', 'No se pudo descartar el borrador.');
    }

    $stmt->close();
    jexitBorrador('success', 'Borrador descartado.');
}

/*
 * Por diseño actual no guardamos borradores mientras
 * se modifica un certificado existente.
 */
if ($actionFormulario === 'modificar') {
    jexitBorrador('success', 'Borrador omitido en modificar.', ['borrador_id' => 0]);
}

$manual = [];

foreach ($_POST as $k => $v) {
    if (strpos($k, 'manual_') === 0) {
        $manual[substr($k, 7)] = is_array($v) ? $v : trim((string)$v);
    }
}

$payload = [
    'paciente_id' => (int)($_POST['paciente_id'] ?? 0),
    'paciente_label' => trim((string)($_POST['paciente_label'] ?? '')),
    'fecha_examen' => trim((string)($_POST['fecha_examen'] ?? '')),
    'motivo_examen' => trim((string)($_POST['motivo_examen'] ?? '')),
    'medico_solicitante' => trim((string)($_POST['medico_solicitante'] ?? '')),
    'recinto' => trim((string)($_POST['recinto'] ?? '')),
    'plantilla_informe_id' => (int)($_POST['plantilla_informe_id'] ?? 0),
    'configuracion_informe_id' => (int)($_POST['configuracion_informe_id'] ?? 0),
    'toggle_manual' => isset($_POST['toggle_manual']) && (string)$_POST['toggle_manual'] === '1' ? 1 : 0,
    'toggle_audio_manual' => isset($_POST['toggle_audio_manual']) && (string)$_POST['toggle_audio_manual'] === '1' ? 1 : 0,
    'contenido_html' => trim((string)($_POST['contenido_html'] ?? '')),
    'manual_data' => $manual,
    'plantillaBase' => trim((string)($_POST['plantillaBase'] ?? '')),
    'rid_ia' => trim((string)($_POST['rid_ia'] ?? '')),
    'rid_revision' => trim((string)($_POST['rid_revision'] ?? '')),
    'audio_tmp' => trim((string)($_POST['audio_tmp'] ?? '')),
    'es_destacado' => isset($_POST['es_destacado']) && (string)$_POST['es_destacado'] === '1' ? 1 : 0,
    'destacado_titulo' => trim((string)($_POST['destacado_titulo'] ?? ''))
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payloadJson === false) {
    jexitBorrador('error', 'No se pudo preparar el contenido del borrador.');
}

$certificadoIdDb = null;

$stmt = $mysqli->prepare(
    "INSERT INTO certificados_borradores
        (veterinario_id, certificado_id, scope_key, estado, payload_json, created_at, updated_at)
     VALUES (?, ?, ?, 'activo', ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
        certificado_id = VALUES(certificado_id),
        estado = 'activo',
        payload_json = VALUES(payload_json),
        updated_at = NOW()"
);

if (!$stmt) {
    error_log('[updBorradorCertificado][guardar][prepare] ' . $mysqli->error);
    jexitBorrador('error', 'No se pudo preparar el borrador.');
}

$stmt->bind_param('iiss', $veterinario, $certificadoIdDb, $scopeKey, $payloadJson);

if (!$stmt->execute()) {
    error_log('[updBorradorCertificado][guardar][execute] ' . $stmt->error);
    $stmt->close();
    jexitBorrador('error', 'No se pudo guardar el borrador.');
}

$stmt->close();

$stmt2 = $mysqli->prepare(
    "SELECT id, updated_at
     FROM certificados_borradores
     WHERE veterinario_id = ?
       AND scope_key = ?
       AND estado = 'activo'
     LIMIT 1"
);

if (!$stmt2) {
    error_log('[updBorradorCertificado][select][prepare] ' . $mysqli->error);
    jexitBorrador('success', 'Borrador guardado.', ['borrador_id' => 0]);
}

$stmt2->bind_param('is', $veterinario, $scopeKey);

if (!$stmt2->execute()) {
    error_log('[updBorradorCertificado][select][execute] ' . $stmt2->error);
    $stmt2->close();
    jexitBorrador('success', 'Borrador guardado.', ['borrador_id' => 0]);
}

$row = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

jexitBorrador('success', 'Borrador guardado.', [
    'borrador_id' => (int)($row['id'] ?? 0),
    'updated_at' => $row['updated_at'] ?? null
]);