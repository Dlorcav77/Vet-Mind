<?php
//admin/control_ia/updControlIa.php
require_once("../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function control_ia_where_created_at(string $rango): string
{
    switch ($rango) {
        case 'hoy':
            return "WHERE DATE(created_at) = CURDATE()";

        case 'semana':
            return "WHERE created_at >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)";

        case 'mes':
            return "WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";

        case 'todo':
        default:
            return "";
    }
}

// ───────────────────────────────────────────
// VER DETALLE (grupo por certificado o flujo)
// ───────────────────────────────────────────
if ($action === 'detalle_grupo') {
    credenciales('control_ia', 'listar');

    $certificado_id = (int)($_POST['certificado_id'] ?? 0);
    $flujo_id = trim((string)($_POST['flujo_id'] ?? ''));

    if ($certificado_id <= 0 && $flujo_id === '') {
        echo json_encode(['status'=>'error','message'=>'Grupo inválido.']);
        exit;
    }

    $data = [
        'informe' => null,
        'revision' => null,
        'transcripcion' => null,
    ];

    if ($certificado_id > 0) {
        $stmt = $mysqli->prepare("
            SELECT id, rid, tipo, certificado_id, flujo_id, plantilla_id, provider, model,
                   input_json, system_text, prompt_text, content_final,
                   prompt_tokens, completion_tokens, total_tokens, cost_usd,
                   datetime_ia, created_at
            FROM ia_requests
            WHERE certificado_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $certificado_id);
    } else {
        $stmt = $mysqli->prepare("
            SELECT id, rid, tipo, certificado_id, flujo_id, plantilla_id, provider, model,
                   input_json, system_text, prompt_text, content_final,
                   prompt_tokens, completion_tokens, total_tokens, cost_usd,
                   datetime_ia, created_at
            FROM ia_requests
            WHERE flujo_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('s', $flujo_id);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        if (($row['tipo'] === 'informe' || $row['tipo'] === 'revision') && $data[$row['tipo']] === null) {
            $data[$row['tipo']] = $row;
        }
    }
    $stmt->close();

    if ($certificado_id > 0) {
        $stmt = $mysqli->prepare("
            SELECT id, audio_tmp, certificado_id, flujo_id, motor_a, motor_b,
                   texto_a, texto_b, texto_doble, discrepancias_json,
                   duracion_seg_a, duracion_seg_b,
                   cost_a, cost_b, cost_total,
                   created_at, updated_at
            FROM ia_transcripciones
            WHERE certificado_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $certificado_id);
    } else {
        $stmt = $mysqli->prepare("
            SELECT id, audio_tmp, certificado_id, flujo_id, motor_a, motor_b,
                   texto_a, texto_b, texto_doble, discrepancias_json,
                   duracion_seg_a, duracion_seg_b,
                   cost_a, cost_b, cost_total,
                   created_at, updated_at
            FROM ia_transcripciones
            WHERE flujo_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param('s', $flujo_id);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $data['transcripcion'] = $res->fetch_assoc() ?: null;
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
// VER DETALLE de una transcripción (por id)
// ───────────────────────────────────────────
if ($action === 'detalle_transcripcion') {
    credenciales('control_ia', 'listar');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'ID inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT id, audio_tmp, certificado_id, motor_a, motor_b,
               texto_a, texto_b, texto_doble, discrepancias_json,
               duracion_seg_a, duracion_seg_b,
               cost_a, cost_b, cost_total,
               created_at, updated_at
        FROM ia_transcripciones
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['status'=>'error','message'=>'Transcripción no encontrada.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'transcripcion' => $row,
            'informe' => null,
            'revision' => null
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ───────────────────────────────────────────
// ELIMINAR grupo (por certificado o flujo)
// ───────────────────────────────────────────
if ($action === 'eliminar_grupo') {
    credenciales('control_ia', 'eliminar');

    $certificado_id = (int)($_POST['certificado_id'] ?? 0);
    $flujo_id = trim((string)($_POST['flujo_id'] ?? ''));

    if ($certificado_id <= 0 && $flujo_id === '') {
        echo json_encode(['status'=>'error','message'=>'Grupo inválido.']);
        exit;
    }

    if ($certificado_id > 0) {
        $stmtReq = $mysqli->prepare("DELETE FROM ia_requests WHERE certificado_id = ?");
        $stmtReq->bind_param('i', $certificado_id);

        $stmtTr = $mysqli->prepare("DELETE FROM ia_transcripciones WHERE certificado_id = ?");
        $stmtTr->bind_param('i', $certificado_id);

        $logTxt = "certificado #$certificado_id";
    } else {
        $stmtReq = $mysqli->prepare("DELETE FROM ia_requests WHERE flujo_id = ?");
        $stmtReq->bind_param('s', $flujo_id);

        $stmtTr = $mysqli->prepare("DELETE FROM ia_transcripciones WHERE flujo_id = ?");
        $stmtTr->bind_param('s', $flujo_id);

        $logTxt = "flujo $flujo_id";
    }

    $okReq = $stmtReq->execute();
    $stmtReq->close();

    $okTr = $stmtTr->execute();
    $stmtTr->close();

    if ($okReq && $okTr) {
        logg("Control IA: eliminado grupo IA del $logTxt");
        echo json_encode(['status'=>'success','message'=>'Grupo eliminado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al eliminar grupo.']);
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
// ELIMINAR transcripción suelta (por id)
// ───────────────────────────────────────────
if ($action === 'eliminar_transcripcion') {
    credenciales('control_ia', 'eliminar');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'ID inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM ia_transcripciones WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Control IA: eliminada transcripción ID $id");
        echo json_encode(['status'=>'success','message'=>'Transcripción eliminada.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al eliminar transcripción.']);
    }
    $stmt->close();
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
// PRECIOS STT: listar
// ───────────────────────────────────────────
if ($action === 'precios_stt_listar') {
    credenciales('control_ia', 'listar');

    $res = $mysqli->query("
        SELECT id, motor, price_min, vigente_desde, vigente_hasta, activo
        FROM ia_pricing_stt
        ORDER BY activo DESC, motor ASC, vigente_desde DESC
    ");

    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    echo json_encode(['status'=>'success','data'=>$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ───────────────────────────────────────────
// PRECIOS STT: agregar
// ───────────────────────────────────────────
if ($action === 'precio_stt_ingresar') {
    credenciales('control_ia', 'ingresar');

    $motor    = trim((string)($_POST['motor'] ?? ''));
    $priceMin = (float)($_POST['price_min'] ?? 0);
    $vigente  = trim((string)($_POST['vigente_desde'] ?? ''));
    $activo   = (int)($_POST['activo'] ?? 1);

    if ($motor === '' || $vigente === '') {
        echo json_encode(['status'=>'error','message'=>'Motor y vigencia son obligatorios.']);
        exit;
    }

    if ($priceMin < 0) {
        echo json_encode(['status'=>'error','message'=>'El precio por minuto no puede ser negativo.']);
        exit;
    }

    validar_length("Motor", $motor, 50);

    $stmt = $mysqli->prepare("
        INSERT INTO ia_pricing_stt (motor, price_min, vigente_desde, activo)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('sdsi', $motor, $priceMin, $vigente, $activo);

    if ($stmt->execute()) {
        logg("Control IA: precio STT agregado para motor $motor");
        echo json_encode(['status'=>'success','message'=>'Precio de transcripción agregado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al agregar precio de transcripción.']);
    }
    $stmt->close();
    exit;
}

// ───────────────────────────────────────────
// PRECIOS STT: modificar
// ───────────────────────────────────────────
if ($action === 'precio_stt_modificar') {
    credenciales('control_ia', 'modificar');

    $id       = (int)($_POST['id'] ?? 0);
    $motor    = trim((string)($_POST['motor'] ?? ''));
    $priceMin = (float)($_POST['price_min'] ?? 0);
    $vigente  = trim((string)($_POST['vigente_desde'] ?? ''));
    $activo   = (int)($_POST['activo'] ?? 1);

    if ($id <= 0 || $motor === '' || $vigente === '') {
        echo json_encode(['status'=>'error','message'=>'Datos incompletos.']);
        exit;
    }

    if ($priceMin < 0) {
        echo json_encode(['status'=>'error','message'=>'El precio por minuto no puede ser negativo.']);
        exit;
    }

    validar_length("Motor", $motor, 50);

    $stmt = $mysqli->prepare("
        UPDATE ia_pricing_stt
        SET motor = ?, price_min = ?, vigente_desde = ?, activo = ?
        WHERE id = ?
    ");
    $stmt->bind_param('sdsii', $motor, $priceMin, $vigente, $activo, $id);

    if ($stmt->execute()) {
        logg("Control IA: precio STT modificado ID $id ($motor)");
        echo json_encode(['status'=>'success','message'=>'Precio de transcripción actualizado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al actualizar precio de transcripción.']);
    }
    $stmt->close();
    exit;
}

// ───────────────────────────────────────────
// PRECIOS STT: eliminar
// ───────────────────────────────────────────
if ($action === 'precio_stt_eliminar') {
    credenciales('control_ia', 'eliminar');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'ID inválido.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM ia_pricing_stt WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        logg("Control IA: precio STT eliminado ID $id");
        echo json_encode(['status'=>'success','message'=>'Precio de transcripción eliminado.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Error al eliminar precio de transcripción.']);
    }
    $stmt->close();
    exit;
}

// ───────────────────────────────────────────
// MÉTRICAS (totales por rango)
// ───────────────────────────────────────────
if ($action === 'metricas') {
    credenciales('control_ia', 'listar');

    $rango = trim((string)($_POST['rango'] ?? 'todo'));

    // Filtro de fecha sobre created_at (se aplica a ambas tablas).
    $where = control_ia_where_created_at($rango);

    // Requests (informe + revisión)
    $sqlReq = "
        SELECT
            SUM(tipo = 'informe' AND certificado_id IS NOT NULL) AS informes_generados,
            SUM(tipo = 'informe') AS consultas_informe,
            SUM(tipo = 'revision') AS revisiones,
            COALESCE(SUM(total_tokens), 0) AS tokens,
            COALESCE(SUM(cost_usd), 0) AS costo_req
        FROM ia_requests
        $where
    ";
    $resReq = $mysqli->query($sqlReq);
    $rowReq = $resReq ? $resReq->fetch_assoc() : null;

    // Costo por proveedor (requests)
    $porProveedor = [];
    $resProv = $mysqli->query("
        SELECT provider, COALESCE(SUM(cost_usd),0) AS costo
        FROM ia_requests
        $where
        GROUP BY provider
        ORDER BY provider ASC
    ");
    if ($resProv) {
        while ($p = $resProv->fetch_assoc()) {
            $prov = ($p['provider'] !== null && $p['provider'] !== '') ? $p['provider'] : 'sin_proveedor';
            $porProveedor[$prov] = (float)$p['costo'];
        }
    }

    // Desglose dinámico por tipo + proveedor + modelo.
    // Esto permite que aparezcan nuevos proveedores/modelos sin dejar nada duro en el JS.
    $iaDesglose = [];
    $resDesglose = $mysqli->query("
        SELECT
            tipo,
            provider,
            model,
            COUNT(*) AS cantidad,
            COALESCE(SUM(total_tokens), 0) AS tokens,
            COALESCE(SUM(cost_usd), 0) AS costo
        FROM ia_requests
        $where
        GROUP BY tipo, provider, model
        ORDER BY tipo ASC, provider ASC, model ASC
    ");

    if ($resDesglose) {
        while ($row = $resDesglose->fetch_assoc()) {
            $tipo = ($row['tipo'] !== null && $row['tipo'] !== '') ? $row['tipo'] : 'sin_tipo';
            $provider = ($row['provider'] !== null && $row['provider'] !== '') ? $row['provider'] : 'sin_proveedor';
            $model = ($row['model'] !== null && $row['model'] !== '') ? $row['model'] : 'sin_modelo';

            $iaDesglose[] = [
                'tipo'     => $tipo,
                'provider' => $provider,
                'model'    => $model,
                'cantidad' => (int)$row['cantidad'],
                'tokens'   => (int)$row['tokens'],
                'costo'    => round((float)$row['costo'], 6),
            ];
        }
    }

    // Transcripciones
    $sqlTr = "
        SELECT
            COUNT(*) AS total_transcripciones,
            COALESCE(SUM((COALESCE(duracion_seg_a,0) + COALESCE(duracion_seg_b,0)) / 60), 0) AS minutos_trans,
            COALESCE(SUM(cost_total), 0) AS costo_trans
        FROM ia_transcripciones
        $where
    ";
    $resTr = $mysqli->query($sqlTr);
    $rowTr = $resTr ? $resTr->fetch_assoc() : null;

    // Desglose dinámico por motor STT.
    // Se consideran motor_a y motor_b por separado para identificar:
    // A = motor principal
    // B = motor de comparación / diferencias
    $sttDesglose = [];
    $resStt = $mysqli->query("
        SELECT
            posicion,
            orden,
            motor,
            COUNT(*) AS cantidad,
            COALESCE(SUM(duracion_seg), 0) AS duracion_seg_total,
            COALESCE(SUM(costo), 0) AS costo_total
        FROM (
            SELECT
                'A' AS posicion,
                1 AS orden,
                motor_a AS motor,
                COALESCE(duracion_seg_a, 0) AS duracion_seg,
                COALESCE(cost_a, 0) AS costo
            FROM ia_transcripciones
            $where

            UNION ALL

            SELECT
                'B' AS posicion,
                2 AS orden,
                motor_b AS motor,
                COALESCE(duracion_seg_b, 0) AS duracion_seg,
                COALESCE(cost_b, 0) AS costo
            FROM ia_transcripciones
            $where
        ) x
        WHERE motor IS NOT NULL AND motor <> ''
        GROUP BY posicion, orden, motor
        ORDER BY orden ASC, motor ASC
    ");

    if ($resStt) {
        while ($row = $resStt->fetch_assoc()) {
            $sttDesglose[] = [
                'posicion'     => (string)$row['posicion'],
                'motor'        => (string)$row['motor'],
                'cantidad'     => (int)$row['cantidad'],
                'minutos'      => round(((float)$row['duracion_seg_total']) / 60, 2),
                'duracion_seg' => round((float)$row['duracion_seg_total'], 2),
                'costo'        => round((float)$row['costo_total'], 6),
            ];
        }
    }

    $costoReq   = $rowReq ? (float)$rowReq['costo_req'] : 0.0;
    $costoTrans = $rowTr ? (float)$rowTr['costo_trans'] : 0.0;

    echo json_encode([
        'status' => 'success',
        'data' => [
            'informes_generados'    => $rowReq ? (int)$rowReq['informes_generados'] : 0,
            'consultas_informe'     => $rowReq ? (int)$rowReq['consultas_informe'] : 0,
            'revisiones'            => $rowReq ? (int)$rowReq['revisiones'] : 0,
            'transcripciones'       => $rowTr ? (int)$rowTr['total_transcripciones'] : 0,
            'minutos_transcripcion' => $rowTr ? round((float)$rowTr['minutos_trans'], 2) : 0,
            'tokens'                => $rowReq ? (int)$rowReq['tokens'] : 0,
            'costo_ia'              => round($costoReq, 6),
            'costo_transcripcion'   => round($costoTrans, 6),
            'costo_total'           => round($costoReq + $costoTrans, 6),
            'por_proveedor'         => $porProveedor,
            'ia_desglose'           => $iaDesglose,
            'stt_desglose'          => $sttDesglose,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ───────────────────────────────────────────
// LISTADO SIEMPRE AGRUPADO
// Agrupa por:
// 1) certificado_id
// 2) flujo_id
// 3) si no tiene ninguno, queda como suelto
// ───────────────────────────────────────────
if ($action === 'listado') {
    credenciales('control_ia', 'listar');

    $rango = trim((string)($_POST['rango'] ?? 'todo'));
    $where = control_ia_where_created_at($rango);

    $reqs = [];
    $r = $mysqli->query("
        SELECT id, rid, tipo, certificado_id, flujo_id, plantilla_id, provider, model,
               total_tokens, cost_usd, created_at
        FROM ia_requests
        $where
        ORDER BY created_at DESC
    ");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $reqs[] = $row;
        }
    }

    $trans = [];
    $t = $mysqli->query("
        SELECT id, audio_tmp, certificado_id, flujo_id, motor_a, motor_b,
               duracion_seg_a, duracion_seg_b, cost_total, created_at
        FROM ia_transcripciones
        $where
        ORDER BY created_at DESC
    ");
    if ($t) {
        while ($row = $t->fetch_assoc()) {
            $trans[] = $row;
        }
    }

    $grupos = [];
    $sueltos = [];

    $crearGrupo = function (&$grupos, string $key, string $tipoAgrupacion, ?int $certificadoId, ?string $flujoId, string $createdAt) {
        if (!isset($grupos[$key])) {
            $grupos[$key] = [
                'grupo_key'          => $key,
                'tipo_agrupacion'    => $tipoAgrupacion,
                'certificado_id'     => $certificadoId,
                'flujo_id'           => $flujoId,
                'tiene_transcripcion'=> false,
                'tiene_informe'      => false,
                'tiene_revision'     => false,
                'plantilla_id'       => null,
                'providers'          => [],
                'tokens'             => 0,
                'costo'              => 0.0,
                'created_at'         => $createdAt,
            ];
        }

        if ($createdAt > $grupos[$key]['created_at']) {
            $grupos[$key]['created_at'] = $createdAt;
        }
    };

    foreach ($reqs as $rq) {
        $cid = $rq['certificado_id'] !== null ? (int)$rq['certificado_id'] : 0;
        $fid = trim((string)($rq['flujo_id'] ?? ''));

        if ($cid > 0) {
            $key = 'cert_' . $cid;
            $crearGrupo($grupos, $key, 'certificado', $cid, $fid !== '' ? $fid : null, $rq['created_at']);
        } elseif ($fid !== '') {
            $key = 'flujo_' . $fid;
            $crearGrupo($grupos, $key, 'flujo', null, $fid, $rq['created_at']);
        } else {
            $sueltos[] = [
                'grupo'          => 'request',
                'op_id'          => (int)$rq['id'],
                'certificado_id' => null,
                'flujo_id'       => null,
                'tipo'           => $rq['tipo'],
                'detalle'        => trim(($rq['provider'] ?? '').' ('.($rq['model'] ?? '').')'),
                'plantilla_id'   => $rq['plantilla_id'] !== null ? (int)$rq['plantilla_id'] : null,
                'tokens'         => (int)$rq['total_tokens'],
                'costo'          => (float)$rq['cost_usd'],
                'created_at'     => $rq['created_at'],
            ];
            continue;
        }

        if ($rq['tipo'] === 'informe') {
            $grupos[$key]['tiene_informe'] = true;
        }

        if ($rq['tipo'] === 'revision') {
            $grupos[$key]['tiene_revision'] = true;
        }

        if ($rq['plantilla_id'] !== null && $grupos[$key]['plantilla_id'] === null) {
            $grupos[$key]['plantilla_id'] = (int)$rq['plantilla_id'];
        }

        if (($rq['provider'] ?? '') !== '') {
            $grupos[$key]['providers'][$rq['provider']] = true;
        }

        $grupos[$key]['tokens'] += (int)$rq['total_tokens'];
        $grupos[$key]['costo']  += (float)$rq['cost_usd'];
    }

    foreach ($trans as $tr) {
        $cid = $tr['certificado_id'] !== null ? (int)$tr['certificado_id'] : 0;
        $fid = trim((string)($tr['flujo_id'] ?? ''));

        if ($cid > 0) {
            $key = 'cert_' . $cid;
            $crearGrupo($grupos, $key, 'certificado', $cid, $fid !== '' ? $fid : null, $tr['created_at']);
        } elseif ($fid !== '') {
            $key = 'flujo_' . $fid;
            $crearGrupo($grupos, $key, 'flujo', null, $fid, $tr['created_at']);
        } else {
            $sueltos[] = [
                'grupo'          => 'transcripcion',
                'op_id'          => (int)$tr['id'],
                'certificado_id' => null,
                'flujo_id'       => null,
                'tipo'           => 'transcripcion',
                'detalle'        => trim(($tr['motor_a'] ?? '').' + '.($tr['motor_b'] ?? '')),
                'plantilla_id'   => null,
                'tokens'         => 0,
                'costo'          => (float)$tr['cost_total'],
                'created_at'     => $tr['created_at'],
            ];
            continue;
        }

        $grupos[$key]['tiene_transcripcion'] = true;
        $grupos[$key]['costo'] += (float)$tr['cost_total'];
    }

    $filas = [];
    foreach ($grupos as $g) {
        $g['providers'] = array_keys($g['providers']);
        $filas[] = $g;
    }

    usort($filas, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    usort($sueltos, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    echo json_encode([
        'status'  => 'success',
        'modo'    => 'agrupado',
        'data'    => $filas,
        'sueltos' => $sueltos,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Acción no válida.']);
exit;