<?php
//admin/control_ia/updControlIa.php
require_once("../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ───────────────────────────────────────────
// VER DETALLE (informe + revision de un certificado)
// ───────────────────────────────────────────
if ($action === 'detalle_grupo') {
    credenciales('control_ia', 'listar');

    $certificado_id = (int)($_POST['certificado_id'] ?? 0);
    if ($certificado_id <= 0) {
        echo json_encode(['status'=>'error','message'=>'Certificado inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT id, rid, tipo, certificado_id, plantilla_id, provider, model,
               input_json, system_text, prompt_text, content_final,
               prompt_tokens, completion_tokens, total_tokens, cost_usd,
               datetime_ia, created_at
        FROM ia_requests
        WHERE certificado_id = ?
        ORDER BY tipo ASC
    ");
    $stmt->bind_param('i', $certificado_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = ['informe'=>null, 'revision'=>null];
    while ($row = $res->fetch_assoc()) {
        $data[$row['tipo']] = $row;
    }
    $stmt->close();

    echo json_encode(['status'=>'success','data'=>$data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ───────────────────────────────────────────
// VER DETALLE de un request suelto (por id)
// ───────────────────────────────────────────
if ($action === 'detalle_suelto') {
    credenciales('control_ia', 'listar');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'ID inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT id, rid, tipo, certificado_id, plantilla_id, provider, model,
               input_json, system_text, prompt_text, content_final,
               prompt_tokens, completion_tokens, total_tokens, cost_usd,
               datetime_ia, created_at
        FROM ia_requests
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['status'=>'error','message'=>'No encontrado.']);
        exit;
    }

    // Devuelve el suelto en el slot que corresponda a su tipo.
    $data = ['informe'=>null, 'revision'=>null];
    $data[$row['tipo']] = $row;

    echo json_encode(['status'=>'success','data'=>$data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ───────────────────────────────────────────
// ELIMINAR grupo (todas las requests de un certificado)
// ───────────────────────────────────────────
if ($action === 'eliminar_grupo') {
    credenciales('control_ia', 'eliminar');

    $certificado_id = (int)($_POST['certificado_id'] ?? 0);
    if ($certificado_id <= 0) {
        echo json_encode(['status'=>'error','message'=>'Certificado inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM ia_requests WHERE certificado_id = ?");
    $stmt->bind_param('i', $certificado_id);

    if ($stmt->execute()) {
        logg("Control IA: eliminadas requests del certificado #$certificado_id");
        echo json_encode(['status'=>'success','message'=>'Registros eliminados.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al eliminar.']);
    }
    exit;
}

// ───────────────────────────────────────────
// ELIMINAR request suelto (por id)
// ───────────────────────────────────────────
if ($action === 'eliminar_suelto') {
    credenciales('control_ia', 'eliminar');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'ID inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM ia_requests WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Control IA: eliminada request suelta ID $id");
        echo json_encode(['status'=>'success','message'=>'Registro eliminado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al eliminar.']);
    }
    exit;
}

// ───────────────────────────────────────────
// PRECIOS: listar
// ───────────────────────────────────────────
if ($action === 'precios_listar') {
    credenciales('control_ia', 'listar');

    $res = $mysqli->query("
        SELECT id, model, price_in, price_out, vigente_desde, vigente_hasta, activo
        FROM ia_pricing
        ORDER BY activo DESC, model ASC, vigente_desde DESC
    ");
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode(['status'=>'success','data'=>$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ───────────────────────────────────────────
// PRECIOS: agregar
// ───────────────────────────────────────────
if ($action === 'precio_ingresar') {
    credenciales('control_ia', 'ingresar');

    $model    = trim((string)($_POST['model'] ?? ''));
    $in       = (float)($_POST['price_in'] ?? 0);
    $out      = (float)($_POST['price_out'] ?? 0);
    $vigente  = trim((string)($_POST['vigente_desde'] ?? ''));
    $activo   = (int)($_POST['activo'] ?? 1);

    if ($model === '' || $vigente === '') {
        echo json_encode(['status'=>'error','message'=>'Modelo y vigencia son obligatorios.']);
        exit;
    }
    validar_length("Modelo", $model, 100);

    $stmt = $mysqli->prepare("
        INSERT INTO ia_pricing (model, price_in, price_out, vigente_desde, activo)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sddsi', $model, $in, $out, $vigente, $activo);

    if ($stmt->execute()) {
        logg("Control IA: precio agregado para modelo $model");
        echo json_encode(['status'=>'success','message'=>'Precio agregado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al agregar precio.']);
    }
    exit;
}

// ───────────────────────────────────────────
// PRECIOS: modificar
// ───────────────────────────────────────────
if ($action === 'precio_modificar') {
    credenciales('control_ia', 'modificar');

    $id       = (int)($_POST['id'] ?? 0);
    $model    = trim((string)($_POST['model'] ?? ''));
    $in       = (float)($_POST['price_in'] ?? 0);
    $out      = (float)($_POST['price_out'] ?? 0);
    $vigente  = trim((string)($_POST['vigente_desde'] ?? ''));
    $activo   = (int)($_POST['activo'] ?? 1);

    if ($id <= 0 || $model === '' || $vigente === '') {
        echo json_encode(['status'=>'error','message'=>'Datos incompletos.']);
        exit;
    }
    validar_length("Modelo", $model, 100);

    $stmt = $mysqli->prepare("
        UPDATE ia_pricing
        SET model = ?, price_in = ?, price_out = ?, vigente_desde = ?, activo = ?
        WHERE id = ?
    ");
    $stmt->bind_param('sddsii', $model, $in, $out, $vigente, $activo, $id);

    if ($stmt->execute()) {
        logg("Control IA: precio modificado ID $id ($model)");
        echo json_encode(['status'=>'success','message'=>'Precio actualizado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al actualizar precio.']);
    }
    exit;
}

// ───────────────────────────────────────────
// PRECIOS: eliminar
// ───────────────────────────────────────────
if ($action === 'precio_eliminar') {
    credenciales('control_ia', 'eliminar');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'ID inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM ia_pricing WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Control IA: precio eliminado ID $id");
        echo json_encode(['status'=>'success','message'=>'Precio eliminado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al eliminar precio.']);
    }
    exit;
}

// ───────────────────────────────────────────
// MÉTRICAS (totales por rango)
// ───────────────────────────────────────────
if ($action === 'metricas') {
    credenciales('control_ia', 'listar');

    $rango = trim((string)($_POST['rango'] ?? 'todo'));

    // Construye el filtro de fecha sobre created_at.
    $where = '';
    switch ($rango) {
        case 'hoy':
            $where = "WHERE DATE(created_at) = CURDATE()";
            break;
        case 'semana':
            $where = "WHERE created_at >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)";
            break;
        case 'mes':
            $where = "WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        case 'todo':
        default:
            $where = "";
            break;
    }

    $sql = "
        SELECT
            SUM(tipo = 'informe' AND certificado_id IS NOT NULL) AS informes_generados,
            SUM(tipo = 'informe') AS consultas_informe,
            SUM(tipo = 'revision') AS revisiones,
            COALESCE(SUM(total_tokens), 0) AS tokens,
            COALESCE(SUM(cost_usd), 0) AS costo
        FROM ia_requests
        $where
    ";

    $res = $mysqli->query($sql);
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row) {
        echo json_encode(['status'=>'error','message'=>'No se pudieron calcular las métricas.']);
        exit;
    }

    // Costo por proveedor (mismo rango).
    $porProveedor = [];
    $sqlProv = "
        SELECT provider, COALESCE(SUM(cost_usd),0) AS costo
        FROM ia_requests
        $where
        GROUP BY provider
        ORDER BY provider ASC
    ";
    $resProv = $mysqli->query($sqlProv);
    if ($resProv) {
        while ($p = $resProv->fetch_assoc()) {
            $prov = $p['provider'] !== null && $p['provider'] !== '' ? $p['provider'] : 'sin_proveedor';
            $porProveedor[$prov] = (float)$p['costo'];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'informes_generados' => (int)$row['informes_generados'],
            'consultas_informe'  => (int)$row['consultas_informe'],
            'revisiones'         => (int)$row['revisiones'],
            'tokens'             => (int)$row['tokens'],
            'costo'              => (float)$row['costo'],
            'por_proveedor'      => $porProveedor,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Acción no válida.']);
exit;