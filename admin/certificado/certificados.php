<?php
###########################################
require_once("../config.php");
require_once(__DIR__ . "/services/certificado_form_data.php");
date_default_timezone_set('America/Santiago');
###########################################

$mysqli = conn();
$action = $_GET['action'] ?? 'ingresar';

if ($action === 'modificar') {
    credenciales('certificado', 'modificar');
} else {
    credenciales('certificado', 'ingresar');
}

$formData = certificado_get_form_data($mysqli, $action, (int)$usuario_id);

$id                              = $formData['id'];
$accion                          = $formData['accion'];
$fila                            = $formData['fila'];
$imagenesGuardadas               = $formData['imagenesGuardadas'];
$mostrarImagenesAntiguas         = $formData['mostrarImagenesAntiguas'];
$plantillas_diseno               = $formData['plantillas_diseno'];
$configuracion_informe_id_actual = $formData['configuracion_informe_id_actual'];
$campos_permitidos_catalogo      = $formData['campos_permitidos_catalogo'];
$campos_visibles_actuales        = $formData['campos_visibles_actuales'];
?>
<div class="card" id="certificado" data-page-id="certificado">
    <div class="card-header pb-1">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <h1 class="h3 fw-bold mb-0"><?= htmlspecialchars($accion) ?> Informe</h1>

            <div class="w-100 w-md-auto" style="max-width: 320px;">
                <select name="configuracion_informe_id" id="configuracion_informe_id" class="form-select">
                    <option value="">Seleccione una plantilla de diseño</option>
                    <?php foreach ($plantillas_diseno as $plantillaDiseno): ?>
                        <option value="<?= (int)$plantillaDiseno['id'] ?>"
                            <?= $configuracion_informe_id_actual === (int)$plantillaDiseno['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plantillaDiseno['nombre_plantilla']) ?>
                            <?= (int)$plantillaDiseno['es_predeterminada'] === 1 ? ' (Predeterminada)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card-body">
        <?php require __DIR__ . '/partials/formulario_certificado.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/modales.php'; ?>

<script>
(function () {
    window.ES_MODIFICAR = <?= $action === 'modificar' ? 'true' : 'false' ?>;
})();
</script>

<script src="certificado/common/js/editor.js?v=3"></script>
<script src="certificado/metodo_ingreso/js/ia.js?v=4"></script>
<script src="certificado/preview/js/preview.js?v=1"></script>
<script src="certificado/guardar/js/guardar.js?v=1"></script>