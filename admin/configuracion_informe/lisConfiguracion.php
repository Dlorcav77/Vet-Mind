<?php
// admin/configuracion_informe/lisConfiguracion.php
###########################################
require_once("../config.php");
credenciales('configuracion_informe', 'listar');
###########################################

$mysqli = conn();
global $usuario_id, $acceso_aplicaciones;

$stmt = $mysqli->prepare("
    SELECT *
    FROM configuracion_informes
    WHERE veterinario_id = ?
    ORDER BY es_predeterminada DESC, updated_at DESC, id DESC
");
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();

$plantillas = [];
while ($row = $res->fetch_assoc()) {
    $plantillas[] = $row;
}

function renderVistaPreviaPlantilla($mysqli, $fila) {
    $map_justify = [
        'left' => 'start',
        'center' => 'center',
        'right' => 'end'
    ];
    $justify_class = $map_justify[$fila['firma_align'] ?? 'center'] ?? 'center';
    $fecha_justify_class = $map_justify[$fila['fecha_align'] ?? 'right'] ?? 'end';

    $tamaños_logo = ['small' => '50px', 'medium' => '80px', 'large' => '120px'];
    $logo_height = $tamaños_logo[$fila['logo_size'] ?? 'medium'] ?? '80px';

    $tamaños_agua = ['small' => '20%', 'medium' => '30%', 'large' => '50%'];
    $marca_width = $tamaños_agua[$fila['marca_agua_size'] ?? 'medium'] ?? '30%';

    $stmt_campos = $mysqli->prepare("
        SELECT cp.etiqueta
        FROM configuracion_informe_campos cic
        JOIN campos_permitidos cp ON cic.campo_id = cp.id
        WHERE cic.configuracion_informe_id = ? AND cic.visible = 1
        ORDER BY cic.orden ASC, cic.id ASC
    ");
    $stmt_campos->bind_param("i", $fila['id']);
    $stmt_campos->execute();
    $result_campos = $stmt_campos->get_result();

    $campos = [];
    while ($campo = $result_campos->fetch_assoc()) {
        $campos[] = $campo['etiqueta'];
    }

    $subtitulos = [];
    if (!empty($fila['firma_subtitulo'])) {
        $decoded = json_decode($fila['firma_subtitulo'], true);
        if (is_array($decoded)) {
            $subtitulos = $decoded;
        } else {
            $subtitulos = [$fila['firma_subtitulo']];
        }
    }

    $lugar = trim($fila['lugar_fecha'] ?? '');
    $fecha = new DateTime();
    $dia   = $fecha->format('j');
    $anio  = $fecha->format('Y');

    $meses = [
        'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
        'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
        'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
        'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
    ];
    $mes_en = $fecha->format('F');
    $mes_es = $meses[$mes_en] ?? strtolower($mes_en);
    $mes_es = ucfirst($mes_es);

    $formato = $fila['formato_fecha'] ?? '{{day}}/{{month}}/{{year}}';
    $fecha_str = str_replace(
        ['{{day}}', '{{month}}', '{{year}}'],
        [$dia, $mes_es, $anio],
        $formato
    );
    $fecha_str = ucfirst($fecha_str);

    $imagenes_por_fila = (int)($fila['imagenes_por_fila'] ?? 2);
    if ($imagenes_por_fila < 1) {
        $imagenes_por_fila = 2;
    }
    if ($imagenes_por_fila > 4) {
        $imagenes_por_fila = 4;
    }

    ob_start();
    ?>
    <div class="vista-previa-plantilla border p-4 p-md-5 bg-white rounded shadow-sm" style="position:relative;">
        <div class="d-flex justify-content-end gap-2 mb-2">
            <div style="
                width: 40px;
                height: 40px;
                border-radius: 8px;
                background: <?= htmlspecialchars($fila['color_primario'] ?? '#000') ?>;
                border: 2px solid #ccc;
                box-shadow: 0 0 5px rgba(0,0,0,0.2);
            " title="Primario"></div>

            <div style="
                width: 40px;
                height: 40px;
                border-radius: 8px;
                background: <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;
                border: 2px solid #ccc;
                box-shadow: 0 0 5px rgba(0,0,0,0.2);
            " title="Secundario"></div>
        </div>

        <?php if (!empty($fila['marca_agua_url']) && !empty($fila['mostrar_marca_agua'])): ?>
            <img src="../<?= htmlspecialchars($fila['marca_agua_url']) ?>"
                 alt="Marca de Agua"
                 style="position:absolute; opacity:0.05; width:<?= $marca_width ?>; top:50%; left:50%; transform:translate(-50%, -50%); z-index:0;">
        <?php endif; ?>

        <div class="text-<?= htmlspecialchars($fila['logo_position'] ?? 'center') ?>" style="position:relative; z-index:1;">
            <?php if (!empty($fila['logo_url'])): ?>
                <img src="../<?= htmlspecialchars($fila['logo_url']) ?>" alt="Logo" style="max-height:<?= $logo_height ?>;">
            <?php endif; ?>
        </div>

        <div class="text-center my-4">
            <h2 style="
                color: <?= htmlspecialchars($fila['color_primario'] ?? '#000') ?>;
                font-weight: 600;
                font-size: 1.5rem;
                letter-spacing: 0.7px;
                font-family: 'Times New Roman', 'Segoe UI', Arial, Helvetica, sans-serif;
            ">
                <?= htmlspecialchars($fila['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>
            </h2>
        </div>

        <?php if (!empty($campos)): ?>
            <table class="table table-sm table-bordered mb-4" style="border: 1px solid #000; font-size: 14px;">
                <tbody>
                <?php for ($i = 0; $i < count($campos); $i += 2): ?>
                    <tr>
                        <td style="font-weight:bold; width: 15%; background-color: <?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71') ?>;">
                            <?= htmlspecialchars($campos[$i]) ?>:
                        </td>

                        <?php if (isset($campos[$i + 1])): ?>
                            <td style="width: 35%;"></td>
                            <td style="font-weight:bold; width: 15%; background-color: <?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71') ?>;">
                                <?= htmlspecialchars($campos[$i + 1]) ?>:
                            </td>
                            <td style="width: 35%;"></td>
                        <?php else: ?>
                            <td colspan="3" style="width: 85%;"></td>
                        <?php endif; ?>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-secondary mt-4 text-center">
                <i class="fas fa-info-circle"></i> No hay campos configurados para mostrar.
            </div>
        <?php endif; ?>

        <?php if (!empty($fila['subtitulo'])): ?>
            <div class="text-<?= htmlspecialchars($fila['subtitulo_align'] ?? 'center') ?>" style="margin-top:0.5rem;">
                <h5 style="
                    color: <?= htmlspecialchars($fila['color_primario'] ?? '#2ecc71') ?>;
                    font-weight: 600;
                    font-size: 1rem;
                    margin-bottom: 1rem;
                ">
                    <?= htmlspecialchars($fila['subtitulo']) ?>
                </h5>
            </div>
        <?php endif; ?>

        <div style="padding: 1.5rem; margin: 1.5rem 0; background-color: #f8f9fa; border: 1px dashed #ccc; border-radius: 8px; text-align: center; color: #888;">
            <em>[Aquí se mostrará el contenido del informe]</em>
        </div>

        <div class="my-4 d-flex justify-content-<?= $justify_class ?> me-0 me-md-5">
            <div class="text-center">
                <?php if (!empty($fila['mostrar_firma_imagen']) && !empty($fila['firma_imagen_url'])): ?>
                    <img src="../<?= htmlspecialchars($fila['firma_imagen_url']) ?>"
                         alt="Firma Escaneada"
                         style="max-height:100px; display:block; margin:0 auto 0px;">
                <?php endif; ?>

                <h4 style="color:#555; margin-bottom:0;">
                    <?= htmlspecialchars($fila['firma_nombre'] ?? 'Nombre de la Firma') ?>
                </h4>

                <p style="color:#555; margin:0;">
                    <?= htmlspecialchars($fila['firma_titulo'] ?? 'Título Profesional') ?>
                </p>

                <?php foreach ($subtitulos as $sub): ?>
                    <small style="color:#555; display:block;">
                        <?= htmlspecialchars($sub) ?>
                    </small>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($fila['mostrar_fecha'])): ?>
            <div class="d-flex justify-content-<?= $fecha_justify_class ?> mb-2">
                <div style="color:#555;">
                    <?= ($lugar ? htmlspecialchars($lugar) . ", " : '') . $fecha_str ?>
                </div>
            </div>
        <?php endif; ?>

        <hr style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">

        <div class="mt-3">
            <div class="row g-2">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="col-<?= (int)(12 / $imagenes_por_fila) ?>">
                        <div class="card shadow-sm border-0" style="background-color: #e9ecef; height: 100px; border-radius: 8px;">
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted" style="font-size: 0.9rem;">
                                Imagen <?= $i + 1 ?>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <hr style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">

        <?php if (!empty($fila['footer_texto'])): ?>
            <div class="text-<?= htmlspecialchars($fila['footer_align'] ?? 'center') ?>">
                <small style="color:#888;">
                    <?= nl2br(htmlspecialchars($fila['footer_texto'])) ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>
<style>
    .config-card-resumen .mini-color {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        border: 1px solid #ccc;
    }

    .preview-modal-body {
        background: #f3f4f6;
    }

    .preview-modal-body .modal-preview-wrap {
        max-width: 980px;
        margin: 0 auto;
    }
</style>

<div id="configuracion_informe" data-page-id="configuracion_informe">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Configuración de Informes</strong></h1>

        <?php if (in_array('ingresar', $acceso_aplicaciones['configuracion_informe'] ?? [])): ?>
            <a href="configuracion_informe/configuracion.php?action=ingresar" class="btn btn-primary ajax-link">
                <i class="fas fa-plus"></i> Nueva plantilla
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($plantillas)): ?>
        <div class="row">
            <?php foreach ($plantillas as $fila): ?>
                <?php
                $stmt_campos = $mysqli->prepare("
                    SELECT COUNT(*) AS total
                    FROM configuracion_informe_campos
                    WHERE configuracion_informe_id = ? AND visible = 1
                ");
                $stmt_campos->bind_param("i", $fila['id']);
                $stmt_campos->execute();
                $res_campos = $stmt_campos->get_result();
                $info_campos = $res_campos->fetch_assoc();
                $total_campos = (int)($info_campos['total'] ?? 0);

                $previewHtml = renderVistaPreviaPlantilla($mysqli, $fila);
                ?>
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="card h-100 shadow-sm border-0 config-card-resumen">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h3 class="mb-1">
                                        <?= htmlspecialchars($fila['nombre_plantilla'] ?? 'Plantilla sin nombre') ?>
                                    </h3>

                                    <?php if ((int)$fila['es_predeterminada'] === 1): ?>
                                        <span class="badge bg-success">Predeterminada</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <div class="mini-color" style="background: <?= htmlspecialchars($fila['color_primario'] ?? '#000') ?>;" title="Color primario"></div>
                                    <div class="mini-color" style="background: <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;" title="Color secundario"></div>
                                </div>
                            </div>

                            <div class="small text-muted mb-3">
                                Última actualización:
                                <?= !empty($fila['updated_at']) ? date('d-m-Y H:i', strtotime($fila['updated_at'])) : '-' ?>
                            </div>

                            <div class="mb-2">
                                <strong>Título:</strong><br>
                                <?= htmlspecialchars($fila['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>
                            </div>

                            <div class="mb-2">
                                <strong>Subtítulo:</strong><br>
                                <?= htmlspecialchars($fila['subtitulo'] ?? '-') ?>
                            </div>

                            <!-- <div class="mb-2">
                                <strong>Campos visibles:</strong><br>
                                <?= $total_campos ?>
                            </div> -->

                            <div class="">
                                <strong>Firma:</strong><br>
                                <?= htmlspecialchars($fila['firma_nombre'] ?? '-') ?>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-0 pt-0">
                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-preview-plantilla"
                                    data-nombre="<?= htmlspecialchars($fila['nombre_plantilla'] ?? 'Vista previa', ENT_QUOTES, 'UTF-8') ?>"
                                    data-preview="<?= htmlspecialchars($previewHtml, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i class="fas fa-eye"></i> Vista previa
                                </button>

                                <?php if (in_array('modificar', $acceso_aplicaciones['configuracion_informe'] ?? [])): ?>
                                    <a href="configuracion_informe/configuracion.php?action=modificar&id=<?= (int)$fila['id'] ?>" class="btn btn-primary ajax-link">
                                        <i class="fas fa-edit"></i> Modificar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <p class="mb-3">No hay plantillas registradas todavía.</p>

                <?php if (in_array('ingresar', $acceso_aplicaciones['configuracion_informe'] ?? [])): ?>
                    <a href="configuracion_informe/configuracion.php?action=ingresar" class="btn btn-primary ajax-link">
                        <i class="fas fa-plus"></i> Crear primera plantilla
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalVistaPreviaPlantilla" tabindex="-1" aria-labelledby="modalVistaPreviaPlantillaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalVistaPreviaPlantillaLabel">Vista previa de plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body preview-modal-body">
                <div class="modal-preview-wrap" id="contenidoVistaPreviaPlantilla"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).off('click', '.btn-preview-plantilla').on('click', '.btn-preview-plantilla', function () {
    const nombre = $(this).data('nombre') || 'Vista previa de plantilla';
    const preview = $(this).data('preview') || '';

    $('#modalVistaPreviaPlantillaLabel').text(nombre);
    $('#contenidoVistaPreviaPlantilla').html(preview);
    $('#modalVistaPreviaPlantilla').modal('show');
});
</script>