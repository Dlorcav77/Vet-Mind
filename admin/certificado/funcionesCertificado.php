<?php
if (!function_exists('normalizarSaltosPaginaInformeHtml')) {
    function normalizarSaltosPaginaInformeHtml($html)
    {
        $html = (string)$html;

        if ($html === '') {
            return '';
        }

        $html = preg_replace(
            '/<div\b[^>]*data-vm-page-break=["\']1["\'][^>]*>.*?<\/div>/is',
            '<div class="vm-pdf-page-break"></div><div class="vm-pdf-page-spacer"></div>',
            $html
        );

        $html = preg_replace(
            '/<p>\s*(?:<span[^>]*>)?\s*\[Salto de página del informe\]\s*(?:<\/span>)?\s*<\/p>/is',
            '<div class="vm-pdf-page-break"></div><div class="vm-pdf-page-spacer"></div>',
            $html
        );

        $html = preg_replace(
            '/<p>\s*(?:<span[^>]*>)?\s*Salto de página\s*(?:<\/span>)?\s*<\/p>/is',
            '<div class="vm-pdf-page-break"></div><div class="vm-pdf-page-spacer"></div>',
            $html
        );

        return $html;
    }
}

function buildInformeHtml($veterinarioId, $configuracionInformeId, $pacienteId, $fecha, $motivo, $descripcion, $imagenes, $recinto, $medico_solicitante, $manual_data = null, $plantillaInformeId = 0)
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

    $manual = [];

    if (is_array($manual_data)) {
        $manual = $manual_data;
    } elseif (!empty($manual_data)) {
        $decodedManual = json_decode((string)$manual_data, true);

        if (is_array($decodedManual)) {
            $manual = $decodedManual;
        }
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
    } elseif (!empty($manual)) {
        $paciente = [
            'paciente'         => $manual['paciente'] ?? '',
            'fecha_nacimiento' => $manual['fecha_nacimiento'] ?? '',
            'n_chip'           => $manual['n_chip'] ?? '',
            'especie'          => $manual['especie'] ?? '',
            'sexo'             => $manual['sexo'] ?? '',
            'raza'             => $manual['raza'] ?? '',
            'codigo_paciente'  => $manual['codigo_paciente'] ?? '',
            'propietario'      => $manual['propietario'] ?? ($manual['tutor_nombre'] ?? ''),
            'edad'             => $manual['edad'] ?? '',
        ];
    } else {
        $paciente = [];
    }

    $nombreEstudio = '';

    if ((int)$plantillaInformeId > 0) {
        $stmtEstudio = $mysqli->prepare("
            SELECT te.nombre
            FROM plantilla_informe pi
            INNER JOIN tipo_examen te ON te.id = pi.tipo_examen_id
            WHERE pi.id = ?
            AND te.veterinario_id = ?
            LIMIT 1
        ");

        if ($stmtEstudio) {
            $plantillaInformeIdInt = (int)$plantillaInformeId;
            $veterinarioIdInt = (int)$veterinarioId;

            $stmtEstudio->bind_param("ii", $plantillaInformeIdInt, $veterinarioIdInt);
            $stmtEstudio->execute();
            $resEstudio = $stmtEstudio->get_result();
            $rowEstudio = $resEstudio->fetch_assoc();

            if (is_array($rowEstudio)) {
                $nombreEstudio = trim((string)($rowEstudio['nombre'] ?? ''));
            }
        }
    }

    $fecha_dt = new DateTime($fecha);
    $fecha_emision_simple = $fecha_dt->format('d-m-Y');

    $paciente['antecedentes']    = $motivo;
    $paciente['recinto']         = $recinto;
    $paciente['m_solicitante']   = $medico_solicitante;
    $paciente['N_ficha']         = trim((string)($manual['N_ficha'] ?? ''));
    $paciente['m_tratante']      = trim((string)($manual['m_tratante'] ?? ''));
    $paciente['estudio']         = $nombreEstudio;
    $paciente['fecha_emision']   = $fecha_emision_simple;

    $descripcion = normalizarSaltosPaginaInformeHtml($descripcion);

    $stmt = $mysqli->prepare("
        SELECT x.campo, x.etiqueta, x.orden_min AS orden
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

    $layoutTipo = $config['layout_tipo'] ?? 'clasico';

    if ($layoutTipo === 'inev') {
        $layoutTipo = 'clinica';
    }

    ob_start();

    if ($layoutTipo === 'clinica') {
        include(__DIR__ . '/planilla_pdf_clinica.php');
    } else {
        include(__DIR__ . '/planilla_pdf.php');
    }

    return ob_get_clean();
}