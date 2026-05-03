<?php
// admin/almacenamiento/api/limpiar_usuario_informes.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

credenciales('certificado', 'eliminar');

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

function limpiar_json_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function limpiar_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);

    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function limpiar_normalizar_ruta(string $ruta): string
{
    $ruta = trim($ruta);
    $ruta = str_replace('\\', '/', $ruta);
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = ltrim($ruta, '/');

    return $ruta;
}

function limpiar_ruta_permitida(string $ruta): bool
{
    $ruta = limpiar_normalizar_ruta($ruta);

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

function limpiar_resolver_archivo(string $baseProyecto, string $ruta): array
{
    $rutaNormalizada = limpiar_normalizar_ruta($ruta);

    $info = [
        'ruta_original' => $ruta,
        'ruta' => $rutaNormalizada,
        'permitida' => limpiar_ruta_permitida($rutaNormalizada),
        'existe' => false,
        'ruta_fisica' => '',
        'size_bytes' => 0,
        'size_label' => '0 B'
    ];

    if (!$info['permitida']) {
        return $info;
    }

    $rutaFisicaIntento = $baseProyecto . '/' . $rutaNormalizada;

    if (!file_exists($rutaFisicaIntento)) {
        return $info;
    }

    $rutaFisica = realpath($rutaFisicaIntento);

    if (!$rutaFisica || strpos($rutaFisica, $baseProyecto) !== 0) {
        return $info;
    }

    if (is_file($rutaFisica)) {
        $size = (int)filesize($rutaFisica);

        $info['existe'] = true;
        $info['ruta_fisica'] = $rutaFisica;
        $info['size_bytes'] = $size;
        $info['size_label'] = limpiar_format_bytes($size);
    }

    return $info;
}

$baseProyecto = realpath(__DIR__ . '/../../../');

if (!$baseProyecto) {
    limpiar_json_out([
        'status' => 'error',
        'message' => 'No se pudo resolver la ruta base del proyecto.'
    ]);
}

$veterinarioId = (int)($_POST['usuario_id'] ?? $_GET['usuario_id'] ?? $usuarioActual);

// Seguridad para esta prueba: solo limpiar el usuario logueado.
// Si necesitas limpiar otro usuario desde admin, lo habilitamos después con una validación real de administrador.
if ($veterinarioId !== $usuarioActual) {
    limpiar_json_out([
        'status' => 'error',
        'message' => 'Por seguridad, solo puedes limpiar informes del usuario actual.'
    ]);
}

$dryRun = (int)($_POST['dry_run'] ?? $_GET['dry_run'] ?? 1) === 1;
$confirmar = trim((string)($_POST['confirmar'] ?? $_GET['confirmar'] ?? ''));

if (!$dryRun && $confirmar !== 'ELIMINAR_USUARIO_' . $veterinarioId) {
    limpiar_json_out([
        'status' => 'error',
        'message' => 'Confirmación inválida. Para borrar realmente debes enviar confirmar=ELIMINAR_USUARIO_' . $veterinarioId
    ]);
}

$sql = "
    SELECT
        id,
        archivo_pdf,
        imagenes_json
    FROM certificados
    WHERE veterinario_id = ?
      AND deleted_at IS NULL
    ORDER BY id ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    limpiar_json_out([
        'status' => 'error',
        'message' => 'Error preparando consulta.',
        'mysql_error' => $mysqli->error
    ]);
}

$stmt->bind_param('i', $veterinarioId);
$stmt->execute();

$res = $stmt->get_result();

$certificadosIds = [];
$rutasAEliminar = [];

while ($row = $res->fetch_assoc()) {
    $certificadoId = (int)$row['id'];
    $certificadosIds[] = $certificadoId;

    if (!empty($row['archivo_pdf'])) {
        $rutaPdf = limpiar_normalizar_ruta((string)$row['archivo_pdf']);

        if ($rutaPdf !== '') {
            $rutasAEliminar[$rutaPdf] = [
                'origen' => 'bd_pdf',
                'certificado_id' => $certificadoId,
                'ruta' => $rutaPdf
            ];
        }
    }

    if (!empty($row['imagenes_json'])) {
        $imagenes = json_decode($row['imagenes_json'], true);

        if (is_array($imagenes)) {
            foreach ($imagenes as $rutaImagen) {
                $rutaImagen = limpiar_normalizar_ruta((string)$rutaImagen);

                if ($rutaImagen === '') {
                    continue;
                }

                $rutasAEliminar[$rutaImagen] = [
                    'origen' => 'bd_img',
                    'certificado_id' => $certificadoId,
                    'ruta' => $rutaImagen
                ];
            }
        }
    }
}

$patronesHuerfanos = [
    'uploads/certificados/informes/' => 'cert_' . $veterinarioId . '_',
    'uploads/certificados/img/' => 'img_' . $veterinarioId . '_',
    'uploads/certificados/informes_subidos/' => 'informe_' . $veterinarioId . '_'
];

foreach ($patronesHuerfanos as $dirRel => $prefijo) {
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

        if (!isset($rutasAEliminar[$rutaRel])) {
            $rutasAEliminar[$rutaRel] = [
                'origen' => 'huerfano_prefijo',
                'certificado_id' => null,
                'ruta' => $rutaRel
            ];
        }
    }
}

$archivos = [];
$resumen = [
    'usuario_id' => $veterinarioId,
    'dry_run' => $dryRun,
    'certificados_detectados' => count($certificadosIds),
    'rutas_detectadas' => count($rutasAEliminar),
    'archivos_existentes' => 0,
    'archivos_faltantes' => 0,
    'archivos_no_permitidos' => 0,
    'bytes_detectados' => 0,
    'bytes_detectados_label' => '0 B',
    'archivos_eliminados' => 0,
    'bytes_eliminados' => 0,
    'bytes_eliminados_label' => '0 B',
    'errores_eliminacion' => 0,
    'certificados_eliminados' => 0,
    'borradores_eliminados' => 0
];

foreach ($rutasAEliminar as $ruta => $meta) {
    $info = limpiar_resolver_archivo($baseProyecto, $ruta);

    $item = [
        'origen' => $meta['origen'],
        'certificado_id' => $meta['certificado_id'],
        'ruta' => $ruta,
        'permitida' => $info['permitida'],
        'existe' => $info['existe'],
        'size_bytes' => $info['size_bytes'],
        'size_label' => $info['size_label'],
        'eliminado' => false,
        'error' => ''
    ];

    if (!$info['permitida']) {
        $resumen['archivos_no_permitidos']++;
        $archivos[] = $item;
        continue;
    }

    if (!$info['existe']) {
        $resumen['archivos_faltantes']++;
        $archivos[] = $item;
        continue;
    }

    $resumen['archivos_existentes']++;
    $resumen['bytes_detectados'] += $info['size_bytes'];

    if (!$dryRun) {
        if (@unlink($info['ruta_fisica'])) {
            $item['eliminado'] = true;
            $resumen['archivos_eliminados']++;
            $resumen['bytes_eliminados'] += $info['size_bytes'];
        } else {
            $item['error'] = 'No se pudo eliminar el archivo físico.';
            $resumen['errores_eliminacion']++;
        }
    }

    $archivos[] = $item;
}

$resumen['bytes_detectados_label'] = limpiar_format_bytes((int)$resumen['bytes_detectados']);
$resumen['bytes_eliminados_label'] = limpiar_format_bytes((int)$resumen['bytes_eliminados']);

if (!$dryRun) {
    $mysqli->begin_transaction();

    try {
        $stmtBor = $mysqli->prepare("DELETE FROM certificados_borradores WHERE veterinario_id = ?");
        if (!$stmtBor) {
            throw new Exception('Error preparando borrado de borradores: ' . $mysqli->error);
        }

        $stmtBor->bind_param('i', $veterinarioId);
        $stmtBor->execute();
        $resumen['borradores_eliminados'] = $stmtBor->affected_rows;

        $stmtDel = $mysqli->prepare("DELETE FROM certificados WHERE veterinario_id = ?");
        if (!$stmtDel) {
            throw new Exception('Error preparando borrado de certificados: ' . $mysqli->error);
        }

        $stmtDel->bind_param('i', $veterinarioId);
        $stmtDel->execute();
        $resumen['certificados_eliminados'] = $stmtDel->affected_rows;

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();

        limpiar_json_out([
            'status' => 'error',
            'message' => 'Se eliminaron archivos físicos, pero falló el borrado en BD. Revisa manualmente.',
            'error' => $e->getMessage(),
            'resumen' => $resumen,
            'archivos' => $archivos
        ]);
    }
}

limpiar_json_out([
    'status' => 'success',
    'message' => $dryRun ? 'Simulación completada. No se eliminó nada.' : 'Limpieza completada.',
    'resumen' => $resumen,
    'archivos' => $archivos
]);