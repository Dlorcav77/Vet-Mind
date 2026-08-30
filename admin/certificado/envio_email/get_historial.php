<?php
// admin/certificado/envio_email/get_historial.php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';

try {
    credenciales('certificado', 'listar');

    $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

    if (!$usuarioId) {
        throw new Exception('Sesión no válida.');
    }

    $mysqli = conn();

    $stmt = $mysqli->prepare("
        SELECT
            id,
            tipo_envio,
            destinatarios,
            asunto,
            cantidad_informes,
            estado,
            error_mensaje,
            creado_en,
            fecha_envio
        FROM certificado_email_envios
        WHERE veterinario_id = ?
        ORDER BY id DESC
        LIMIT 100
    ");

    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();

    $res = $stmt->get_result();

    $envios = [];
    $ids = [];

    while ($row = $res->fetch_assoc()) {
        $envioId = (int)$row['id'];

        $destinatarios = json_decode(
            (string)$row['destinatarios'],
            true
        );

        if (!is_array($destinatarios)) {
            $destinatarios = [];
        }

        $envios[$envioId] = [
            'id' => $envioId,
            'tipo_envio' => (string)$row['tipo_envio'],
            'destinatarios' => $destinatarios,
            'asunto' => (string)$row['asunto'],
            'cantidad_informes' => (int)$row['cantidad_informes'],
            'estado' => (string)$row['estado'],
            'error_mensaje' => $row['error_mensaje'],
            'creado_en' => $row['creado_en'],
            'fecha_envio' => $row['fecha_envio'],
            'informes' => []
        ];

        $ids[] = $envioId;
    }

    if ($ids) {
        $idsSql = implode(',', array_map('intval', $ids));

        $resDetalle = $mysqli->query("
            SELECT
                envio_id,
                certificado_id,
                paciente,
                propietario,
                tipo_examen,
                fecha_examen,
                nombre_pdf
            FROM certificado_email_envios_detalle
            WHERE envio_id IN ($idsSql)
            ORDER BY id ASC
        ");

        while ($detalle = $resDetalle->fetch_assoc()) {
            $envioId = (int)$detalle['envio_id'];

            if (!isset($envios[$envioId])) {
                continue;
            }

            $envios[$envioId]['informes'][] = [
                'certificado_id' => (int)$detalle['certificado_id'],
                'paciente' => (string)$detalle['paciente'],
                'propietario' => (string)($detalle['propietario'] ?? ''),
                'tipo_examen' => (string)($detalle['tipo_examen'] ?? ''),
                'fecha_examen' => $detalle['fecha_examen'],
                'nombre_pdf' => (string)($detalle['nombre_pdf'] ?? '')
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'envios' => array_values($envios)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}