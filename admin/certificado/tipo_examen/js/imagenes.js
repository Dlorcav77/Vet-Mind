// admin/certificado/tipo_examen/js/imagenes.js
var imagenesArray = [];
var imagenActual = 0;
var archivosSeleccionados = [];
var LIMITE_IMAGENES = 40;
var modoSoloGuardar = false;
var nombreTempImagen = null;
var imagenesAntiguasCargadas = [];

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
                        Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                        return;
                    }
                }

                if (res.status === 'success') {
                    let urlTemporal = res.url;
                    nombreTempImagen = urlTemporal;
                    llamarCalibrar(urlTemporal);
                } else {
                    Swal.fire('Error', res.message || 'No se recibió la imagen', 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Error al subir imagen temporal', 'error');
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
                    Swal.fire('Error', res.message, 'error');
                    Swal.close();
                }
            },
            error: function () {
                Swal.fire('Error', 'Error al calibrar imagen.', 'error');
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

    canvas.onmousedown = function (e) {
        drawing = true;
        start = getMousePos(canvas, e);
        end = { ...start };
        redraw();
    };

    canvas.onmousemove = function (e) {
        if (!drawing) return;
        end = getMousePos(canvas, e);
        redraw();
    };

    canvas.onmouseup = function (e) {
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
    };

    canvas.onclick = function (e) {
        if (!botonLimpiarRect) return;
        const pos = getMousePos(canvas, e);
        if (
            pos.x >= botonLimpiarRect.x && pos.x <= botonLimpiarRect.x + botonLimpiarRect.w &&
            pos.y >= botonLimpiarRect.y && pos.y <= botonLimpiarRect.y + botonLimpiarRect.h
        ) {
            limpiarMediciones();
        }
    };

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

        const nuevaImagen = canvas.toDataURL('image/jpeg', 0.85);
        modoSoloGuardar = estabaActivo;
        redraw();

        imagenesArray[imagenActual] = nuevaImagen;
        $('#imagenesPreview img').eq(imagenActual).attr('src', nuevaImagen);
        $('#imagenModalSrc').attr('src', nuevaImagen);

        const $imgContainer = $('#imagenesPreview img').eq(imagenActual).parent();
        const esAntigua = $imgContainer.data('antigua') === true;
        const idxAntigua = $imgContainer.data('idx');
        const fileIdx = $imgContainer.data('file-idx');

        let nombreOriginal = 'imagen_editada_' + Date.now() + '.jpg';

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
                nombreOriginal = archivosSeleccionados[fileIdx].name.replace(/\.[^.]+$/, '') + '.jpg';
                archivosSeleccionados[fileIdx] = dataURLToFile(nuevaImagen, nombreOriginal);
                updateInputFiles();
            }
        }

        $('#medirModal').modal('hide');
        renderPreview();
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

    if (document.activeElement) {
        document.activeElement.blur();
    }
});

$('#imagenModal').on('hidden.bs.modal', function () {
    if (document.activeElement) {
        document.activeElement.blur();
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