//admin/certificado/metodo_ingreso/js/ia.js
function calcularEdadTexto(fechaNacimiento) {
    if (!fechaNacimiento) {
        return '';
    }

    const fecha = new Date(fechaNacimiento + 'T00:00:00');
    if (isNaN(fecha.getTime())) {
        return '';
    }

    const hoy = new Date();

    let anios = hoy.getFullYear() - fecha.getFullYear();
    let meses = hoy.getMonth() - fecha.getMonth();
    let dias = hoy.getDate() - fecha.getDate();

    if (dias < 0) {
        meses--;
        const mesAnterior = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
        dias += mesAnterior.getDate();
    }

    if (meses < 0) {
        anios--;
        meses += 12;
    }

    if (anios > 0) {
        return meses > 0 ? anios + ' años ' + meses + ' meses' : anios + ' años';
    }

    if (meses > 0) {
        return meses + ' meses';
    }

    return dias + ' días';
}

function leerSelectTextoOValor(selector) {
    const $el = $(selector);

    if (!$el.length) {
        return '';
    }

    if ($el.is('select')) {
        const txt = ($el.find('option:selected').text() || '').trim();
        const val = ($el.val() || '').toString().trim();

        if (txt !== '' && txt.toLowerCase() !== 'seleccione') {
            return txt;
        }

        return val;
    }

    return ($el.val() || '').toString().trim();
}

function obtenerDatosPaciente() {
    const esManual = $('#toggle_manual').prop('checked');
    const datos = {};

    const leerValor = function (selector) {
        const $el = $(selector);

        if (!$el.length) {
            return '';
        }

        return ($el.val() || '').toString().trim();
    };

    const agregarSiExiste = function (key, value) {
        const val = (value || '').toString().trim();

        if (val !== '') {
            datos[key] = val;
        }
    };

    if (esManual) {
        $('input[name^="manual_"], select[name^="manual_"], textarea[name^="manual_"]').each(function () {
            const nombre = this.name.replace('manual_', '');
            agregarSiExiste(nombre, $(this).val());
        });

        const fechaNacimientoManual = leerValor('#manual_fecha_nacimiento');
        const edadManual = calcularEdadTexto(fechaNacimientoManual);

        agregarSiExiste('raza', leerSelectTextoOValor('#manual_raza'));
        agregarSiExiste('sexo', leerSelectTextoOValor('#manual_sexo'));
        agregarSiExiste('paciente', leerValor('#manual_paciente'));
        agregarSiExiste('especie', leerSelectTextoOValor('#manual_especie'));
        agregarSiExiste('fecha_nacimiento', fechaNacimientoManual);
        agregarSiExiste('edad', edadManual);
        agregarSiExiste('propietario', leerValor('#manual_propietario'));
        agregarSiExiste('codigo_paciente', leerValor('#manual_codigo_paciente'));
        agregarSiExiste('n_chip', leerValor('#manual_n_chip'));
    } else {
        const $paciente = $('#paciente_seleccionado');

        const fechaNacimiento = ($paciente.data('fecha_nacimiento') || '').toString().trim();
        const edadData = ($paciente.data('edad') || '').toString().trim();
        const edadCalculada = edadData !== '' ? edadData : calcularEdadTexto(fechaNacimiento);

        agregarSiExiste('paciente', $paciente.val());
        agregarSiExiste('especie', $paciente.data('especie'));
        agregarSiExiste('raza', $paciente.data('raza'));
        agregarSiExiste('edad', edadCalculada);
        agregarSiExiste('fecha_nacimiento', fechaNacimiento);
        agregarSiExiste('sexo', $paciente.data('sexo'));
    }

    const tipo_examen = ($('select[name="plantilla_informe_id"] option:selected').text() || '').trim();

    agregarSiExiste('tipo_estudio', tipo_examen);
    agregarSiExiste('motivo', leerValor('#motivo_examen'));
    agregarSiExiste('medico_solicitante', leerValor('#medico_solicitante'));
    agregarSiExiste('recinto', leerValor('#recinto'));
    agregarSiExiste('N_ficha', leerValor('#manual_N_ficha'));
    agregarSiExiste('m_tratante', leerValor('#manual_m_tratante'));

    if (!tipo_examen || tipo_examen === 'Seleccione una plantilla') {
        return null;
    }

    return datos;
}

function generarFlujoIdIA() {
    const ahora = new Date();
    const yyyy = ahora.getFullYear();
    const mm = String(ahora.getMonth() + 1).padStart(2, '0');
    const dd = String(ahora.getDate()).padStart(2, '0');
    const hh = String(ahora.getHours()).padStart(2, '0');
    const mi = String(ahora.getMinutes()).padStart(2, '0');
    const ss = String(ahora.getSeconds()).padStart(2, '0');
    const rnd = Math.random().toString(16).slice(2, 10);

    return 'ia_' + yyyy + mm + dd + '_' + hh + mi + ss + '_' + rnd;
}

function obtenerFlujoIdIA(nuevo) {
    if (nuevo || !window.__flujoIdIA) {
        window.__flujoIdIA = generarFlujoIdIA();
    }

    return window.__flujoIdIA;
}

// Llama a la IA revisora y pinta el panel (no editable) arriba del informe.
// Llama a la IA revisora y pinta el panel (no editable) arriba del informe.
function ejecutarRevisor(dictado, informeHtml, plantillaBase) {
    const $panel = $('#revisor-panel');
    const flujoId = obtenerFlujoIdIA(false);

    $panel.html('<div style="padding:12px 14px;color:#64748b">Revisando informe…</div>').show();

    return $.post('/funciones/GPT/proceso_ia/proceso_revisor.php', {
        flujo_id: flujoId,
        dictado: dictado,
        informe: informeHtml,
        plantilla: plantillaBase
    }, null, 'json')
    .done(function (resp) {
        if (!resp || resp.status !== 'success') {
            const msg = (resp && resp.message) ? resp.message : 'No se pudo completar la revisión.';
            $panel.html('<div style="padding:12px 14px;background:#fef2f2;color:#991b1b">⚠ Revisor: ' + $('<div>').text(msg).html() + '</div>');
            return;
        }
        if (resp.rid) { $('#rid_revision').val(resp.rid); }
        const items = Array.isArray(resp.items) ? resp.items : [];
        if (items.length === 0) {
            $panel.html('<div style="padding:12px 14px;background:#ecfdf5;color:#065f46">✓ El revisor no encontró inconsistencias entre el dictado y el informe.</div>');
            return;
        }
        const esc = function (v) { return $('<div>').text(v || '').html(); };
        let filas = '';
        items.forEach(function (it, idx) {
            const sev = (it.severidad || 'media').toLowerCase();
            const bg = sev === 'alta' ? '#fee2e2;color:#991b1b' : (sev === 'media' ? '#fef3c7;color:#92400e' : '#e2e8f0;color:#475569');
            const sevBadge = '<span style="display:inline-block;font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;background:' + bg + '">' + esc(sev) + '</span>';

            // Fila resumen (una línea, clickeable).
            filas += '<tr class="rev-row" data-idx="' + idx + '" style="cursor:pointer">'
                + '<td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;width:18px;color:#94a3b8"><span class="rev-caret">▸</span></td>'
                + '<td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;white-space:nowrap">' + sevBadge + '</td>'
                + '<td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;white-space:nowrap;font-weight:600;color:#334155">' + esc(it.tipo) + '</td>'
                + '<td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;color:#334155">' + esc(it.zona) + '</td>'
                + '</tr>';

            // Fila detalle (oculta por defecto): 3 cards.
            const card = function (titulo, valor, color, bgCard, bdr) {
                return '<div style="flex:1;min-width:200px;background:' + bgCard + ';border:1px solid ' + bdr + ';border-radius:8px;padding:10px 12px">'
                    + '<div style="font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:' + color + ';margin-bottom:4px">' + titulo + '</div>'
                    + '<div style="font-size:13px;line-height:1.45;color:#334155">' + esc(valor) + '</div>'
                    + '</div>';
            };
            filas += '<tr class="rev-detail" data-idx="' + idx + '" style="display:none">'
                + '<td></td>'
                + '<td colspan="3" style="padding:2px 10px 14px;border-bottom:1px solid #f1f5f9">'
                + '<div style="display:flex;flex-wrap:wrap;gap:10px">'
                + card('Dictado', it.dictado, '#0369a1', '#f0f9ff', '#bae6fd')
                + card('Informe', it.informe, '#92400e', '#fffbeb', '#fde68a')
                + card('Revisar', it.detalle, '#9a3412', '#fff7ed', '#fed7aa')
                + '</div>'
                + '</td>'
                + '</tr>';
        });
        $panel.html(
            '<div style="padding:10px 14px;background:#fff7ed;color:#9a3412;font-weight:600;border-bottom:1px solid #e2e8f0">⚠ El revisor detectó ' + items.length + ' punto(s) a revisar (no se modificó el informe)</div>'
            + '<div style="overflow:auto"><table style="width:100%;border-collapse:collapse">'
            + '<tr style="background:#f8fafc;color:#475569">'
            + '<th style="padding:6px 10px"></th><th style="text-align:left;padding:6px 10px">Sev.</th><th style="text-align:left;padding:6px 10px">Tipo</th><th style="text-align:left;padding:6px 10px">Zona</th>'
            + '</tr>' + filas + '</table></div>'
        );

        // Toggle: al clickear una fila resumen, despliega/oculta su detalle.
        $panel.off('click', '.rev-row').on('click', '.rev-row', function () {
            const idx = $(this).data('idx');
            const $detail = $panel.find('.rev-detail[data-idx="' + idx + '"]');
            const $caret = $(this).find('.rev-caret');
            const visible = $detail.is(':visible');
            $detail.toggle(!visible);
            $caret.text(visible ? '▸' : '▾');
        });
    })
    .fail(function () {
        $panel.html('<div style="padding:12px 14px;background:#fef2f2;color:#991b1b">⚠ No se pudo conectar al revisor.</div>');
    });
}

// Resalta en el informe las palabras que vinieron de una discrepancia entre los 2 motores.
// Solo color (no número, no observación). El vet ve dónde hubo duda y revisa.
function resaltarDiscrepancias(html, discrepancias) {
    if (!Array.isArray(discrepancias) || discrepancias.length === 0) return html;

    // Junta los tokens candidatos de ambos lados (A y B), separa por palabras,
    // limpia signos y descarta palabras muy cortas o vacías para no pintar ruido.
    const stop = new Set(['nada','con','de','el','la','en','por','x','y','o','un','una','del']);
    const candidatos = new Set();
    discrepancias.forEach(function (d) {
        [d.a, d.b].forEach(function (lado) {
            (lado || '').split(/\s+/).forEach(function (w) {
                const limpia = w.replace(/[.,;:()"]/g, '').trim();
                const norm = limpia.toLowerCase();
                if (limpia.length >= 4 && !stop.has(norm)) candidatos.add(limpia);
            });
        });
    });
    if (candidatos.size === 0) return html;

    // Reemplaza cada candidato por su versión resaltada, evitando tocar dentro de etiquetas.
    let out = html;
    candidatos.forEach(function (palabra) {
        const esc = palabra.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        // (?![^<]*>) evita reemplazar dentro de atributos/tags HTML.
        const re = new RegExp('(' + esc + ')(?![^<]*>)', 'gi');
        out = out.replace(re, '<span class="vm-discrepancia" style="background:#fff3cd;border-bottom:2px solid #f59e0b;padding:0 2px;border-radius:3px">$1</span>');
    });
    return out;
}

function obtenerContenidoInformeActual() {
    if (window.VetmindTiptap && typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
        window.VetmindTiptap.syncMainEditorToTextarea();
    }
    if (window.VetmindTiptap && typeof window.VetmindTiptap.getMainEditorHTML === 'function') {
        return (window.VetmindTiptap.getMainEditorHTML() || '').trim();
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
    $('#contenido_html').val(contenido);
}

function obtenerContenidoModalIA() {
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

        const flujoId = obtenerFlujoIdIA(true);

        $.post('/funciones/GPT/proceso_gpt.php', {
            flujo_id: flujoId,
            texto: texto,
            plantilla_base: plantillaBase,
            plantilla_id: plantillaId,
            ...pacienteData
        }, function (response) {
            Swal.close();

            if (response.status === 'success') {
                if (response.rid) { $('#rid_ia').val(response.rid); }
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
                // console.log("Respuesta cruda:", xhr.responseText);
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
    let audioTmp = ($('#audio_tmp').val() || '').trim();
    let audioFilename = $('#bloque-audio').data('audioFilename');

    if (!audioFile && !audioTmp && !audioFilename) {
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
    } else if (audioTmp) {
        formData.append('audio_tmp', audioTmp);
    } else {
        formData.append('audio_filename', audioFilename);
    }

    for (const key in pacienteData) {
        formData.append(key, pacienteData[key]);
    }

    fetch('/funciones/GPT/transcribir_doble.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.status !== 'success') {
            throw new Error(resp.message || 'Error al transcribir.');
        }

        if (resp.audio_tmp) {
            $('#audio_tmp').val(resp.audio_tmp);
            $('#bloque-audio').data('audioTmp', resp.audio_tmp);
            $('#bloque-audio').data('audioFilename', resp.audio_tmp.split('/').pop());
        }

        if (resp.flujo_id) {
            window.__flujoIdIA = resp.flujo_id;
        } else {
            obtenerFlujoIdIA(true);
        }

        const textoTranscrito = ((resp.texto || '') + (resp.texto_doble || '')).trim();
        if (!textoTranscrito) {
            throw new Error('La transcripción volvió vacía.');
        }
        window.__ultimoDictadoIA = textoTranscrito;
        window.__ultimasDiscrepancias = Array.isArray(resp.discrepancias) ? resp.discrepancias : [];

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
            flujo_id: obtenerFlujoIdIA(false),
            texto: textoTranscrito,
            plantilla_base: plantillaBase,
            plantilla_id: plantillaId,
            ...pacienteData
        }, null, 'json');
    })
    .then(respGPT => {
        Swal.close();

        if (respGPT.status === 'success') {
            if (respGPT.rid) { $('#rid_ia').val(respGPT.rid); }
            const informeResaltado = resaltarDiscrepancias(respGPT.content, window.__ultimasDiscrepancias || []);
            mostrarModalIA(informeResaltado);
            // Revisor: usa el informe ORIGINAL (sin el resaltado), para no confundirlo.
            const dictadoCompleto = (window.__ultimoDictadoIA || '').trim();
            const plantillaBase = $('#plantillaBase').val();
            ejecutarRevisor(dictadoCompleto, respGPT.content, plantillaBase);
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
        .replace(/<span[^>]*class=['"]vm-discrepancia['"][^>]*>(.*?)<\/span>/gi, '$1')
        .replace(/<span[^>]*style=['"]?color:(orange|blue);?['"]?[^>]*>(.*?)<\/span>/gi, '$2')
        .replace(/(?:<[^>]+>)?Observaciones del Asistente:?<\/?.*?>?(?:<br\s*\/?>)?[\s\S]*$/i, '')
        .replace(/<sup\b[^>]*class=['"]flag['"][^>]*>.*?<\/sup>/gi, '')
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