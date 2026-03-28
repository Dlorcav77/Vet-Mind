<?php
function buildInformeHtml($veterinarioId, $configuracionInformeId, $pacienteId, $fecha, $motivo, $descripcion, $imagenes, $recinto, $medico_solicitante, $manual_data = null)
{
    global $mysqli;

    // Configuración de la plantilla seleccionada
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

    // ---- DATOS PACIENTE ----
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

    // ---- CAMPOS VISIBLES DE LA PLANTILLA ----
    $stmt = $mysqli->prepare("
        SELECT cp.campo, cp.etiqueta
        FROM configuracion_informe_campos cic
        JOIN campos_permitidos cp ON cic.campo_id = cp.id
        WHERE cic.configuracion_informe_id = ?
            AND cic.visible = 1
        ORDER BY cic.orden ASC, cic.id ASC
    ");
    $stmt->bind_param("i", $configuracionInformeId);
    $stmt->execute();
    $campos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ---- ARMADO DEL HTML ----
    ob_start();
    include(__DIR__ . '/planilla_pdf.php');
    return ob_get_clean();
}