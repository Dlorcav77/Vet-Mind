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
    const mediciones = [];
    let pxPorCm = 0;

    function abrirMedicionManual() {
        Swal.close();

        inicializarCalibracionManual(canvas, ctx, img, function (nuevoPxPorCm, cmReferencia) {
            pxPorCm = nuevoPxPorCm;
            mediciones.length = 0;
            $('#estadoCalibracion').text('Manual · ' + cmReferencia + ' cm').show();
            inicializarMedicion(canvas, ctx, img, mediciones, () => pxPorCm);
        });

        $('#medirModal').modal('show');
    }

    img.onload = function () {
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        inicializarLupaPrecision(canvas, img);
        inicializarVisualizacionTemporal(canvas);
        inicializarZoomPan(canvas);

        $('#estadoCalibracion').hide().text('');
        $('#calibracionManualPanel').hide();
        $('#calibracionManualCm').val('');
        $('#btnAplicarCalibracionManual').prop('disabled', true);
        $('#btnGuardarMediciones, #btnDescargarImagen').prop('disabled', false);

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
                        abrirMedicionManual();
                        return;
                    }
                }

                if (res.status !== 'success') {
                    abrirMedicionManual();
                    return;
                }

                pxPorCm = parseFloat(res.pxPorCm);
                if (!Number.isFinite(pxPorCm) || pxPorCm <= 0) {
                    abrirMedicionManual();
                    return;
                }

                Swal.close();
                $('#estadoCalibracion').text('Auto · ' + pxPorCm.toFixed(1) + ' px/cm').show();
                inicializarMedicion(canvas, ctx, img, mediciones, () => pxPorCm);
                $('#medirModal').modal('show');
            },
            error: abrirMedicionManual
        });
    };

    img.onerror = function () {
        Swal.fire('Error', 'No se pudo cargar la imagen para medir.', 'error');
    };

    img.src = imgUrl;
}

function inicializarLupaPrecision(canvas, img) {
    const lupa = document.getElementById('canvasLupaMedicion');
    const ctxLupa = lupa.getContext('2d');
    const tamano = lupa.width;
    const zoom = 2;
    const margen = 16;
    const tamanoVisual = 120;
    const $canvas = $(canvas);

    $canvas.off('.lupaPrecision');

    $canvas.on('mouseenter.lupaPrecision', function () {
        lupa.style.display = 'block';
    });

    $canvas.on('mouseleave.lupaPrecision', function () {
        lupa.style.display = 'none';
    });

    $canvas.on('mousemove.lupaPrecision', function (e) {
        const pos = getMousePos(canvas, e);

        ctxLupa.clearRect(0, 0, tamano, tamano);
        ctxLupa.save();
        ctxLupa.imageSmoothingEnabled = false;
        ctxLupa.translate(tamano / 2 - pos.x * zoom, tamano / 2 - pos.y * zoom);
        ctxLupa.scale(zoom, zoom);
        ctxLupa.drawImage(img, 0, 0);
        ctxLupa.restore();

        ctxLupa.save();
        ctxLupa.strokeStyle = '#FFD600';
        ctxLupa.lineWidth = 1;
        ctxLupa.beginPath();
        ctxLupa.moveTo(tamano / 2 - 12, tamano / 2);
        ctxLupa.lineTo(tamano / 2 + 12, tamano / 2);
        ctxLupa.moveTo(tamano / 2, tamano / 2 - 12);
        ctxLupa.lineTo(tamano / 2, tamano / 2 + 12);
        ctxLupa.stroke();
        ctxLupa.restore();

        const contenedor = lupa.parentElement.getBoundingClientRect();
        let left = e.clientX - contenedor.left + margen;
        let top = e.clientY - contenedor.top - tamanoVisual - margen;

        if (left + tamanoVisual > contenedor.width) left = e.clientX - contenedor.left - tamanoVisual - margen;
        if (top < 0) top = e.clientY - contenedor.top + margen;

        lupa.style.left = Math.max(0, left) + 'px';
        lupa.style.top = Math.max(0, top) + 'px';
    });
}

function inicializarVisualizacionTemporal(canvas) {
    const $panel = $('#controlesVisualizacion');
    const $brillo = $('#visualBrillo');
    const $contraste = $('#visualContraste');
    const $gamma = $('#visualGamma');
    const $nitidez = $('#visualNitidez');
    const $invertir = $('#btnInvertirVisualizacion');
    let invertido = false;

    function aplicar() {
        const brillo = parseInt($brillo.val(), 10) || 100;
        const contraste = parseInt($contraste.val(), 10) || 100;
        const gamma = parseFloat($gamma.val()) || 1;
        const nitidez = (parseInt($nitidez.val(), 10) || 0) / 100;

        $('#filtroGammaMedicion feFuncR, #filtroGammaMedicion feFuncG, #filtroGammaMedicion feFuncB').attr('exponent', gamma);

        const centro = 1 + nitidez * 4;
        const lateral = -nitidez;
        $('#filtroNitidezMedicion').attr('kernelMatrix', `0 ${lateral} 0 ${lateral} ${centro} ${lateral} 0 ${lateral} 0`);

        canvas.style.filter = `url(#filtroGammaMedicion) brightness(${brillo}%) contrast(${contraste}%) invert(${invertido ? 1 : 0})`;

        $('#visualBrilloValor').text(brillo + '%');
        $('#visualContrasteValor').text(contraste + '%');
        $('#visualGammaValor').text(gamma.toFixed(2));
        $('#visualNitidezValor').text(Math.round(nitidez * 100) + '%');
        $invertir.toggleClass('active', invertido);
    }

    $('#btnToggleVisualizacion').off('click.visualizacion').on('click.visualizacion', function () {
        $panel.toggle();
    });

    $brillo.add($contraste).add($gamma).add($nitidez).off('input.visualizacion').on('input.visualizacion', aplicar);

    $invertir.off('click.visualizacion').on('click.visualizacion', function () {
        invertido = !invertido;
        aplicar();
    });

    $('#btnResetVisualizacion').off('click.visualizacion').on('click.visualizacion', function () {
        $brillo.val(100);
        $contraste.val(100);
        $gamma.val(1);
        $nitidez.val(0);
        invertido = false;
        aplicar();
    });

    $brillo.val(100);
    $contraste.val(100);
    $gamma.val(1);
    $nitidez.val(0);
    invertido = false;
    aplicar();
}

function inicializarZoomPan(canvas) {
    const $canvas = $(canvas);
    const $zoom = $('#visualZoom');
    const $btnMover = $('#btnMoverImagen');
    let zoom = 1;
    let moverActivo = false;
    let arrastrando = false;
    let inicioX = 0;
    let inicioY = 0;
    let offsetX = 0;
    let offsetY = 0;

    function aplicarTransformacion() {
        canvas.style.transformOrigin = 'top left';
        canvas.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${zoom})`;
        $('#visualZoomValor').text(Math.round(zoom * 100) + '%');
    }

    function limitarPosicion() {
        if (zoom <= 1) {
            offsetX = 0;
            offsetY = 0;
            return;
        }

        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        const minX = -(w * zoom - w);
        const minY = -(h * zoom - h);

        offsetX = Math.min(0, Math.max(minX, offsetX));
        offsetY = Math.min(0, Math.max(minY, offsetY));
    }

    function resetVista() {
        zoom = 1;
        offsetX = 0;
        offsetY = 0;
        $zoom.val(100);
        aplicarTransformacion();
    }

    $canvas.off('.zoomPan');

    $zoom.off('input.zoomPan').on('input.zoomPan', function () {
        zoom = parseInt($(this).val(), 10) / 100;
        limitarPosicion();
        aplicarTransformacion();
    });

    $btnMover.off('click.zoomPan').on('click.zoomPan', function () {
        moverActivo = !moverActivo;
        canvas.dataset.modoMover = moverActivo ? '1' : '0';
        $canvas.toggleClass('modo-mover', moverActivo);
        $btnMover.toggleClass('active', moverActivo);
    });

    $('#btnResetVista').off('click.zoomPan').on('click.zoomPan', resetVista);

    $canvas.on('mousedown.zoomPan', function (e) {
        if (!moverActivo || zoom <= 1) return;

        arrastrando = true;
        inicioX = e.clientX - offsetX;
        inicioY = e.clientY - offsetY;
        e.preventDefault();
    });

    $(document).off('mousemove.zoomPan').on('mousemove.zoomPan', function (e) {
        if (!arrastrando) return;

        offsetX = e.clientX - inicioX;
        offsetY = e.clientY - inicioY;
        limitarPosicion();
        aplicarTransformacion();
    });

    $(document).off('mouseup.zoomPan').on('mouseup.zoomPan', function () {
        arrastrando = false;
    });

    canvas.dataset.modoMover = '0';
    moverActivo = false;
    $canvas.removeClass('modo-mover');
    $btnMover.removeClass('active');
    resetVista();
}

function inicializarCalibracionManual(canvas, ctx, img, onAplicar) {
    let puntos = [];
    const $panel = $('#calibracionManualPanel');
    const $input = $('#calibracionManualCm');
    const $btn = $('#btnAplicarCalibracionManual');

    $panel.show();
    $input.val('');
    $btn.prop('disabled', true);
    $('#btnGuardarMediciones, #btnDescargarImagen').prop('disabled', true);

    canvas.onmousemove = null;
    canvas.onmouseup = null;
    canvas.onclick = null;

    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        puntos.forEach(p => dibujarCruz(ctx, p.x, p.y));

        if (puntos.length === 2) {
            ctx.save();
            ctx.strokeStyle = '#FFD24A';
            ctx.lineWidth = 1.5;
            ctx.setLineDash([8, 6]);
            ctx.beginPath();
            ctx.moveTo(puntos[0].x, puntos[0].y);
            ctx.lineTo(puntos[1].x, puntos[1].y);
            ctx.stroke();
            ctx.restore();
        }
    }

    canvas.onmousedown = function (e) {
        if (canvas.dataset.modoMover === '1') return;
        if (puntos.length === 2) puntos = [];
        puntos.push(getMousePos(canvas, e));
        $btn.prop('disabled', puntos.length !== 2);
        redraw();
    };

    $btn.off('click.calibracionManual').on('click.calibracionManual', function () {
        const cm = parseFloat($input.val());

        if (!Number.isFinite(cm) || cm <= 0 || puntos.length !== 2) {
            Swal.fire('Calibración', 'Indica una distancia válida en centímetros.', 'warning');
            return;
        }

        const dx = puntos[1].x - puntos[0].x;
        const dy = puntos[1].y - puntos[0].y;
        const distanciaPx = Math.sqrt(dx * dx + dy * dy);

        if (distanciaPx <= 0) return;

        const pxPorCm = distanciaPx / cm;

        canvas.onmousedown = null;
        $panel.hide();
        $('#btnGuardarMediciones, #btnDescargarImagen').prop('disabled', false);

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        onAplicar(pxPorCm, cm);
    });

    redraw();
}

function inicializarMedicion(canvas, ctx, img, mediciones, getPxPorCm) {
    let drawing = false;
    let start = { x: 0, y: 0 };
    let end = { x: 0, y: 0 };
    let botonLimpiarRect = null;
    let clavePosicionTabla = '';
    let posicionTablaY = null;

    const canvasAnalisis = document.createElement('canvas');
    canvasAnalisis.width = img.width;
    canvasAnalisis.height = img.height;
    const ctxAnalisis = canvasAnalisis.getContext('2d');
    ctxAnalisis.drawImage(img, 0, 0, canvasAnalisis.width, canvasAnalisis.height);

    canvas.onmousedown = function (e) {
        if (canvas.dataset.modoMover === '1') return;

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

        clavePosicionTabla = '';
        redraw();
    };

    canvas.onclick = function (e) {
        if (!botonLimpiarRect) return;

        const pos = getMousePos(canvas, e);

        if (
            pos.x >= botonLimpiarRect.x &&
            pos.x <= botonLimpiarRect.x + botonLimpiarRect.w &&
            pos.y >= botonLimpiarRect.y &&
            pos.y <= botonLimpiarRect.y + botonLimpiarRect.h
        ) {
            limpiarMediciones();
        }
    };

    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        mediciones.forEach((m, idx) => {
            dibujarLinea(ctx, m.start, m.end, idx + 1);
        });

        if (drawing && (start.x !== end.x || start.y !== end.y)) {
            dibujarLinea(ctx, start, end, mediciones.length + 1);
        }

        drawMedicionesTable();
    }

    function limpiarMediciones() {
        mediciones.length = 0;
        clavePosicionTabla = '';
        redraw();
    }

    function puntuarZona(x, y, w, h) {
        const extra = 24;

        x = Math.max(0, Math.floor(x - extra));
        y = Math.max(0, Math.floor(y - extra));
        w = Math.min(Math.floor(w + extra * 2), canvasAnalisis.width - x);
        h = Math.min(Math.floor(h + extra * 2), canvasAnalisis.height - y);

        if (w <= 0 || h <= 0) return Infinity;

        try {
            const data = ctxAnalisis.getImageData(x, y, w, h).data;
            let score = 0;
            let muestras = 0;

            for (let yy = 0; yy < h; yy += 4) {
                let brilloAnterior = null;

                for (let xx = 0; xx < w; xx += 4) {
                    const i = (yy * w + xx) * 4;
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    const brillo = (r + g + b) / 3;
                    const max = Math.max(r, g, b);
                    const min = Math.min(r, g, b);

                    if (brillo > 210) score += 6;
                    else if (brillo > 130) score += 2.5;
                    else if (brillo > 55) score += 1;

                    if (max - min > 60 && max > 120) score += 3;

                    if (brilloAnterior !== null && Math.abs(brillo - brilloAnterior) > 55) {
                        score += 1.5;
                    }

                    brilloAnterior = brillo;
                    muestras++;
                }
            }

            return muestras ? score / muestras : 0;
        } catch (e) {
            return Infinity;
        }
    }

    function obtenerPosicionTabla(tablaWidth, bloqueHeight, totalRows) {
        const margin = 18;
        const yArriba = 56;
        const yAbajo = canvas.height - bloqueHeight - margin;
        const x = canvas.width - tablaWidth - margin;
        const clave = `${totalRows}-${modoSoloGuardar ? 1 : 0}`;

        if (clave === clavePosicionTabla && posicionTablaY !== null) {
            return posicionTablaY;
        }

        const scoreArriba = puntuarZona(x, yArriba, tablaWidth, bloqueHeight);
        const scoreAbajo = puntuarZona(x, yAbajo, tablaWidth, bloqueHeight);

        posicionTablaY = scoreAbajo < scoreArriba * 0.82 ? yAbajo : yArriba;
        clavePosicionTabla = clave;

        return posicionTablaY;
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
        const espacioBoton = modoSoloGuardar ? 0 : btnH + 6;
        const bloqueHeight = tablaHeight + espacioBoton;
        const x = canvas.width - tablaWidth - margin;
        const y = obtenerPosicionTabla(tablaWidth, bloqueHeight, totalRows);

        ctx.save();
        ctx.globalAlpha = 0.85;
        ctx.fillStyle = '#fff';
        ctx.fillRect(x, y, tablaWidth, tablaHeight);
        ctx.globalAlpha = 1;

        ctx.font = 'bold 16px Arial';
        ctx.fillStyle = '#222';
        ctx.fillText('#', x + padding, y + padding + 16);
        ctx.fillText('Distancia (cm)', x + padding + col1w, y + padding + 16);

        ctx.font = '15px Arial';

        mediciones.forEach((m, idx) => {
            ctx.fillText(idx + 1, x + padding, y + padding + 16 + rowHeight * (idx + 1));
            ctx.fillText(m.distanciaCm.toFixed(2), x + padding + col1w, y + padding + 16 + rowHeight * (idx + 1));
        });

        const btnW = tablaWidth - padding * 2;
        const btnX = x + padding;
        const btnY = y + tablaHeight + 6;

        if (!modoSoloGuardar) {
            ctx.fillStyle = '#ea5050';
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.globalAlpha = 0.95;
            ctx.fillRect(btnX, btnY, btnW, btnH);
            ctx.globalAlpha = 1;
            ctx.strokeRect(btnX, btnY, btnW, btnH);

            ctx.font = 'bold 17px Arial';
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.fillText('Limpiar', btnX + btnW / 2, btnY + btnH / 2 + 7);

            ctx.textAlign = 'start';
            botonLimpiarRect = { x: btnX, y: btnY, w: btnW, h: btnH };
        } else {
            botonLimpiarRect = null;
        }

        ctx.restore();
    }

    $('#btnGuardarMediciones').off('click').on('click', function () {
        const estabaActivo = modoSoloGuardar;
        modoSoloGuardar = true;
        clavePosicionTabla = '';
        redraw();

        const nuevaImagen = canvas.toDataURL('image/jpeg', 0.85);

        modoSoloGuardar = estabaActivo;
        clavePosicionTabla = '';
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

            archivosSeleccionados.push(dataURLToFile(nuevaImagen, nombreOriginal));
            updateInputFiles();
        } else if (typeof fileIdx !== 'undefined' && archivosSeleccionados[fileIdx]) {
            nombreOriginal = archivosSeleccionados[fileIdx].name.replace(/\.[^.]+$/, '') + '.jpg';
            archivosSeleccionados[fileIdx] = dataURLToFile(nuevaImagen, nombreOriginal);
            updateInputFiles();
        }

        $('#medirModal').modal('hide');
        renderPreview();
    });

    $('#btnDescargarImagen').off('click').on('click', function () {
        modoSoloGuardar = true;
        clavePosicionTabla = '';
        redraw();

        setTimeout(() => {
            const link = document.createElement('a');
            link.download = 'imagen_medida.png';
            link.href = canvas.toDataURL('image/png');
            link.click();

            modoSoloGuardar = false;
            clavePosicionTabla = '';
            redraw();
        }, 50);
    });
}

function dibujarLinea(ctx, p1, p2, numero) {
    ctx.save();
    ctx.strokeStyle = "#FFD24A";
    ctx.lineWidth = 1.5;
    ctx.setLineDash([8, 6]);
    ctx.beginPath();
    ctx.moveTo(p1.x, p1.y);
    ctx.lineTo(p2.x, p2.y);
    ctx.stroke();
    ctx.restore();

    dibujarCruz(ctx, p1.x, p1.y);
    dibujarCruz(ctx, p2.x, p2.y);

    ctx.save();
    ctx.font = "bold 14px Arial";
    ctx.fillStyle = "#FFD24A";
    ctx.fillText(numero, (p1.x + p2.x) / 2 + 5, (p1.y + p2.y) / 2 - 5);
    ctx.restore();
}

function dibujarCruz(ctx, x, y, color = "#FFD24A", size = 12, lineW = 2.5) {
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
    const canvas = document.getElementById('canvasMedicion');

    if (nombreTempImagen) {
        const imagenTemporal = nombreTempImagen;
        nombreTempImagen = null;

        $.ajax({
            url: 'certificado/tipo_examen/eliminar_temp_imagen.php',
            type: 'POST',
            data: { imagen: imagenTemporal },
            error: function () {
                console.error('Error al eliminar imagen temporal');
            }
        });
    }

    if (canvas) {
        canvas.onmousedown = null;
        canvas.onmousemove = null;
        canvas.onmouseup = null;
        canvas.onclick = null;
        canvas.style.filter = '';
        canvas.style.transform = '';
        canvas.style.transformOrigin = '';
        canvas.dataset.modoMover = '0';

        $(canvas).off('.lupaPrecision').off('.zoomPan').removeClass('modo-mover');
    }

    $(document).off('mousemove.zoomPan mouseup.zoomPan');

    $('#canvasLupaMedicion').hide();
    $('#controlesVisualizacion').hide();
    $('#calibracionManualPanel').hide();
    $('#estadoCalibracion').hide().text('');
    $('#btnMoverImagen').removeClass('active');
    $('#btnGuardarMediciones, #btnDescargarImagen').prop('disabled', false);

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