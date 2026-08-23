<?php
// admin/certificado/partials/formulario_certificado.php
?>
<form method="post" action="certificado/updCertificados.php" enctype="multipart/form-data" id="formCertificado">
    <div class="row g-1 mb-1">
        <input
            type="hidden"
            name="es_destacado"
            id="es_destacado"
            value="<?= $es_destacado_inicial ? '1' : '0' ?>"
        >

        <div
            id="destacadoTituloWrap"
            class="mb-3"
            style="<?= $es_destacado_inicial ? '' : 'display:none;' ?>"
        >
            <div class="border rounded bg-light p-2">
                <label for="destacado_titulo" class="form-label fw-bold mb-1">
                    <i class="fas fa-bookmark text-warning me-1"></i>
                    Título del destacado
                    <span class="text-muted fw-normal">(opcional)</span>
                </label>

                <input
                    type="text"
                    class="form-control"
                    name="destacado_titulo"
                    id="destacado_titulo"
                    maxlength="255"
                    value="<?= htmlspecialchars($destacado_titulo_inicial) ?>"
                    placeholder="Ej: hallazgo poco frecuente, condición especial..."
                >
            </div>
        </div>
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