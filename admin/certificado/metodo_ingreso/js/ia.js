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

function ejecutarRevisor(dictado, informeHtml, plantillaBase, observacionesGenerador, organosOrigen) {
    const $bloque = $('#revision_ia_bloque');
    const $estado = $('#revision_ia_estado');
    const $toggle = $('#revision_ia_toggle');
    const $modalBody = $('#revision_ia_modal_body');
    const flujoId = obtenerFlujoIdIA(false);

    const setEstado = function (texto, clase, disabled) {
        $estado.text(texto);
        $toggle
            .removeClass('vm-revision-status-pending vm-revision-status-warning vm-revision-status-ok vm-revision-status-error')
            .addClass(clase)
            .prop('disabled', disabled);
    };

    const esc = function (v) {
        return $('<div>').text(v || '').html();
    };

    $bloque.show();
    $('#revision_ia_leyenda').show();
    setEstado('Revisión IA · Revisando…', 'vm-revision-status-pending', true);
    $modalBody.empty();

    return $.post('/funciones/GPT/proceso_ia/proceso_revisor.php', {
        flujo_id: flujoId,
        dictado: dictado,
        informe: informeHtml,
        plantilla: plantillaBase
    }, null, 'json')
    .done(function (resp) {
        if (!resp || resp.status !== 'success') {
            const msg = (resp && resp.message) ? resp.message : 'No se pudo completar la revisión.';
            setEstado('⚠ No se pudo completar la revisión', 'vm-revision-status-error', false);
            $modalBody.html('<div class="alert alert-danger mb-0">' + esc(msg) + '</div>');
            return;
        }

        if (resp.rid) $('#rid_revision').val(resp.rid);

        const itemsRevisor = Array.isArray(resp.items) ? resp.items : [];
        const organos = Array.isArray(organosOrigen) ? organosOrigen : [];
        const observaciones = Array.isArray(observacionesGenerador) ? observacionesGenerador : [];

        const norm = function (v) {
            return String(v || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        };

        const organosVisuales = organos.map(function (organo) {
            return {
                organo: organo.organo,
                atributos: Array.isArray(organo.atributos) ? organo.atributos : [],
                alertas: [],
                dudasTranscripcion: []
            };
        });

        const buscarOrgano = function (texto) {
            const pista = norm(texto);
            if (!pista) return -1;

            const candidatos = [];

            organosVisuales.forEach(function (organo, indice) {
                const nombre = norm(organo.organo);

                if (nombre && pista.includes(nombre)) {
                    candidatos.push({
                        indice: indice,
                        largo: nombre.length
                    });
                }
            });

            if (!candidatos.length) return -1;

            candidatos.sort(function (a, b) {
                return b.largo - a.largo;
            });

            return candidatos[0].indice;
        };

        const items = itemsRevisor.map(function (item) {
            return Object.assign({}, item);
        });

        itemsRevisor.forEach(function (item) {
            let indice = buscarOrgano(item.zona);

            if (indice === -1) {
                const zona = norm(item.zona);

                indice = organosVisuales.findIndex(function (organo) {
                    const nombre = norm(organo.organo);
                    return nombre && (zona.includes(nombre) || nombre.includes(zona));
                });
            }

            if (indice === -1) return;

            organosVisuales[indice].alertas.push({
                severidad: item.severidad || 'media',
                tipo: item.tipo || 'Revisar',
                detalle: item.detalle || '',
                dictado: item.dictado || '',
                informe: item.informe || ''
            });
        });

        observaciones.forEach(function (obs) {
            const indice = buscarOrgano(obs.contexto);

            if (indice !== -1 && obs.tipo === 'termino_confuso') {
                const detalle = norm(obs.texto || '');

                const esDudaUreter =
                    detalle.includes('ureter') &&
                    (
                        detalle.includes('negacion') ||
                        detalle.includes('fusionada') ||
                        detalle.includes('transcripcion') ||
                        detalle.includes('visibilidad')
                    );

                if (esDudaUreter) {
                    const contexto = String(obs.contexto || '');

                    const fraseUreter = contexto.match(
                        /(?:no\s+se\s+(?:visualiza|observa|identifica)\s+(?:el\s+)?ur[eé]ter|ur[eé]ter\s+(?:no\s+)?visible)/iu
                    );

                    if (fraseUreter) {
                        organosVisuales[indice].dudasTranscripcion.push(
                            fraseUreter[0]
                        );
                    }
                }
            }

            const item = {
                severidad: 'media',
                tipo: obs.tipo || 'observacion_generador',
                zona: indice !== -1 ? organosVisuales[indice].organo : 'Informe',
                dictado: '',
                informe: obs.contexto || '',
                detalle: obs.texto || 'Punto marcado por el generador para revisión.'
            };

            items.push(item);

            if (indice === -1) return;

            organosVisuales[indice].alertas.push({
                severidad: 'media',
                tipo: 'Generador · ' + (obs.tipo || 'revisar'),
                detalle: obs.texto || 'Punto marcado por el generador para revisión.',
                informe: obs.contexto || ''
            });
        });

        if (
            window.VetmindRevision
            && typeof window.VetmindRevision.aplicar === 'function'
            && organosVisuales.length
        ) {
            window.VetmindRevision.aplicar({
                organos: organosVisuales
            });
        }

        if (items.length === 0) {
            setEstado('✓ Revisión IA · Sin observaciones', 'vm-revision-status-ok', true);
            $modalBody.html('<div class="alert alert-success mb-0">No se detectaron observaciones en la revisión.</div>');
            return;
        }

        setEstado(
            '⚠ Revisión IA · ' + items.length + ' observación' + (items.length === 1 ? '' : 'es'),
            'vm-revision-status-warning',
            false
        );

        let filas = '';

        items.forEach(function (it, idx) {
            const sev = (it.severidad || 'media').toLowerCase();
            const claseSeveridad = sev === 'alta'
                ? 'vm-revision-modal-badge-alta'
                : (sev === 'media'
                    ? 'vm-revision-modal-badge-media'
                    : 'vm-revision-modal-badge-baja');

            let campos = '';

            if (it.dictado) {
                campos += '<div class="vm-revision-modal-campo">'
                    + '<strong>Dictado</strong>'
                    + '<span>' + esc(it.dictado) + '</span>'
                    + '</div>';
            }

            if (it.informe) {
                campos += '<div class="vm-revision-modal-campo">'
                    + '<strong>Informe</strong>'
                    + '<span>' + esc(it.informe) + '</span>'
                    + '</div>';
            }

            if (it.detalle) {
                campos += '<div class="vm-revision-modal-revisar">'
                    + '<div class="vm-revision-modal-campo">'
                    + '<strong>Revisar</strong>'
                    + '<span>' + esc(it.detalle) + '</span>'
                    + '</div>'
                    + '</div>';
            }

            filas += '<article class="vm-revision-modal-item">'
                + '<button type="button" class="vm-revision-modal-item-header" data-revision-idx="' + idx + '" aria-expanded="false">'
                + '<span class="vm-revision-modal-badge ' + claseSeveridad + '">' + esc(sev) + '</span>'
                + '<span class="vm-revision-modal-zona">' + esc(it.zona || it.tipo || 'Informe') + '</span>'
                + '<span class="vm-revision-modal-tipo">' + esc(it.tipo || '') + '</span>'
                + '<span class="vm-revision-modal-caret" aria-hidden="true">⌄</span>'
                + '</button>'
                + '<div class="vm-revision-modal-item-body" data-revision-body="' + idx + '">'
                + campos
                + '</div>'
                + '</article>';
        });

        $modalBody.html(
            '<div class="vm-revision-modal-resumen">'
            + 'Se encontraron ' + items.length + ' punto' + (items.length === 1 ? '' : 's') + ' para revisar.'
            + '</div>'
            + filas
        );

        $modalBody
            .off('click.revisionAccordion', '.vm-revision-modal-item-header')
            .on('click.revisionAccordion', '.vm-revision-modal-item-header', function () {
                const idx = $(this).data('revision-idx');
                const $body = $modalBody.find('[data-revision-body="' + idx + '"]');
                const abierto = $(this).attr('aria-expanded') === 'true';

                $(this)
                    .attr('aria-expanded', abierto ? 'false' : 'true')
                    .toggleClass('is-open', !abierto);

                $body.toggleClass('is-open', !abierto);
            });
    })
    .fail(function () {
        setEstado('⚠ No se pudo conectar al revisor', 'vm-revision-status-error', false);
        $modalBody.html('<div class="alert alert-danger mb-0">No se pudo conectar al revisor.</div>');
    });
}

function limpiarContenidoInformeIA(html) {
    return (html || '')
        .replace(/<span[^>]*class=['"]vm-discrepancia['"][^>]*>(.*?)<\/span>/gi, '$1')
        .replace(/<span[^>]*style=['"]?color:(orange|blue);?['"]?[^>]*>(.*?)<\/span>/gi, '$2')
        .replace(/(?:<[^>]+>)?Observaciones del Asistente:?<\/?.*?>?(?:<br\s*\/?>)?[\s\S]*$/i, '')
        .replace(/<sup\b[^>]*class=['"]flag['"][^>]*>.*?<\/sup>/gi, '')
        .replace(/\s*\(\d+\)/g, '')
        .replace(/CONCLUSION:\s*((?:- .*?\.)(?:\s*- .*?\.)*)/i, function (match, contenido) {
            const lineas = contenido.split(/\s*-\s+/).filter(Boolean).map(l => '&nbsp;&nbsp;- ' + l.trim() + '<br>').join('');
            return 'CONCLUSION:<br>' + lineas;
        })
        .trim();
}

function prepararAudioRevision(audioFile) {
    if (window.__audioRevisionObjectUrl) {
        URL.revokeObjectURL(window.__audioRevisionObjectUrl);
        window.__audioRevisionObjectUrl = null;
    }

    if (audioFile) {
        window.__audioRevisionObjectUrl = URL.createObjectURL(audioFile);
        window.__audioRevisionSrc = window.__audioRevisionObjectUrl;
        return;
    }

    window.__audioRevisionSrc = ($('#audioPlayback').attr('src') || '').trim();
}

function restaurarAudioRevisionDesdeBorrador() {
    let audioTmp = ($('#audio_tmp').val() || '')
        .trim()
        .replace(/\\/g, '/')
        .replace(/^\/+/, '');

    if (!audioTmp) {
        return false;
    }

    const prefix = 'uploads/tmp/audio/';

    if (audioTmp.indexOf(prefix) !== 0) {
        return false;
    }

    const filename = audioTmp.split('/').pop();

    if (!filename) {
        return false;
    }

    /*
     * audio_tmp viene como:
     *
     * uploads/tmp/audio/archivo.wav
     *
     * Como estamos dentro de /admin, necesitamos convertirlo
     * a una URL absoluta desde la raíz del sitio.
     */
    const src = '/' + audioTmp;

    $('#bloque-audio')
        .data('audioTmp', audioTmp)
        .data('audioFilename', filename);

    $('#audioPlayback')
        .attr('src', src)
        .show();

    window.__audioRevisionSrc = src;

    inicializarAudioRevision();

    return true;
}

function inicializarAudioRevision() {
    const src = (window.__audioRevisionSrc || '').trim();
    const audio = document.getElementById('revision_audio');
    const $barra = $('#revision_audio_barra');
    const $play = $('#revision_audio_play');

    if (!audio || !src) {
        $barra.removeClass('is-visible');
        return;
    }

    /*
     * No confiamos exclusivamente en audio.paused para decidir
     * qué quiso hacer el usuario.
     *
     * play() es asíncrono y puede existir una pequeña ventana
     * donde audio.paused todavía no representa la intención
     * del último clic.
     */
    let reproduccionDeseada = false;
    let operacionReproduccion = 0;

    const formato = function (seg) {
        if (!isFinite(seg)) seg = 0;

        const m = Math.floor(seg / 60);
        const s = Math.floor(seg % 60);

        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    };

    const iconoPlay =
        '<svg viewBox="0 0 24 24" aria-hidden="true">' +
            '<polygon points="9,7 18,12 9,17"></polygon>' +
        '</svg>';

    const iconoPause =
        '<svg viewBox="0 0 24 24" aria-hidden="true">' +
            '<rect x="8" y="7" width="3" height="10" rx="1"></rect>' +
            '<rect x="13" y="7" width="3" height="10" rx="1"></rect>' +
        '</svg>';

    let iconoMostrado = null;

    const actualizar = function () {
        const dur = isFinite(audio.duration)
            ? audio.duration
            : 0;

        const actual = isFinite(audio.currentTime)
            ? audio.currentTime
            : 0;

        $('#revision_audio_seek').val(
            dur > 0 ? (actual / dur) * 100 : 0
        );

        $('#revision_audio_tiempo').text(
            formato(actual) + ' / ' + formato(dur)
        );

        const mostrarPause =
            reproduccionDeseada && !audio.ended;

        const nuevoIcono = mostrarPause
            ? 'pause'
            : 'play';

        /*
        * Solo reemplazamos el SVG cuando cambia
        * realmente el estado del botón.
        */
        if (iconoMostrado !== nuevoIcono) {
            $play.html(
                mostrarPause ? iconoPause : iconoPlay
            );

            iconoMostrado = nuevoIcono;
        }

        $play
            .toggleClass('is-pause', mostrarPause)
            .attr(
                'title',
                mostrarPause ? 'Pausar' : 'Reproducir'
            )
            .attr(
                'aria-label',
                mostrarPause ? 'Pausar' : 'Reproducir'
            );
    };

    const marcarListo = function () {
        $play
            .prop('disabled', false)
            .removeClass('is-loading');

        actualizar();
    };

    const marcarCargando = function () {
        $play
            .prop('disabled', true)
            .addClass('is-loading')
            .attr('title', 'Cargando audio...')
            .attr('aria-label', 'Cargando audio...');
    };

    $play
        .off('.revisionAudio')
        .on('click.revisionAudio', function (e) {
            e.preventDefault();
            e.stopPropagation();

            reproduccionDeseada = !reproduccionDeseada;

            const miOperacion = ++operacionReproduccion;

            actualizar();

            if (!reproduccionDeseada) {
                audio.pause();
                actualizar();
                return;
            }

            if (
                audio.ended ||
                (
                    isFinite(audio.duration) &&
                    audio.duration > 0 &&
                    audio.currentTime >= audio.duration
                )
            ) {
                audio.currentTime = 0;
            }

            let promesaPlay;

            try {
                promesaPlay = audio.play();
            } catch (error) {
                reproduccionDeseada = false;
                console.error('No se pudo reproducir revision_audio:', error);
                actualizar();
                return;
            }

            Promise.resolve(promesaPlay)
                .then(function () {
                    if (
                        miOperacion !== operacionReproduccion ||
                        !reproduccionDeseada
                    ) {
                        audio.pause();
                        actualizar();
                        return;
                    }

                    actualizar();
                })
                .catch(function (error) {
                    if (miOperacion === operacionReproduccion) {
                        reproduccionDeseada = false;
                    }

                    console.error('No se pudo reproducir revision_audio:', error);
                    actualizar();
                });
        });

    $('#revision_audio_back')
        .off('.revisionAudio')
        .on('click.revisionAudio', function () {
            audio.currentTime =
                Math.max(
                    0,
                    audio.currentTime - 5
                );

            actualizar();
        });

    $('#revision_audio_forward')
        .off('.revisionAudio')
        .on('click.revisionAudio', function () {
            if (!isFinite(audio.duration)) {
                return;
            }

            audio.currentTime =
                Math.min(
                    audio.duration,
                    audio.currentTime + 5
                );

            actualizar();
        });

    $('#revision_audio_seek')
        .off('.revisionAudio')
        .on('input.revisionAudio', function () {
            if (
                !isFinite(audio.duration) ||
                audio.duration <= 0
            ) {
                return;
            }

            audio.currentTime =
                audio.duration *
                (
                    parseFloat(this.value) /
                    100
                );

            actualizar();
        });

    $('#revision_audio_speed')
        .off('.revisionAudio')
        .on('change.revisionAudio', function () {
            audio.playbackRate =
                parseFloat(this.value) || 1;
        });

    $('#revision_audio_volume')
        .off('.revisionAudio')
        .on('input.revisionAudio', function () {
            audio.volume =
                Math.max(
                    0,
                    Math.min(
                        1,
                        parseFloat(this.value) / 100
                    )
                );

            audio.muted = false;

            $('#revision_audio_mute')
                .removeClass('is-muted')
                .attr('title', 'Silenciar')
                .attr('aria-label', 'Silenciar');
        });

    $('#revision_audio_mute')
        .off('.revisionAudio')
        .on('click.revisionAudio', function () {
            audio.muted = !audio.muted;

            $(this)
                .toggleClass(
                    'is-muted',
                    audio.muted
                )
                .attr(
                    'title',
                    audio.muted
                        ? 'Activar sonido'
                        : 'Silenciar'
                )
                .attr(
                    'aria-label',
                    audio.muted
                        ? 'Activar sonido'
                        : 'Silenciar'
                );
        });

    /*
     * Eventos propios del audio.
     *
     * No cambiamos reproduccionDeseada en pause,
     * porque un pause antiguo podría llegar después
     * de que el usuario ya haya solicitado otro play.
     */
    $(audio)
        .off('.revisionAudio')

        .on(
            'loadedmetadata.revisionAudio ' +
            'durationchange.revisionAudio ' +
            'timeupdate.revisionAudio ' +
            'ratechange.revisionAudio ' +
            'volumechange.revisionAudio',
            actualizar
        )

        .on(
            'loadeddata.revisionAudio ' +
            'canplay.revisionAudio',
            marcarListo
        )

        .on(
            'play.revisionAudio',
            function () {
                /*
                 * Si un play antiguo terminó después de que
                 * el usuario pidió pausa, lo detenemos.
                 */
                if (!reproduccionDeseada) {
                    audio.pause();
                    actualizar();
                    return;
                }

                actualizar();
            }
        )

        .on(
            'pause.revisionAudio',
            function () {
                actualizar();
            }
        )

        .on(
            'ended.revisionAudio',
            function () {
                reproduccionDeseada = false;
                operacionReproduccion++;

                actualizar();
            }
        )

        .on(
            'error.revisionAudio',
            function () {
                reproduccionDeseada = false;
                operacionReproduccion++;

                $play.prop('disabled', true);

                console.error(
                    'Error cargando revision_audio:',
                    audio.error
                );

                actualizar();
            }
        );

    /*
     * Estado inicial.
     */
    reproduccionDeseada = false;
    operacionReproduccion++;

    audio.pause();
    audio.preload = 'auto';

    marcarCargando();

    audio.src = src;
    audio.load();

    $barra.addClass('is-visible');

    if (
        audio.readyState >=
        HTMLMediaElement.HAVE_CURRENT_DATA
    ) {
        marcarListo();
    } else {
        actualizar();
    }
}

$(function () {
    restaurarAudioRevisionDesdeBorrador();
});

function aplicarZoomInforme(valor) {
    const zoom = parseFloat(valor) || 1;
    const editor = document.querySelector('#contenido_html_editor .ProseMirror');
    if (!editor) return;

    editor.style.zoom = String(zoom);
    editor.style.width = '';
}

$(document)
    .off('change.vmEditorZoom', '#contenido_html_zoom')
    .on('change.vmEditorZoom', '#contenido_html_zoom', function () {
        aplicarZoomInforme(this.value);
});

$(document).off('click.revisionIA', '#revision_ia_toggle').on('click.revisionIA', '#revision_ia_toggle', function () {
    if ($(this).prop('disabled')) return;
    $('#modalRevisionIA').modal('show');
});

$(document).off('click.revisionIAModal', '#revision_ia_modal_cerrar, #revision_ia_modal_cerrar_x')
    .on('click.revisionIAModal', '#revision_ia_modal_cerrar, #revision_ia_modal_cerrar_x', function () {
        $('#modalRevisionIA').modal('hide');
    });

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

    prepararAudioRevision(audioFile);

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

            const dictadoCompleto = (window.__ultimoDictadoIA || '').trim();
            const plantillaBase = $('#plantillaBase').val();
            const informeOriginal = respGPT.content;
            const informeLimpio = limpiarContenidoInformeIA(informeOriginal);

            const observacionesGenerador = Array.isArray(respGPT.observaciones)
                ? respGPT.observaciones
                : [];

            const organosOrigen = Array.isArray(respGPT.organos)
                ? respGPT.organos
                : [];

            audio_manual_setMode('manual');
            aplicarContenidoInforme(informeLimpio);

            setTimeout(function () {
                if (
                    window.VetmindRevision
                    && typeof window.VetmindRevision.aplicar === 'function'
                    && organosOrigen.length
                ) {
                    window.VetmindRevision.aplicar({
                        organos: organosOrigen.map(function (organo) {
                            return {
                                organo: organo.organo,
                                atributos: Array.isArray(organo.atributos) ? organo.atributos : [],
                                alertas: []
                            };
                        })
                    });
                }
            }, 0);

            $('#revision_ia_bloque').show();
            $('#revision_ia_leyenda').show();
            $('#revision_ia_estado').text('Revisión IA · Revisando…');
            $('#revision_ia_toggle')
                .removeClass('vm-revision-status-warning vm-revision-status-ok vm-revision-status-error')
                .addClass('vm-revision-status-pending')
                .prop('disabled', true);
            $('#revision_ia_modal_body').empty();

            inicializarAudioRevision();

            setTimeout(function () {
                ejecutarRevisor(
                    dictadoCompleto,
                    informeOriginal,
                    plantillaBase,
                    observacionesGenerador,
                    organosOrigen
                );
            }, 100);
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