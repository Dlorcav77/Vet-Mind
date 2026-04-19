//admin/certificado/tipo_examen/js/tipo_examen.js
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
            console.log('get_campos_visibles response:', res);

            if (res && res.status === 'success') {
                if (typeof aplicarCamposVisiblesFormulario === 'function') {
                    aplicarCamposVisiblesFormulario(res.campos || []);
                }
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
                return;
            }

            $.ajax({
                url: 'certificado/tipo_examen/getPlantillaPorTipo.php',
                type: 'POST',
                dataType: 'json',
                data: { plantilla_informe_id: tipo },
                success: function (res) {
                    console.log('getPlantillaPorTipo response:', res);

                    if (res && res.status === 'success') {
                        const contenidoPlantilla = (res.contenido || '').trim();

                        $('#plantillaBase').val(contenidoPlantilla);
                        $('#plantillaContenido').html(contenidoPlantilla);
                        $('#plantillaPlaceholder').hide();
                        $('#plantillaPreview').show();
                        $('#procesarIA').prop('disabled', false);

                        if (!ES_MODIFICAR && typeof audio_manual_isManual === 'function' && audio_manual_isManual()) {
                            const $txt = $('#contenido_html');
                            const contenidoTextarea = ($txt.val() || '').trim();

                            if (
                                window.VetmindTiptap &&
                                typeof window.VetmindTiptap.getMainEditor === 'function' &&
                                typeof window.VetmindTiptap.setMainEditorHTML === 'function'
                            ) {
                                const editor = window.VetmindTiptap.getMainEditor();
                                let contenidoEditor = '';

                                if (editor && typeof editor.getHTML === 'function') {
                                    contenidoEditor = (editor.getHTML() || '').trim();
                                }

                                const editorVacio =
                                    !contenidoEditor ||
                                    contenidoEditor === '<p></p>' ||
                                    contenidoEditor === '<p class="is-editor-empty"></p>';

                                const textareaVacio = !contenidoTextarea;

                                if (editorVacio && textareaVacio) {
                                    window.VetmindTiptap.setMainEditorHTML(contenidoPlantilla || '<p></p>');

                                    if (typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
                                        window.VetmindTiptap.syncMainEditorToTextarea();
                                    }
                                }
                            } else if ($txt.length && !$txt.val().trim()) {
                                $txt.val(contenidoPlantilla);
                            }
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

    $('#configuracion_informe_id')
        .off('change.certCamposVisibles')
        .on('change.certCamposVisibles', function () {
            const configuracionInformeId = $(this).val() || '';
            cargarCamposVisiblesPorConfiguracion(configuracionInformeId);
        });

    if ($('#plantilla_informe_id').val()) {
        $('#plantilla_informe_id').trigger('change');
    }
});