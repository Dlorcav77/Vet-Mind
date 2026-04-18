<?php
require_once("../config.php");
date_default_timezone_set('America/Santiago');

$mysqli = conn();
$usuario_id = $_SESSION['usuario_id'] ?? 0;

$id = (int)($_GET['id'] ?? 0);
$dl = (int)($_GET['dl'] ?? 0);

if (!$usuario_id || $id <= 0) {
    http_response_code(400);
    echo "Solicitud inválida.";
    exit;
}

$stmt = $mysqli->prepare("
    SELECT
        c.archivo_pdf,
        c.manual_data,
        c.paciente_id,
        p.nombre AS paciente_nombre,
        p.codigo_paciente AS codigo_paciente
    FROM certificados c
    LEFT JOIN pacientes p ON c.paciente_id = p.id
    WHERE c.id = ? AND c.veterinario_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $id, $usuario_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || empty($row['archivo_pdf'])) {
    http_response_code(404);
    echo "PDF no encontrado.";
    exit;
}

$pacienteNombre = trim((string)($row['paciente_nombre'] ?? ''));
$codigoPaciente = trim((string)($row['codigo_paciente'] ?? ''));

if ($pacienteNombre === '' && !empty($row['manual_data'])) {
    $md = json_decode($row['manual_data'], true);
    if (is_array($md)) {
        $pacienteNombre = trim((string)($md['paciente'] ?? ''));
        $codigoPaciente = $codigoPaciente ?: trim((string)($md['codigo_paciente'] ?? $md['cod_paciente'] ?? ''));
    }
}

if ($pacienteNombre === '') {
    $pacienteNombre = "certificado_{$id}";
}

$base = slug_filename($pacienteNombre);
if ($codigoPaciente !== '') {
    $base .= '(' . slug_filename($codigoPaciente, true) . ')';
}
$downloadName = $base . '.pdf';

$rel = ltrim((string)$row['archivo_pdf'], '/');
$full = realpath(__DIR__ . "/../../" . $rel);

$allowedBase = realpath(__DIR__ . "/../../uploads/certificados/informes");
if (!$full || !$allowedBase || strpos($full, $allowedBase) !== 0 || !file_exists($full)) {
    http_response_code(404);
    echo "Archivo inválido.";
    exit;
}

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');

$disposition = $dl === 1 ? 'attachment' : 'inline';
header("Content-Disposition: {$disposition}; filename=\"" . addslashes($downloadName) . "\"; filename*=UTF-8''" . rawurlencode($downloadName));
header('Content-Length: ' . filesize($full));

readfile($full);
exit;

function slug_filename(string $s, bool $keepDashes = false): string {
    $s = trim($s);
    if ($s === '') return 'archivo';

    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) $s = $t;

    $s = strtolower($s);

    $pattern = $keepDashes ? '/[^a-z0-9\-\_\s]+/' : '/[^a-z0-9\_\s]+/';
    $s = preg_replace($pattern, '', $s);

    $s = preg_replace('/\s+/', '_', $s);
    $s = preg_replace('/_+/', '_', $s);

    return trim($s, '_');
}