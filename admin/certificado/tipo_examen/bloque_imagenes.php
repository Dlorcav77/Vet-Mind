<?php
// admin/certificado/tipo_examen/bloque_imagenes.php

/**
 * @var string $action
 * @var string $mostrarImagenesAntiguas
 */
?>
<link rel="stylesheet" href="certificado/tipo_examen/css/imagenes.css?v=4">
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
                data-visible="0"
                title="Mostrar imágenes"
            >
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>

    <div
        id="imagenesPreview"
        class="border rounded bg-light"
        style="display:none; min-height: 400px;"
    >
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
            <button type="button" id="btnEditarMedirImg" class="btn btn-medicion-flotante">
                <i class="fas fa-ruler-combined"></i>
                <span>Medir / Editar</span>
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="medirModal" tabindex="-1" aria-labelledby="medirModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-body p-2">
                <div class="canvas-container position-relative">
                    <canvas id="canvasMedicion" width="1200" height="1300"></canvas>
                    <div id="estadoCalibracion" class="estado-calibracion"></div>
                    <svg class="filtros-medicion-svg" width="0" height="0">
                        <filter id="filtroGammaMedicion" color-interpolation-filters="sRGB">
                            <feComponentTransfer>
                                <feFuncR type="gamma" amplitude="1" exponent="1" offset="0"/>
                                <feFuncG type="gamma" amplitude="1" exponent="1" offset="0"/>
                                <feFuncB type="gamma" amplitude="1" exponent="1" offset="0"/>
                            </feComponentTransfer>
                            <feConvolveMatrix id="filtroNitidezMedicion" order="3" kernelMatrix="0 0 0 0 1 0 0 0 0" divisor="1" edgeMode="duplicate" preserveAlpha="true"/>
                        </filter>
                    </svg>

                    <div id="panelVisualizacion" class="panel-visualizacion">
                        <button type="button" class="btn btn-sm btn-panel-medicion" id="btnToggleVisualizacion">
                            <i class="fas fa-sliders-h me-1"></i> Visualización
                        </button>

                        <div id="controlesVisualizacion" class="controles-visualizacion">
                            <div class="small mb-2">
                                <label for="visualBrillo" class="d-flex justify-content-between">
                                    <span>Brillo</span>
                                    <span id="visualBrilloValor">100%</span>
                                </label>
                                <input type="range" id="visualBrillo" class="form-range" min="50" max="160" value="100">
                            </div>

                            <div class="small mb-2">
                                <label for="visualContraste" class="d-flex justify-content-between">
                                    <span>Contraste</span>
                                    <span id="visualContrasteValor">100%</span>
                                </label>
                                <input type="range" id="visualContraste" class="form-range" min="50" max="180" value="100">
                            </div>

                            <div class="small mb-2">
                                <label for="visualGamma" class="d-flex justify-content-between">
                                    <span>Gamma</span>
                                    <span id="visualGammaValor">1.00</span>
                                </label>
                                <input type="range" id="visualGamma" class="form-range" min="0.60" max="1.60" step="0.05" value="1">
                            </div>

                            <div class="small mb-2">
                                <label for="visualNitidez" class="d-flex justify-content-between">
                                    <span>Nitidez</span>
                                    <span id="visualNitidezValor">0%</span>
                                </label>
                                <input type="range" id="visualNitidez" class="form-range" min="0" max="50" step="5" value="0">
                            </div>

                            <div class="small mb-2">
                                <label for="visualZoom" class="d-flex justify-content-between">
                                    <span>Zoom</span>
                                    <span id="visualZoomValor">100%</span>
                                </label>
                                <input type="range" id="visualZoom" class="form-range" min="100" max="250" step="25" value="100">
                            </div>

                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-light flex-fill" id="btnMoverImagen">
                                    <i class="fas fa-hand-paper me-1"></i> Mover
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-light flex-fill" id="btnResetVista">
                                    <i class="fas fa-crosshairs me-1"></i> Centrar
                                </button>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-light flex-fill" id="btnInvertirVisualizacion">Invertir</button>
                                <button type="button" class="btn btn-sm btn-outline-light flex-fill" id="btnResetVisualizacion">Restablecer</button>
                            </div>
                        </div>
                    </div>
                    <div id="calibracionManualPanel" class="calibracion-manual-panel">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fas fa-ruler-horizontal text-warning"></i>
                            <div class="fw-bold">Calibración manual</div>
                        </div>
                        <div class="small text-muted mb-2">Marca dos puntos de la regla e indica la distancia.</div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" id="calibracionManualCm" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="Ej: 5">
                            <span class="small">cm</span>
                            <button type="button" class="btn btn-sm btn-warning flex-grow-1" id="btnAplicarCalibracionManual" disabled>
                                <i class="fas fa-check me-1"></i> Aplicar
                            </button>
                        </div>
                    </div>

                    <canvas id="canvasLupaMedicion" width="160" height="160"></canvas>
                </div>

                <div class="d-flex gap-1 mt-1">
                    <button type="button" class="btn btn-success flex-fill btn-accion-medicion" id="btnGuardarMediciones">
                        <i class="fas fa-save me-1"></i> Guardar mediciones
                    </button>
                    <button type="button" class="btn btn-primary flex-fill btn-accion-medicion" id="btnDescargarImagen">
                        <i class="fas fa-download me-1"></i> Descargar imagen
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