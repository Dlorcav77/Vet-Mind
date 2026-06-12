<?php
// admin/certificado/certificados.php

###########################################
require_once("../config.php");
require_once(__DIR__ . "/services/certificado_form_data.php");
require_once(__DIR__ . "/services/limpiar_temporales_certificados.php");
date_default_timezone_set('America/Santiago');
###########################################

$mysqli = conn();
$action = $_GET['action'] ?? 'ingresar';

if ($action === 'modificar') {
    credenciales('certificado', 'modificar');
} else {
    credenciales('certificado', 'ingresar');
}

limpiarTemporalesCertificados();

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
$toggle_manual_inicial           = $formData['toggle_manual_inicial'];
$hay_borrador                    = $formData['hay_borrador'];
$borrador_id                     = $formData['borrador_id'];
$borrador_updated_at             = $formData['borrador_updated_at'];
$borrador_payload                = $formData['borrador_payload'];
$borrador_scope_key              = $formData['borrador_scope_key'];
$modo_ingreso_contenido_inicial  = $formData['modo_ingreso_contenido_inicial'];
$clinicas_recinto                = $formData['clinicas_recinto'];
?>
<link rel="stylesheet" href="certificado/common/css/certificado.css?v=2">
<div class="card" id="certificado" data-page-id="certificado">
    <div class="card-header pb-1">
        <div class="cert-header-top">
            <div class="cert-title-row">
                <div class="cert-title-wrap">
                    <h1 class="h3 fw-bold mb-0"><?= htmlspecialchars($accion) ?> Informe</h1>

                    <span id="draftBadgeStatus" class="draft-badge-status <?= !empty($hay_borrador) ? 'is-saved' : 'is-idle' ?>">
                        <span class="draft-dot"></span>

                        <span id="draftBadgeText">
                            <?= !empty($hay_borrador) ? 'Borrador recuperado' : 'Sin cambios guardados' ?>
                        </span>

                        <button
                            type="button"
                            id="btnDescartarBorradorHeader"
                            class="draft-badge-trash"
                            title="Descartar borrador"
                            aria-label="Descartar borrador"
                        >
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </span>
                </div>

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
    </div>

    <div class="card-body">
        <?php require __DIR__ . '/partials/formulario_certificado.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/modales.php'; ?>

<script>
(function () {
    window.ES_MODIFICAR = <?= $action === 'modificar' ? 'true' : 'false' ?>;
    window.CERT_BORRADOR = {
        id: <?= (int)$borrador_id ?>,
        hasDraft: <?= $hay_borrador ? 'true' : 'false' ?>,
        updatedAt: <?= json_encode($borrador_updated_at, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        scopeKey: <?= json_encode($borrador_scope_key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
})();
</script>

<script src="certificado/common/js/editor.js?v=3"></script>
<script src="certificado/metodo_ingreso/js/ia.js?v=11"></script>
<script src="certificado/preview/js/preview.js?v=4"></script>
<script src="certificado/guardar/js/guardar.js?v=15"></script>