<?php
// admin/almacenamiento/api/listar_archivos.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
global $usuario_id;

$baseProyecto = realpath(__DIR__ . '/../../../');

if (!$baseProyecto) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo resolver la ruta base del proyecto.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$usuarioActual = (int)($usuario_id ?? ($_SESSION['usuario_id'] ?? 0));

if ($usuarioActual <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function alm_json_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function alm_formatear_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);

    return round($bytes / pow(1024, $i), 2) . ' ' . $unidades[$i];
}

function alm_ruta_permitida(string $ruta): bool
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

function alm_info_archivo(string $ruta): array
{
    $ruta = trim($ruta);

    $info = [
        'ruta' => $ruta,
        'nombre' => basename($ruta),
        'existe' => false,
        'size_bytes' => 0,
        'size_label' => '0 B'
    ];

    if (!alm_ruta_permitida($ruta)) {
        return $info;
    }

    $baseProyecto = realpath(__DIR__ . '/../../../');

    if (!$baseProyecto) {
        return $info;
    }

    $rutaFisica = realpath($baseProyecto . '/' . $ruta);

    if (!$rutaFisica || strpos($rutaFisica, $baseProyecto) !== 0) {
        return $info;
    }

    if (is_file($rutaFisica)) {
        $size = (int)filesize($rutaFisica);

        $info['existe'] = true;
        $info['size_bytes'] = $size;
        $info['size_label'] = alm_formatear_bytes($size);
    }

    return $info;
}

function alm_parse_audio_certificado(string $nombreArchivo, int $usuarioId): array
{
    $nombreArchivo = basename(trim($nombreArchivo));

    $resultado = [
        'ok' => false,
        'usuario_id' => 0,
        'certificado_id' => 0
    ];

    if ($nombreArchivo === '') {
        return $resultado;
    }

    if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'wav') {
        return $resultado;
    }

    $pattern = '/^' . preg_quote((string)$usuarioId, '/') . '_(\d+)_(\d{2})_(\d{6,})\.wav$/';

    if (!preg_match($pattern, $nombreArchivo, $matches)) {
        return $resultado;
    }

    $resultado['ok'] = true;
    $resultado['usuario_id'] = $usuarioId;
    $resultado['certificado_id'] = (int)$matches[1];

    return $resultado;
}

function alm_listar_grabaciones_usuario(string $baseProyecto, int $usuarioId): array
{
    $baseGrabaciones = $baseProyecto . '/uploads/certificados/audio';

    $resultado = [
        'total' => 0,
        'bytes' => 0,
        'label' => '0 B',
        'items' => []
    ];

    if ($usuarioId <= 0 || !is_dir($baseGrabaciones)) {
        return $resultado;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseGrabaciones, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $nombre = $fileInfo->getFilename();
        $audioInfo = alm_parse_audio_certificado($nombre, $usuarioId);

        if (empty($audioInfo['ok'])) {
            continue;
        }

        $rutaFisica = $fileInfo->getPathname();
        $rutaRelativa = str_replace($baseProyecto . '/', '', $rutaFisica);
        $rutaRelativa = str_replace('\\', '/', $rutaRelativa);

        $size = (int)$fileInfo->getSize();

        $resultado['total']++;
        $resultado['bytes'] += $size;

        $resultado['items'][] = [
            'certificado_id' => (int)$audioInfo['certificado_id'],
            'tipo' => 'Audio',
            'nombre' => $nombre,
            'ruta' => $rutaRelativa,
            'url_ver' => '../' . $rutaRelativa,
            'url_descargar' => '../' . $rutaRelativa,
            'size_bytes' => $size,
            'size_label' => alm_formatear_bytes($size),
            'fecha_archivo' => date('d-m-Y H:i', $fileInfo->getMTime()),
            'fecha_sort' => date('Y-m-d H:i:s', $fileInfo->getMTime())
        ];
    }

    usort($resultado['items'], function ($a, $b) {
        return strcmp((string)$b['fecha_sort'], (string)$a['fecha_sort']);
    });

    $resultado['label'] = alm_formatear_bytes((int)$resultado['bytes']);

    return $resultado;
}

function alm_normalizar_texto_grupo(string $texto): string
{
    $texto = trim(mb_strtolower($texto, 'UTF-8'));
    $texto = preg_replace('/\s+/', ' ', $texto);

    return $texto ?: '-';
}

$sql = "
    SELECT
        c.id,
        c.veterinario_id,
        c.paciente_id,
        c.manual_data,
        c.tipo_estudio,
        c.fecha_examen,
        c.archivo_pdf,
        c.imagenes_json,
        c.tipo_ingreso,
        c.created_at,
        p.nombre AS paciente,
        t.nombre_completo AS propietario,
        pi.nombre AS tipo_examen
    FROM certificados c
    LEFT JOIN pacientes p ON c.paciente_id = p.id
    LEFT JOIN tutores t ON p.tutor_id = t.id
    LEFT JOIN plantilla_informe pi ON c.tipo_estudio = pi.id
    WHERE c.veterinario_id = ?
      AND c.deleted_at IS NULL
    ORDER BY c.created_at DESC, c.id DESC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    alm_json_out([
        'status' => 'error',
        'message' => 'Error preparando consulta.',
        'mysql_error' => $mysqli->error
    ]);
}

$stmt->bind_param('i', $usuarioActual);
$stmt->execute();

$res = $stmt->get_result();

$grupos = [];
$certificadoGrupoMap = [];

$resumen = [
    'total_grupos' => 0,
    'total_informes' => 0,
    'total_archivos' => 0,
    'total_bytes' => 0,
    'pdf_total' => 0,
    'pdf_bytes' => 0,
    'imagenes_total' => 0,
    'imagenes_bytes' => 0,
    'audios_total' => 0,
    'audios_bytes' => 0,
    'grabaciones_total' => 0,
    'grabaciones_bytes' => 0,
    'faltantes_total' => 0
];

while ($row = $res->fetch_assoc()) {
    $certificadoId = (int)$row['id'];
    $pacienteId = (int)($row['paciente_id'] ?? 0);

    $paciente = trim((string)($row['paciente'] ?? ''));
    $propietario = trim((string)($row['propietario'] ?? ''));

    if (($paciente === '' || $propietario === '') && !empty($row['manual_data'])) {
        $manual = json_decode($row['manual_data'], true);

        if (is_array($manual)) {
            if ($paciente === '') {
                $paciente = trim((string)($manual['paciente'] ?? ''));
            }

            if ($propietario === '') {
                $propietario = trim((string)($manual['propietario'] ?? ''));
            }
        }
    }

    if ($paciente === '') {
        $paciente = 'Sin paciente';
    }

    if ($propietario === '') {
        $propietario = '-';
    }

    $tipoExamen = trim((string)($row['tipo_examen'] ?? ''));
    if ($tipoExamen === '') {
        $tipoExamen = '-';
    }

    $fechaInforme = '';
    if (!empty($row['fecha_examen'])) {
        $fechaInforme = date('d-m-Y', strtotime($row['fecha_examen']));
    }

    if ($pacienteId > 0) {
        $grupoKey = 'paciente:' . $pacienteId;
    } else {
        $grupoKey = 'manual:' . alm_normalizar_texto_grupo($paciente) . '|' . alm_normalizar_texto_grupo($propietario);
    }

    if (!isset($grupos[$grupoKey])) {
        $grupos[$grupoKey] = [
            'grupo_key' => $grupoKey,
            'paciente_id' => $pacienteId,
            'paciente' => $paciente,
            'propietario' => $propietario,

            'ultimo_certificado_id' => 0,
            'ultima_fecha' => '',
            'ultima_fecha_label' => '-',
            'ultima_fecha_sort' => '',

            'informes_total' => 0,
            'pdf_total' => 0,
            'pdf_bytes' => 0,
            'pdf_label' => '0 B',
            'imagenes_total' => 0,
            'imagenes_bytes' => 0,
            'imagenes_label' => '0 B',
            'audio_total' => 0,
            'audio_bytes' => 0,
            'audio_label' => '0 B',
            'grabaciones_total' => 0,
            'grabaciones_bytes' => 0,
            'grabaciones_label' => '0 B',
            'total_archivos' => 0,
            'total_bytes' => 0,
            'total_label' => '0 B',
            'faltantes_total' => 0,

            'informes' => [],
            'pdfs' => [],
            'imagenes' => [],
            'audios' => [],
            'grabaciones' => []
        ];
    }

    $certificadoGrupoMap[$certificadoId] = $grupoKey;

    $urlInforme = ((string)$row['tipo_ingreso'] === 'manual')
        ? 'certificado/subir_informe/subir_informe.php?action=modificar&id=' . $certificadoId
        : 'certificado/certificados.php?action=modificar&id=' . $certificadoId;

    $fechaSort = '';
    if (!empty($row['fecha_examen'])) {
        $fechaSort = date('Y-m-d', strtotime($row['fecha_examen']));
    }

    $debeActualizarUltimo =
        $grupos[$grupoKey]['ultima_fecha_sort'] === '' ||
        $fechaSort > $grupos[$grupoKey]['ultima_fecha_sort'] ||
        (
            $fechaSort === $grupos[$grupoKey]['ultima_fecha_sort'] &&
            $certificadoId > (int)$grupos[$grupoKey]['ultimo_certificado_id']
        );

    if ($debeActualizarUltimo) {
        $grupos[$grupoKey]['ultimo_certificado_id'] = $certificadoId;
        $grupos[$grupoKey]['ultima_fecha'] = $fechaSort;
        $grupos[$grupoKey]['ultima_fecha_sort'] = $fechaSort;
        $grupos[$grupoKey]['ultima_fecha_label'] = $fechaInforme !== '' ? $fechaInforme : '-';
    }

    $grupos[$grupoKey]['informes_total']++;

    $grupos[$grupoKey]['informes'][] = [
        'certificado_id' => $certificadoId,
        'tipo_examen' => $tipoExamen,
        'fecha_informe' => $fechaInforme,
        'tipo_ingreso' => (string)$row['tipo_ingreso'],
        'url_informe' => $urlInforme
    ];

    $resumen['total_informes']++;

    if (!empty($row['archivo_pdf'])) {
        $infoPdf = alm_info_archivo((string)$row['archivo_pdf']);

        $pdfItem = [
            'certificado_id' => $certificadoId,
            'tipo' => 'PDF',
            'tipo_examen' => $tipoExamen,
            'fecha_informe' => $fechaInforme,
            'ruta' => $infoPdf['ruta'],
            'nombre' => $infoPdf['nombre'],
            'existe' => $infoPdf['existe'],
            'estado' => $infoPdf['existe'] ? 'Existe' : 'No existe',
            'size_bytes' => $infoPdf['size_bytes'],
            'size_label' => $infoPdf['size_label'],
            'url_ver' => 'certificado/descargar.php?id=' . $certificadoId,
            'url_descargar' => 'certificado/descargar.php?id=' . $certificadoId . '&dl=1',
            'url_informe' => $urlInforme
        ];

        $grupos[$grupoKey]['pdfs'][] = $pdfItem;
        $grupos[$grupoKey]['pdf_total']++;
        $grupos[$grupoKey]['total_archivos']++;

        $resumen['pdf_total']++;
        $resumen['total_archivos']++;

        if ($infoPdf['existe']) {
            $grupos[$grupoKey]['pdf_bytes'] += $infoPdf['size_bytes'];
            $grupos[$grupoKey]['total_bytes'] += $infoPdf['size_bytes'];

            $resumen['pdf_bytes'] += $infoPdf['size_bytes'];
            $resumen['total_bytes'] += $infoPdf['size_bytes'];
        } else {
            $grupos[$grupoKey]['faltantes_total']++;
            $resumen['faltantes_total']++;
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

                $infoImg = alm_info_archivo($rutaImagen);

                $imgItem = [
                    'certificado_id' => $certificadoId,
                    'tipo' => 'Imagen',
                    'tipo_examen' => $tipoExamen,
                    'fecha_informe' => $fechaInforme,
                    'ruta' => $infoImg['ruta'],
                    'nombre' => $infoImg['nombre'],
                    'existe' => $infoImg['existe'],
                    'estado' => $infoImg['existe'] ? 'Existe' : 'No existe',
                    'size_bytes' => $infoImg['size_bytes'],
                    'size_label' => $infoImg['size_label'],
                    'url_ver' => '../' . $infoImg['ruta'],
                    'url_descargar' => '../' . $infoImg['ruta'],
                    'url_informe' => $urlInforme
                ];

                $grupos[$grupoKey]['imagenes'][] = $imgItem;
                $grupos[$grupoKey]['imagenes_total']++;
                $grupos[$grupoKey]['total_archivos']++;

                $resumen['imagenes_total']++;
                $resumen['total_archivos']++;

                if ($infoImg['existe']) {
                    $grupos[$grupoKey]['imagenes_bytes'] += $infoImg['size_bytes'];
                    $grupos[$grupoKey]['total_bytes'] += $infoImg['size_bytes'];

                    $resumen['imagenes_bytes'] += $infoImg['size_bytes'];
                    $resumen['total_bytes'] += $infoImg['size_bytes'];
                } else {
                    $grupos[$grupoKey]['faltantes_total']++;
                    $resumen['faltantes_total']++;
                }
            }
        }
    }
}

$grabacionesUsuario = alm_listar_grabaciones_usuario($baseProyecto, $usuarioActual);

foreach ($grabacionesUsuario['items'] as $audioItem) {
    $audioCertId = (int)($audioItem['certificado_id'] ?? 0);

    if ($audioCertId <= 0 || !isset($certificadoGrupoMap[$audioCertId])) {
        continue;
    }

    $grupoKeyAudio = $certificadoGrupoMap[$audioCertId];

    if (!isset($grupos[$grupoKeyAudio])) {
        continue;
    }

    if (!isset($grupos[$grupoKeyAudio]['audios'])) {
        $grupos[$grupoKeyAudio]['audios'] = [];
    }

    if (!isset($grupos[$grupoKeyAudio]['grabaciones'])) {
        $grupos[$grupoKeyAudio]['grabaciones'] = [];
    }

    $grupos[$grupoKeyAudio]['audios'][] = $audioItem;
    $grupos[$grupoKeyAudio]['grabaciones'][] = $audioItem;

    $grupos[$grupoKeyAudio]['audio_total'] = (int)($grupos[$grupoKeyAudio]['audio_total'] ?? 0) + 1;
    $grupos[$grupoKeyAudio]['audio_bytes'] = (int)($grupos[$grupoKeyAudio]['audio_bytes'] ?? 0) + (int)$audioItem['size_bytes'];

    $grupos[$grupoKeyAudio]['grabaciones_total'] = (int)($grupos[$grupoKeyAudio]['grabaciones_total'] ?? 0) + 1;
    $grupos[$grupoKeyAudio]['grabaciones_bytes'] = (int)($grupos[$grupoKeyAudio]['grabaciones_bytes'] ?? 0) + (int)$audioItem['size_bytes'];

    $grupos[$grupoKeyAudio]['total_archivos'] = (int)($grupos[$grupoKeyAudio]['total_archivos'] ?? 0) + 1;
    $grupos[$grupoKeyAudio]['total_bytes'] = (int)($grupos[$grupoKeyAudio]['total_bytes'] ?? 0) + (int)$audioItem['size_bytes'];

    $resumen['audios_total']++;
    $resumen['audios_bytes'] += (int)$audioItem['size_bytes'];

    $resumen['grabaciones_total']++;
    $resumen['grabaciones_bytes'] += (int)$audioItem['size_bytes'];

    $resumen['total_archivos']++;
    $resumen['total_bytes'] += (int)$audioItem['size_bytes'];
}

foreach ($grupos as &$grupo) {
    $grupo['pdf_label'] = alm_formatear_bytes((int)$grupo['pdf_bytes']);
    $grupo['imagenes_label'] = alm_formatear_bytes((int)$grupo['imagenes_bytes']);

    $grupo['audio_total'] = (int)($grupo['audio_total'] ?? 0);
    $grupo['audio_bytes'] = (int)($grupo['audio_bytes'] ?? 0);
    $grupo['audio_label'] = alm_formatear_bytes((int)$grupo['audio_bytes']);

    $grupo['grabaciones_total'] = (int)($grupo['grabaciones_total'] ?? 0);
    $grupo['grabaciones_bytes'] = (int)($grupo['grabaciones_bytes'] ?? 0);
    $grupo['grabaciones_label'] = alm_formatear_bytes((int)$grupo['grabaciones_bytes']);

    if (!isset($grupo['audios'])) {
        $grupo['audios'] = [];
    }

    if (!isset($grupo['grabaciones'])) {
        $grupo['grabaciones'] = [];
    }

    $grupo['total_label'] = alm_formatear_bytes((int)$grupo['total_bytes']);
}

unset($grupo);

$grupos = array_values($grupos);

usort($grupos, function ($a, $b) {
    $fechaCmp = strcmp((string)$b['ultima_fecha_sort'], (string)$a['ultima_fecha_sort']);

    if ($fechaCmp !== 0) {
        return $fechaCmp;
    }

    return ((int)$b['ultimo_certificado_id']) <=> ((int)$a['ultimo_certificado_id']);
});

$resumen['audios_label'] = alm_formatear_bytes((int)$resumen['audios_bytes']);
$resumen['grabaciones_label'] = alm_formatear_bytes((int)$resumen['grabaciones_bytes']);

$resumen['total_grupos'] = count($grupos);
$resumen['total_label'] = alm_formatear_bytes((int)$resumen['total_bytes']);
$resumen['pdf_label'] = alm_formatear_bytes((int)$resumen['pdf_bytes']);
$resumen['imagenes_label'] = alm_formatear_bytes((int)$resumen['imagenes_bytes']);

alm_json_out([
    'status' => 'success',
    'modo' => 'agrupado',
    'resumen' => $resumen,
    'grupos' => $grupos,
    'audios' => $grabacionesUsuario['items'],
    'grabaciones' => $grabacionesUsuario['items']
]);