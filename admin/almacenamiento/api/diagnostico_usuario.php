<?php
// admin/almacenamiento/api/diagnostico_usuario.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

credenciales('certificado', 'listar');

$mysqli = conn();
global $usuario_id;

$usuarioActual = (int)($usuario_id ?? ($_SESSION['usuario_id'] ?? 0));

if ($usuarioActual <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function diag_json_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function diag_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);

    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function diag_ruta_permitida(string $ruta): bool
{
    $ruta = trim($ruta);

    if ($ruta === '') {
        return false;
    }

    if (strpos($ruta, '..') !== false) {
        return false;
    }

    $permitidas = [
        'uploads/certificados/informes/',
        'uploads/certificados/img/',
        'uploads/certificados/informes_subidos/'
    ];

    foreach ($permitidas as $base) {
        if (strpos($ruta, $base) === 0) {
            return true;
        }
    }

    return false;
}

function diag_info_archivo(string $baseProyecto, string $ruta): array
{
    $ruta = trim($ruta);

    $info = [
        'ruta' => $ruta,
        'nombre' => basename($ruta),
        'permitida' => diag_ruta_permitida($ruta),
        'existe' => false,
        'size_bytes' => 0,
        'size_label' => '0 B',
        'ruta_fisica' => ''
    ];

    if (!$info['permitida']) {
        return $info;
    }

    $rutaFisica = realpath($baseProyecto . '/' . $ruta);

    if (!$rutaFisica || strpos($rutaFisica, $baseProyecto) !== 0) {
        return $info;
    }

    $info['ruta_fisica'] = $rutaFisica;

    if (is_file($rutaFisica)) {
        $size = (int)filesize($rutaFisica);

        $info['existe'] = true;
        $info['size_bytes'] = $size;
        $info['size_label'] = diag_format_bytes($size);
    }

    return $info;
}

$baseProyecto = realpath(__DIR__ . '/../../../');

if (!$baseProyecto) {
    diag_json_out([
        'status' => 'error',
        'message' => 'No se pudo resolver la ruta base del proyecto.'
    ]);
}

$veterinarioId = (int)($_GET['usuario_id'] ?? $usuarioActual);

// Por seguridad, por ahora solo permite diagnosticar el usuario actual.
// Si después confirmamos cómo identificar admin, habilitamos diagnosticar usuario 57 desde el mismo panel.
if ($veterinarioId !== $usuarioActual) {
    diag_json_out([
        'status' => 'error',
        'message' => 'Por seguridad, este diagnóstico solo permite revisar el usuario actual.'
    ]);
}

$sql = "
    SELECT
        id,
        veterinario_id,
        paciente_id,
        fecha_examen,
        archivo_pdf,
        imagenes_json,
        created_at,
        updated_at
    FROM certificados
    WHERE veterinario_id = ?
      AND deleted_at IS NULL
    ORDER BY id ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    diag_json_out([
        'status' => 'error',
        'message' => 'Error preparando consulta.',
        'mysql_error' => $mysqli->error
    ]);
}

$stmt->bind_param('i', $veterinarioId);
$stmt->execute();

$res = $stmt->get_result();

$registrados = [];
$registradosPorRuta = [];

$resumen = [
    'usuario_id' => $veterinarioId,
    'certificados' => 0,
    'pdfs_registrados' => 0,
    'imagenes_registradas' => 0,
    'registrados_existentes' => 0,
    'registrados_faltantes' => 0,
    'registrados_bytes' => 0,
    'registrados_label' => '0 B',
    'fisicos_usuario' => 0,
    'fisicos_usuario_bytes' => 0,
    'fisicos_usuario_label' => '0 B',
    'huerfanos_usuario' => 0,
    'huerfanos_usuario_bytes' => 0,
    'huerfanos_usuario_label' => '0 B'
];

while ($row = $res->fetch_assoc()) {
    $resumen['certificados']++;

    $certificadoId = (int)$row['id'];

    if (!empty($row['archivo_pdf'])) {
        $rutaPdf = trim((string)$row['archivo_pdf']);
        $infoPdf = diag_info_archivo($baseProyecto, $rutaPdf);

        $item = [
            'tipo' => 'PDF',
            'certificado_id' => $certificadoId,
            'fecha_examen' => (string)$row['fecha_examen'],
            'ruta' => $rutaPdf,
            'nombre' => basename($rutaPdf),
            'existe' => $infoPdf['existe'],
            'estado' => $infoPdf['existe'] ? 'Existe' : 'No existe',
            'size_bytes' => $infoPdf['size_bytes'],
            'size_label' => $infoPdf['size_label']
        ];

        $registrados[] = $item;
        $registradosPorRuta[$rutaPdf] = true;

        $resumen['pdfs_registrados']++;

        if ($infoPdf['existe']) {
            $resumen['registrados_existentes']++;
            $resumen['registrados_bytes'] += $infoPdf['size_bytes'];
        } else {
            $resumen['registrados_faltantes']++;
        }
    }

    if (!empty($row['imagenes_json'])) {
        $imagenes = json_decode($row['imagenes_json'], true);

        if (is_array($imagenes)) {
            foreach ($imagenes as $rutaImagen) {
                $rutaImagen = trim((string)$rutaImagen);

                if ($rutaImagen === '') {
                    continue;
                }

                $infoImg = diag_info_archivo($baseProyecto, $rutaImagen);

                $item = [
                    'tipo' => 'IMG',
                    'certificado_id' => $certificadoId,
                    'fecha_examen' => (string)$row['fecha_examen'],
                    'ruta' => $rutaImagen,
                    'nombre' => basename($rutaImagen),
                    'existe' => $infoImg['existe'],
                    'estado' => $infoImg['existe'] ? 'Existe' : 'No existe',
                    'size_bytes' => $infoImg['size_bytes'],
                    'size_label' => $infoImg['size_label']
                ];

                $registrados[] = $item;
                $registradosPorRuta[$rutaImagen] = true;

                $resumen['imagenes_registradas']++;

                if ($infoImg['existe']) {
                    $resumen['registrados_existentes']++;
                    $resumen['registrados_bytes'] += $infoImg['size_bytes'];
                } else {
                    $resumen['registrados_faltantes']++;
                }
            }
        }
    }
}

$archivosFisicosUsuario = [];

$patrones = [
    'uploads/certificados/informes/' => 'cert_' . $veterinarioId . '_',
    'uploads/certificados/img/' => 'img_' . $veterinarioId . '_',
    'uploads/certificados/informes_subidos/' => 'informe_' . $veterinarioId . '_'
];

foreach ($patrones as $dirRel => $prefijo) {
    $dirAbs = $baseProyecto . '/' . $dirRel;

    if (!is_dir($dirAbs)) {
        continue;
    }

    $files = scandir($dirAbs);

    if (!is_array($files)) {
        continue;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        if (strpos($file, $prefijo) !== 0) {
            continue;
        }

        $rutaRel = $dirRel . $file;
        $rutaAbs = $dirAbs . $file;

        if (!is_file($rutaAbs)) {
            continue;
        }

        $size = (int)filesize($rutaAbs);
        $estaReferenciado = isset($registradosPorRuta[$rutaRel]);

        $itemFisico = [
            'ruta' => $rutaRel,
            'nombre' => basename($rutaRel),
            'size_bytes' => $size,
            'size_label' => diag_format_bytes($size),
            'referenciado' => $estaReferenciado
        ];

        $archivosFisicosUsuario[] = $itemFisico;

        $resumen['fisicos_usuario']++;
        $resumen['fisicos_usuario_bytes'] += $size;

        if (!$estaReferenciado) {
            $resumen['huerfanos_usuario']++;
            $resumen['huerfanos_usuario_bytes'] += $size;
        }
    }
}

$resumen['registrados_label'] = diag_format_bytes((int)$resumen['registrados_bytes']);
$resumen['fisicos_usuario_label'] = diag_format_bytes((int)$resumen['fisicos_usuario_bytes']);
$resumen['huerfanos_usuario_label'] = diag_format_bytes((int)$resumen['huerfanos_usuario_bytes']);

$faltantes = array_values(array_filter($registrados, function ($item) {
    return empty($item['existe']);
}));

$existentes = array_values(array_filter($registrados, function ($item) {
    return !empty($item['existe']);
}));

$huerfanos = array_values(array_filter($archivosFisicosUsuario, function ($item) {
    return empty($item['referenciado']);
}));

diag_json_out([
    'status' => 'success',
    'resumen' => $resumen,
    'faltantes' => $faltantes,
    'existentes' => $existentes,
    'huerfanos' => $huerfanos
]);