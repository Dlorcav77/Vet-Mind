$(function () {
    function syncConfiguracionInformeId() {
        $('#configuracion_informe_id_hidden').val($('#configuracion_informe_id').val() || '');
    }

    syncConfiguracionInformeId();

    $('#configuracion_informe_id').off('change.syncConfigHidden').on('change.syncConfigHidden', function () {
        syncConfiguracionInformeId();
    });
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

    let form = $('form')[0];
    let formData = new FormData(form);

    if ($('#guardarMascota').is(':checked')) {
        formData.append('guardar_mascota', '1');
    }

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

                if (typeof destroyAllCKEditorsSafe === 'function') {
                    destroyAllCKEditorsSafe();
                }

                $('#content').load('certificado/lisCertificados.php');
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