//admin/certificado/tipo_examen/js/tipo_examen.js

window.__vmPlantillaContenidoAplicada = window.__vmPlantillaContenidoAplicada || {
    id: '',
    html: ''
};

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
            // console.log('get_campos_visibles response:', res);

            if (res && res.status === 'success') {
                if (typeof aplicarCamposVisiblesFormulario === 'function') {
                    aplicarCamposVisiblesFormulario(res.campos || []);
                }

                setTimeout(vmAjustarLayoutCamposGenerales, 0);
            } else {
                Swal.fire(
                    'Error',
                    (res && res.message) ? res.message : 'No se pudieron cargar los campos de la plantilla.',
                    'error'
                );
            }
        },
        error: function (xhr, status, error) {
            console.error('get_campos_visibles error:', {
                status: status,
                error: error,
                http_status: xhr.status,
                responseText: xhr.responseText
            });

            let msg = 'No se pudieron cargar los campos de la plantilla.';
            if (xhr && xhr.responseText) {
                msg += '<br><small style="word-break:break-word;">' + $('<div>').text(xhr.responseText).html() + '</small>';
            }

            Swal.fire('Error', msg, 'error');
        }
    });
}

function vmAjustarLayoutCamposGenerales() {
    const $fila = $('#fila_campos_generales');

    if (!$fila.length) {
        return;
    }

    const $campos = $fila.find('[data-campo-general]');
    let visibles = 0;

    $campos.each(function () {
        const $campo = $(this);
        const visible = $campo.is(':visible') && $campo.css('display') !== 'none';

        $campo.removeClass('vm-visible');

        if (visible) {
            visibles++;
            $campo.addClass('vm-visible');
        }
    });

    $fila.removeClass('vm-campos-0 vm-campos-1 vm-campos-2 vm-campos-3 vm-campos-4 vm-campos-5 vm-campos-mas');

    if (visibles <= 0) {
        $fila.addClass('vm-campos-0');
        return;
    }

    if (visibles >= 6) {
        $fila.addClass('vm-campos-mas');
        return;
    }

    $fila.addClass('vm-campos-' + visibles);
}

window.vmAjustarLayoutCamposGenerales = vmAjustarLayoutCamposGenerales;

function vmNormalizarHtmlParaComparar(html) {
    const raw = String(html || '').trim();

    if (!raw) {
        return '';
    }

    return raw
        .replace(/\s+/g, ' ')
        .replace(/>\s+</g, '><')
        .replace(/<p><\/p>/gi, '')
        .replace(/<p>\s*<\/p>/gi, '')
        .trim();
}

function vmTextoPlanoDesdeHtml(html) {
    const raw = String(html || '').trim();

    if (!raw) {
        return '';
    }

    return $('<div>').html(raw).text()
        .replace(/\u00a0/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function vmHtmlVacio(html) {
    const normalizado = vmNormalizarHtmlParaComparar(html);
    const texto = vmTextoPlanoDesdeHtml(html);

    if (!normalizado) {
        return true;
    }

    if (!texto && (
        normalizado === '<p></p>' ||
        normalizado === '<p><br></p>' ||
        normalizado === '<p class="is-editor-empty"></p>'
    )) {
        return true;
    }

    return !texto && normalizado.indexOf('<img') === -1 && normalizado.indexOf('<table') === -1;
}

function vmGetContenidoActualEditor() {
    if (
        window.VetmindTiptap &&
        typeof window.VetmindTiptap.getMainEditor === 'function'
    ) {
        const editor = window.VetmindTiptap.getMainEditor();

        if (editor && typeof editor.getHTML === 'function') {
            return editor.getHTML() || '';
        }
    }

    return $('#contenido_html').val() || $('textarea[name="contenido_html"]').val() || '';
}

function vmSetContenidoEditor(html) {
    const contenido = String(html || '').trim();

    if (
        window.VetmindTiptap &&
        typeof window.VetmindTiptap.setMainEditorHTML === 'function'
    ) {
        window.VetmindTiptap.setMainEditorHTML(contenido || '<p></p>');

        if (typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
            window.VetmindTiptap.syncMainEditorToTextarea();
        }

        return;
    }

    $('#contenido_html').val(contenido);

    const $textarea = $('textarea[name="contenido_html"]');
    if ($textarea.length) {
        $textarea.val(contenido);
    }
}

function vmContenidoActualEsPlantillaAplicada() {
    const actual = vmNormalizarHtmlParaComparar(vmGetContenidoActualEditor());
    const aplicada = vmNormalizarHtmlParaComparar(window.__vmPlantillaContenidoAplicada.html || '');

    return !!actual && !!aplicada && actual === aplicada;
}

function vmAplicarPlantillaAlContenidoSiCorresponde(options = {}) {
    const opts = Object.assign({
        force: false
    }, options);

    const plantillaId = $('#plantilla_informe_id').val() || '';
    const plantilla = ($('#plantillaBase').val() || '').trim();

    if (!plantillaId) {
        return false;
    }

    const actual = vmGetContenidoActualEditor();
    const actualVacio = vmHtmlVacio(actual);

    if (!actualVacio && !opts.force) {
        return false;
    }

    if (!plantilla) {
        vmSetContenidoEditor('<p></p>');
        window.__vmPlantillaContenidoAplicada = {
            id: plantillaId,
            html: ''
        };

        $(document).trigger('change.autodraftEditor');
        return true;
    }

    vmSetContenidoEditor(plantilla);

    window.__vmPlantillaContenidoAplicada = {
        id: plantillaId,
        html: plantilla
    };

    $(document).trigger('change.autodraftEditor');

    return true;
}

window.vmAplicarPlantillaAlContenidoSiCorresponde = vmAplicarPlantillaAlContenidoSiCorresponde;

function vmUsarPlantillaEnContenidoManual() {
    const plantilla = ($('#plantillaBase').val() || '').trim();

    if (!plantilla) {
        Swal.fire('Sin contenido', 'La plantilla seleccionada no tiene contenido para usar.', 'info');
        return;
    }

    const contenidoActual = vmGetContenidoActualEditor();
    const contenidoVacio = vmHtmlVacio(contenidoActual);

    if (contenidoVacio) {
        vmAplicarPlantillaAlContenidoSiCorresponde({ force: true });
        return;
    }

    Swal.fire({
        title: '¿Reemplazar contenido?',
        text: 'El contenido actual del informe será reemplazado por la plantilla seleccionada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, reemplazar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        vmAplicarPlantillaAlContenidoSiCorresponde({ force: true });
    });
}

$(function () {
    $('#plantilla_informe_id')
        .off('change.tipoPlantilla')
        .on('change.tipoPlantilla', function () {
            let tipo = $(this).val();
            $('#procesarIA').prop('disabled', true);

            if (!tipo) {
                $('#plantillaBase').val('');
                $('#plantillaPreview').hide();
                $('#plantillaPlaceholder').show();
                $('#plantillaContenido').html('<em class="text-muted">Selecciona un tipo de examen para ver su plantilla...</em>');
                $('#btnUsarPlantillaContenido').hide();

                window.__vmPlantillaContenidoAplicada = {
                    id: '',
                    html: ''
                };

                return;
            }

            $.ajax({
                url: 'certificado/tipo_examen/getPlantillaPorTipo.php',
                type: 'POST',
                dataType: 'json',
                data: { plantilla_informe_id: tipo },
                success: function (res) {
                    // console.log('getPlantillaPorTipo response:', res);

                    if (res && res.status === 'success') {
                        const contenidoPlantilla = (res.contenido || '').trim();

                        $('#plantillaBase').val(contenidoPlantilla);
                        $('#plantillaContenido').html(contenidoPlantilla || '<em class="text-muted">Esta plantilla no tiene contenido.</em>');
                        $('#plantillaPlaceholder').hide();
                        $('#plantillaPreview').show();
                        $('#procesarIA').prop('disabled', false);
                        $('#btnUsarPlantillaContenido').show();

                        if (!window.__vmPlantillaContenidoAplicada) {
                            window.__vmPlantillaContenidoAplicada = {
                                id: '',
                                html: ''
                            };
                        }

                        if (!ES_MODIFICAR && typeof audio_manual_isManual === 'function' && audio_manual_isManual()) {
                            vmAplicarPlantillaAlContenidoSiCorresponde({
                                force: false
                            });
                        }

                    } else {
                        $('#plantillaBase').val('');
                        $('#plantillaContenido').html('<div class="text-danger">' + ((res && res.message) ? res.message : 'No se pudo cargar la plantilla.') + '</div>');
                        $('#plantillaPlaceholder').hide();
                        $('#plantillaPreview').show();

                        Swal.fire(
                            'Error',
                            (res && res.message) ? res.message : 'No se pudo cargar la plantilla del examen.',
                            'error'
                        );
                    }
                },
                error: function (xhr, status, error) {
                    console.error('getPlantillaPorTipo error:', {
                        status: status,
                        error: error,
                        http_status: xhr.status,
                        responseText: xhr.responseText
                    });

                    $('#plantillaBase').val('');
                    $('#plantillaContenido').html('<div class="text-danger">Error al cargar la plantilla para el examen.</div>');
                    $('#plantillaPlaceholder').hide();
                    $('#plantillaPreview').show();

                    let msg = 'Error al cargar la plantilla para el examen.';
                    if (xhr && xhr.responseText) {
                        msg += '<br><small style="word-break:break-word;">' + $('<div>').text(xhr.responseText).html() + '</small>';
                    }

                    Swal.fire('Error', msg, 'error');
                }
            });
        });
    
        // Al cargar (recarga / borrador): si ya hay un tipo de examen elegido, mostrar su plantilla
    (function () {
        const $selTipo = $('#plantilla_informe_id');
        if ($selTipo.length && ($selTipo.val() || '').trim() !== '') {
            $selTipo.trigger('change.tipoPlantilla');
        }
    })();

    $('#configuracion_informe_id')
        .off('change.certCamposVisibles')
        .on('change.certCamposVisibles', function () {
            const configuracionInformeId = $(this).val() || '';
            cargarCamposVisiblesPorConfiguracion(configuracionInformeId);
        });

    $('#btnUsarPlantillaContenido')
        .off('click.usarPlantillaContenido')
        .on('click.usarPlantillaContenido', function () {
            vmUsarPlantillaEnContenidoManual();
        });

    $('#btnTogglePlantillaPreview')
        .off('click.togglePlantillaPreview')
        .on('click.togglePlantillaPreview', function () {
            const $btn = $(this);
            const visible = $btn.attr('data-visible') === '1';

            if (visible) {
                $('#plantillaContenido').stop(true, true).slideUp(150);
                $btn.attr('data-visible', '0');
                $btn.attr('title', 'Mostrar plantilla asociada');
                $btn.html('<i class="fas fa-eye"></i>');
            } else {
                $('#plantillaContenido').stop(true, true).slideDown(150);
                $btn.attr('data-visible', '1');
                $btn.attr('title', 'Ocultar plantilla asociada');
                $btn.html('<i class="fas fa-eye-slash"></i>');
            }
        });

    $('#btnToggleImagenesPreview')
        .off('click.toggleImagenesPreview')
        .on('click.toggleImagenesPreview', function () {
            const $btn = $(this);
            const visible = $btn.attr('data-visible') === '1';

            if (visible) {
                $('#imagenesPreview, #maxImgsWarning').stop(true, true).slideUp(150);
                $btn.attr('data-visible', '0');
                $btn.attr('title', 'Mostrar imágenes');
                $btn.html('<i class="fas fa-eye"></i>');
            } else {
                $('#imagenesPreview').stop(true, true).slideDown(150);

                if ($('#maxImgsWarning').html().trim() !== '') {
                    $('#maxImgsWarning').stop(true, true).slideDown(150);
                }

                $btn.attr('data-visible', '1');
                $btn.attr('title', 'Ocultar imágenes');
                $btn.html('<i class="fas fa-eye-slash"></i>');
            }
        });

    vmAjustarLayoutCamposGenerales();

    const filaCamposGenerales = document.getElementById('fila_campos_generales');

    if (filaCamposGenerales) {
        let vmAjustandoLayoutCampos = false;
        let vmTimerLayoutCampos = null;

        const observerCamposGenerales = new MutationObserver(function () {
            if (vmAjustandoLayoutCampos) {
                return;
            }

            clearTimeout(vmTimerLayoutCampos);

            vmTimerLayoutCampos = setTimeout(function () {
                vmAjustandoLayoutCampos = true;

                vmAjustarLayoutCamposGenerales();

                setTimeout(function () {
                    vmAjustandoLayoutCampos = false;
                }, 50);
            }, 50);
        });

        observerCamposGenerales.observe(filaCamposGenerales, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['style']
        });
    }
});