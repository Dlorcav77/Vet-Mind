<?php
// admin/certificado/envio_email/email_historial.php

function vmCrearHistorialEmail(
    mysqli $mysqli,
    int $veterinarioId,
    string $tipoEnvio,
    array $destinatarios,
    string $asunto,
    array $informes
): int {
    $destinatarios = array_values(array_unique(array_filter(
        array_map(
            fn($correo) => trim((string)$correo),
            $destinatarios
        )
    )));

    $destinatariosJson = json_encode(
        $destinatarios,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($destinatariosJson === false) {
        $destinatariosJson = '[]';
    }

    $cantidadInformes = count($informes);

    $mysqli->begin_transaction();

    try {
        $stmt = $mysqli->prepare("
            INSERT INTO certificado_email_envios (
                veterinario_id,
                tipo_envio,
                destinatarios,
                asunto,
                cantidad_informes,
                estado,
                creado_en
            ) VALUES (?, ?, ?, ?, ?, 'procesando', NOW())
        ");

        $stmt->bind_param(
            'isssi',
            $veterinarioId,
            $tipoEnvio,
            $destinatariosJson,
            $asunto,
            $cantidadInformes
        );

        $stmt->execute();
        $envioId = (int)$mysqli->insert_id;

        $stmtDetalle = $mysqli->prepare("
            INSERT INTO certificado_email_envios_detalle (
                envio_id,
                certificado_id,
                paciente,
                propietario,
                tipo_examen,
                fecha_examen,
                nombre_pdf
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($informes as $informe) {
            $certificadoId = (int)($informe['id'] ?? 0);
            $paciente = trim((string)($informe['paciente'] ?? '')) ?: '-';
            $propietario = trim((string)($informe['propietario'] ?? '')) ?: '-';
            $tipoExamen = trim((string)($informe['tipo_examen'] ?? '')) ?: '-';
            $nombrePdf = trim((string)($informe['nombre_pdf'] ?? ''));

            $fechaExamen = null;

            if (!empty($informe['fecha_examen'])) {
                $timestamp = strtotime((string)$informe['fecha_examen']);

                if ($timestamp !== false) {
                    $fechaExamen = date('Y-m-d', $timestamp);
                }
            }

            $stmtDetalle->bind_param(
                'iisssss',
                $envioId,
                $certificadoId,
                $paciente,
                $propietario,
                $tipoExamen,
                $fechaExamen,
                $nombrePdf
            );

            $stmtDetalle->execute();
        }

        $mysqli->commit();
        return $envioId;

    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}

function vmFinalizarHistorialEmail(
    mysqli $mysqli,
    int $envioId,
    string $estado,
    ?string $errorMensaje = null
): void {
    $estado = $estado === 'success'
        ? 'success'
        : 'error';

    $stmt = $mysqli->prepare("
        UPDATE certificado_email_envios
        SET estado = ?,
            error_mensaje = ?,
            fecha_envio = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'ssi',
        $estado,
        $errorMensaje,
        $envioId
    );

    $stmt->execute();
}