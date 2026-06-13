<?php
//admin/certificado/guardar/upBorradorCertificado.php
require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);

if (!$mysqli) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($usuario_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$accionBorrador = trim((string)($_POST['action_borrador'] ?? 'guardar'));
$actionFormulario = trim((string)($_POST['action'] ?? 'ingresar'));
$certificadoId = (int)($_POST['id'] ?? 0);
$scopeKey = trim((string)($_POST['borrador_scope_key'] ?? ''));

if ($scopeKey === '') {
    $scopeKey = ($actionFormulario === 'modificar' && $certificadoId > 0)
        ? 'modificar:' . $certificadoId
        : 'nuevo';
}

if ($accionBorrador === 'descartar') {
    $stmt = $mysqli->prepare("
        UPDATE certificados_borradores
        SET estado = 'descartado', updated_at = NOW()
        WHERE veterinario_id = ?
          AND scope_key = ?
          AND estado = 'activo'
    ");

    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo preparar el descarte.',
            'mysql_error' => $mysqli->error
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt->bind_param("is", $usuario_id, $scopeKey);
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Borrador descartado.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($actionFormulario === 'modificar') {
    echo json_encode([
        'status' => 'success',
        'message' => 'Borrador omitido en modificar.',
        'borrador_id' => 0
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$manual = [];
foreach ($_POST as $k => $v) {
    if (strpos($k, 'manual_') === 0) {
        $manual[substr($k, 7)] = is_array($v) ? $v : trim((string)$v);
    }
}

$payload = [
    'paciente_id'               => (int)($_POST['paciente_id'] ?? 0),
    'paciente_label'            => trim((string)($_POST['paciente_label'] ?? '')),
    'fecha_examen'              => trim((string)($_POST['fecha_examen'] ?? '')),
    'motivo_examen'             => trim((string)($_POST['motivo_examen'] ?? '')),
    'medico_solicitante'        => trim((string)($_POST['medico_solicitante'] ?? '')),
    'recinto'                   => trim((string)($_POST['recinto'] ?? '')),
    'plantilla_informe_id'      => (int)($_POST['plantilla_informe_id'] ?? 0),
    'configuracion_informe_id'  => (int)($_POST['configuracion_informe_id'] ?? 0),
    'toggle_manual'             => isset($_POST['toggle_manual']) && (string)$_POST['toggle_manual'] === '1' ? 1 : 0,
    'toggle_audio_manual'       => isset($_POST['toggle_audio_manual']) && (string)$_POST['toggle_audio_manual'] === '1' ? 1 : 0,
    'contenido_html'            => trim((string)($_POST['contenido_html'] ?? '')),
    'manual_data'               => $manual,
    'plantillaBase'             => trim((string)($_POST['plantillaBase'] ?? '')),
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$stmt = $mysqli->prepare("
    INSERT INTO certificados_borradores
        (veterinario_id, certificado_id, scope_key, estado, payload_json, created_at, updated_at)
    VALUES
        (?, ?, ?, 'activo', ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        certificado_id = VALUES(certificado_id),
        estado = 'activo',
        payload_json = VALUES(payload_json),
        updated_at = NOW()
");

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo preparar el borrador.',
        'mysql_error' => $mysqli->error
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$certificadoIdDb = ($actionFormulario === 'modificar' && $certificadoId > 0) ? $certificadoId : null;

$stmt->bind_param(
    "iiss",
    $usuario_id,
    $certificadoIdDb,
    $scopeKey,
    $payloadJson
);

if (!$stmt->execute()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo guardar el borrador.',
        'mysql_error' => $stmt->error
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt2 = $mysqli->prepare("
    SELECT id, updated_at
    FROM certificados_borradores
    WHERE veterinario_id = ?
      AND scope_key = ?
      AND estado = 'activo'
    LIMIT 1
");

if (!$stmt2) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Borrador guardado.',
        'borrador_id' => 0
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt2->bind_param("is", $usuario_id, $scopeKey);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row = $res2->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'message' => 'Borrador guardado.',
    'borrador_id' => (int)($row['id'] ?? 0),
    'updated_at' => $row['updated_at'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;