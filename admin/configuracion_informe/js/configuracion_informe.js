// admin/configuracion_informe/js/configuracion_informe.js

(function () {
    const CAMPOS_FIJOS_CONFIG = [1, 5];

    const $configuracion = $('#configuracion_informe');
    const $configForm = $('#configuracion_informe form');

    if (!$configuracion.length || !$configForm.length) {
        return;
    }

    inicializarFormulario();
    inicializarSelect2();
    inicializarDragDropCampos();
    inicializarSubtitulosFirma();
    inicializarCamposInforme();
    inicializarColores();
    inicializarLayoutConfig();
    inicializarVistaPreviaPlantilla();

    actualizarOpcionesSelect();
    actualizarVistaPrevia();
    actualizarConfiguracionLayoutVisible();

    function inicializarFormulario() {
        $configForm.off('submit.configInformes').on('submit.configInformes', function (e) {
            e.preventDefault();

            const resultadoOrden = calcularOrdenesCamposSegunLayout();

            $('#campos_ids_actuales').val(resultadoOrden.idsActuales.join(','));
            $('#campos_orden').val(JSON.stringify(resultadoOrden.ordenesActualizados));

            let formData = new FormData(this);

            $('#campos-lista .campo-chip').each(function () {
                const id = String($(this).data('id') || '');
                const campoId = parseInt($(this).data('campo-id'), 10) || 0;
                const esFijo = CAMPOS_FIJOS_CONFIG.includes(campoId);

                if (esFijo && /^\d+$/.test(id)) {
                    formData.set(`campos[${id}][visible]`, '1');
                }
            });

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    let jsonResponse;

                    try {
                        jsonResponse = JSON.parse(response);
                    } catch (error) {
                        Swal.fire('Error', 'La respuesta del servidor no es válida.', 'error');
                        return;
                    }

                    if (jsonResponse.status === 'success') {
                        $('#content').load('configuracion_informe/lisConfiguracion.php');
                        Swal.fire('Éxito', jsonResponse.message, 'success');
                    } else {
                        Swal.fire('Error', jsonResponse.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'No se pudo guardar la configuración.', 'error');
                }
            });
        });
    }

    function inicializarSelect2() {
        $('.select2').select2({
            templateResult: formatState,
            templateSelection: formatState,
            minimumResultsForSearch: Infinity,
            width: '100%'
        });
    }

    function formatState(state) {
        if (!state.id) {
            return state.text;
        }

        const icon = $(state.element).data('icon');

        if (icon) {
            return $('<span><i class="' + icon + '"></i> ' + state.text + '</span>');
        }

        return state.text;
    }

    function inicializarDragDropCampos() {
        let campoArrastrado = null;

        $(document)
            .off('dragstart.configCamposDrag', '.campo-chip')
            .on('dragstart.configCamposDrag', '.campo-chip', function (e) {
                campoArrastrado = this;

                $(this).addClass('campo-chip-dragging');

                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/plain', String($(this).data('campo-id') || ''));
            });

        $(document)
            .off('dragend.configCamposDrag', '.campo-chip')
            .on('dragend.configCamposDrag', '.campo-chip', function () {
                $(this).removeClass('campo-chip-dragging');
                $('.campos-chip-row').removeClass('campos-chip-row-hover');
                campoArrastrado = null;

                limpiarFilasCamposVacias();
                actualizarVistaPrevia();
            });

        $(document)
            .off('dragover.configCamposRow', '.campos-chip-row')
            .on('dragover.configCamposRow', '.campos-chip-row', function (e) {
                e.preventDefault();

                if (!campoArrastrado) {
                    return;
                }

                $(this).addClass('campos-chip-row-hover');

                const afterElement = obtenerElementoDespuesDeMouse(this, e.originalEvent.clientX);

                if (afterElement == null) {
                    this.appendChild(campoArrastrado);
                } else {
                    this.insertBefore(campoArrastrado, afterElement);
                }
            });

        $(document)
            .off('dragleave.configCamposRow', '.campos-chip-row')
            .on('dragleave.configCamposRow', '.campos-chip-row', function () {
                $(this).removeClass('campos-chip-row-hover');
            });

        $(document)
            .off('drop.configCamposRow', '.campos-chip-row')
            .on('drop.configCamposRow', '.campos-chip-row', function (e) {
                e.preventDefault();

                $(this).removeClass('campos-chip-row-hover');

                limpiarFilasCamposVacias();
                actualizarVistaPrevia();
            });

        $(document)
            .off('dragover.configCamposLista', '#campos-lista')
            .on('dragover.configCamposLista', '#campos-lista', function (e) {
                e.preventDefault();
            });

        $(document)
            .off('drop.configCamposLista', '#campos-lista')
            .on('drop.configCamposLista', '#campos-lista', function (e) {
                e.preventDefault();

                if (!campoArrastrado) {
                    return;
                }

                if ($(e.target).closest('.campos-chip-row').length > 0) {
                    return;
                }

                const nuevaFila = document.createElement('div');
                nuevaFila.className = 'campos-chip-row';
                nuevaFila.appendChild(campoArrastrado);

                this.appendChild(nuevaFila);

                limpiarFilasCamposVacias();
                actualizarVistaPrevia();
            });
    }

    function obtenerElementoDespuesDeMouse(contenedor, mouseX) {
        const elementos = [...contenedor.querySelectorAll('.campo-chip:not(.campo-chip-dragging)')];

        return elementos.reduce(function (closest, child) {
            const box = child.getBoundingClientRect();
            const offset = mouseX - box.left - box.width / 2;

            if (offset < 0 && offset > closest.offset) {
                return {
                    offset: offset,
                    element: child
                };
            }

            return closest;
        }, {
            offset: Number.NEGATIVE_INFINITY,
            element: null
        }).element;
    }

    function limpiarFilasCamposVacias() {
        $('#campos-lista .campos-chip-row').each(function () {
            if ($(this).find('.campo-chip').length === 0) {
                $(this).remove();
            }
        });
    }

        function calcularOrdenesCamposSegunLayout() {
        const layoutTipo = $('#layout_tipo').val() || 'clasico';
        const esClinica = layoutTipo === 'clinica' || layoutTipo === 'inev';

        let idsActuales = [];
        let ordenesActualizados = {};
        let indicePlano = 1;

        if (esClinica) {
            $('#campos-lista .campos-chip-row').each(function (rowIndex) {
                const ordenBaseFila = (rowIndex + 1) * 10;

                $(this).find('.campo-chip').each(function (chipIndex) {
                    const id = String($(this).data('id') || '');
                    const campoId = parseInt($(this).data('campo-id'), 10) || 0;
                    const esFijo = CAMPOS_FIJOS_CONFIG.includes(campoId);
                    const ordenCampo = ordenBaseFila + chipIndex;

                    if (id) {
                        ordenesActualizados[id] = ordenCampo;
                    }

                    if (id && String(id).indexOf('nuevo-') !== 0 && !String(id).startsWith('fijo-')) {
                        idsActuales.push(id);
                    }

                    if (esFijo) {
                        $(this).find('.campo-visible-input').prop('checked', true);
                    }
                });
            });

            return {
                idsActuales: idsActuales,
                ordenesActualizados: ordenesActualizados
            };
        }

        $('#campos-lista .campo-chip').each(function () {
            const id = String($(this).data('id') || '');
            const campoId = parseInt($(this).data('campo-id'), 10) || 0;
            const esFijo = CAMPOS_FIJOS_CONFIG.includes(campoId);

            if (id) {
                ordenesActualizados[id] = indicePlano;
            }

            if (id && String(id).indexOf('nuevo-') !== 0 && !String(id).startsWith('fijo-')) {
                idsActuales.push(id);
            }

            if (esFijo) {
                $(this).find('.campo-visible-input').prop('checked', true);
            }

            indicePlano++;
        });

        return {
            idsActuales: idsActuales,
            ordenesActualizados: ordenesActualizados
        };
    }

    function inicializarSubtitulosFirma() {
        $(document)
            .off('click.configSubtitulo', '#agregar-subtitulo')
            .on('click.configSubtitulo', '#agregar-subtitulo', function () {
                const html = `
                    <div class="input-group mb-2">
                        <input type="text" name="firma_subtitulos[]" class="form-control" placeholder="Nueva línea">
                        <button type="button" class="btn btn-danger eliminar-subtitulo">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;

                $('#firma-subtitulos-container').append(html);
            });

        $(document)
            .off('click.configEliminarSubtitulo', '.eliminar-subtitulo')
            .on('click.configEliminarSubtitulo', '.eliminar-subtitulo', function () {
                $(this).closest('.input-group').remove();
            });
    }

    function inicializarCamposInforme() {
        $(document)
            .off('click.configEliminarCampo', '.eliminar-campo-chip')
            .on('click.configEliminarCampo', '.eliminar-campo-chip', function () {
                const $chip = $(this).closest('.campo-chip');
                const campoId = parseInt($chip.data('campo-id'), 10) || 0;

                if (CAMPOS_FIJOS_CONFIG.includes(campoId)) {
                    Swal.fire('Atención', 'Paciente y Propietario son campos obligatorios y no se pueden eliminar.', 'warning');
                    return;
                }

                const $row = $chip.closest('.campos-chip-row');

                $chip.remove();

                if ($row.find('.campo-chip').length === 0) {
                    $row.remove();
                }

                actualizarVistaPrevia();
                actualizarOpcionesSelect();
            });

        $('#agregar-campo')
            .off('click.configAgregarCampo')
            .on('click.configAgregarCampo', function () {
                const selectedId = $('#campo-select').val();
                const selectedText = $('#campo-select option:selected').text();

                if (!selectedId) {
                    Swal.fire('Atención', 'No hay más campos disponibles para agregar.', 'warning');
                    return;
                }

                if (CAMPOS_FIJOS_CONFIG.includes(parseInt(selectedId, 10))) {
                    Swal.fire('Atención', 'Paciente y Propietario ya son obligatorios y siempre estarán incluidos.', 'warning');
                    return;
                }

                if ($('#campos-lista .campo-chip[data-campo-id="' + selectedId + '"]').length > 0) {
                    Swal.fire('Atención', 'Este campo ya está agregado.', 'warning');
                    return;
                }

                const newChip = `
                    <div class="campos-chip-row">
                        <div class="campo-chip" data-id="nuevo-${selectedId}" data-campo-id="${selectedId}" data-fixed="0" draggable="true" title="Mover campo">
                            <span class="campo-chip-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </span>

                            <span class="campo-chip-label">${selectedText}</span>

                            <input
                                type="checkbox"
                                class="campo-visible-input d-none"
                                name="campos_nuevos[${selectedId}][visible]"
                                value="1"
                                checked
                            >

                            <button
                                type="button"
                                class="btn btn-sm campo-chip-btn eliminar-campo-chip"
                                title="Eliminar campo"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;

                $('#campos-lista').append(newChip);

                actualizarOpcionesSelect();
                actualizarVistaPrevia();
            });

        $(document)
            .off('change.configCampos', '.campo-visible-input')
            .on('change.configCampos', '.campo-visible-input', function () {
                actualizarVistaPrevia();
            });
    }

    function inicializarColores() {
        $(document)
            .off('click.configColorPreview', '.color-mini-button')
            .on('click.configColorPreview', '.color-mini-button', function () {
                const targetId = $(this).data('color-target');

                if (!targetId) {
                    return;
                }

                const input = document.getElementById(targetId);

                if (input) {
                    input.click();
                }
            });

        $(document)
            .off('input.configColorPreview change.configColorPreview', '#color_primario, #color_secundario')
            .on('input.configColorPreview change.configColorPreview', '#color_primario, #color_secundario', function () {
                const colorPrimario = $('#color_primario').val() || '#3498db';
                const colorSecundario = $('#color_secundario').val() || '#2ecc71';

                $('#btn_color_primario').css('background-color', colorPrimario);
                $('#btn_color_secundario').css('background-color', colorSecundario);
            });
    }

    function inicializarLayoutConfig() {
        $('#layout_tipo')
            .off('change.configLayoutCampos')
            .on('change.configLayoutCampos', function () {
                actualizarVistaPrevia();
                actualizarConfiguracionLayoutVisible();
            });
    }

    function actualizarVistaPrevia() {
        const campos = obtenerCamposVisibles();
        const layoutTipo = $('#layout_tipo').val() || 'clasico';

        if (layoutTipo === 'clinica' || layoutTipo === 'inev') {
            $('#vista-previa-campos-clasico').hide();
            $('#vista-previa-campos-clinica').show();

            renderVistaPreviaCamposClinica();
            return;
        }

        $('#vista-previa-campos-clinica').hide();
        $('#vista-previa-campos-clasico').show();

        renderVistaPreviaCamposClasico(campos);
    }

    function obtenerCamposVisibles() {
        let campos = [];

        $('#campos-lista .campo-chip').each(function () {
            const etiqueta = $(this).find('.campo-chip-label').text().trim();
            const campoId = parseInt($(this).data('campo-id'), 10) || 0;
            let visible = $(this).find('.campo-visible-input').is(':checked');

            if (CAMPOS_FIJOS_CONFIG.includes(campoId)) {
                visible = true;
            }

            if (etiqueta !== '' && visible) {
                campos.push({
                    campoId: campoId,
                    etiqueta: etiqueta
                });
            }
        });

        return campos;
    }

    function renderVistaPreviaCamposClasico(campos) {
        let html = '';

        for (let i = 0; i < campos.length; i += 2) {
            html += '<tr>';
            html += `<th style="width:15%; white-space:nowrap;">${campos[i].etiqueta}:</th><td style="width:35%;"></td>`;

            if (campos[i + 1]) {
                html += `<th style="width:15%; white-space:nowrap;">${campos[i + 1].etiqueta}:</th><td style="width:35%;"></td>`;
            } else {
                html += '<td colspan="3"></td>';
            }

            html += '</tr>';
        }

        $('#vista-previa-campos-clasico-body').html(html);
    }

    function renderVistaPreviaCamposClinica() {
        let html = '';

        $('#campos-lista .campos-chip-row').each(function () {
            let camposFila = [];

            $(this).find('.campo-chip').each(function () {
                const etiqueta = $(this).find('.campo-chip-label').text().trim();
                const campoId = parseInt($(this).data('campo-id'), 10) || 0;
                let visible = $(this).find('.campo-visible-input').is(':checked');

                if (CAMPOS_FIJOS_CONFIG.includes(campoId)) {
                    visible = true;
                }

                if (etiqueta !== '' && visible) {
                    camposFila.push({
                        campoId: campoId,
                        etiqueta: etiqueta
                    });
                }
            });

            if (camposFila.length === 0) {
                return;
            }

            const campoPrincipal = camposFila[0];

            const valores = camposFila.map(function (campo) {
                return `[${campo.etiqueta}]`;
            });

            html += `
                <div class="preview-campo-clinica-linea">
                    <strong>${campoPrincipal.etiqueta}:</strong>
                    <span>${valores.join(', ')}</span>
                </div>
            `;
        });

        $('#vista-previa-campos-clinica-body').html(html);
    }

    function actualizarOpcionesSelect() {
        $('#campo-select option').prop('disabled', false);

        $('#campos-lista .campo-chip').each(function () {
            const campoId = parseInt($(this).data('campo-id'), 10) || 0;
            $('#campo-select option[value="' + campoId + '"]').prop('disabled', true);
        });

        const $primerHabilitado = $('#campo-select option:not(:disabled)').first();

        if ($('#campo-select option:selected').prop('disabled')) {
            $('#campo-select').val($primerHabilitado.length ? $primerHabilitado.val() : '');
        }

        if ($('#campo-select option:not(:disabled)').length === 0) {
            $('#campo-select').val('');
        }
    }

    function actualizarConfiguracionLayoutVisible() {
        const layoutTipo = $('#layout_tipo').val() || 'clasico';

        $('.layout-config-bloque').hide();
        $('.layout-config-bloque[data-layout-config="' + layoutTipo + '"]').show();
    }


    function inicializarVistaPreviaPlantilla() {
        $(document)
            .off('click.configVistaPreviaPlantilla', '#btn-vista-previa-plantilla')
            .on('click.configVistaPreviaPlantilla', '#btn-vista-previa-plantilla', function () {
                prepararCamposAntesDeEnviar();

                const form = document.getElementById('configuracion_informe_form');

                if (!form) {
                    Swal.fire('Error', 'No se encontró el formulario de configuración.', 'error');
                    return;
                }

                const formData = new FormData(form);
                formData.set('preview_campos_json', JSON.stringify(obtenerCamposPreviewParaServidor()));

                $('#vista-previa-plantilla-contenido').empty();
                $('#vista-previa-plantilla-loading').removeClass('d-none');

                const modalElement = document.getElementById('modalVistaPreviaPlantilla');

                if (modalElement && typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } else {
                    $('#modalVistaPreviaPlantilla').modal('show');
                }

                $.ajax({
                    url: 'configuracion_informe/previews/preview_temp.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        $('#vista-previa-plantilla-loading').addClass('d-none');

                        let jsonResponse = response;

                        if (typeof response === 'string') {
                            try {
                                jsonResponse = JSON.parse(response);
                            } catch (error) {
                                $('#vista-previa-plantilla-contenido').html(`
                                    <div class="alert alert-danger mb-0">
                                        La respuesta del servidor no es válida.
                                    </div>
                                `);
                                return;
                            }
                        }

                        if (jsonResponse.status !== 'success') {
                            $('#vista-previa-plantilla-contenido').html(`
                                <div class="alert alert-danger mb-0">
                                    ${jsonResponse.message || 'No se pudo generar la vista previa.'}
                                </div>
                            `);
                            return;
                        }

                        $('#vista-previa-plantilla-contenido').html(jsonResponse.html || '');
                    },
                    error: function () {
                        $('#vista-previa-plantilla-loading').addClass('d-none');
                        $('#vista-previa-plantilla-contenido').html(`
                            <div class="alert alert-danger mb-0">
                                No se pudo conectar con el servidor para generar la vista previa.
                            </div>
                        `);
                    }
                });
            });
    }

    function prepararCamposAntesDeEnviar() {
        const resultadoOrden = calcularOrdenesCamposSegunLayout();

        $('#campos_ids_actuales').val(resultadoOrden.idsActuales.join(','));
        $('#campos_orden').val(JSON.stringify(resultadoOrden.ordenesActualizados));
    }

    function obtenerCamposPreviewParaServidor() {
        let campos = [];
        const layoutTipo = $('#layout_tipo').val() || 'clasico';
        const esClinica = layoutTipo === 'clinica' || layoutTipo === 'inev';

        if (esClinica) {
            $('#campos-lista .campos-chip-row').each(function (rowIndex) {
                const ordenBaseFila = (rowIndex + 1) * 10;

                $(this).find('.campo-chip').each(function (chipIndex) {
                    const etiqueta = $(this).find('.campo-chip-label').text().trim();
                    const campoId = parseInt($(this).data('campo-id'), 10) || 0;
                    let visible = $(this).find('.campo-visible-input').is(':checked');

                    if (CAMPOS_FIJOS_CONFIG.includes(campoId)) {
                        visible = true;
                    }

                    if (etiqueta !== '' && visible) {
                        campos.push({
                            campo_id: campoId,
                            etiqueta: etiqueta,
                            orden: ordenBaseFila + chipIndex
                        });
                    }
                });
            });

            return campos;
        }

        $('#campos-lista .campo-chip').each(function (index) {
            const etiqueta = $(this).find('.campo-chip-label').text().trim();
            const campoId = parseInt($(this).data('campo-id'), 10) || 0;
            let visible = $(this).find('.campo-visible-input').is(':checked');

            if (CAMPOS_FIJOS_CONFIG.includes(campoId)) {
                visible = true;
            }

            if (etiqueta !== '' && visible) {
                campos.push({
                    campo_id: campoId,
                    etiqueta: etiqueta,
                    orden: index + 1
                });
            }
        });

        return campos;
    }

    /* =========================================================
    Mostrar/Ocultar pestaña Configuración Layout según plantilla
    ========================================================= */

    function actualizarVisibilidadTabLayoutConfig() {
        const layoutTipo = $('#layout_tipo').val() || 'clasico';
        const esClinica = layoutTipo === 'clinica';

        const $tabLayoutButton = $('#layout-config-tab');
        const $tabLayoutItem = $tabLayoutButton.closest('.nav-item');
        const $tabLayoutPane = $('#layout-config');

        if (esClinica) {
            $tabLayoutItem.removeClass('d-none');
            return;
        }

        $tabLayoutItem.addClass('d-none');

        if ($tabLayoutButton.hasClass('active') || $tabLayoutPane.hasClass('active')) {
            const generalTab = document.querySelector('#general-tab');

            if (generalTab && typeof bootstrap !== 'undefined') {
                const tab = new bootstrap.Tab(generalTab);
                tab.show();
            } else {
                $('#general-tab').addClass('active');
                $('#general').addClass('show active');

                $tabLayoutButton.removeClass('active');
                $tabLayoutPane.removeClass('show active');
            }
        }
    }

    $(document).ready(function () {
        actualizarVisibilidadTabLayoutConfig();

        $('#layout_tipo').on('change', function () {
            actualizarVisibilidadTabLayoutConfig();
        });
    });


})();