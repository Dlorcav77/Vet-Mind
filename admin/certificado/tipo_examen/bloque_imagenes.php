<?php
// admin/certificado/tipo_examen/bloque_imagenes.php

/**
 * @var string $action
 * @var string $mostrarImagenesAntiguas
 */
?>
<style>

</style>
<link rel="stylesheet" href="certificado/tipo_examen/css/imagenes.css?v=1">
<div id="imagenesColumna" style="display:none;">
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
        <label for="columnasImagenes" class="form-label fw-bold mb-0">Imágenes</label>

        <div class="d-flex align-items-center gap-2">
            <select id="columnasImagenes" class="form-select form-select-sm" style="width: auto; min-width: 96px;">
                <option value="1">1 por fila</option>
                <option value="2" selected>2 por fila</option>
                <option value="3">3 por fila</option>
                <option value="4">4 por fila</option>
            </select>

            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                id="btnToggleImagenesPreview"
                data-visible="1"
                title="Ocultar imágenes"
            >
                <i class="fas fa-eye-slash"></i>
            </button>
        </div>
    </div>

    <div id="imagenesPreview" class="border rounded bg-light" style="min-height: 400px;">
        <em class="text-muted">Sube imágenes para verlas aquí.</em>
    </div>

    <div id="maxImgsWarning" style="display:none;"></div>
</div>

<div class="modal fade" id="imagenModal" tabindex="-1" aria-labelledby="imagenModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body p-0">
                <img src="" id="imagenModalSrc" class="img-fluid w-100 rounded">
            </div>

            <button type="button" id="prevImg" class="btn btn-light position-absolute top-50 start-0 translate-middle-y" style="z-index:1051;">
                &#8592;
            </button>

            <button type="button" id="nextImg" class="btn btn-light position-absolute top-50 end-0 translate-middle-y" style="z-index:1051;">
                &#8594;
            </button>

            <button
                type="button"
                id="btnEditarMedirImg"
                class="btn btn-warning position-absolute bottom-0 end-0 m-3"
                style="z-index:1052;">
                ✏️ Medir / Editar
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="medirModal" tabindex="-1" aria-labelledby="medirModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-body p-2">
                <div class="canvas-container">
                    <canvas id="canvasMedicion" style="border:1px solid #333; width: 100%; background: #fff;" width="1200" height="1300"></canvas>
                </div>

                <div class="d-flex">
                    <button type="button" class="btn btn-success flex-fill" id="btnGuardarMediciones">
                        💾 Guardar Mediciones
                    </button>
                    <button type="button" class="btn btn-primary flex-fill" id="btnDescargarImagen">
                        ⬇️ Descargar Imagen
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($action === 'modificar' && $mostrarImagenesAntiguas && !empty($imagenesGuardadas)): ?>
<script>
var imagenesAntiguas = <?= json_encode($imagenesGuardadas) ?>;
</script>
<?php else: ?>
<script>
var imagenesAntiguas = [];
</script>
<?php endif; ?>