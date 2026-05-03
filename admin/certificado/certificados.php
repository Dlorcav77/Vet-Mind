<?php
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
?>
<style>
    .cert-header-top {
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    .cert-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .cert-title-wrap {
        display: flex;
        align-items: center;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .draft-badge-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .28rem .58rem;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 600;
        line-height: 1;
        border: 1px solid transparent;
        transition: all .2s ease;
        white-space: nowrap;
    }

    .draft-badge-status .draft-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
        flex: 0 0 7px;
    }

    .draft-badge-trash {
        border: 0;
        background: transparent;
        padding: 0;
        margin-left: .15rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #dc3545;
        opacity: .9;
        cursor: pointer;
        line-height: 1;
        font-size: .72rem;
    }

    .draft-badge-trash:hover {
        color: #bb2d3b;
        opacity: 1;
        transform: scale(1.08);
    }

    .draft-badge-trash:focus {
        outline: none;
        box-shadow: none;
        color: #bb2d3b;
    }

    .draft-badge-status.is-idle {
        background: #f8f9fa;
        color: #6c757d;
        border-color: #dee2e6;
    }

    .draft-badge-status.is-idle .draft-dot {
        background: #adb5bd;
    }

    .draft-badge-status.is-saving {
        background: #fff3cd;
        color: #856404;
        border-color: #ffe69c;
    }

    .draft-badge-status.is-saving .draft-dot {
        background: #f0ad4e;
        box-shadow: 0 0 0 0 rgba(240, 173, 78, .55);
        animation: draftPulse 1.2s infinite;
    }

    .draft-badge-status.is-saved {
        background: #d1e7dd;
        color: #0f5132;
        border-color: #badbcc;
    }

    .draft-badge-status.is-saved .draft-dot {
        background: #198754;
    }

    .draft-badge-status.is-error {
        background: #f8d7da;
        color: #842029;
        border-color: #f1aeb5;
    }

    .draft-badge-status.is-error .draft-dot {
        background: #dc3545;
    }

    @keyframes draftPulse {
        0% {
            box-shadow: 0 0 0 0 rgba(240, 173, 78, .55);
        }
        70% {
            box-shadow: 0 0 0 8px rgba(240, 173, 78, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(240, 173, 78, 0);
        }
    }

    @media (max-width: 768px) {
        .cert-title-row {
            align-items: flex-start;
        }

        .cert-title-wrap {
            width: 100%;
        }
    }
</style>
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
<script src="certificado/metodo_ingreso/js/ia.js?v=5"></script>
<script src="certificado/preview/js/preview.js?v=2"></script>
<script src="certificado/guardar/js/guardar.js?v=14"></script>