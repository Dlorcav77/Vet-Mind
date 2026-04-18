<?php
// admin/certificado/services/certificado_form_data.php

if (!function_exists('certificado_get_form_data')) {
    function certificado_get_form_data(mysqli $mysqli, string $action, int $usuario_id): array
    {
        $id = (int)($_GET['id'] ?? 0);
        $accion = 'Ingresar';

        $fila = [
            'paciente_id'               => '',
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

        $campos_permitidos_catalogo = [];
        $resCamposPermitidos = $mysqli->query("
            SELECT id, campo, etiqueta
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
        ];
    }
}