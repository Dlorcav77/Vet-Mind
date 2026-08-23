<?php
// admin/certificado/ver/getInforme.php

require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$certificado_id = (int)($_GET['id'] ?? 0);

if (!$mysqli) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if ($usuario_id <= 0) {
    http_response_code(401);

    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if ($certificado_id <= 0) {
    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => 'Informe inválido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Informe.
 *
 * Se mantienen los datos que ya utilizaba el visor
 * y se agregan:
 *
 * configuracion_informe_id
 * fecha_nacimiento
 * n_chip
 * estudio
 */
$stmt = $mysqli->prepare("
    SELECT
        c.id,
        c.paciente_id,
        c.configuracion_informe_id,
        c.fecha_examen,
        c.created_at,
        c.medico_solicitante,
        c.recinto,
        c.motivo,
        c.contenido_html,
        c.archivo_pdf,
        c.manual_data,
        c.tipo_ingreso,
        c.es_destacado,
        c.destacado_titulo,

        p.nombre AS paciente,
        p.codigo_paciente,
        p.fecha_nacimiento,
        p.n_chip,
        p.especie,
        p.raza,
        p.sexo,

        t.nombre_completo AS propietario,

        pi.nombre AS tipo_examen,

        te.nombre AS estudio

    FROM certificados c

    LEFT JOIN pacientes p
        ON c.paciente_id = p.id

    LEFT JOIN tutores t
        ON p.tutor_id = t.id

    LEFT JOIN plantilla_informe pi
        ON c.tipo_estudio = pi.id

    LEFT JOIN tipo_examen te
        ON te.id = pi.tipo_examen_id
        AND te.veterinario_id = c.veterinario_id

    WHERE
        c.id = ?
        AND c.veterinario_id = ?

    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo preparar la consulta.',
        'mysql_error' => $mysqli->error
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

$stmt->bind_param(
    "ii",
    $certificado_id,
    $usuario_id
);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo consultar el informe.',
        'mysql_error' => $stmt->error
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

$res = $stmt->get_result();

$fila = $res->fetch_assoc();

if (!$fila) {
    http_response_code(404);

    echo json_encode([
        'status' => 'error',
        'message' => 'Informe no encontrado o sin permisos.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Datos manuales.
 *
 * manual_data corresponde a informes donde los datos
 * del paciente fueron ingresados sin guardar el paciente.
 */
$manual = [];

if (!empty($fila['manual_data'])) {
    $manualTmp = json_decode(
        (string)$fila['manual_data'],
        true
    );

    if (is_array($manualTmp)) {
        $manual = $manualTmp;
    }
}


/*
 * Determinar origen de los datos del paciente.
 *
 * paciente_id > 0:
 *     pacientes + tutores
 *
 * paciente_id vacío:
 *     manual_data
 */
$pacienteId = (int)($fila['paciente_id'] ?? 0);

if ($pacienteId > 0) {

    $paciente = trim(
        (string)($fila['paciente'] ?? '')
    );

    $propietario = trim(
        (string)($fila['propietario'] ?? '')
    );

    $codigoPaciente = trim(
        (string)($fila['codigo_paciente'] ?? '')
    );

    $fechaNacimiento = trim(
        (string)($fila['fecha_nacimiento'] ?? '')
    );

    $nChip = trim(
        (string)($fila['n_chip'] ?? '')
    );

    $especie = trim(
        (string)($fila['especie'] ?? '')
    );

    $raza = trim(
        (string)($fila['raza'] ?? '')
    );

    $sexo = trim(
        (string)($fila['sexo'] ?? '')
    );

} else {

    $paciente = trim(
        (string)($manual['paciente'] ?? '')
    );

    $propietario = trim(
        (string)(
            $manual['propietario']
            ?? $manual['tutor_nombre']
            ?? ''
        )
    );

    $codigoPaciente = trim(
        (string)(
            $manual['codigo_paciente']
            ?? $manual['cod_paciente']
            ?? ''
        )
    );

    $fechaNacimiento = trim(
        (string)($manual['fecha_nacimiento'] ?? '')
    );

    $nChip = trim(
        (string)($manual['n_chip'] ?? '')
    );

    $especie = trim(
        (string)($manual['especie'] ?? '')
    );

    $raza = trim(
        (string)($manual['raza'] ?? '')
    );

    $sexo = trim(
        (string)($manual['sexo'] ?? '')
    );
}


/*
 * Médico solicitante.
 *
 * En datos manuales antiguos puede existir como
 * m_tratante, por eso mantenemos ese fallback.
 */
$medicoSolicitante = trim(
    (string)($fila['medico_solicitante'] ?? '')
);

if (
    $medicoSolicitante === '' &&
    $pacienteId <= 0
) {
    $medicoSolicitante = trim(
        (string)(
            $manual['m_solicitante']
            ?? $manual['m_tratante']
            ?? ''
        )
    );
}


/*
 * Datos propios del informe.
 */
$recinto = trim(
    (string)($fila['recinto'] ?? '')
);

$motivo = trim(
    (string)($fila['motivo'] ?? '')
);

$estudio = trim(
    (string)($fila['estudio'] ?? '')
);

$fechaExamen = trim(
    (string)($fila['fecha_examen'] ?? '')
);


/*
 * Misma fecha derivada que utiliza funcionesCertificado.php
 * para fecha_emision.
 */
$fechaEmision = '';

if ($fechaExamen !== '') {
    try {
        $fechaDt = new DateTime($fechaExamen);
        $fechaEmision = $fechaDt->format('d-m-Y');
    } catch (Exception $e) {
        $fechaEmision = $fechaExamen;
    }
}


/*
 * Valores disponibles para los campos configurables.
 *
 * Importante:
 *
 * codigo_paciente
 *     código interno del paciente.
 *
 * N_ficha
 *     alias visual de codigo_paciente.
 *
 * n_chip
 *     número físico del microchip.
 *
 * No se mezclan entre sí.
 */

/*
 * Edad del paciente a la fecha del examen.
 *
 * La edad es un valor derivado de fecha_nacimiento.
 * Se calcula contra fecha_examen para conservar
 * el contexto temporal del informe.
 */
$edadPaciente = '';

if (
    $fechaNacimiento !== '' &&
    $fechaExamen !== ''
) {
    try {
        $fechaNacimientoDt = new DateTime(
            $fechaNacimiento
        );

        $fechaReferenciaDt = new DateTime(
            $fechaExamen
        );

        if (
            $fechaNacimientoDt <=
            $fechaReferenciaDt
        ) {
            $diferenciaEdad =
                $fechaNacimientoDt->diff(
                    $fechaReferenciaDt
                );

            $partesEdad = [];

            if ($diferenciaEdad->y > 0) {
                $partesEdad[] =
                    $diferenciaEdad->y .
                    (
                        $diferenciaEdad->y === 1
                            ? ' año'
                            : ' años'
                    );
            }

            if ($diferenciaEdad->m > 0) {
                $partesEdad[] =
                    $diferenciaEdad->m .
                    (
                        $diferenciaEdad->m === 1
                            ? ' mes'
                            : ' meses'
                    );
            }

            /*
             * En pacientes menores de un mes,
             * mostramos días para no dejar "0 meses".
             */
            if (
                $diferenciaEdad->y === 0 &&
                $diferenciaEdad->m === 0
            ) {
                $partesEdad[] =
                    $diferenciaEdad->d .
                    (
                        $diferenciaEdad->d === 1
                            ? ' día'
                            : ' días'
                    );
            }

            $edadPaciente = implode(
                ' ',
                $partesEdad
            );
        }

    } catch (Exception $e) {
        $edadPaciente = '';
    }
}

/*
 * Para manual_data antiguo que pudiera tener
 * una edad explícitamente almacenada y no tenga
 * fecha_nacimiento, la conservamos como fallback.
 */
if (
    $edadPaciente === '' &&
    $pacienteId <= 0
) {
    $edadPaciente = trim(
        (string)($manual['edad'] ?? '')
    );
}

$valoresCampos = [

    'paciente' => $paciente,

    'propietario' => $propietario,

    'codigo_paciente' => $codigoPaciente,

    'N_ficha' => $codigoPaciente,

    'fecha_nacimiento' => $fechaNacimiento,

    'edad' => $edadPaciente,

    'n_chip' => $nChip,

    'especie' => $especie,

    'raza' => $raza,

    'sexo' => $sexo,

    'antecedentes' => $motivo,

    'recinto' => $recinto,

    'm_solicitante' => $medicoSolicitante,

    'm_tratante' => $medicoSolicitante,

    'estudio' => $estudio,

    'fecha_emision' => $fechaEmision
];


/*
 * Campos visibles de la configuración utilizada
 * al generar el informe.
 *
 * Esta consulta replica el criterio utilizado
 * actualmente por funcionesCertificado.php:
 *
 * visible = 1
 * orden configurado
 * sin duplicados
 */
$camposInforme = [];

$configuracionInformeId = (int)(
    $fila['configuracion_informe_id'] ?? 0
);

if ($configuracionInformeId > 0) {

    $stmtCampos = $mysqli->prepare("
        SELECT
            x.campo,
            x.campo_interno,
            x.etiqueta,
            x.ambito,
            x.orden_min AS orden

        FROM (
            SELECT
                cp.campo,
                cp.campo_interno,
                cp.etiqueta,
                cp.ambito,
                MIN(cic.orden) AS orden_min,
                MIN(cic.id) AS id_min

            FROM configuracion_informe_campos cic

            INNER JOIN campos_permitidos cp
                ON cp.id = cic.campo_id

            WHERE
                cic.configuracion_informe_id = ?
                AND cic.visible = 1

            GROUP BY
                cp.id,
                cp.campo,
                cp.campo_interno,
                cp.etiqueta,
                cp.ambito
        ) x

        ORDER BY
            x.orden_min ASC,
            x.id_min ASC
    ");

    if ($stmtCampos) {

        $stmtCampos->bind_param(
            "i",
            $configuracionInformeId
        );

        if ($stmtCampos->execute()) {

            $resCampos = $stmtCampos->get_result();

            while ($campoFila = $resCampos->fetch_assoc()) {

                $campo = trim(
                    (string)($campoFila['campo'] ?? '')
                );

                $campoInterno = trim(
                    (string)($campoFila['campo_interno'] ?? '')
                );

                $etiqueta = trim(
                    (string)($campoFila['etiqueta'] ?? '')
                );

                /*
                 * Primero buscamos por el nombre configurado,
                 * igual que hace el PDF.
                 */
                $valor = '';

                if (
                    $campo !== '' &&
                    array_key_exists(
                        $campo,
                        $valoresCampos
                    )
                ) {
                    $valor = $valoresCampos[$campo];
                }

                /*
                 * Si campo tiene un alias visual y existe
                 * campo_interno, usamos el interno como fallback.
                 *
                 * Ejemplo:
                 *
                 * campo         = N_ficha
                 * campo_interno = codigo_paciente
                 */
                if (
                    trim((string)$valor) === '' &&
                    $campoInterno !== '' &&
                    array_key_exists(
                        $campoInterno,
                        $valoresCampos
                    )
                ) {
                    $valor =
                        $valoresCampos[$campoInterno];
                }

                /*
                 * Para ingreso manual permitimos además
                 * buscar directamente en manual_data.
                 *
                 * Esto ayuda con campos existentes del catálogo
                 * que no necesiten una transformación especial.
                 */
                if (
                    trim((string)$valor) === '' &&
                    $pacienteId <= 0
                ) {

                    if (
                        $campoInterno !== '' &&
                        array_key_exists(
                            $campoInterno,
                            $manual
                        )
                    ) {
                        $valor =
                            $manual[$campoInterno];

                    } elseif (
                        $campo !== '' &&
                        array_key_exists(
                            $campo,
                            $manual
                        )
                    ) {
                        $valor =
                            $manual[$campo];
                    }
                }

                $camposInforme[] = [
                    'campo' => $campo,

                    'campo_interno' =>
                        $campoInterno,

                    'etiqueta' =>
                        $etiqueta,

                    'ambito' => trim(
                        (string)(
                            $campoFila['ambito']
                            ?? 'paciente'
                        )
                    ),

                    'orden' => (int)(
                        $campoFila['orden'] ?? 0
                    ),

                    'valor' => trim(
                        (string)$valor
                    )
                ];
            }
        }
    }
}


/*
 * Respuesta.
 *
 * Se mantienen los campos anteriores porque el JS actual
 * todavía los utiliza.
 *
 * Además se agregan:
 *
 * configuracion_informe_id
 * n_chip
 * fecha_nacimiento
 * estudio
 * campos
 */
echo json_encode([
    'status' => 'success',

    'informe' => [

        'id' => (int)$fila['id'],

        'configuracion_informe_id' =>
            $configuracionInformeId,

        'paciente' =>
            $paciente !== ''
                ? $paciente
                : 'Sin nombre',

        'codigo_paciente' =>
            $codigoPaciente,

        'propietario' =>
            $propietario !== ''
                ? $propietario
                : '-',

        'fecha_nacimiento' =>
            $fechaNacimiento,

        'n_chip' =>
            $nChip,

        'especie' =>
            $especie,

        'raza' =>
            $raza,

        'sexo' =>
            $sexo,

        /*
         * Se conserva pi.nombre para la cabecera actual:
         *
         * Paciente · Plantilla
         */
        'tipo_examen' => trim(
            (string)($fila['tipo_examen'] ?? '')
        ),

        /*
         * Nombre del tipo de examen real utilizado por
         * el campo configurable "estudio".
         */
        'estudio' =>
            $estudio,

        'medico_solicitante' =>
            $medicoSolicitante !== ''
                ? $medicoSolicitante
                : '-',

        'recinto' =>
            $recinto,

        'motivo' =>
            $motivo,

        'fecha_examen' =>
            $fechaExamen,

        'contenido_html' => (string)(
            $fila['contenido_html'] ?? ''
        ),

        'tipo_ingreso' => (string)(
            $fila['tipo_ingreso'] ?? 'sistema'
        ),

        'es_destacado' => (int)(
            $fila['es_destacado'] ?? 0
        ),

        'destacado_titulo' => trim(
            (string)(
                $fila['destacado_titulo'] ?? ''
            )
        ),

        /*
         * Nueva estructura dinámica.
         */
        'campos' =>
            $camposInforme,

        /*
         * El PDF continúa sirviéndose mediante
         * descargar.php, que valida veterinario_id.
         */
        'pdf_url' =>
            'certificado/pdf/descargar.php?id=' .
            (int)$fila['id'],

        'pdf_download_url' =>
            'certificado/pdf/descargar.php?id=' .
            (int)$fila['id'] .
            '&dl=1'
    ]

], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;