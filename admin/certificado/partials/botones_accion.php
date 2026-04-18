<?php
// admin/certificado/partials/botones_accion.php
?>
<div class="row">
    <div class="col-md-6 text-start">
        <button type="button" class="btn btn-primary btn-lg" id="btnGuardarCertificado">
            <?= htmlspecialchars($accion) ?>
        </button>
    </div>

    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-secondary btn-lg" id="btnVistaPrevia">
            <i class="fas fa-eye me-2"></i> Vista previa
        </button>
    </div>
</div>