<?php
// admin/certificado/pdf/descargar.php

require_once(__DIR__ . "/../../config.php");
date_default_timezone_set('America/Santiago');

$mysqli = conn();
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
$dl = (int)($_GET['dl'] ?? 0);

if (!$usuario_id || $id <= 0) {
    http_response_code(400);
    echo "Solicitud inválida.";
    exit;
}

$stmt = $mysqli->prepare("
    SELECT c.archivo_pdf, c.manual_data, c.paciente_id,
           p.nombre AS paciente_nombre, p.codigo_paciente
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
        $codigoPaciente = $codigoPaciente ?: trim((string)(
            $md['codigo_paciente'] ?? $md['cod_paciente'] ?? ''
        ));
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
$full = realpath(__DIR__ . "/../../../" . $rel);
$allowedBase = realpath(__DIR__ . "/../../../uploads/certificados/informes");

$allowedPrefix = $allowedBase
    ? rtrim($allowedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    : '';

if (
    !$full ||
    !$allowedBase ||
    strpos($full, $allowedPrefix) !== 0 ||
    !is_file($full)
) {
    http_response_code(404);
    echo "Archivo inválido.";
    exit;
}

$fileSize = filesize($full);

if ($fileSize === false || $fileSize <= 0) {
    http_response_code(404);
    echo "Archivo vacío o inválido.";
    exit;
}

/*
 * Evitamos compresión/buffering sobre un PDF.
 * Es importante para que Content-Length y Range
 * representen los bytes reales del archivo.
 */
@ini_set('zlib.output_compression', 'Off');

while (ob_get_level() > 0) {
    @ob_end_clean();
}

$disposition = $dl === 1 ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header("Content-Disposition: {$disposition}; filename=\"" . addslashes($downloadName) . "\"; filename*=UTF-8''" . rawurlencode($downloadName));

$start = 0;
$end = $fileSize - 1;
$statusCode = 200;

$rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));

if ($rangeHeader !== '') {
    /*
     * Soportamos un solo rango, que es lo habitual
     * para visores PDF del navegador.
     */
    if (
        !preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $matches) ||
        strpos($rangeHeader, ',') !== false
    ) {
        http_response_code(416);
        header("Content-Range: bytes */{$fileSize}");
        exit;
    }

    $rangeStart = $matches[1];
    $rangeEnd = $matches[2];

    /*
     * bytes=-500
     * Últimos 500 bytes.
     */
    if ($rangeStart === '' && $rangeEnd !== '') {
        $suffixLength = (int)$rangeEnd;

        if ($suffixLength <= 0) {
            http_response_code(416);
            header("Content-Range: bytes */{$fileSize}");
            exit;
        }

        $suffixLength = min($suffixLength, $fileSize);
        $start = $fileSize - $suffixLength;
        $end = $fileSize - 1;

    /*
     * bytes=500-
     * Desde el byte 500 hasta el final.
     */
    } elseif ($rangeStart !== '' && $rangeEnd === '') {
        $start = (int)$rangeStart;
        $end = $fileSize - 1;

    /*
     * bytes=500-999
     */
    } else {
        $start = (int)$rangeStart;
        $end = (int)$rangeEnd;
    }

    if (
        $start < 0 ||
        $start >= $fileSize ||
        $end < $start
    ) {
        http_response_code(416);
        header("Content-Range: bytes */{$fileSize}");
        exit;
    }

    if ($end >= $fileSize) {
        $end = $fileSize - 1;
    }

    $statusCode = 206;
}

$contentLength = ($end - $start) + 1;

http_response_code($statusCode);
header('Content-Length: ' . $contentLength);

if ($statusCode === 206) {
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
}

$fp = fopen($full, 'rb');

if (!$fp) {
    http_response_code(500);
    exit;
}

if ($start > 0) {
    fseek($fp, $start);
}

$remaining = $contentLength;
$chunkSize = 1024 * 1024; // 1 MB

while ($remaining > 0 && !feof($fp)) {
    $readLength = min($chunkSize, $remaining);
    $buffer = fread($fp, $readLength);

    if ($buffer === false || $buffer === '') {
        break;
    }

    echo $buffer;
    $remaining -= strlen($buffer);

    flush();

    if (connection_aborted()) {
        break;
    }
}

fclose($fp);
exit;


function slug_filename(string $s, bool $keepDashes = false): string {
    $s = trim($s);

    if ($s === '') {
        return 'archivo';
    }

    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

    if ($t !== false) {
        $s = $t;
    }

    $s = strtolower($s);

    $pattern = $keepDashes
        ? '/[^a-z0-9\-\_\s]+/'
        : '/[^a-z0-9\_\s]+/';

    $s = preg_replace($pattern, '', $s);
    $s = preg_replace('/\s+/', '_', $s);
    $s = preg_replace('/_+/', '_', $s);

    return trim($s, '_');
}