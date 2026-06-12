<?php
// admin/certificado/services/certificado_form_data.php

if (!function_exists('certificado_get_form_data')) {
    function certificado_get_form_data(mysqli $mysqli, string $action, int $usuario_id): array
    {
        $id = (int)($_GET['id'] ?? 0);
        $accion = 'Ingresar';
        $scopeKey = ($action === 'modificar' && $id > 0) ? 'modificar:' . $id : 'nuevo';

        $fila = [
            'paciente_id'               => '',
            'paciente_label'            => '',
            'tipo_estudio'              => '',
            'fecha_examen'              => date('Y-m-d'),
            'contenido_html'            => '',
            'estado'                    => 'pendiente',
            'manual_data'               => null,
            'motivo'                    => '',
            'medico_solicitante'        => '',
            'recinto'                   => '',
            'configuracion_informe_id'  => ''
        ];

        $imagenesGuardadas = [];
        $mostrarImagenesAntiguas = false;

        if ($action === 'modificar') {
            $accion = 'Modificar';

            $stmt = $mysqli->prepare("
                SELECT 
                    c.*, 
                    p.nombre AS paciente, 
                    p.especie, 
                    p.raza, 
                    p.sexo,
                    t.nombre_completo AS propietario
                FROM certificados c
                LEFT JOIN pacientes p ON c.paciente_id = p.id
                LEFT JOIN tutores t ON p.tutor_id = t.id
                WHERE c.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();

            if (is_array($row)) {
                $fila = $row;
            }

            if (!empty($fila['imagenes_json'])) {
                $lista = json_decode($fila['imagenes_json'], true);
                if (is_array($lista)) {
                    foreach ($lista as $nombre) {
                        $imagenesGuardadas[] = '/' . ltrim($nombre, '/');
                    }
                }
            }

            if (!empty($fila['fecha_examen'])) {
                $fechaExamen = new DateTime($fila['fecha_examen']);
                $hoy = new DateTime();
                $diff = $hoy->diff($fechaExamen);
                $mostrarImagenesAntiguas = ($diff->days <= 7 && $fechaExamen <= $hoy);
            }
        }

        $stmtPlantillas = $mysqli->prepare("
            SELECT id, nombre_plantilla, es_predeterminada
            FROM configuracion_informes
            WHERE veterinario_id = ?
            ORDER BY es_predeterminada DESC, nombre_plantilla ASC, id ASC
        ");
        $stmtPlantillas->bind_param("i", $usuario_id);
        $stmtPlantillas->execute();
        $resPlantillas = $stmtPlantillas->get_result();

        $plantillas_diseno = [];
        while ($rowPlantilla = $resPlantillas->fetch_assoc()) {
            $plantillas_diseno[] = $rowPlantilla;
        }

        $configuracion_informe_id_actual = 0;

        if ($action === 'modificar' && !empty($fila['configuracion_informe_id'])) {
            $configuracion_informe_id_actual = (int)$fila['configuracion_informe_id'];
        } else {
            foreach ($plantillas_diseno as $pl) {
                if ((int)$pl['es_predeterminada'] === 1) {
                    $configuracion_informe_id_actual = (int)$pl['id'];
                    break;
                }
            }

            if ($configuracion_informe_id_actual === 0 && !empty($plantillas_diseno)) {
                $configuracion_informe_id_actual = (int)$plantillas_diseno[0]['id'];
            }
        }

        $hay_borrador = false;
        $borrador_id = 0;
        $borrador_updated_at = null;
        $borrador_payload = null;
        $modo_ingreso_contenido_inicial = ($action === 'modificar') ? 'manual' : 'audio';

        $stmtBorrador = $mysqli->prepare("
            SELECT id, payload_json, updated_at
            FROM certificados_borradores
            WHERE veterinario_id = ?
              AND scope_key = ?
              AND estado = 'activo'
            ORDER BY updated_at DESC
            LIMIT 1
        ");

        if ($stmtBorrador) {
            $stmtBorrador->bind_param("is", $usuario_id, $scopeKey);
            $stmtBorrador->execute();
            $resBorrador = $stmtBorrador->get_result();
            $rowBorrador = $resBorrador->fetch_assoc();

            if (is_array($rowBorrador) && !empty($rowBorrador['payload_json'])) {
                $payload = json_decode($rowBorrador['payload_json'], true);

                if (is_array($payload)) {
                    $hay_borrador = true;
                    $borrador_id = (int)$rowBorrador['id'];
                    $borrador_updated_at = $rowBorrador['updated_at'];
                    $borrador_payload = $payload;

                    if (array_key_exists('paciente_id', $payload)) {
                        $fila['paciente_id'] = (int)$payload['paciente_id'];
                    }

                    if (!empty($payload['paciente_label'])) {
                        $fila['paciente_label'] = (string)$payload['paciente_label'];
                    }

                    if (!empty($payload['fecha_examen'])) {
                        $fila['fecha_examen'] = (string)$payload['fecha_examen'];
                    }

                    if (array_key_exists('contenido_html', $payload)) {
                        $fila['contenido_html'] = (string)$payload['contenido_html'];
                    }

                    if (array_key_exists('motivo_examen', $payload)) {
                        $fila['motivo'] = (string)$payload['motivo_examen'];
                    }

                    if (array_key_exists('medico_solicitante', $payload)) {
                        $fila['medico_solicitante'] = (string)$payload['medico_solicitante'];
                    }

                    if (array_key_exists('recinto', $payload)) {
                        $fila['recinto'] = (string)$payload['recinto'];
                    }

                    if (!empty($payload['plantilla_informe_id'])) {
                        $fila['tipo_estudio'] = (int)$payload['plantilla_informe_id'];
                    }

                    if (!empty($payload['configuracion_informe_id'])) {
                        $fila['configuracion_informe_id'] = (int)$payload['configuracion_informe_id'];
                        $configuracion_informe_id_actual = (int)$payload['configuracion_informe_id'];
                    }

                    if (!empty($payload['manual_data']) && is_array($payload['manual_data'])) {
                        $fila['manual_data'] = json_encode(
                            $payload['manual_data'],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        );
                    }

                    $modo_ingreso_contenido_inicial = (!empty($payload['toggle_audio_manual']) && (int)$payload['toggle_audio_manual'] === 1)
                        ? 'manual'
                        : 'audio';
                }
            }
        }

        $campos_permitidos_catalogo = [];
        $resCamposPermitidos = $mysqli->query("
            SELECT id, campo, etiqueta, ambito, campo_interno
            FROM campos_permitidos
            WHERE activo = 1
            ORDER BY id ASC
        ");
        while ($rowCampoPermitido = $resCamposPermitidos->fetch_assoc()) {
            $campos_permitidos_catalogo[] = $rowCampoPermitido;
        }

        $campos_visibles_actuales = [];
        if ($configuracion_informe_id_actual > 0) {
            $stmtCamposVisibles = $mysqli->prepare("
                SELECT x.campo
                FROM (
                    SELECT 
                        cp.id AS campo_id,
                        cp.campo,
                        MIN(cic.orden) AS orden_min,
                        MIN(cic.id) AS id_min
                    FROM configuracion_informe_campos cic
                    INNER JOIN campos_permitidos cp ON cp.id = cic.campo_id
                    WHERE cic.configuracion_informe_id = ?
                      AND cic.visible = 1
                    GROUP BY cp.id, cp.campo
                ) x
                ORDER BY x.orden_min ASC, x.id_min ASC
            ");
            $stmtCamposVisibles->bind_param("i", $configuracion_informe_id_actual);
            $stmtCamposVisibles->execute();
            $resCamposVisibles = $stmtCamposVisibles->get_result();

            while ($rowCampoVisible = $resCamposVisibles->fetch_assoc()) {
                $campos_visibles_actuales[] = $rowCampoVisible['campo'];
            }
        }

        $recinto_default = '';
        if ($configuracion_informe_id_actual > 0) {
            $stmtRecintoDefault = $mysqli->prepare("
                SELECT recinto_default
                FROM configuracion_informes
                WHERE id = ? AND veterinario_id = ?
                LIMIT 1
            ");

            if ($stmtRecintoDefault) {
                $stmtRecintoDefault->bind_param("ii", $configuracion_informe_id_actual, $usuario_id);
                $stmtRecintoDefault->execute();
                $rowRecintoDefault = $stmtRecintoDefault->get_result()->fetch_assoc();

                if (is_array($rowRecintoDefault) && $rowRecintoDefault['recinto_default'] !== null) {
                    $recinto_default = (string)$rowRecintoDefault['recinto_default'];
                }
            }
        }

        $recinto_visible_plantilla = in_array('recinto', $campos_visibles_actuales, true);
        $recinto_pide_siempre = (!$recinto_visible_plantilla && trim($recinto_default) === '');

        if ($recinto_pide_siempre && !$recinto_visible_plantilla) {
            $campos_visibles_actuales[] = 'recinto';
        }

        $clinicas_recinto = [];
        $stmtClinicas = $mysqli->prepare("
            SELECT nombre_clinica
            FROM clinicas
            WHERE veterinario_id = ?
            ORDER BY nombre_clinica ASC
        ");

        if ($stmtClinicas) {
            $stmtClinicas->bind_param("i", $usuario_id);
            $stmtClinicas->execute();
            $resClinicas = $stmtClinicas->get_result();
            while ($rowClinica = $resClinicas->fetch_assoc()) {
                $clinicas_recinto[] = $rowClinica['nombre_clinica'];
            }
        }

        $toggle_manual_inicial = false;

        if (!empty($fila['manual_data'])) {
            $manualDataArr = json_decode((string)$fila['manual_data'], true);

            if (is_array($manualDataArr)) {
                foreach ($manualDataArr as $valorManual) {
                    if (is_string($valorManual) && trim($valorManual) !== '') {
                        $toggle_manual_inicial = true;
                        break;
                    }

                    if (is_numeric($valorManual) && (string)$valorManual !== '') {
                        $toggle_manual_inicial = true;
                        break;
                    }
                }
            }
        }

        if (!empty($fila['paciente_id'])) {
            $toggle_manual_inicial = false;
        }

        return [
            'id'                              => $id,
            'accion'                          => $accion,
            'fila'                            => $fila,
            'imagenesGuardadas'               => $imagenesGuardadas,
            'mostrarImagenesAntiguas'         => $mostrarImagenesAntiguas,
            'plantillas_diseno'               => $plantillas_diseno,
            'configuracion_informe_id_actual' => $configuracion_informe_id_actual,
            'campos_permitidos_catalogo'      => $campos_permitidos_catalogo,
            'campos_visibles_actuales'        => $campos_visibles_actuales,
            'hay_borrador'                    => $hay_borrador,
            'borrador_id'                     => $borrador_id,
            'borrador_updated_at'             => $borrador_updated_at,
            'borrador_payload'                => $borrador_payload,
            'borrador_scope_key'              => $scopeKey,
            'modo_ingreso_contenido_inicial'  => $modo_ingreso_contenido_inicial,
            'toggle_manual_inicial'           => $toggle_manual_inicial,
            'recinto_default'                 => $recinto_default,
            'clinicas_recinto'                => $clinicas_recinto,
        ];
    }
}