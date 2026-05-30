<?php
// admin/configuracion_informe/previews/preview_clinica.php

function obtenerCamposVistaPreviaClinica($mysqli, $fila) {
    $campos = [];

    if (!empty($fila['_preview_campos']) && is_array($fila['_preview_campos'])) {
        foreach ($fila['_preview_campos'] as $index => $campoPreview) {
            if (is_array($campoPreview)) {
                $etiqueta = trim($campoPreview['etiqueta'] ?? '');
                $campoId = (int)($campoPreview['campo_id'] ?? 0);
                $orden = (int)($campoPreview['orden'] ?? ($index + 1));
            } else {
                $etiqueta = trim((string)$campoPreview);
                $campoId = 0;
                $orden = $index + 1;
            }

            if ($etiqueta === '') {
                continue;
            }

            $campos[] = [
                'campo_id' => $campoId,
                'etiqueta' => $etiqueta,
                'orden' => $orden
            ];
        }

        if (!empty($campos)) {
            return $campos;
        }
    }

    $configuracion_informe_id = (int)($fila['id'] ?? 0);
    $veterinario_id = (int)($fila['veterinario_id'] ?? 0);

    if ($configuracion_informe_id > 0 && $veterinario_id > 0) {
        $stmt = $mysqli->prepare("
            SELECT 
                cic.campo_id,
                cp.etiqueta,
                cic.orden
            FROM configuracion_informe_campos cic
            INNER JOIN campos_permitidos cp ON cp.id = cic.campo_id
            WHERE cic.configuracion_informe_id = ?
              AND cic.veterinario_id = ?
              AND cic.visible = 1
            ORDER BY cic.orden ASC, cic.id ASC
        ");

        $stmt->bind_param("ii", $configuracion_informe_id, $veterinario_id);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $etiqueta = trim($row['etiqueta'] ?? '');

            if ($etiqueta === '') {
                continue;
            }

            $campos[] = [
                'campo_id' => (int)$row['campo_id'],
                'etiqueta' => $etiqueta,
                'orden' => (int)($row['orden'] ?? 0)
            ];
        }
    }

    if (!empty($campos)) {
        return $campos;
    }

    return [
        [
            'campo_id' => 1,
            'etiqueta' => 'Paciente',
            'orden' => 10
        ],
        [
            'campo_id' => 5,
            'etiqueta' => 'Propietario',
            'orden' => 20
        ],
        [
            'campo_id' => 0,
            'etiqueta' => 'Médico Solicitante',
            'orden' => 30
        ],
        [
            'campo_id' => 0,
            'etiqueta' => 'Cód. Paciente',
            'orden' => 40
        ],
        [
            'campo_id' => 0,
            'etiqueta' => 'Estudio',
            'orden' => 50
        ],
        [
            'campo_id' => 0,
            'etiqueta' => 'Fecha emisión',
            'orden' => 60
        ]
    ];
}


function obtenerEtiquetaCampoClinicaPreview($etiqueta) {
    return trim((string)$etiqueta);
}

function obtenerPlaceholderCampoClinicaPreview($etiqueta) {
    $etiqueta = trim((string)$etiqueta);

    if ($etiqueta === '') {
        return '';
    }

    return '[' . $etiqueta . ']';
}


function agruparCamposClinicaPorOrden($campos) {
    $filas = [];

    foreach ($campos as $index => $campo) {
        $orden = (int)($campo['orden'] ?? 0);

        if ($orden >= 10) {
            $grupoOrden = (int)(floor($orden / 10) * 10);
            $claveFila = 'grupo-' . $grupoOrden;
        } else {
            $claveFila = 'campo-' . $index;
        }

        if (!isset($filas[$claveFila])) {
            $filas[$claveFila] = [];
        }

        $filas[$claveFila][] = $campo;
    }

    return $filas;
}

function renderVistaPreviaPlantillaClinica($mysqli, $fila) {
    $color_primario = $fila['color_primario'] ?? '#4f7fb7';
    $color_secundario = $fila['color_secundario'] ?? '#d6771f';

    $marca_agua_sizes = [
        'small'  => '52%',
        'medium' => '68%',
        'large'  => '82%'
    ];

    $marca_agua_size = $fila['marca_agua_size'] ?? 'medium';
    $marca_agua_width = $marca_agua_sizes[$marca_agua_size] ?? $marca_agua_sizes['medium'];

    $layout_config = [];
    if (!empty($fila['layout_config_json'])) {
        $decoded = json_decode($fila['layout_config_json'], true);
        if (is_array($decoded)) {
            $layout_config = $decoded['clinica'] ?? $decoded;
        }
    }

    $institucion_nombre = trim($layout_config['institucion_nombre'] ?? 'Instituto Neurológico Veterinario');
    $institucion_direccion = trim($layout_config['direccion'] ?? 'Pepe Vila #25, La Reina, Santiago, Chile');
    $institucion_telefonos = trim($layout_config['telefonos'] ?? '22 356 39 89 - 22 356 39 90');
    $institucion_correo = trim($layout_config['correo'] ?? 'contacto@institutoneurologico.cl');
    $institucion_web = trim($layout_config['web'] ?? 'clinica.cl');

    $footer_texto = trim($fila['footer_texto'] ?? '');
    if ($footer_texto === '') {
        $footer_texto = 'Este examen es una impresión diagnóstica que debe ser evaluada por su médico tratante en el contexto del cuadro clínico. Dada la naturaleza evolutiva del mismo, dicha impresión puede variar en el tiempo.';
    }

    $firma_subtitulos = [];

    if (!empty($fila['firma_subtitulo'])) {
        $decoded_subtitulos = json_decode($fila['firma_subtitulo'], true);

        if (is_array($decoded_subtitulos)) {
            $firma_subtitulos = array_filter(array_map('trim', $decoded_subtitulos));
        } else {
            $texto_subtitulo = trim((string)$fila['firma_subtitulo']);

            if ($texto_subtitulo !== '') {
                $firma_subtitulos[] = $texto_subtitulo;
            }
        }
    }

    $align_permitidos = ['left', 'center', 'right'];

    $firma_align = $fila['firma_align'] ?? 'right';
    if (!in_array($firma_align, $align_permitidos, true)) {
        $firma_align = 'right';
    }

    $footer_align = $fila['footer_align'] ?? 'center';
    if (!in_array($footer_align, $align_permitidos, true)) {
        $footer_align = 'center';
    }

    $firma_margin_map = [
        'left'   => '42px auto 0 0',
        'center' => '42px auto 0 auto',
        'right'  => '42px 36px 0 auto'
    ];

    $firma_margin = $firma_margin_map[$firma_align] ?? $firma_margin_map['right'];

    $fecha = new DateTime();
    $dia = $fecha->format('j');
    $anio = $fecha->format('Y');

    $meses = [
        'January' => 'enero',
        'February' => 'febrero',
        'March' => 'marzo',
        'April' => 'abril',
        'May' => 'mayo',
        'June' => 'junio',
        'July' => 'julio',
        'August' => 'agosto',
        'September' => 'septiembre',
        'October' => 'octubre',
        'November' => 'noviembre',
        'December' => 'diciembre'
    ];

    $mes_en = $fecha->format('F');
    $mes_es = $meses[$mes_en] ?? strtolower($mes_en);
    $fecha_str = $dia . ' ' . $mes_es . ' ' . $anio;

    $imagenes_por_fila = (int)($fila['imagenes_por_fila'] ?? 2);

    if ($imagenes_por_fila < 1) {
        $imagenes_por_fila = 2;
    }

    if ($imagenes_por_fila > 4) {
        $imagenes_por_fila = 4;
    }

    $col_imagen = (int)(12 / $imagenes_por_fila);

    $campos_antecedentes = obtenerCamposVistaPreviaClinica($mysqli, $fila);
    $filas_campos_antecedentes = agruparCamposClinicaPorOrden($campos_antecedentes);

    ob_start();
    ?>
    <div class="vista-previa-plantilla vista-previa-clinica bg-white shadow-sm" style="
        position:relative;
        width:100%;
        max-width:820px;
        margin:0 auto;
        overflow:hidden;
        border:1px solid #d8dee6;
        font-family:Calibri, 'Segoe UI', Arial, Helvetica, sans-serif;
        color:#111;
        background:#fff;
    ">
        <div style="
            position:relative;
            min-height:1060px;
            overflow:hidden;
            background:#fff;
        ">
            <div style="
                display:flex;
                min-height:215px;
                align-items:flex-start;
            ">
                <div style="
                    width:240px;
                    padding:4px 1px 0 1px;
                    box-sizing:border-box;
                    /* background: blue; */
                ">
                    <?php if (!empty($fila['logo_url'])): ?>
                        <img src="../<?= htmlspecialchars($fila['logo_url']) ?>" alt="Logo" style="
                            width:240px;
                            max-height:212px;
                            object-fit:contain;
                            display:block;
                            margin-top:0;
                        ">
                    <?php else: ?>
                        <div style="
                            width:170px;
                            height:170px;
                            border:5px solid <?= htmlspecialchars($color_primario) ?>;
                            border-radius:50%;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            color:<?= htmlspecialchars($color_primario) ?>;
                            font-weight:bold;
                            text-align:center;
                            font-size:16px;
                            margin-top:0;
                        ">
                            LOGO
                        </div>
                    <?php endif; ?>
                </div>
                <div style="
                    flex:1;
                    padding:4px 0 0 0;
                    box-sizing:border-box;
                ">
                    <div style="
                        background:<?= htmlspecialchars($color_secundario) ?>;
                        color:#fff;
                        text-align:center;
                        font-size:22px;
                        letter-spacing:.3px;
                        padding:11px 10px;
                        text-transform:uppercase;
                        margin-bottom:8px;
                    ">
                        <?= htmlspecialchars($fila['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>
                    </div>
                    <div style="
                        background:<?= htmlspecialchars($color_primario) ?>;
                        color:#dfe9f6;
                        padding:12px 28px 10px 28px;
                        min-height:108px;
                        font-size:15px;
                        line-height:1.32;
                        text-align:center;
                    ">
                        <div style="
                            font-weight:bold;
                            font-size:16px;
                            margin-bottom:8px;
                            color:#f4f8fd;
                            text-align:center;
                        ">
                            <?= htmlspecialchars($institucion_nombre) ?>
                        </div>

                        <table style="
                            border-collapse:collapse;
                            color:#dfe9f6;
                            font-size:15px;
                            line-height:1.18;
                            width:78%;
                            margin:0 auto;
                            text-align:left;
                        ">      
                            <tr>
                                <td style="width:34px; color:<?= htmlspecialchars($color_secundario) ?>; text-align:left; padding:0 10px 0 0;">
                                    <i class="fas fa-map-marker-alt"></i>
                                </td>
                                <td style="padding:1px 0; color:#dfe9f6;">
                                    <?= htmlspecialchars($institucion_direccion) ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:34px; color:<?= htmlspecialchars($color_secundario) ?>; text-align:left; padding:0 10px 0 0;">
                                    <i class="fas fa-phone"></i>
                                </td>
                                <td style="padding:1px 0; color:#dfe9f6;">
                                    <?= htmlspecialchars($institucion_telefonos) ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:34px; color:<?= htmlspecialchars($color_secundario) ?>; text-align:left; padding:0 10px 0 0;">
                                    <i class="far fa-envelope"></i>
                                </td>
                                <td style="padding:1px 0; color:#dfe9f6;">
                                    <?= htmlspecialchars($institucion_correo) ?>
                                </td>
                            </tr>
                            <?php if ($institucion_web !== ''): ?>
                                <tr>
                                    <td style="width:34px; color:<?= htmlspecialchars($color_secundario) ?>; text-align:left; padding:0 10px 0 0;">
                                        <i class="fas fa-globe"></i>
                                    </td>
                                    <td style="padding:1px 0; color:#dfe9f6;">
                                        <?= htmlspecialchars($institucion_web) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            <?php if (!empty($fila['marca_agua_url']) && !empty($fila['mostrar_marca_agua'])): ?>
                <img src="../<?= htmlspecialchars($fila['marca_agua_url']) ?>"
                    alt="Marca de Agua"
                    style="
                        position:absolute;
                        opacity:0.70;
                        width:<?= htmlspecialchars($marca_agua_width) ?>;
                        max-width:720px;
                        right:36px;
                        bottom:34px;
                        z-index:0;
                        pointer-events:none;
                    ">
            <?php endif; ?>

            <div style="position:relative; z-index:1; padding:30px 48px 0 48px;">
                <h2 style="
                    margin:0 0 6px 0;
                    color:<?= htmlspecialchars($color_secundario) ?>;
                    font-size:24px;
                    line-height:1;
                    font-weight:600;
                    text-transform:uppercase;
                ">
                    ANTECEDENTES GENERALES
                </h2>

                <div style="font-size:14px; line-height:1.38; margin-bottom:12px; color:#000;">
                    <?php foreach ($filas_campos_antecedentes as $camposFila): ?>
                        <?php
                            $campoPrincipal = $camposFila[0] ?? null;

                            if (!$campoPrincipal) {
                                continue;
                            }

                            $etiqueta_principal_original = trim($campoPrincipal['etiqueta'] ?? '');

                            if ($etiqueta_principal_original === '') {
                                continue;
                            }

                            $etiqueta_principal_preview = obtenerEtiquetaCampoClinicaPreview($etiqueta_principal_original);

                            $placeholders_fila = [];

                            foreach ($camposFila as $campoFila) {
                                $etiqueta_original = trim($campoFila['etiqueta'] ?? '');

                                if ($etiqueta_original === '') {
                                    continue;
                                }

                                $placeholders_fila[] = obtenerPlaceholderCampoClinicaPreview($etiqueta_original);
                            }
                        ?>

                        <div>
                            <strong><?= htmlspecialchars($etiqueta_principal_preview) ?>:</strong>
                            <span style="color:#777; font-style:italic;">
                                <?= htmlspecialchars(implode(', ', $placeholders_fila)) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="
                    height:3px;
                    background:<?= htmlspecialchars($color_primario) ?>;
                    margin:14px -10px 12px -10px;
                "></div>

                <h2 style="
                    margin:0 0 8px 0;
                    color:<?= htmlspecialchars($color_secundario) ?>;
                    font-size:24px;
                    line-height:1;
                    font-weight:800;
                    text-transform:uppercase;
                ">
                    DESCRIPCIÓN DEL ESTUDIO
                </h2>

                <div style="
                    padding:22px 18px;
                    margin:18px 0 0 0;
                    background-color:#f8f9fa;
                    border:1px dashed #ccc;
                    border-radius:8px;
                    text-align:center;
                    color:#777;
                    font-size:14px;
                ">
                    <em>[Aquí se mostrará el contenido del informe]</em>
                </div>

                <div style="
                    width:290px;
                    margin:<?= $firma_margin ?>;
                    text-align:center;
                    font-size:14px;
                    line-height:1.35;
                    color:#000;
                ">
                    <div style="margin-bottom:18px;">Atentamente,</div>

                    <?php if (!empty($fila['mostrar_firma_imagen']) && !empty($fila['firma_imagen_url'])): ?>
                        <img src="../<?= htmlspecialchars($fila['firma_imagen_url']) ?>"
                            alt="Firma"
                            style="
                                max-height:72px;
                                max-width:220px;
                                display:block;
                                margin:0 auto 8px auto;
                            ">
                    <?php endif; ?>

                    <div><?= htmlspecialchars($fila['firma_nombre'] ?? 'Nombre de la firma') ?></div>
                    <div><?= htmlspecialchars($fila['firma_titulo'] ?? 'Título profesional') ?></div>

                    <?php foreach ($firma_subtitulos as $linea): ?>
                        <small style="display:block;">
                            <?= htmlspecialchars($linea) ?>
                        </small>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($footer_texto !== ''): ?>
                <div style="
                    position:absolute;
                    left:70px;
                    right:70px;
                    bottom:72px;
                    text-align:<?= htmlspecialchars($footer_align) ?>;
                    font-size:12px;
                    line-height:1.35;
                    color:#111;
                ">
                    <?= nl2br(htmlspecialchars($footer_texto)) ?>
                </div>
            <?php endif; ?>

            <div style="
                position:absolute;
                left:36px;
                right:36px;
                bottom:46px;
                height:3px;
                background:<?= htmlspecialchars($color_primario) ?>;
            "></div>

            <div style="
                position:absolute;
                left:0;
                right:0;
                bottom:0;
                height:34px;
                background:<?= htmlspecialchars($color_primario) ?>;
            "></div>
        </div>
    

        <div style="
            position:relative;
            min-height:1060px;
            overflow:hidden;
            background:#fff;
            padding:60px 36px 40px 36px;
            border-top:18px solid #f3f4f6;
        ">
            <h2 style="
                margin:0 0 22px 0;
                color:<?= htmlspecialchars($color_secundario) ?>;
                font-size:23px;
                line-height:1;
                font-weight:400;
                text-transform:uppercase;
            ">
                IMÁGENES
            </h2>

            <div class="row g-3">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="col-<?= $col_imagen ?>">
                        <div style="
                            background:#e9ecef;
                            height:<?= $imagenes_por_fila === 1 ? '260px' : ($imagenes_por_fila === 2 ? '190px' : '135px') ?>;
                            border-radius:8px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            color:#6c757d;
                            font-size:14px;
                        ">
                            Imagen <?= $i + 1 ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <?php if ($footer_texto !== ''): ?>
                <div style="
                    position:absolute;
                    left:70px;
                    right:70px;
                    bottom:72px;
                    text-align:<?= htmlspecialchars($footer_align) ?>;
                    font-size:12px;
                    line-height:1.35;
                    color:#111;
                ">
                    <?= nl2br(htmlspecialchars($footer_texto)) ?>
                </div>
            <?php endif; ?>

            <div style="
                position:absolute;
                left:36px;
                right:36px;
                bottom:46px;
                height:3px;
                background:<?= htmlspecialchars($color_primario) ?>;
            "></div>

            <div style="
                position:absolute;
                left:0;
                right:0;
                bottom:0;
                height:34px;
                background:<?= htmlspecialchars($color_primario) ?>;
            "></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}