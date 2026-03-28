<?php
//admin/configuracion_informe/updConfiguracion.php
require_once("../config.php");
$mysqli = conn();

$action     = $_POST['action'] ?? '';
$id         = $_POST['id'] ?? null;
$usuario_id = $_SESSION['usuario_id'] ?? 0;

$nombre_plantilla     = trim($_POST['nombre_plantilla'] ?? 'Plantilla principal');
$es_predeterminada    = isset($_POST['es_predeterminada']) ? 1 : 0;
$logo_position        = $_POST['logo_position'] ?? 'center';
$mostrar_marca_agua   = isset($_POST['mostrar_marca_agua']) ? 1 : 0;
$color_primario       = $_POST['color_primario'] ?? '#3498db';
$color_secundario     = $_POST['color_secundario'] ?? '#2ecc71';
$firma_nombre         = trim($_POST['firma_nombre'] ?? '');
$firma_titulo         = trim($_POST['firma_titulo'] ?? '');
$firma_subtitulos     = $_POST['firma_subtitulos'] ?? [];
$firma_subtitulos     = array_filter(array_map('trim', $firma_subtitulos));
$firma_subtitulo      = !empty($firma_subtitulos) ? json_encode(array_values($firma_subtitulos)) : null;
$firma_align          = $_POST['firma_align'] ?? 'center';
$footer_texto         = trim($_POST['footer_texto'] ?? '');
$footer_align         = $_POST['footer_align'] ?? 'center';
$mostrar_fecha        = isset($_POST['mostrar_fecha']) ? 1 : 0;
$formato_fecha        = $_POST['formato_fecha'] ?? 'd-m-Y';
$lugar_fecha          = trim($_POST['lugar_fecha'] ?? '');
$fecha_align          = $_POST['fecha_align'] ?? 'right';
$logo_size            = $_POST['logo_size'] ?? 'medium';
$marca_agua_size      = $_POST['marca_agua_size'] ?? 'medium';
$imagenes_por_fila    = (int)($_POST['imagenes_por_fila'] ?? 2);
$titulo_informe       = trim($_POST['titulo_informe'] ?? 'INFORME ECOGRÁFICO');
$mostrar_firma_imagen = isset($_POST['mostrar_firma_imagen']) ? 1 : 0;
$subtitulo            = trim($_POST['subtitulo'] ?? '');
$subtitulo_align      = $_POST['subtitulo_align'] ?? 'center';

$firma_imagen_subida = null;
if (isset($_FILES['firma_imagen']) && !empty($_FILES['firma_imagen']['name'])) {
    $firma_imagen_subida = subir_imagen('firma_imagen', 'firmas', $usuario_id, 'firma');
}

try {
    $logo_subido       = subir_imagen('logo', 'logos', $usuario_id, 'logo');
    $marca_agua_subida = subir_imagen('marca_agua', 'marcas_agua', $usuario_id, 'marcaagua');
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

//
// ✅ MODIFICAR
//
if ($action === 'modificar' && !empty($id)) {
    if (!$logo_subido) {
        $logo_subido = obtener_url_actual($mysqli, $id, $usuario_id, 'logo_url');
    }
    if (!$marca_agua_subida) {
        $marca_agua_subida = obtener_url_actual($mysqli, $id, $usuario_id, 'marca_agua_url');
    }
    if (!$firma_imagen_subida) {
        $firma_imagen_subida = obtener_url_actual($mysqli, $id, $usuario_id, 'firma_imagen_url');
    }

    $sql = "UPDATE configuracion_informes SET
        nombre_plantilla = ?,
        es_predeterminada = ?,
        logo_url = ?, logo_position = ?, logo_size = ?, 
        marca_agua_url = ?, marca_agua_size = ?, mostrar_marca_agua = ?,
        color_primario = ?, color_secundario = ?,
        firma_nombre = ?, firma_titulo = ?, firma_subtitulo = ?, firma_align = ?,
        footer_texto = ?, footer_align = ?, mostrar_fecha = ?, formato_fecha = ?, 
        lugar_fecha = ?, fecha_align = ?, imagenes_por_fila = ?, titulo_informe = ?, 
        firma_imagen_url = ?, mostrar_firma_imagen = ?, subtitulo = ?, subtitulo_align = ?,
        updated_at = NOW()
        WHERE id = ? AND veterinario_id = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "sisssssissssssssisssississii",
        $nombre_plantilla,
        $es_predeterminada,
        $logo_subido, $logo_position, $logo_size,
        $marca_agua_subida, $marca_agua_size, $mostrar_marca_agua,
        $color_primario, $color_secundario,
        $firma_nombre, $firma_titulo, $firma_subtitulo, $firma_align,
        $footer_texto, $footer_align,
        $mostrar_fecha, $formato_fecha, $lugar_fecha, $fecha_align,
        $imagenes_por_fila, $titulo_informe, $firma_imagen_subida, $mostrar_firma_imagen,
        $subtitulo, $subtitulo_align,
        $id, $usuario_id
    );


    if ($stmt->execute()) {
        if ($es_predeterminada === 1) {
            $stmtDefault = $mysqli->prepare("
                UPDATE configuracion_informes
                SET es_predeterminada = 0
                WHERE veterinario_id = ? AND id <> ?
            ");
            $stmtDefault->bind_param("ii", $usuario_id, $id);
            $stmtDefault->execute();
        }

        logg("Modificación de configuración para veterinario ID $usuario_id");
        guardarCamposInforme(
            $mysqli,
            $usuario_id,
            (int)$id,
            'modificar',
            $_POST['campos_nuevos'] ?? [],
            $_POST['campos'] ?? [],
            explode(',', $_POST['campos_ids_actuales'] ?? '')
        );

        echo json_encode(['status' => 'success', 'message' => 'Configuración actualizada correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la configuración.']);
    }
    exit;
}

//
// ✅ INGRESAR
//
if ($action === 'ingresar') {
    $sql = "INSERT INTO configuracion_informes 
        (veterinario_id, nombre_plantilla, es_predeterminada, logo_url, logo_position, logo_size, marca_agua_url, marca_agua_size, mostrar_marca_agua,
        color_primario, color_secundario, firma_nombre, firma_titulo, firma_subtitulo, firma_align,
        footer_texto, footer_align, mostrar_fecha, formato_fecha, lugar_fecha, fecha_align, 
        imagenes_por_fila, titulo_informe, firma_imagen_url, mostrar_firma_imagen, subtitulo, subtitulo_align,
        created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "isisssssissssssssisssississ",
        $usuario_id, $nombre_plantilla, $es_predeterminada,
        $logo_subido, $logo_position, $logo_size, $marca_agua_subida, $marca_agua_size, $mostrar_marca_agua,
        $color_primario, $color_secundario, $firma_nombre, $firma_titulo, $firma_subtitulo, $firma_align,
        $footer_texto, $footer_align, $mostrar_fecha, $formato_fecha, $lugar_fecha, $fecha_align,
        $imagenes_por_fila, $titulo_informe, $firma_imagen_subida, $mostrar_firma_imagen,
        $subtitulo, $subtitulo_align
    );

    if ($stmt->execute()) {
        $nueva_configuracion_id = (int)$mysqli->insert_id;

        if ($es_predeterminada === 1) {
            $stmtDefault = $mysqli->prepare("
                UPDATE configuracion_informes
                SET es_predeterminada = 0
                WHERE veterinario_id = ? AND id <> ?
            ");
            $stmtDefault->bind_param("ii", $usuario_id, $nueva_configuracion_id);
            $stmtDefault->execute();
        }

        logg("Creación de configuración para veterinario ID $usuario_id");
        $nueva_configuracion_id = (int)$mysqli->insert_id;

        guardarCamposInforme(
            $mysqli,
            $usuario_id,
            $nueva_configuracion_id,
            'ingresar',
            $_POST['campos_nuevos'] ?? []
        );

        echo json_encode(['status' => 'success', 'message' => 'Configuración ingresada correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al ingresar la configuración.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);




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
    if ($modo === 'modificar') {
        $ordenes_actualizados = json_decode($_POST['campos_orden'] ?? '{}', true);
        if (!is_array($ordenes_actualizados)) {
            $ordenes_actualizados = [];
        }

        if (!empty($campos_existentes)) {
            foreach ($campos_existentes as $registro_id => $data) {
                $visible = isset($data['visible']) ? 1 : 0;
                $nuevo_orden = (int)($ordenes_actualizados[$registro_id] ?? 0);

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
                   AND id NOT IN ($ids_actuales_str)"
            );
        } else {
            $mysqli->query(
                "DELETE FROM configuracion_informe_campos
                 WHERE veterinario_id = " . (int)$usuario_id . "
                   AND configuracion_informe_id = " . (int)$configuracion_informe_id
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
            if ($campo_id <= 0) {
                continue;
            }

            $visible = isset($data['visible']) ? 1 : 0;
            $ordenBase++;

            $stmt = $mysqli->prepare(
                "INSERT INTO configuracion_informe_campos
                 (configuracion_informe_id, veterinario_id, campo_id, visible, orden)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiiii", $configuracion_informe_id, $usuario_id, $campo_id, $visible, $ordenBase);
            $stmt->execute();
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
