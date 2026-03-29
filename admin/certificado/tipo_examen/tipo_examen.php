<?php
###########################################
require_once("../config.php");
###########################################

if (!isset($tipos_estudio)) {
    $tipos_estudio = [];
    $query = "
        SELECT te.id AS tipo_id, te.nombre AS tipo_nombre, pi.id AS plantilla_id, pi.nombre AS plantilla_nombre
        FROM tipo_examen te
        LEFT JOIN plantilla_informe pi ON pi.tipo_examen_id = te.id AND pi.estado = 'activo' AND pi.deleted_at IS NULL
        WHERE te.veterinario_id = ?
        ORDER BY te.nombre ASC, pi.nombre ASC
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tipo_id = $row['tipo_id'];
        $tipo_nombre = $row['tipo_nombre'];
        if (!isset($tipos_estudio[$tipo_id])) {
            $tipos_estudio[$tipo_id] = [
                'nombre' => $tipo_nombre,
                'plantillas' => []
            ];
        }
        if (!empty($row['plantilla_id'])) {
            $tipos_estudio[$tipo_id]['plantillas'][] = [
                'id' => $row['plantilla_id'],
                'nombre' => $row['plantilla_nombre']
            ];
        }
    }
}
?>
<style>
    #imagenesPreview {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0px;
        max-height: 400px;
        overflow-y: auto;
        padding: 0px;
    }

    #imagenesPreview img {
        width: 100%;
        height: auto;
        object-fit: contain;
        border-radius: 8px;
        border: 1px solid #ddd;
    }

    #plantillaContenido {
        max-height: 400px;
        overflow-y: auto;
        padding: 8px;
    }
</style>

<div
    class="col-md-4 mb-3"
    id="wrap_motivo_examen"
    data-campo-general="antecedentes"
    style="<?= in_array('antecedentes', $campos_visibles_actuales ?? [], true) ? '' : 'display:none;' ?>"
>
    <label for="motivo_examen" class="form-label fw-bold">Motivo</label>
    <input
        type="text"
        class="form-control"
        name="motivo_examen"
        id="motivo_examen"
        value="<?= htmlspecialchars($fila['motivo'] ?? '') ?>"
    >
</div>

<div
    class="col-md-4 mb-3"
    id="wrap_medico_solicitante"
    data-campo-general="m_solicitante"
    style="<?= in_array('m_solicitante', $campos_visibles_actuales ?? [], true) ? '' : 'display:none;' ?>"
>
    <label for="medico_solicitante" class="form-label fw-bold">Médico Solicitante</label>
    <input
        type="text"
        class="form-control"
        name="medico_solicitante"
        id="medico_solicitante"
        value="<?= htmlspecialchars($fila['medico_solicitante'] ?? '') ?>"
    >
</div>

<div
    class="col-md-4 mb-3"
    id="wrap_recinto"
    data-campo-general="recinto"
    style="<?= in_array('recinto', $campos_visibles_actuales ?? [], true) ? '' : 'display:none;' ?>"
>
    <label for="recinto" class="form-label fw-bold">Recinto</label>
    <input
        type="text"
        class="form-control"
        name="recinto"
        id="recinto"
        value="<?= htmlspecialchars($fila['recinto'] ?? '') ?>"
    >
</div>

<div class="col-md-6 mb-3">
    <label for="plantilla_informe_id" class="form-label fw-bold">Tipo de Examen</label>
    <select name="plantilla_informe_id" id="plantilla_informe_id" class="form-select" required>
        <option value="">Seleccione una plantilla</option>
        <?php foreach ($tipos_estudio as $tipo): ?>
            <?php if (!empty($tipo['plantillas'])): ?>
                <optgroup label="<?= htmlspecialchars($tipo['nombre']) ?>">
                    <?php foreach ($tipo['plantillas'] as $plantilla): ?>
                        <option value="<?= htmlspecialchars($plantilla['id']) ?>"
                            <?= (isset($fila['tipo_estudio']) && $plantilla['id'] == $fila['tipo_estudio']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plantilla['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-6">
    <label for="imagenInput" class="form-label fw-bold">Imágenes Asociadas</label>
    <input type="file" id="imagenInput" class="form-control mb-2" name="imagenes[]" multiple accept="image/*">
</div>

<div class="col-md-6" id="plantillaPlaceholder" style="display:block;"></div>

<div class="col-md-6 mb-2" id="plantillaPreview" style="display:none;">
    <span class="form-label fw-bold">Plantilla Asociada</span>
    <div class="border rounded p-3 mt-2 bg-light" id="plantillaContenido" style="min-height: 300px; overflow-y: auto;">
        <em class="text-muted">Selecciona un tipo de examen para ver su plantilla...</em>
    </div>
</div>

<div class="col-md-6 mb-2" id="imagenesColumna" style="display:none;">
    <div class="d-flex justify-content-between align-items-center">
        <label for="columnasImagenes" class="form-label fw-bold">Imágenes</label>
        <select id="columnasImagenes" class="form-select form-select-sm" style="width: auto;">
            <option value="1">1 por fila</option>
            <option value="2" selected>2 por fila</option>
            <option value="3">3 por fila</option>
            <option value="4">4 por fila</option>
        </select>
    </div>

    <div id="imagenesPreview" class="border rounded bg-light" style="min-height: 150px; overflow-y: auto;">
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

<script>
var imagenesArray = [];
var imagenActual = 0;
var archivosSeleccionados = [];
var LIMITE_IMAGENES = 20;
var modoSoloGuardar = false;
var nombreTempImagen = null;
var imagenesAntiguasCargadas = [];

$('select[name="plantilla_informe_id"]').on('change', function () {
    let tipo = $(this).val();
    $('#procesarIA').prop('disabled', true);

    if (!tipo) {
        $('#plantillaBase').val('');
        $('#plantillaPreview').hide();
        $('#plantillaPlaceholder').show();
        $('#plantillaContenido').html('<em class="text-muted">Selecciona un tipo de examen para ver su plantilla...</em>');
        return;
    }

    $.ajax({
        url: 'certificado/tipo_examen/getPlantillaPorTipo.php',
        type: 'POST',
        data: { plantilla_informe_id: tipo },
        success: function (res) {
            if (res.status === 'success') {
                $('#plantillaBase').val(res.contenido);
                $('#plantillaContenido').html(res.contenido);
                $('#plantillaPlaceholder').hide();
                $('#plantillaPreview').show();
                $('#procesarIA').prop('disabled', false);

                if (!ES_MODIFICAR && audio_manual_isManual()) {
                    if (CKEDITOR.instances['contenido_html']) {
                        const actual = CKEDITOR.instances['contenido_html'].getData().trim();
                        if (!actual) {
                            CKEDITOR.instances['contenido_html'].setData(res.contenido);
                        }
                    } else {
                        const $txt = $('#contenido_html');
                        if ($txt.length && !$txt.val().trim()) {
                            $txt.val(res.contenido);
                        }
                    }
                }
            } else {
                $('#plantillaBase').val('');
                $('#plantillaContenido').html('<div class="text-danger">' + res.message + '</div>');
                $('#plantillaPlaceholder').hide();
                $('#plantillaPreview').show();
            }
        },
        error: function () {
            $('#plantillaBase').val('');
            $('#plantillaContenido').html('<div class="text-danger">Error al cargar la plantilla para el examen.</div>');
            $('#plantillaPlaceholder').hide();
            $('#plantillaPreview').show();
            Swal.fire('Error', 'Error al cargar la plantilla para el examen.', 'error');
        }
    });
});

$('#maxImgs').text(LIMITE_IMAGENES);

$('#imagenInput').on('change', function (e) {
    archivosSeleccionados = Array.from(e.target.files);
    renderPreview();
});

function renderPreview() {
    const scrollTop = $('#imagenesPreview').scrollTop();
    $('#imagenesPreview').html('');

    if (imagenesAntiguasCargadas.length > 0) {
        imagenesAntiguasCargadas.forEach((src, idx) => {
            const imageContainer = $('<div>', {
                class: 'position-relative d-inline-block',
                css: { margin: '5px' },
                'data-antigua': 'true',
                'data-idx': idx,
                'data-file-idx': idx
            });

            const img = $('<img>', {
                src: src,
                class: 'rounded img-medium',
                css: { objectFit: 'cover', border: '1px solid #ddd' }
            });

            const deleteBtn = $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-danger position-absolute top-0 end-0',
                html: '&times;',
                css: { padding: '2px 6px', borderRadius: '50%' },
                click: function () {
                    imagenesAntiguas.splice(idx, 1);
                    renderImagenesAntiguas();
                    $('#imagenes_antiguas').val(JSON.stringify(imagenesAntiguas));
                }
            });

            imageContainer.append(img).append(deleteBtn);
            $('#imagenesPreview').append(imageContainer);
        });
    }

    archivosSeleccionados.forEach((file, idx) => {
        if (!file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const imageContainer = $('<div>', {
                class: 'position-relative d-inline-block',
                css: { margin: '5px' },
                'data-idx': idx,
                'data-file-idx': idx
            });

            const img = $('<img>', {
                src: e.target.result,
                class: 'rounded img-medium',
                css: { objectFit: 'cover', border: '1px solid #ddd' }
            });

            const deleteBtn = $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-danger position-absolute top-0 end-0',
                html: '&times;',
                css: { padding: '2px 6px', borderRadius: '50%' },
                click: function () {
                    const realIdx = $(this).parent().data('idx');
                    $(this).parent().fadeOut(200, function () {
                        archivosSeleccionados.splice(realIdx, 1);
                        renderPreview();
                        updateInputFiles();
                    });
                }
            });

            imageContainer.append(img).append(deleteBtn);
            $('#imagenesPreview').append(imageContainer);
            $('#imagenesPreview').scrollTop(scrollTop);
        };

        reader.readAsDataURL(file);
    });

    if (archivosSeleccionados.length > LIMITE_IMAGENES) {
        let cantidadEliminar = archivosSeleccionados.length - LIMITE_IMAGENES;
        $('#maxImgsWarning')
            .html('<div class="alert alert-warning p-1 my-2" role="alert" style="font-size:0.8rem;">' +
                '⚠️ <strong>Límite de imágenes:</strong> Se pueden subir como máximo <b>' +
                LIMITE_IMAGENES + '</b> imágenes. Elimine <b>' + cantidadEliminar + '</b>' +
                '</div>')
            .show();
    } else {
        $('#maxImgsWarning').hide();
    }

    if (archivosSeleccionados.length === 0 && imagenesAntiguasCargadas.length === 0) {
        $('#imagenesPreview').html('<em class="text-muted">Sube imágenes para verlas aquí.</em>');
        $('#imagenesColumna').hide();
        $('#maxImgsWarning').hide();
    } else {
        $('#imagenesColumna').show();
    }
}

imagenesAntiguasCargadas = [];

function renderImagenesAntiguas() {
    if (!imagenesAntiguas.length) return;

    imagenesAntiguasCargadas = [];
    $('#imagenesColumna').show();
    $('#imagenesPreview').empty();

    imagenesAntiguas.forEach((src, idx) => {
        const imageContainer = $('<div>', {
            class: 'position-relative d-inline-block',
            css: { margin: '5px' },
            'data-antigua': 'true',
            'data-idx': idx
        }).attr('data-file-idx', idx);

        const img = $('<img>', {
            src: src,
            class: 'rounded img-medium',
            css: { objectFit: 'cover', border: '1px solid #ddd' }
        });

        const deleteBtn = $('<button>', {
            type: 'button',
            class: 'btn btn-sm btn-danger position-absolute top-0 end-0',
            html: '&times;',
            css: { padding: '2px 6px', borderRadius: '50%' },
            click: function () {
                imagenesAntiguas.splice(idx, 1);
                renderImagenesAntiguas();
                $('#imagenes_antiguas').val(JSON.stringify(imagenesAntiguas));
            }
        });

        imageContainer.append(img).append(deleteBtn);
        $('#imagenesPreview').append(imageContainer);
        imagenesAntiguasCargadas.push(src);
    });
}

function updateInputFiles() {
    let dt = new DataTransfer();
    archivosSeleccionados.forEach(f => dt.items.add(f));
    $('#imagenInput')[0].files = dt.files;
}

function controlarGuardar() {
    if (archivosSeleccionados.length > LIMITE_IMAGENES) {
        $('#btnGuardarCertificado').prop('disabled', true);
    } else {
        $('#btnGuardarCertificado').prop('disabled', false);
    }
}

$('#tamanoImagenes').on('change', function () {
    const sizeClass = 'img-' + $(this).val();
    $('#imagenesPreview img')
        .removeClass('img-small img-medium img-large')
        .addClass(sizeClass);
});

$('#columnasImagenes').on('change', function () {
    const columnas = $(this).val();
    $('#imagenesPreview').css('grid-template-columns', `repeat(${columnas}, 1fr)`);
});

$(document).on('click', '#imagenesPreview img', function () {
    imagenesArray = [];

    $('#imagenesPreview img').each(function () {
        imagenesArray.push($(this).attr('src'));
    });

    imagenActual = $('#imagenesPreview img').index(this);
    $('#imagenModalSrc').attr('src', imagenesArray[imagenActual]);
    $('#imagenModal').modal('show');
});

$('#prevImg').on('click', function () {
    if (imagenActual > 0) {
        imagenActual--;
        $('#imagenModalSrc').attr('src', imagenesArray[imagenActual]);
    }
});

$('#nextImg').on('click', function () {
    if (imagenActual < imagenesArray.length - 1) {
        imagenActual++;
        $('#imagenModalSrc').attr('src', imagenesArray[imagenActual]);
    }
});

$(document).on('keydown', function (e) {
    if ($('#imagenModal').hasClass('show')) {
        if (e.key === "ArrowLeft" && imagenActual > 0) $('#prevImg').click();
        if (e.key === "ArrowRight" && imagenActual < imagenesArray.length - 1) $('#nextImg').click();
    }
});

function abrirModalMedir(imgUrl) {
    const esAntigua = $('#imagenesPreview img').eq(imagenActual).parent().data('antigua') === true;

    if (esAntigua) {
        fetch(imgUrl)
            .then(res => res.blob())
            .then(blob => {
                const filename = imgUrl.split('/').pop();
                const file = new File([blob], filename, { type: blob.type });
                enviarImagenTemporal(file);
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo cargar la imagen antigua.', 'error');
            });
    } else {
        const fileIdx = $('#imagenesPreview img').eq(imagenActual).parent().data('file-idx');
        const file = archivosSeleccionados[fileIdx];

        if (!file) {
            Swal.fire('Error', 'No se pudo encontrar la imagen seleccionada.', 'error');
            return;
        }

        enviarImagenTemporal(file);
    }

    function enviarImagenTemporal(file) {
        const formData = new FormData();
        formData.append('imagen', file);

        $.ajax({
            url: 'certificado/tipo_examen/subir_temp_imagen.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (typeof res === 'string') {
                    try {
                        res = JSON.parse(res);
                    } catch (e) {
                        console.error("Error al parsear JSON:", e);
                        Swal.fire('Error1', 'Respuesta inválida del servidor', 'error');
                        return;
                    }
                }

                if (res.status === 'success') {
                    let urlTemporal = res.url;
                    nombreTempImagen = urlTemporal.replace('/uploads/tmp/', '');
                    llamarCalibrar(urlTemporal);
                } else {
                    Swal.fire('Error1', res.message || 'No se recibió la imagen', 'error');
                }
            },
            error: function () {
                Swal.fire('Error2', 'Error al subir imagen temporal', 'error');
            }
        });
    }
}

function llamarCalibrar(imgUrl) {
    const canvas = document.getElementById('canvasMedicion');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    let mediciones = [];
    let pxPorCm = 0;

    img.onload = function () {
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        $.ajax({
            url: 'certificado/tipo_examen/calibrar_imagen.php',
            method: 'POST',
            data: { imagen: imgUrl },
            dataType: 'json',
            success: function (res) {
                if (typeof res === 'string') {
                    try {
                        res = JSON.parse(res);
                    } catch (e) {
                        console.error('No es JSON (calibrar):', res);
                        Swal.fire('Error', 'Respuesta inválida del servidor (calibrar).', 'error');
                        return;
                    }
                }

                if (res.status === 'success') {
                    pxPorCm = res.pxPorCm;
                    inicializarMedicion(canvas, ctx, img, mediciones, () => pxPorCm);
                    $('#medirModal').modal('show');
                    Swal.close();
                } else {
                    Swal.fire('Error3', res.message, 'error');
                    Swal.close();
                }
            },
            error: function () {
                Swal.fire('Error4', 'Error al calibrar imagen.', 'error');
                Swal.close();
            }
        });
    };
    img.src = imgUrl;
}

function inicializarMedicion(canvas, ctx, img, mediciones, getPxPorCm) {
    let drawing = false;
    let start = { x: 0, y: 0 };
    let end = { x: 0, y: 0 };
    let botonLimpiarRect = null;

    canvas.addEventListener('mousedown', function (e) {
        drawing = true;
        start = getMousePos(canvas, e);
        end = { ...start };
        redraw();
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!drawing) return;
        end = getMousePos(canvas, e);
        redraw();
    });

    canvas.addEventListener('mouseup', function (e) {
        if (!drawing) return;
        drawing = false;
        end = getMousePos(canvas, e);

        if (start.x !== end.x || start.y !== end.y) {
            mediciones.push({
                start: { ...start },
                end: { ...end },
                distanciaCm: calcularDistanciaCm(start, end, getPxPorCm())
            });
        }
        redraw();
    });

    canvas.addEventListener('click', function (e) {
        if (!botonLimpiarRect) return;
        const pos = getMousePos(canvas, e);
        if (
            pos.x >= botonLimpiarRect.x && pos.x <= botonLimpiarRect.x + botonLimpiarRect.w &&
            pos.y >= botonLimpiarRect.y && pos.y <= botonLimpiarRect.y + botonLimpiarRect.h
        ) {
            limpiarMediciones();
        }
    });

    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        mediciones.forEach((m, idx) => {
            dibujarLinea(ctx, m.start, m.end, idx + 1, m.distanciaCm);
        });

        if (drawing && (start.x !== end.x || start.y !== end.y)) {
            dibujarLinea(ctx, start, end, mediciones.length + 1, calcularDistanciaCm(start, end, getPxPorCm()));
        }

        drawMedicionesTable();
    }

    function limpiarMediciones() {
        mediciones.length = 0;
        redraw();
    }

    function drawMedicionesTable() {
        const padding = 10;
        const rowHeight = 26;
        const totalRows = mediciones.length + 1;
        const col1w = 38;
        const col2w = 110;
        const tablaWidth = col1w + col2w + padding * 2;
        const tablaHeight = rowHeight * totalRows + padding * 2;
        const btnH = 32;
        const margin = 18;

        const tablaY = canvas.height - tablaHeight - btnH - margin;
        const x = canvas.width - tablaWidth - margin;
        const y = tablaY;

        ctx.save();
        ctx.globalAlpha = 0.85;
        ctx.fillStyle = "#fff";
        ctx.fillRect(x, y, tablaWidth, tablaHeight);
        ctx.globalAlpha = 1;

        ctx.font = "bold 16px Arial";
        ctx.fillStyle = "#222";
        ctx.fillText("#", x + padding, y + padding + 16);
        ctx.fillText("Distancia (cm)", x + padding + col1w, y + padding + 16);

        ctx.font = "15px Arial";
        mediciones.forEach((m, idx) => {
            ctx.fillText((idx + 1), x + padding, y + padding + 16 + rowHeight * (idx + 1));
            ctx.fillText(m.distanciaCm.toFixed(2), x + padding + col1w, y + padding + 16 + rowHeight * (idx + 1));
        });

        const btnW = tablaWidth - 2 * padding;
        const btnX = x + padding;
        const btnY = y + tablaHeight + 6;

        if (!modoSoloGuardar) {
            ctx.fillStyle = "#ea5050";
            ctx.strokeStyle = "#fff";
            ctx.lineWidth = 2;
            ctx.globalAlpha = 0.95;
            ctx.fillRect(btnX, btnY, btnW, btnH);
            ctx.globalAlpha = 1;
            ctx.strokeRect(btnX, btnY, btnW, btnH);

            ctx.font = "bold 17px Arial";
            ctx.fillStyle = "#fff";
            ctx.textAlign = "center";
            ctx.fillText("Limpiar", btnX + btnW / 2, btnY + btnH / 2 + 7);

            ctx.textAlign = "start";
            botonLimpiarRect = { x: btnX, y: btnY, w: btnW, h: btnH };
        }
    }

    $('#btnGuardarMediciones').off('click').on('click', function () {
        const estabaActivo = modoSoloGuardar;
        modoSoloGuardar = true;
        redraw();

        const nuevaImagen = canvas.toDataURL('image/png');
        modoSoloGuardar = estabaActivo;
        redraw();

        imagenesArray[imagenActual] = nuevaImagen;
        $('#imagenesPreview img').eq(imagenActual).attr('src', nuevaImagen);
        $('#imagenModalSrc').attr('src', nuevaImagen);

        const $imgContainer = $('#imagenesPreview img').eq(imagenActual).parent();
        const esAntigua = $imgContainer.data('antigua') === true;
        const idxAntigua = $imgContainer.data('idx');
        const fileIdx = $imgContainer.data('file-idx');

        let nombreOriginal = 'imagen_editada_' + Date.now() + '.png';

        if (esAntigua) {
            if (typeof idxAntigua !== 'undefined') {
                imagenesAntiguas.splice(idxAntigua, 1);
                $('#imagenes_antiguas').val(JSON.stringify(imagenesAntiguas));
            }

            const nuevoFile = dataURLToFile(nuevaImagen, nombreOriginal);
            archivosSeleccionados.push(nuevoFile);
            updateInputFiles();
        } else {
            if (typeof fileIdx !== 'undefined' && archivosSeleccionados[fileIdx]) {
                nombreOriginal = archivosSeleccionados[fileIdx].name;
                archivosSeleccionados[fileIdx] = dataURLToFile(nuevaImagen, nombreOriginal);
                updateInputFiles();
            }
        }

        $('#medirModal').modal('hide');
    });

    $('#btnDescargarImagen').off('click').on('click', function () {
        modoSoloGuardar = true;
        redraw();

        setTimeout(() => {
            const link = document.createElement('a');
            link.download = 'imagen_medida.png';
            link.href = canvas.toDataURL('image/png');
            link.click();

            modoSoloGuardar = false;
            redraw();
        }, 50);
    });
}

function dibujarLinea(ctx, p1, p2, numero, distCm) {
    ctx.strokeStyle = "#FFD600";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(p1.x, p1.y);
    ctx.lineTo(p2.x, p2.y);
    ctx.stroke();

    dibujarCruz(ctx, p1.x, p1.y);
    dibujarCruz(ctx, p2.x, p2.y);

    ctx.font = "bold 22px Arial";
    ctx.fillStyle = "#FFD600";
    ctx.fillText(numero, (p1.x + p2.x) / 2 + 8, (p1.y + p2.y) / 2 - 8);
}

function dibujarCruz(ctx, x, y, color = "#FFD600", size = 16, lineW = 4) {
    ctx.save();
    ctx.strokeStyle = color;
    ctx.lineWidth = lineW;
    ctx.beginPath();
    ctx.moveTo(x - size / 2, y);
    ctx.lineTo(x + size / 2, y);
    ctx.moveTo(x, y - size / 2);
    ctx.lineTo(x, y + size / 2);
    ctx.stroke();
    ctx.restore();
}

function getMousePos(canvas, evt) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (evt.clientX - rect.left) * scaleX,
        y: (evt.clientY - rect.top) * scaleY
    };
}

function calcularDistanciaCm(a, b, pxPorCm) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const distancia = Math.sqrt(dx * dx + dy * dy);
    return pxPorCm ? (distancia / pxPorCm) : 0;
}

$('#btnEditarMedirImg').on('click', function () {
    Swal.fire({
        title: 'Cargando imagen...',
        html: 'Por favor espera mientras se prepara la medición.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    if (!imagenesArray.length) {
        Swal.fire('Error', 'No hay imágenes cargadas para medir.', 'error');
        return;
    }

    const imgUrl = imagenesArray[imagenActual];
    abrirModalMedir(imgUrl);
});

$('#medirModal').on('hidden.bs.modal', function () {
    if (nombreTempImagen) {
        $.ajax({
            url: 'certificado/tipo_examen/eliminar_temp_imagen.php',
            type: 'POST',
            data: { imagen: nombreTempImagen },
            success: function () {
                nombreTempImagen = null;
            },
            error: function () {
                console.error('Error al eliminar imagen temporal');
            }
        });
    }
});

if (imagenesAntiguas.length > 0) {
    renderImagenesAntiguas();
}

$('#imagenes_antiguas').val(JSON.stringify(imagenesAntiguas));

function dataURLToFile(dataurl, filename) {
    let arr = dataurl.split(',');
    let mime = arr[0].match(/:(.*?);/)[1];
    let bstr = atob(arr[1]);
    let n = bstr.length;
    let u8arr = new Uint8Array(n);

    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }

    return new File([u8arr], filename, { type: mime });
}

$(function () {
    <?php if ($action === 'modificar' && isset($fila['tipo_estudio'])): ?>
        $('#plantilla_informe_id').trigger('change');
    <?php endif; ?>
});
</script>

<script>
function cargarCamposVisiblesPorConfiguracion(configuracionInformeId) {
    if (!configuracionInformeId) {
        if (typeof aplicarCamposVisiblesFormulario === 'function') {
            aplicarCamposVisiblesFormulario([]);
        }
        return;
    }

    $.ajax({
        url: 'certificado/configuracion/get_campos_visibles.php',
        type: 'POST',
        dataType: 'json',
        data: { configuracion_informe_id: configuracionInformeId },
        success: function (res) {
            if (res && res.status === 'success') {
                if (typeof aplicarCamposVisiblesFormulario === 'function') {
                    aplicarCamposVisiblesFormulario(res.campos || []);
                }
            } else {
                Swal.fire('Error', (res && res.message) ? res.message : 'No se pudieron cargar los campos de la plantilla.', 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudieron cargar los campos de la plantilla.', 'error');
        }
    });
}

$(function () {
    $('#configuracion_informe_id').off('change.certCamposVisibles').on('change.certCamposVisibles', function () {
        const configuracionInformeId = $(this).val() || '';
        cargarCamposVisiblesPorConfiguracion(configuracionInformeId);
    });
});
</script>