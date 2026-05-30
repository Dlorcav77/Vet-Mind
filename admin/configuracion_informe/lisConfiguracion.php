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

function nombreLayoutInforme($layoutTipo) {
    $layoutTipo = trim((string)$layoutTipo);

    $layouts = [
        'clasico' => 'Clásico',
        'clinica'    => 'Clinica'
    ];

    return $layouts[$layoutTipo] ?? 'Clásico';
}

require_once __DIR__ . '/previews/preview_loader.php';
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

                                    <div class="d-flex gap-1 flex-wrap mt-1">
                                        <?php if ((int)$fila['es_predeterminada'] === 1): ?>
                                            <span class="badge bg-success">Predeterminada</span>
                                        <?php endif; ?>

                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars(nombreLayoutInforme($fila['layout_tipo'] ?? 'clasico')) ?>
                                        </span>
                                    </div>
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