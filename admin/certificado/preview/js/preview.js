// admin/certificado/preview/js/preview.js
window.ultimoTriggerVistaPrevia = window.ultimoTriggerVistaPrevia || null;
window.nombreTempPDF = window.nombreTempPDF || null;
window.imagenesTempPreview = window.imagenesTempPreview || [];

window.vmPreviewBodyPaddingOriginal = window.vmPreviewBodyPaddingOriginal || '';

function vmPreviewGuardarEstadoBody() {
    window.vmPreviewBodyPaddingOriginal = document.body.style.paddingRight || '';
}

function vmPreviewRestaurarEstadoBody() {
    document.body.style.paddingRight = window.vmPreviewBodyPaddingOriginal || '';

    if (!document.body.getAttribute('style')) {
        document.body.removeAttribute('style');
    }
}

function vmPreviewLimpiarPaddingBodyAcumulado() {
    const hayModalVisible = document.querySelector('.modal.show');

    if (!hayModalVisible) {
        document.body.style.paddingRight = '';
    }
}

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

    window.nombreTempPDF = null;
    window.imagenesTempPreview = [];

    vmPreviewLimpiarPaddingBodyAcumulado();
    vmPreviewGuardarEstadoBody();

    let form = $('form')[0];
    let formData = new FormData(form);

    Swal.fire({
        title: 'Generando vista previa...',
        text: 'Por favor espera unos segundos.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'certificado/pdf/previewPDF.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (data) {
            Swal.close();

            if (!data || data.status !== 'success' || !data.pdfUrl) {
                vmPreviewRestaurarEstadoBody();
                Swal.fire('Error', data?.message || 'No se pudo generar la vista previa del PDF.', 'error');
                return;
            }

            setTimeout(function () {
                vmPreviewRestaurarEstadoBody();

                $('#contenidoVistaPrevia').html(
                    '<iframe src="' + data.pdfUrl + '" style="width:100%;height:80vh;border:none;"></iframe>'
                );

                $('#modalVistaPrevia').modal('show');

                window.nombreTempPDF = data.pdf || data.pdfUrl.split('/').pop();
                window.imagenesTempPreview = Array.isArray(data.imagenesTemporales) ? data.imagenesTemporales : [];
            }, 0);
        },
        error: function (xhr) {
            Swal.close();

            console.error('previewPDF error:', xhr);
            console.error('previewPDF responseText:', xhr.responseText);

            let msg = 'No se pudo generar la vista previa del PDF.';

            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                msg += '\n' + xhr.responseJSON.message;
            } else if (xhr && xhr.responseText) {
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
        $('#contenidoVistaPrevia').empty();
        vmPreviewRestaurarEstadoBody();

        if (!window.nombreTempPDF) {
            return;
        }

        $.ajax({
            url: 'certificado/tipo_examen/eliminar_temp_pdf.php',
            type: 'POST',
            dataType: 'json',
            data: {
                pdf: window.nombreTempPDF,
                imagenes: JSON.stringify(window.imagenesTempPreview || [])
            },
            success: function () {
                window.nombreTempPDF = null;
                window.imagenesTempPreview = [];
            },
            error: function () {
                console.error('Error al eliminar temporales de vista previa');
            }
        });
    });