<?php
// admin/certificado/partials/botones_accion.php
?>
<div class="row align-items-center mt-3">
    <div class="col-md-6 text-start mb-2 mb-md-0">
        <button type="button" class="btn btn-primary btn-lg" id="btnGuardarCertificado">
            <?= htmlspecialchars($accion) ?>
        </button>
    </div>

    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-secondary btn-lg" id="btnVistaPrevia">
            <i class="fas fa-eye me-2"></i> Vista previa
        </button>
    </div>
</div>