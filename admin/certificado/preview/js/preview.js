window.ultimoTriggerVistaPrevia = window.ultimoTriggerVistaPrevia || null;

$('#btnVistaPrevia')
    .off('mousedown.storeTriggerVista focus.storeTriggerVista')
    .on('mousedown.storeTriggerVista focus.storeTriggerVista', function () {
        window.ultimoTriggerVistaPrevia = this;
    });

$('#btnVistaPrevia').off('click.vistaPrevia').on('click.vistaPrevia', function () {
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
            html: 'Se pueden subir como máximo <b>' + LIMITE_IMAGENES + '</b> imágenes para la vista previa.<br>Elimina <b>' + (archivosSeleccionados.length - LIMITE_IMAGENES) + '</b> para continuar.',
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

    let form = $('form')[0];
    let formData = new FormData(form);

    Swal.fire({
        title: 'Generando vista previa...',
        text: 'Por favor espera unos segundos.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'certificado/previewPDF.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (pdfUrl) {
            Swal.close();
            $('#contenidoVistaPrevia').html('<iframe src="' + pdfUrl + '" style="width:100%;height:80vh;border:none;"></iframe>');
            $('#modalVistaPrevia').modal('show');
            window.nombreTempPDF = pdfUrl.split('/').pop();
        },
        error: function (xhr) {
            Swal.close();

            console.error('previewPDF error:', xhr);
            console.error('previewPDF responseText:', xhr.responseText);

            let msg = 'No se pudo generar la vista previa del PDF.';
            if (xhr && xhr.responseText) {
                msg += '\n' + xhr.responseText;
            }

            Swal.fire('Error', msg, 'error');
        }
    });
});

$('#modalVistaPrevia')
    .off('hide.bs.modal.storeTriggerVista')
    .on('hide.bs.modal.storeTriggerVista', function () {
        const activo = document.activeElement;
        if (activo && this.contains(activo)) {
            activo.blur();
        }

        const destino = window.ultimoTriggerVistaPrevia || document.getElementById('btnVistaPrevia');
        setTimeout(function () {
            if (destino && typeof destino.focus === 'function') {
                destino.focus();
                destino.blur();
            }
        }, 0);
    });

$('#modalVistaPrevia')
    .off('hidden.bs.modal.storeTriggerVista')
    .on('hidden.bs.modal.storeTriggerVista', function () {
        if (window.nombreTempPDF) {
            $.ajax({
                url: 'certificado/tipo_examen/eliminar_temp_pdf.php',
                type: 'POST',
                data: { pdf: window.nombreTempPDF },
                success: function () {
                    window.nombreTempPDF = null;
                },
                error: function () {
                    console.error('Error al eliminar PDF temporal');
                }
            });
        }
    });