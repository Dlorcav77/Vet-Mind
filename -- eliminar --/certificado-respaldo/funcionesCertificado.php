<?php
function buildInformeHtml($veterinarioId, $configuracionInformeId, $pacienteId, $fecha, $motivo, $descripcion, $imagenes, $recinto, $medico_solicitante, $manual_data = null)
{
    global $mysqli;

    $stmt = $mysqli->prepare("
        SELECT *
        FROM configuracion_informes
        WHERE id = ? AND veterinario_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $configuracionInformeId, $veterinarioId);
    $stmt->execute();
    $config = $stmt->get_result()->fetch_assoc();

    if (!$config) {
        throw new Exception("No se encontró la plantilla de diseño seleccionada.");
    }

    if ($pacienteId) {
        $stmt = $mysqli->prepare("
            SELECT 
                p.nombre AS paciente,
                p.fecha_nacimiento,
                p.n_chip,
                p.especie,
                p.sexo,
                p.raza,
                p.codigo_paciente,
                t.nombre_completo AS propietario
            FROM pacientes p
            LEFT JOIN tutores t ON p.tutor_id = t.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $pacienteId);
        $stmt->execute();
        $paciente = $stmt->get_result()->fetch_assoc();

        if (!is_array($paciente)) {
            $paciente = [];
        }
    } elseif ($manual_data) {
        $manual = is_array($manual_data) ? $manual_data : json_decode($manual_data, true);

        if (!is_array($manual)) {
            $manual = [];
        }

        $paciente = [
            'paciente'         => $manual['paciente'] ?? '',
            'fecha_nacimiento' => $manual['fecha_nacimiento'] ?? '',
            'n_chip'           => $manual['n_chip'] ?? '',
            'especie'          => $manual['especie'] ?? '',
            'sexo'             => $manual['sexo'] ?? '',
            'raza'             => $manual['raza'] ?? '',
            'codigo_paciente'  => $manual['codigo_paciente'] ?? '',
            'propietario'      => $manual['propietario'] ?? ($manual['tutor_nombre'] ?? ''),
        ];
    } else {
        $paciente = [];
    }

    $paciente['antecedentes']  = $motivo;
    $paciente['recinto']       = $recinto;
    $paciente['m_solicitante'] = $medico_solicitante;

    $stmt = $mysqli->prepare("
        SELECT x.campo, x.etiqueta
        FROM (
            SELECT 
                cp.id AS campo_id,
                cp.campo,
                cp.etiqueta,
                MIN(cic.orden) AS orden_min,
                MIN(cic.id) AS id_min
            FROM configuracion_informe_campos cic
            INNER JOIN campos_permitidos cp ON cp.id = cic.campo_id
            WHERE cic.configuracion_informe_id = ?
              AND cic.visible = 1
            GROUP BY cp.id, cp.campo, cp.etiqueta
        ) x
        ORDER BY x.orden_min ASC, x.id_min ASC
    ");
    $stmt->bind_param("i", $configuracionInformeId);
    $stmt->execute();
    $campos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    ob_start();
    include(__DIR__ . '/planilla_pdf.php');
    return ob_get_clean();
}