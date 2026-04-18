function obtenerDatosPaciente() {
    const esManual = $('#toggle_manual').prop('checked');
    const datos = {};

    if (esManual) {
        $('input[name^="manual_"]').each(function () {
            const nombre = this.name.replace('manual_', '');
            datos[nombre] = ($(this).val() || '').trim();
        });

        const sexoVal = ($('#manual_sexo').val() || '').trim();
        if (sexoVal) datos['sexo'] = sexoVal;
    } else {
        datos['paciente'] = ($('#paciente_seleccionado').val() || '').trim();
        datos['especie'] = ($('#paciente_seleccionado').data('especie') || '').trim();
        datos['raza'] = ($('#paciente_seleccionado').data('raza') || '').trim();
        datos['fecha_nacimiento'] = ($('#paciente_seleccionado').data('fecha_nacimiento') || '').trim();
        datos['sexo'] = ($('#paciente_seleccionado').data('sexo') || '').trim();
    }

    const tipo_examen = ($('select[name="plantilla_informe_id"] option:selected').text() || '').trim();
    datos['tipo_estudio'] = tipo_examen;
    datos['motivo_examen'] = ($('#motivo_examen').val() || '').trim();

    if (!tipo_examen || tipo_examen === 'Seleccione una plantilla') {
        return null;
    }

    return datos;
}

function obtenerContenidoInformeActual() {
    if (window.VetmindTiptap && typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
        window.VetmindTiptap.syncMainEditorToTextarea();
    }

    if (window.VetmindTiptap && typeof window.VetmindTiptap.getMainEditorHTML === 'function') {
        return (window.VetmindTiptap.getMainEditorHTML() || '').trim();
    }

    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances['contenido_html']) {
        return (CKEDITOR.instances['contenido_html'].getData() || '').trim();
    }

    return ($('#contenido_html').val() || '').trim();
}

function aplicarContenidoInforme(html) {
    const contenido = (html || '').trim();

    if (window.VetmindTiptap && typeof window.VetmindTiptap.setMainEditorHTML === 'function') {
        window.VetmindTiptap.setMainEditorHTML(contenido);
        if (typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
            window.VetmindTiptap.syncMainEditorToTextarea();
        }
        return;
    }

    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances['contenido_html']) {
        CKEDITOR.instances['contenido_html'].setData(contenido);
        return;
    }

    $('#contenido_html').val(contenido);
}

function obtenerContenidoModalIA() {
    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances['editorIA']) {
        return CKEDITOR.instances['editorIA'].getData() || '';
    }

    if ($('#editorIA').length) {
        return $('#editorIA').val() || $('#editorIA').html() || '';
    }

    return '';
}

function procesarTextoConGPT(texto) {
    let pacienteData = obtenerDatosPaciente();
    if (!pacienteData) {
        Swal.fire('Datos del paciente requeridos', 'Debes ingresar o seleccionar un paciente con todos los datos completos antes de procesar.', 'warning');
        return;
    }

    return new Promise((resolve, reject) => {
        Swal.fire({
            title: 'Procesando...',
            text: 'Generando informe...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        let plantillaBase = $('#plantillaBase').val();
        let plantillaId = $('select[name="plantilla_informe_id"]').val();
        let pacienteData = obtenerDatosPaciente();

        $.post('/funciones/GPT/proceso_gpt.php', {
            texto: texto,
            plantilla_base: plantillaBase,
            plantilla_id: plantillaId,
            ...pacienteData
        }, function (response) {
            Swal.close();

            if (response.status === 'success') {
                mostrarModalIA(response.content);
                resolve(response);
            } else if (response.status === 'dry_run') {
                const html = response.debug_html || response.content_demo || '<p><strong>DEBUG:</strong> Dry-run activo.</p>';
                mostrarModalDebug(html);
                resolve(response);
            } else {
                Swal.fire('Error', response.message || 'Fallo al procesar.', 'error');
                reject(response);
            }
        }, 'json')
        .fail(function (xhr, status, error) {
            Swal.close();

            if (xhr && xhr.responseText) {
                console.log("Respuesta cruda:", xhr.responseText);
            }

            Swal.fire('Error', 'No se pudo conectar al servicio GPT.', 'error');
            reject(error);
        });
    });
}

$('#procesarIA').on('click', function () {
    let $btnProcesar = $(this);
    let pacienteData = obtenerDatosPaciente();

    if (!pacienteData) {
        Swal.fire('Tipo de Examen requerido', 'Debes seleccionar un tipo de examen antes de procesar.', 'warning');
        return;
    }

    $btnProcesar.prop('disabled', true);

    let tipoExamen = $('select[name="plantilla_informe_id"]').val();
    if (!tipoExamen) {
        Swal.fire('Tipo de Examen requerido', 'Debes seleccionar un tipo de examen antes de procesar.', 'warning');
        $btnProcesar.prop('disabled', false);
        return;
    }

    if (window.recorder && window.recorder.state === 'recording') {
        Swal.fire('Espera', 'Termina la grabación antes de procesar.', 'info');
        $btnProcesar.prop('disabled', false);
        return;
    }

    let esManual = $('#toggle_audio_manual').prop('checked');

    if (esManual) {
        let texto = obtenerContenidoInformeActual();

        if (texto.length < 5) {
            Swal.fire('Error', 'Debes ingresar un texto antes de procesar.', 'warning');
            $btnProcesar.prop('disabled', false);
            return;
        }

        procesarTextoConGPT(texto).finally(() => {
            $btnProcesar.prop('disabled', false);
        });
        return;
    }

    let audioFile = $('input[name="archivo_audio"]')[0].files[0];
    let audioFilename = $('#bloque-audio').data('audioFilename');

    if (!audioFile && !audioFilename) {
        Swal.fire('Error', 'Debes subir o grabar un audio antes de procesar.', 'warning');
        $btnProcesar.prop('disabled', false);
        return;
    }

    Swal.fire({
        title: 'Procesando con Vet-Mind...',
        html: 'Transcribiendo tu audio, espera un momento.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    let formData = new FormData();
    if (audioFile) {
        formData.append('audio', audioFile);
    } else {
        formData.append('audio_filename', audioFilename);
    }

    for (const key in pacienteData) {
        formData.append(key, pacienteData[key]);
    }

    fetch('/funciones/GPT/transcribir_audio.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.status !== 'success') {
            throw new Error(resp.message || 'Error al transcribir.');
        }

        const textoTranscrito = (resp.texto || '').trim();
        if (!textoTranscrito) {
            throw new Error('La transcripción volvió vacía.');
        }

        Swal.update({
            title: 'Procesando con Vet-Mind...',
            html: 'Generando el informe con la plantilla seleccionada...',
            showConfirmButton: false,
            allowOutsideClick: false
        });
        Swal.showLoading();

        let plantillaBase = $('#plantillaBase').val();
        let plantillaId = $('select[name="plantilla_informe_id"]').val();

        return $.post('/funciones/GPT/proceso_gpt.php', {
            texto: textoTranscrito,
            plantilla_base: plantillaBase,
            plantilla_id: plantillaId,
            ...pacienteData
        }, null, 'json');
    })
    .then(respGPT => {
        Swal.close();

        if (respGPT.status === 'success') {
            mostrarModalIA(respGPT.content);
        } else if (respGPT.status === 'dry_run') {
            const html = respGPT.debug_html || respGPT.content_demo || '<p><strong>DEBUG:</strong> Dry-run activo.</p>';
            mostrarModalDebug(html);
        } else {
            Swal.fire('Error', respGPT.message || 'Fallo al procesar con GPT.', 'error');
        }
    })
    .catch(err => {
        Swal.close();
        Swal.fire('Error', err.message || 'No se pudo procesar el audio.', 'error');
    })
    .finally(() => {
        $btnProcesar.prop('disabled', false);
    });
});

$('#aceptarIA').on('click', function () {
    let textoIA = obtenerContenidoModalIA();

    audio_manual_setMode('manual');

    textoIA = textoIA
        .replace(/<span[^>]*style=['"]?color:(orange|blue);?['"]?[^>]*>(.*?)<\/span>/gi, '$2')
        .replace(/(?:<[^>]+>)?Observaciones del Asistente:?<\/?.*?>?(?:<br\s*\/?>)?[\s\S]*$/i, '')
        .replace(/\s*\(\d+\)/g, '')
        .replace(/CONCLUSION:\s*((?:- .*?\.)(?:\s*- .*?\.)*)/i, function(match, contenido) {
            const lineas = contenido
                .split(/\s*-\s+/)
                .filter(Boolean)
                .map(l => '&nbsp;&nbsp;- ' + l.trim() + '<br>')
                .join('');
            return 'CONCLUSION:<br>' + lineas;
        });

    aplicarContenidoInforme(textoIA);

    $('#modalProcesarIA').modal('hide');
    Swal.fire('Éxito', 'El contenido procesado ha sido aplicado.', 'success');
});