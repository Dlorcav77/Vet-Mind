<?php
// admin/configuracion_informe/previews/preview_temp.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/preview_loader.php';

$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

$usuario_id = $_SESSION['usuario_id'] ?? 0;

if ($usuario_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida.'
    ]);
    exit;
}

$action = $_POST['action'] ?? 'ingresar';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$fila_base = obtenerFilaBasePreview($mysqli, $usuario_id, $action, $id);

if (!$fila_base) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo cargar la plantilla para vista previa.'
    ]);
    exit;
}

$fila = array_merge($fila_base, armarFilaTemporalDesdePost());

$fila['id'] = $id > 0 ? $id : 0;
$fila['_preview_campos'] = obtenerCamposPreviewDesdePost();

try {
    $html = renderVistaPreviaPlantilla($mysqli, $fila);

    echo json_encode([
        'status' => 'success',
        'html' => $html
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo generar la vista previa.'
    ]);
}

exit;

function obtenerFilaBasePreview($mysqli, $usuario_id, $action, $id) {
    if ($action === 'modificar' && $id > 0) {
        $stmt = $mysqli->prepare("
            SELECT *
            FROM configuracion_informes
            WHERE id = ? AND veterinario_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $id, $usuario_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $fila = $res->fetch_assoc();

        if ($fila) {
            return $fila;
        }

        return null;
    }

    return [
        'id'                    => 0,
        'nombre_plantilla'      => 'Nueva plantilla',
        'logo_url'              => '',
        'logo_position'         => 'center',
        'logo_size'             => 'medium',
        'marca_agua_url'        => '',
        'marca_agua_size'       => 'medium',
        'mostrar_marca_agua'    => 0,
        'color_primario'        => '#3498db',
        'color_secundario'      => '#2ecc71',
        'firma_nombre'          => '',
        'firma_titulo'          => '',
        'firma_subtitulo'       => null,
        'firma_align'           => 'center',
        'firma_imagen_url'      => '',
        'mostrar_firma_imagen'  => 0,
        'footer_texto'          => '',
        'footer_align'          => 'center',
        'mostrar_fecha'         => 1,
        'formato_fecha'         => '{{day}} de {{month}} del {{year}}',
        'lugar_fecha'           => '',
        'fecha_align'           => 'right',
        'imagenes_por_fila'     => 2,
        'titulo_informe'        => 'INFORME ECOGRÁFICO',
        'subtitulo'             => '',
        'subtitulo_align'       => 'center',
        'layout_tipo'           => 'clasico',
        'layout_config_json'    => null
    ];
}

function armarFilaTemporalDesdePost() {
    $layout_tipo = $_POST['layout_tipo'] ?? 'clasico';
    if (!in_array($layout_tipo, ['clasico', 'clinica'], true)) {
        $layout_tipo = 'clasico';
    }

    $firma_subtitulos = $_POST['firma_subtitulos'] ?? [];
    if (!is_array($firma_subtitulos)) {
        $firma_subtitulos = [];
    }

    $firma_subtitulos = array_filter(array_map('trim', $firma_subtitulos));
    $firma_subtitulo = !empty($firma_subtitulos)
        ? json_encode(array_values($firma_subtitulos), JSON_UNESCAPED_UNICODE)
        : null;

    $layout_config_json = prepararLayoutConfigPreview($_POST['layout_config'] ?? []);

    return [
        'nombre_plantilla'      => trim($_POST['nombre_plantilla'] ?? ''),
        'layout_tipo'           => $layout_tipo,
        'logo_position'         => $_POST['logo_position'] ?? 'center',
        'logo_size'             => $_POST['logo_size'] ?? 'medium',
        'marca_agua_size'       => $_POST['marca_agua_size'] ?? 'medium',
        'mostrar_marca_agua'    => isset($_POST['mostrar_marca_agua']) ? 1 : 0,
        'color_primario'        => $_POST['color_primario'] ?? '#3498db',
        'color_secundario'      => $_POST['color_secundario'] ?? '#2ecc71',
        'firma_nombre'          => trim($_POST['firma_nombre'] ?? ''),
        'firma_titulo'          => trim($_POST['firma_titulo'] ?? ''),
        'firma_subtitulo'       => $firma_subtitulo,
        'firma_align'           => $_POST['firma_align'] ?? 'center',
        'mostrar_firma_imagen'  => isset($_POST['mostrar_firma_imagen']) ? 1 : 0,
        'footer_texto'          => trim($_POST['footer_texto'] ?? ''),
        'footer_align'          => $_POST['footer_align'] ?? 'center',
        'mostrar_fecha'         => isset($_POST['mostrar_fecha']) ? 1 : 0,
        'formato_fecha'         => $_POST['formato_fecha'] ?? '{{day}} de {{month}} del {{year}}',
        'lugar_fecha'           => trim($_POST['lugar_fecha'] ?? ''),
        'fecha_align'           => $_POST['fecha_align'] ?? 'right',
        'imagenes_por_fila'     => (int)($_POST['imagenes_por_fila'] ?? 2),
        'titulo_informe'        => trim($_POST['titulo_informe'] ?? 'INFORME ECOGRÁFICO'),
        'subtitulo'             => trim($_POST['subtitulo'] ?? ''),
        'subtitulo_align'       => $_POST['subtitulo_align'] ?? 'center',
        'layout_config_json'    => $layout_config_json
    ];
}

function prepararLayoutConfigPreview($layout_config_post) {
    $layout_config = [];

    if (isset($layout_config_post['clinica']) && is_array($layout_config_post['clinica'])) {
        $clinica = $layout_config_post['clinica'];

        $layout_config['clinica'] = [
            'institucion_nombre' => trim($clinica['institucion_nombre'] ?? ''),
            'direccion'          => trim($clinica['direccion'] ?? ''),
            'telefonos'          => trim($clinica['telefonos'] ?? ''),
            'correo'             => trim($clinica['correo'] ?? ''),
            'web'                => trim($clinica['web'] ?? '')
        ];
    }

    if (empty($layout_config)) {
        return null;
    }

    return json_encode($layout_config, JSON_UNESCAPED_UNICODE);
}

function obtenerCamposPreviewDesdePost() {
    $json = $_POST['preview_campos_json'] ?? '';

    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        return [];
    }

    $campos = [];

    foreach ($decoded as $index => $campo) {
        if (!is_array($campo)) {
            continue;
        }

        $etiqueta = trim($campo['etiqueta'] ?? '');

        if ($etiqueta === '') {
            continue;
        }

        $campos[] = [
            'campo_id' => (int)($campo['campo_id'] ?? 0),
            'etiqueta' => $etiqueta,
            'orden'    => (int)($campo['orden'] ?? ($index + 1))
        ];
    }

    return $campos;
}