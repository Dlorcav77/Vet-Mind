//admin/certificado/guardar/js/guardar.js
$(function () {
    const AUTOSAVE_MS = 20000;
    const AUTOSAVE_HABILITADO = !(window.ES_MODIFICAR === true);

    let draftTimer = null;
    let draftDirty = false;
    let draftSaving = false;
    let lastDraftHash = '';
    let pendingDraftSave = false;
    let draftInitializing = true;
    let draftWatcherTimer = null;

    function syncConfiguracionInformeId() {
        $('#configuracion_informe_id_hidden').val($('#configuracion_informe_id').val() || '');
    }

    function syncEditorIfNeeded() {
        if (window.VetmindTiptap && typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
            window.VetmindTiptap.syncMainEditorToTextarea();
        }
    }

    function collectDraftData() {
        syncConfiguracionInformeId();
        syncEditorIfNeeded();

        const formArray = $('#formCertificado').serializeArray();
        const data = {};

        formArray.forEach(function (item) {
            if (item.name === 'imagenes[]' || item.name === 'imagenes_antiguas') {
                return;
            }
            data[item.name] = item.value;
        });

        data.action_borrador = 'guardar';
        data.toggle_manual = $('#toggle_manual').is(':checked') ? '1' : '0';
        data.toggle_audio_manual = $('#toggle_audio_manual').is(':checked') ? '1' : '0';
        data.paciente_label = $('#paciente_seleccionado').val() || '';
        data.contenido_html = $('#contenido_html').val() || $('textarea[name="contenido_html"]').val() || '';
        data.borrador_id = $('#borrador_id').val() || '0';
        data.borrador_scope_key = $('#borrador_scope_key').val() || (window.CERT_BORRADOR?.scopeKey || '');

        return data;
    }

    function setInitialDraftSnapshot() {
        const initialData = collectDraftData();
        lastDraftHash = JSON.stringify(initialData);
        draftDirty = false;
    }

    function updateDraftStatus(text, cls) {
        const $badge = $('#draftBadgeStatus');
        const $badgeText = $('#draftBadgeText');
        const $trash = $('#btnDescartarBorradorHeader');

        if (!$badge.length || !$badgeText.length) {
            return;
        }

        const estadoClase =
            cls === 'text-warning' ? 'is-saving' :
            cls === 'text-success' ? 'is-saved' :
            cls === 'text-danger' ? 'is-error' :
            'is-idle';

        $badge
            .removeClass('is-idle is-saving is-saved is-error')
            .addClass(estadoClase);

        $badgeText.text(text);

        if ($trash.length) {
            const mostrarTrash =
                AUTOSAVE_HABILITADO &&
                (
                    cls === 'text-success' ||
                    cls === 'text-warning' ||
                    cls === 'text-danger'
                );

            $trash.toggle(mostrarTrash);
        }
    }

    function scheduleDraftSave() {
        if (!AUTOSAVE_HABILITADO || draftInitializing) {
            return;
        }

        draftDirty = true;
        updateDraftStatus('Cambios sin guardar', 'text-muted');

        if (draftTimer) {
            clearTimeout(draftTimer);
        }

        draftTimer = setTimeout(function () {
            saveDraft({ silent: false, force: false });
        }, AUTOSAVE_MS);
    }

    function saveDraft(options = {}) {
        if (!AUTOSAVE_HABILITADO) {
            return;
        }

        const opts = Object.assign({ silent: false, force: false }, options);

        if (draftSaving) {
            pendingDraftSave = true;
            return;
        }

        const data = collectDraftData();
        const currentHash = JSON.stringify(data);

        if (!opts.force && currentHash === lastDraftHash) {
            return;
        }

        draftSaving = true;

        if (!opts.silent) {
            updateDraftStatus('Guardando borrador...', 'text-warning');
        }

        $.ajax({
            url: 'certificado/guardar/updBorradorCertificado.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response && response.status === 'success') {
                    if (response.borrador_id) {
                        $('#borrador_id').val(response.borrador_id);
                    }

                    const syncedData = collectDraftData();
                    lastDraftHash = JSON.stringify(syncedData);
                    draftDirty = false;

                    updateDraftStatus('Guardado en borrador', 'text-success');
                } else {
                    updateDraftStatus('Error al guardar borrador', 'text-danger');
                }
            },
            error: function () {
                updateDraftStatus('Error al guardar borrador', 'text-danger');
            },
            complete: function () {
                draftSaving = false;

                if (pendingDraftSave) {
                    pendingDraftSave = false;
                    saveDraft({ silent: true, force: false });
                }
            }
        });
    }

    function saveDraftWithBeacon() {
        if (!AUTOSAVE_HABILITADO || !draftDirty) {
            return;
        }

        const data = collectDraftData();
        const currentHash = JSON.stringify(data);

        if (currentHash === lastDraftHash) {
            return;
        }

        try {
            const payload = new URLSearchParams(data);
            navigator.sendBeacon(
                'certificado/guardar/updBorradorCertificado.php',
                new Blob([payload.toString()], { type: 'application/x-www-form-urlencoded;charset=UTF-8' })
            );

            lastDraftHash = currentHash;
            draftDirty = false;
        } catch (e) {
            // silencioso
        }
    }

    syncConfiguracionInformeId();

    if (!AUTOSAVE_HABILITADO) {
        $('#draftBadgeStatus').hide();
        return;
    }

    setTimeout(function () {
        setInitialDraftSnapshot();
        draftInitializing = false;
    }, 1200);

    setTimeout(function () {
        setInitialDraftSnapshot();
    }, 2200);

    $('#configuracion_informe_id')
        .off('change.syncConfigHidden')
        .on('change.syncConfigHidden', function () {
            syncConfiguracionInformeId();
            scheduleDraftSave();
        });

    $('#formCertificado')
        .off('input.autodraft change.autodraft')
        .on('input.autodraft change.autodraft', 'input:not([type="file"]), textarea, select', function () {
            scheduleDraftSave();
        });

    $('#audio_manual_audioBtn, #audio_manual_manualBtn, #procesarIA')
        .off('click.autodraft')
        .on('click.autodraft', function () {
            setTimeout(function () {
                scheduleDraftSave();
            }, 300);
        });

    $(document)
        .off('input.autodraftEditor', '#contenido_html')
        .on('input.autodraftEditor', '#contenido_html', function () {
            scheduleDraftSave();
        });

    $(document)
        .off('change.autodraftEditor', '#contenido_html')
        .on('change.autodraftEditor', '#contenido_html', function () {
            scheduleDraftSave();
        });

    $(document)
        .off('click.autodraftPaciente', '#resultadosBuscarPaciente [onclick*="seleccionarPaciente"]')
        .on('click.autodraftPaciente', '#resultadosBuscarPaciente [onclick*="seleccionarPaciente"]', function () {
            setTimeout(function () {
                scheduleDraftSave();
            }, 150);
        });

    draftWatcherTimer = setInterval(function () {
        if (draftInitializing || draftSaving) {
            return;
        }

        const data = collectDraftData();
        const currentHash = JSON.stringify(data);

        if (currentHash !== lastDraftHash && !draftDirty) {
            draftDirty = true;
            updateDraftStatus('Cambios sin guardar', 'text-muted');
        }
    }, 2000);

    $(document).off('click.clearDraftWatcher', '.ajax-link').on('click.clearDraftWatcher', '.ajax-link', function () {
        if (draftWatcherTimer) {
            clearInterval(draftWatcherTimer);
            draftWatcherTimer = null;
        }
    });

    if (window.CERT_BORRADOR && window.CERT_BORRADOR.hasDraft) {
        updateDraftStatus('Guardado en borrador', 'text-success');
    } else {
        updateDraftStatus('Sin cambios guardados', 'text-muted');
    }

    $('#btnDescartarBorradorHeader').off('click.descartarBorrador').on('click.descartarBorrador', function (e) {
        e.preventDefault();

        Swal.fire({
            title: '¿Descartar borrador?',
            text: 'Se eliminarán los cambios automáticos no guardados como informe final.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, descartar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'certificado/guardar/updBorradorCertificado.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action_borrador: 'descartar',
                    borrador_scope_key: $('#borrador_scope_key').val() || (window.CERT_BORRADOR?.scopeKey || ''),
                    action: $('input[name="action"]').val() || 'ingresar',
                    id: $('input[name="id"]').val() || 0
                },
                success: function (response) {
                    if (response && response.status === 'success') {
                        $('#borrador_id').val('0');
                        draftDirty = false;
                        lastDraftHash = JSON.stringify(collectDraftData());

                        Swal.fire('Listo', 'Borrador descartado.', 'success');

                        const href = 'certificado/certificados.php';

                        if (window.VetmindTiptap && typeof window.VetmindTiptap.destroyMainEditor === 'function') {
                            window.VetmindTiptap.destroyMainEditor();
                        }

                        if (typeof destroyAllCKEditorsSafe === 'function') {
                            destroyAllCKEditorsSafe();
                        }

                        $('#content').empty().load(href);
                    } else {
                        Swal.fire('Error', response.message || 'No se pudo descartar el borrador.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'No se pudo descartar el borrador.', 'error');
                }
            });
        });
    });

    window.addEventListener('beforeunload', saveDraftWithBeacon);
});

$('#btnGuardarCertificado').off('click.guardarCertificado').on('click.guardarCertificado', function (e) {
    e.preventDefault();

    let esManual = $('#toggle_manual').is(':checked');

    let configuracionInformeId = $('#configuracion_informe_id').val() || '';
    if (!configuracionInformeId) {
        Swal.fire('Falta Plantilla', 'Debes seleccionar una plantilla de diseño.', 'warning');
        return;
    }

    if (!esManual) {
        let pacienteId = $('input[name="paciente_id"]').val() || 0;
        if (!pacienteId) {
            Swal.fire('Falta Paciente', 'Debes seleccionar un paciente.', 'warning');
            return;
        }
    } else {
        let manualOk = true;

        if (typeof validarPacienteManualAntesDeGuardar === 'function') {
            manualOk = validarPacienteManualAntesDeGuardar();
        } else if (typeof validarPacienteManualUI === 'function') {
            manualOk = validarPacienteManualUI();
        }

        if (!manualOk) {
            Swal.fire('Faltan datos', 'Debes completar Paciente y Propietario en ingreso manual.', 'warning');
            return;
        }
    }

    if (archivosSeleccionados.length > LIMITE_IMAGENES) {
        Swal.fire({
            icon: 'warning',
            title: 'Demasiadas imágenes',
            html: 'Se pueden subir como máximo <b>' + LIMITE_IMAGENES + '</b> imágenes.<br>Elimina <b>' + (archivosSeleccionados.length - LIMITE_IMAGENES) + '</b> para poder guardar el informe.',
            confirmButtonText: 'Entendido',
            customClass: {
                title: 'fw-bold',
                popup: 'shadow rounded-4'
            }
        });
        return;
    }

    if (window.VetmindTiptap && typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
        window.VetmindTiptap.syncMainEditorToTextarea();
    }

    let contenido = $('textarea[name="contenido_html"]').val()?.trim() || '';
    if (contenido.length < 5) {
        Swal.fire('Falta Contenido', 'El informe debe tener contenido.', 'warning');
        return;
    }

    let form = $('#formCertificado')[0];
    let formData = new FormData(form);

    if ($('#guardarMascota').is(':checked')) {
        formData.append('guardar_mascota', '1');
    }

    formData.set('toggle_manual', $('#toggle_manual').is(':checked') ? '1' : '0');
    formData.set('toggle_audio_manual', $('#toggle_audio_manual').is(':checked') ? '1' : '0');
    formData.set('borrador_id', $('#borrador_id').val() || '0');
    formData.set('borrador_scope_key', $('#borrador_scope_key').val() || (window.CERT_BORRADOR?.scopeKey || ''));

    Swal.fire({
        title: 'Guardando certificado...',
        text: 'Por favor espera unos segundos.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'certificado/updCertificados.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            Swal.close();

            if (response.status === 'success') {
                let certId = response.id || 0;

                if (certId) {
                    window.open('certificado/descargar.php?id=' + encodeURIComponent(certId), '_blank');
                } else {
                    let rutaPdf = response.rutaPdf || null;
                    if (rutaPdf) {
                        let urlPdf = rutaPdf.startsWith('/') ? rutaPdf : '/' + rutaPdf;
                        window.open(urlPdf, '_blank');
                    }
                }

                if (window.VetmindTiptap && typeof window.VetmindTiptap.destroyMainEditor === 'function') {
                    window.VetmindTiptap.destroyMainEditor();
                }

                if (typeof destroyAllCKEditorsSafe === 'function') {
                    destroyAllCKEditorsSafe();
                }

                $('#content').empty().load('certificado/lisCertificados.php');
            } else {
                Swal.fire('Error', response.message || 'No se pudo guardar el certificado.', 'error');
            }
        },
        error: function (xhr) {
            Swal.close();

            let msg = 'No se pudo guardar el certificado.';
            if (xhr.responseText) {
                try {
                    let res = JSON.parse(xhr.responseText);
                    if (res.message) msg += "\n" + res.message;
                    if (res.mysql_error) msg += "\n" + res.mysql_error;
                } catch (e) {
                    msg += "\n" + xhr.responseText;
                }
            }

            Swal.fire('Error', msg, 'error');
            console.error('AJAX error:', xhr);
        }
    });
});