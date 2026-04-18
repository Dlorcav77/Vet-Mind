<?php
// admin/certificado/partials/formulario_certificado.php
?>
<form method="post" action="certificado/updCertificados.php" enctype="multipart/form-data" id="formCertificado">
    <div class="row g-1 mb-1">
        <?php include __DIR__ . '/../pacientes/paciente.php'; ?>
        <hr>
        <?php include __DIR__ . '/../tipo_examen/tipo_examen.php'; ?>
        <hr>
        <div class="bg-light p-2 rounded-4">
            <?php include __DIR__ . '/../metodo_ingreso/metodo_ingreso.php'; ?>
        </div>
    </div>

    <?php include __DIR__ . '/hidden_fields.php'; ?>
    <?php include __DIR__ . '/botones_accion.php'; ?>
</form>