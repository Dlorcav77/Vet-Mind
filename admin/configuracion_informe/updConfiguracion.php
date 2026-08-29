<?php
// admin/configuracion_informe/updConfiguracion.php

declare(strict_types=1);

require_once("../config.php");

$mysqli = conn();

function jexit(string $status, string $message): void
{
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jexit('error', 'Método no permitido.');
}

validarTokenCsrf();

$action = trim((string)($_POST['action'] ?? ''));
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$veterinarioId = (int)$usuario_id;

if (!in_array($action, ['ingresar', 'modificar'], true)) {
    jexit('error', 'Acción no válida.');
}

if ($action === 'ingresar') {
    credenciales('configuracion_informe', 'ingresar');
}

if ($action === 'modificar') {
    credenciales('configuracion_informe', 'modificar');

    if ($id <= 0) {
        jexit('error', 'Configuración inválida.');
    }

    $stmtOwner = $mysqli->prepare(
        "SELECT id
         FROM configuracion_informes
         WHERE id = ? AND veterinario_id = ?
         LIMIT 1"
    );
    $stmtOwner->bind_param('ii', $id, $veterinarioId);
    $stmtOwner->execute();
    $resOwner = $stmtOwner->get_result();
    $stmtOwner->close();

    if ($resOwner->num_rows === 0) {
        jexit('error', 'No tienes permiso para modificar esta configuración.');
    }
}

$nombre_plantilla = trim((string)($_POST['nombre_plantilla'] ?? 'Plantilla principal'));
$es_predeterminada = isset($_POST['es_predeterminada']) ? 1 : 0;

$layouts_permitidos = ['clasico', 'clinica'];
$layout_tipo = (string)($_POST['layout_tipo'] ?? 'clasico');

if (!in_array($layout_tipo, $layouts_permitidos, true)) {
    $layout_tipo = 'clasico';
}

$logo_position = (string)($_POST['logo_position'] ?? 'center');
$mostrar_marca_agua = isset($_POST['mostrar_marca_agua']) ? 1 : 0;
$color_primario = (string)($_POST['color_primario'] ?? '#3498db');
$color_secundario = (string)($_POST['color_secundario'] ?? '#2ecc71');

$firma_nombre = trim((string)($_POST['firma_nombre'] ?? ''));
$firma_titulo = trim((string)($_POST['firma_titulo'] ?? ''));

$firma_subtitulos = $_POST['firma_subtitulos'] ?? [];
if (!is_array($firma_subtitulos)) {
    $firma_subtitulos = [];
}

$firma_subtitulos = array_values(array_filter(array_map(
    static fn($valor): string => trim((string)$valor),
    $firma_subtitulos
)));

$firma_subtitulo = !empty($firma_subtitulos)
    ? json_encode($firma_subtitulos, JSON_UNESCAPED_UNICODE)
    : null;

$firma_align = (string)($_POST['firma_align'] ?? 'center');
$footer_texto = trim((string)($_POST['footer_texto'] ?? ''));
$footer_align = (string)($_POST['footer_align'] ?? 'center');
$mostrar_fecha = isset($_POST['mostrar_fecha']) ? 1 : 0;
$formato_fecha = (string)($_POST['formato_fecha'] ?? 'd-m-Y');
$lugar_fecha = trim((string)($_POST['lugar_fecha'] ?? ''));
$fecha_align = (string)($_POST['fecha_align'] ?? 'right');
$logo_size = (string)($_POST['logo_size'] ?? 'medium');
$marca_agua_size = (string)($_POST['marca_agua_size'] ?? 'medium');
$imagenes_por_fila = (int)($_POST['imagenes_por_fila'] ?? 2);
$titulo_informe = trim((string)($_POST['titulo_informe'] ?? 'INFORME ECOGRÁFICO'));
$mostrar_firma_imagen = isset($_POST['mostrar_firma_imagen']) ? 1 : 0;
$subtitulo = trim((string)($_POST['subtitulo'] ?? ''));
$subtitulo_align = (string)($_POST['subtitulo_align'] ?? 'center');
$recinto_default = trim((string)($_POST['recinto_default'] ?? ''));

$layout_config_post = $_POST['layout_config'] ?? [];
if (!is_array($layout_config_post)) {
    $layout_config_post = [];
}

$layout_config_json = prepararLayoutConfigJson($layout_config_post);

try {
    $firma_imagen_subida = subir_imagen('firma_imagen', 'firmas', $veterinarioId, 'firma');
    $logo_subido = subir_imagen('logo', 'logos', $veterinarioId, 'logo');
    $marca_agua_subida = subir_imagen('marca_agua', 'marcas_agua', $veterinarioId, 'marcaagua');
} catch (Throwable $e) {
    error_log('[updConfiguracion][upload] ' . $e->getMessage());
    jexit('error', $e->getMessage());
}

if ($action === 'modificar') {
    if (!$logo_subido) {
        $logo_subido = obtener_url_actual($mysqli, $id, $veterinarioId, 'logo_url');
    }

    if (!$marca_agua_subida) {
        $marca_agua_subida = obtener_url_actual($mysqli, $id, $veterinarioId, 'marca_agua_url');
    }

    if (!$firma_imagen_subida) {
        $firma_imagen_subida = obtener_url_actual($mysqli, $id, $veterinarioId, 'firma_imagen_url');
    }

    try {
        $sql = "UPDATE configuracion_informes SET
            nombre_plantilla = ?,
            es_predeterminada = ?,
            layout_tipo = ?,
            logo_url = ?, logo_position = ?, logo_size = ?,
            marca_agua_url = ?, marca_agua_size = ?, mostrar_marca_agua = ?,
            color_primario = ?, color_secundario = ?,
            firma_nombre = ?, firma_titulo = ?, firma_subtitulo = ?, firma_align = ?,
            footer_texto = ?, footer_align = ?, mostrar_fecha = ?, formato_fecha = ?,
            lugar_fecha = ?, fecha_align = ?, imagenes_por_fila = ?, titulo_informe = ?,
            firma_imagen_url = ?, mostrar_firma_imagen = ?, subtitulo = ?, subtitulo_align = ?,
            layout_config_json = ?,
            recinto_default = ?,
            updated_at = NOW()
            WHERE id = ? AND veterinario_id = ?";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "sissssssissssssssisssississssii",
            $nombre_plantilla,
            $es_predeterminada,
            $layout_tipo,
            $logo_subido,
            $logo_position,
            $logo_size,
            $marca_agua_subida,
            $marca_agua_size,
            $mostrar_marca_agua,
            $color_primario,
            $color_secundario,
            $firma_nombre,
            $firma_titulo,
            $firma_subtitulo,
            $firma_align,
            $footer_texto,
            $footer_align,
            $mostrar_fecha,
            $formato_fecha,
            $lugar_fecha,
            $fecha_align,
            $imagenes_por_fila,
            $titulo_informe,
            $firma_imagen_subida,
            $mostrar_firma_imagen,
            $subtitulo,
            $subtitulo_align,
            $layout_config_json,
            $recinto_default,
            $id,
            $veterinarioId
        );

        $stmt->execute();
        $stmt->close();

        if ($es_predeterminada === 1) {
            $stmtDefault = $mysqli->prepare(
                "UPDATE configuracion_informes
                 SET es_predeterminada = 0
                 WHERE veterinario_id = ? AND id <> ?"
            );
            $stmtDefault->bind_param('ii', $veterinarioId, $id);
            $stmtDefault->execute();
            $stmtDefault->close();
        }

        guardarCamposInforme(
            $mysqli,
            $veterinarioId,
            $id,
            'modificar',
            $_POST['campos_nuevos'] ?? [],
            $_POST['campos'] ?? [],
            explode(',', (string)($_POST['campos_ids_actuales'] ?? ''))
        );

        logg("Modificación de configuración para veterinario ID $veterinarioId");
        jexit('success', 'Configuración actualizada correctamente.');
    } catch (Throwable $e) {
        error_log('[updConfiguracion][modificar] ' . $e->getMessage());
        jexit('error', 'Error al actualizar la configuración.');
    }
}

if ($action === 'ingresar') {
    try {
        $sql = "INSERT INTO configuracion_informes
            (veterinario_id, nombre_plantilla, es_predeterminada, layout_tipo, logo_url, logo_position, logo_size,
             marca_agua_url, marca_agua_size, mostrar_marca_agua, color_primario, color_secundario,
             firma_nombre, firma_titulo, firma_subtitulo, firma_align, footer_texto, footer_align,
             mostrar_fecha, formato_fecha, lugar_fecha, fecha_align, imagenes_por_fila, titulo_informe,
             firma_imagen_url, mostrar_firma_imagen, subtitulo, subtitulo_align, layout_config_json,
             recinto_default, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "isissssssissssssssisssississss",
            $veterinarioId,
            $nombre_plantilla,
            $es_predeterminada,
            $layout_tipo,
            $logo_subido,
            $logo_position,
            $logo_size,
            $marca_agua_subida,
            $marca_agua_size,
            $mostrar_marca_agua,
            $color_primario,
            $color_secundario,
            $firma_nombre,
            $firma_titulo,
            $firma_subtitulo,
            $firma_align,
            $footer_texto,
            $footer_align,
            $mostrar_fecha,
            $formato_fecha,
            $lugar_fecha,
            $fecha_align,
            $imagenes_por_fila,
            $titulo_informe,
            $firma_imagen_subida,
            $mostrar_firma_imagen,
            $subtitulo,
            $subtitulo_align,
            $layout_config_json,
            $recinto_default
        );

        $stmt->execute();
        $nueva_configuracion_id = (int)$stmt->insert_id;
        $stmt->close();

        if ($es_predeterminada === 1) {
            $stmtDefault = $mysqli->prepare(
                "UPDATE configuracion_informes
                 SET es_predeterminada = 0
                 WHERE veterinario_id = ? AND id <> ?"
            );
            $stmtDefault->bind_param('ii', $veterinarioId, $nueva_configuracion_id);
            $stmtDefault->execute();
            $stmtDefault->close();
        }

        guardarCamposInforme(
            $mysqli,
            $veterinarioId,
            $nueva_configuracion_id,
            'ingresar',
            $_POST['campos_nuevos'] ?? []
        );

        logg("Creación de configuración para veterinario ID $veterinarioId");
        jexit('success', 'Configuración ingresada correctamente.');
    } catch (Throwable $e) {
        error_log('[updConfiguracion][ingresar] ' . $e->getMessage());
        jexit('error', 'Error al ingresar la configuración.');
    }
}

function prepararLayoutConfigJson($layout_config_post) {
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

//
// ✅ Función auxiliar
//
function obtener_url_actual($mysqli, $id, $usuario_id, $campo) {
    $stmt = $mysqli->prepare("SELECT $campo FROM configuracion_informes WHERE id = ? AND veterinario_id = ?");
    $stmt->bind_param("ii", $id, $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return $row[$campo] ?? '';
    }
    return '';
}

// ✅ Subida de imágenes
function subir_imagen($campo, $directorio, $veterinario_id, $tipo) {
    if (!empty($_FILES[$campo]['name'])) {
        if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir '{$campo}'. Código: " . $_FILES[$campo]['error']);
        }
        $allowed_types = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png', 'gif'=>'image/gif'];
        $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
        $file_type = mime_content_type($_FILES[$campo]['tmp_name']);
        if (!isset($allowed_types[$ext]) || $file_type !== $allowed_types[$ext]) {
            throw new Exception("Archivo '{$campo}' inválido. Solo JPG, PNG o GIF.");
        }
        if ($_FILES[$campo]['size'] > 2 * 1024 * 1024) {
            throw new Exception("Archivo '{$campo}' excede los 2 MB permitidos.");
        }
        $filename = "{$tipo}_{$veterinario_id}_" . date('Ymd_His') . ".{$ext}";
        $target = __DIR__ . "/../../uploads/$directorio/$filename";
        if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $target)) {
            throw new Exception("No se pudo mover '{$campo}' al destino.");
        }
        return "uploads/$directorio/$filename";
    }
    return null;
}


function guardarCamposInforme($mysqli, $usuario_id, $configuracion_informe_id, $modo = 'ingresar', $campos_nuevos = [], $campos_existentes = [], $campos_ids_actuales = []) {
    $campos_fijos_ids = [1, 5];

    $ordenes_actualizados = json_decode($_POST['campos_orden'] ?? '{}', true);
    if (!is_array($ordenes_actualizados)) {
        $ordenes_actualizados = [];
    }

    if ($modo === 'modificar') {
        if (!empty($campos_existentes)) {
            foreach ($campos_existentes as $registro_id => $data) {
                $registro_id = (int)$registro_id;
                if ($registro_id <= 0) {
                    continue;
                }

                $stmtCampo = $mysqli->prepare("
                    SELECT campo_id
                    FROM configuracion_informe_campos
                    WHERE id = ? AND veterinario_id = ? AND configuracion_informe_id = ?
                    LIMIT 1
                ");
                $stmtCampo->bind_param("iii", $registro_id, $usuario_id, $configuracion_informe_id);
                $stmtCampo->execute();
                $resCampo = $stmtCampo->get_result();
                $rowCampo = $resCampo->fetch_assoc();

                $campo_id_actual = (int)($rowCampo['campo_id'] ?? 0);
                $es_fijo = in_array($campo_id_actual, $campos_fijos_ids, true);

                $visible = $es_fijo ? 1 : (isset($data['visible']) ? 1 : 0);
                $nuevo_orden = (int)($ordenes_actualizados[(string)$registro_id] ?? 0);

                if ($nuevo_orden <= 0) {
                    $nuevo_orden = 999;
                }

                $stmt = $mysqli->prepare(
                    "UPDATE configuracion_informe_campos
                     SET visible = ?, orden = ?
                     WHERE id = ? AND veterinario_id = ? AND configuracion_informe_id = ?"
                );
                $stmt->bind_param("iiiii", $visible, $nuevo_orden, $registro_id, $usuario_id, $configuracion_informe_id);
                $stmt->execute();
            }
        }

        $ids_actuales_limpios = [];
        foreach ($campos_ids_actuales as $valor) {
            $valor = (int)$valor;
            if ($valor > 0) {
                $ids_actuales_limpios[] = $valor;
            }
        }

        if (!empty($ids_actuales_limpios)) {
            $ids_actuales_str = implode(',', $ids_actuales_limpios);
            $mysqli->query(
                "DELETE FROM configuracion_informe_campos
                 WHERE veterinario_id = " . (int)$usuario_id . "
                   AND configuracion_informe_id = " . (int)$configuracion_informe_id . "
                   AND id NOT IN ($ids_actuales_str)
                   AND campo_id NOT IN (1,5)"
            );
        } else {
            $mysqli->query(
                "DELETE FROM configuracion_informe_campos
                 WHERE veterinario_id = " . (int)$usuario_id . "
                   AND configuracion_informe_id = " . (int)$configuracion_informe_id . "
                   AND campo_id NOT IN (1,5)"
            );
        }
    }

    if (!empty($campos_nuevos)) {
        $stmtMax = $mysqli->prepare(
            "SELECT IFNULL(MAX(orden), 0) AS max_orden
             FROM configuracion_informe_campos
             WHERE veterinario_id = ? AND configuracion_informe_id = ?"
        );
        $stmtMax->bind_param("ii", $usuario_id, $configuracion_informe_id);
        $stmtMax->execute();
        $resMax = $stmtMax->get_result();
        $ordenBase = (int)($resMax->fetch_assoc()['max_orden'] ?? 0);

        foreach ($campos_nuevos as $campo_id => $data) {
            $campo_id = (int)$campo_id;
            if ($campo_id <= 0 || in_array($campo_id, $campos_fijos_ids, true)) {
                continue;
            }

            $visible = isset($data['visible']) ? 1 : 0;

            $claveOrdenNuevo = 'nuevo-' . $campo_id;
            $ordenNuevo = (int)($ordenes_actualizados[$claveOrdenNuevo] ?? 0);

            if ($ordenNuevo <= 0) {
                $ordenBase++;
                $ordenNuevo = $ordenBase;
            }

            $stmtExiste = $mysqli->prepare(
                "SELECT id
                 FROM configuracion_informe_campos
                 WHERE configuracion_informe_id = ? AND veterinario_id = ? AND campo_id = ?
                 LIMIT 1"
            );
            $stmtExiste->bind_param("iii", $configuracion_informe_id, $usuario_id, $campo_id);
            $stmtExiste->execute();
            $resExiste = $stmtExiste->get_result();

            if ($resExiste->fetch_assoc()) {
                continue;
            }

            $stmt = $mysqli->prepare(
                "INSERT INTO configuracion_informe_campos
                 (configuracion_informe_id, veterinario_id, campo_id, visible, orden)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiiii", $configuracion_informe_id, $usuario_id, $campo_id, $visible, $ordenNuevo);
            $stmt->execute();
        }
    }

    foreach ($campos_fijos_ids as $campo_fijo_id) {
        $stmtExiste = $mysqli->prepare(
            "SELECT id
             FROM configuracion_informe_campos
             WHERE configuracion_informe_id = ? AND veterinario_id = ? AND campo_id = ?
             LIMIT 1"
        );
        $stmtExiste->bind_param("iii", $configuracion_informe_id, $usuario_id, $campo_fijo_id);
        $stmtExiste->execute();
        $resExiste = $stmtExiste->get_result();
        $rowExiste = $resExiste->fetch_assoc();

        if ($rowExiste) {
            $registro_id = (int)$rowExiste['id'];

            $ordenActualizado = (int)($ordenes_actualizados[(string)$registro_id] ?? 0);

            if ($ordenActualizado <= 0) {
                $ordenActualizado = (int)($ordenes_actualizados['fijo-' . $campo_fijo_id] ?? 0);
            }

            if ($ordenActualizado <= 0) {
                $stmtOrden = $mysqli->prepare(
                    "SELECT orden
                     FROM configuracion_informe_campos
                     WHERE id = ? AND veterinario_id = ? AND configuracion_informe_id = ?
                     LIMIT 1"
                );
                $stmtOrden->bind_param("iii", $registro_id, $usuario_id, $configuracion_informe_id);
                $stmtOrden->execute();
                $resOrden = $stmtOrden->get_result();
                $rowOrden = $resOrden->fetch_assoc();
                $ordenActualizado = (int)($rowOrden['orden'] ?? 0);
            }

            if ($ordenActualizado <= 0) {
                $ordenActualizado = 999;
            }

            $stmtUpd = $mysqli->prepare(
                "UPDATE configuracion_informe_campos
                 SET visible = 1, orden = ?
                 WHERE id = ? AND veterinario_id = ? AND configuracion_informe_id = ?"
            );
            $stmtUpd->bind_param("iiii", $ordenActualizado, $registro_id, $usuario_id, $configuracion_informe_id);
            $stmtUpd->execute();
        } else {
            $claveOrdenFijo = 'fijo-' . $campo_fijo_id;
            $ordenNuevoFijo = (int)($ordenes_actualizados[$claveOrdenFijo] ?? 0);

            if ($ordenNuevoFijo <= 0) {
                $stmtMax = $mysqli->prepare(
                    "SELECT IFNULL(MAX(orden), 0) AS max_orden
                     FROM configuracion_informe_campos
                     WHERE veterinario_id = ? AND configuracion_informe_id = ?"
                );
                $stmtMax->bind_param("ii", $usuario_id, $configuracion_informe_id);
                $stmtMax->execute();
                $resMax = $stmtMax->get_result();
                $ordenBase = (int)($resMax->fetch_assoc()['max_orden'] ?? 0);
                $ordenNuevoFijo = $ordenBase + 1;
            }

            $visible = 1;
            $stmtIns = $mysqli->prepare(
                "INSERT INTO configuracion_informe_campos
                 (configuracion_informe_id, veterinario_id, campo_id, visible, orden)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmtIns->bind_param("iiiii", $configuracion_informe_id, $usuario_id, $campo_fijo_id, $visible, $ordenNuevoFijo);
            $stmtIns->execute();
        }
    }
}

function generarNombreCampo($etiqueta) {
    $etiqueta = strtolower($etiqueta);                         // minúsculas
    $etiqueta = iconv('UTF-8', 'ASCII//TRANSLIT', $etiqueta);   // quita tildes
    $etiqueta = preg_replace('/[^a-z0-9\s]/', '', $etiqueta);   // solo letras y números
    $etiqueta = preg_replace('/\s+/', '_', $etiqueta);          // espacios a _
    return trim($etiqueta, '_');                                // limpia bordes
}


?>
