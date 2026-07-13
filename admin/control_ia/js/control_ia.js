//admin/control_ia/js/control_ia.js
(function () {
    const URL = 'control_ia/updControlIa.php';
    const PERMS = window.CONTROL_IA_PERMS || { eliminar: false };

    let detalleData = { informe: null, revision: null, transcripcion: null };
    let modoActual = 'agrupado';
    let dtInstance = null;
    let listadoRequestId = 0;

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }
    function fmtUsd(v) {
        return '$' + parseFloat(v || 0).toFixed(6);
    }
    function fmtNum(v) {
        return Number(v || 0).toLocaleString('es-CL');
    }

    function iaTipoLabel(tipo) {
        if (tipo === 'informe') return 'Informe';
        if (tipo === 'revision') return 'Revisión';
        return tipo || 'Operación';
    }

    function iaProviderLabel(provider) {
        if (!provider) return 'Sin proveedor';
        return String(provider).toUpperCase();
    }

    function pintarDesgloseIa(rows) {
        let html = '';

        (rows || []).forEach(function (r) {
            html += '<div class="ia-provider-card">'
                + '<div class="ia-provider-head">'
                    + '<div class="ia-provider-name">'
                        + '<i class="fas fa-robot"></i>' + esc(iaProviderLabel(r.provider))
                        + '<small>' + esc(r.model || 'Sin modelo') + '</small>'
                    + '</div>'

                    + '<div class="ia-provider-badges">'
                        + '<span class="ia-provider-type">' + esc(iaTipoLabel(r.tipo)) + '</span>'
                        + '<span class="ia-provider-qty">N: ' + fmtNum(r.cantidad) + '</span>'
                    + '</div>'
                + '</div>'

                + '<div class="ia-provider-metrics">'
                    + '<span class="metric-tokens"><small>Tokens</small><strong>' + fmtNum(r.tokens) + '</strong></span>'
                    + '<span class="metric-cost"><small>Costo</small><strong>' + fmtUsd(r.costo) + '</strong></span>'
                + '</div>'
                + '</div>';
        });

        if (html === '') {
            html = '<div class="text-muted small text-center py-2" style="grid-column:1/-1;">Sin consumo IA en este rango.</div>';
        }

        $('#m_ia_desglose').html(html);
    }

    function pintarDesgloseStt(rows) {
        let html = '';

        (rows || []).forEach(function (r) {
            const posicion = String(r.posicion || '').toUpperCase();
            const esA = posicion === 'A';

            const rolTexto = esA ? 'Motor A · Principal' : 'Motor B · Comparación';
            const rolClase = esA ? 'stt-role-a' : 'stt-role-b';

            html += '<div class="stt-provider-card">'
                + '<div class="stt-provider-head">'
                    + '<div class="stt-provider-name">'
                        + '<i class="fas fa-microphone-alt"></i>' + esc((r.motor || 'Sin motor').toUpperCase())
                        + '<small>' + esc(rolTexto) + '</small>'
                    + '</div>'

                    + '<div class="stt-provider-badges">'
                        + '<span class="stt-provider-role ' + rolClase + '">' + esc(posicion || '-') + '</span>'
                        + '<span class="stt-provider-qty">N: ' + fmtNum(r.cantidad) + '</span>'
                    + '</div>'
                + '</div>'

                + '<div class="stt-provider-metrics">'
                    + '<span class="metric-minutes"><small>Minutos</small><strong>' + fmtNum(r.minutos) + '</strong></span>'
                    + '<span class="metric-cost"><small>Costo</small><strong>' + fmtUsd(r.costo) + '</strong></span>'
                + '</div>'
                + '</div>';
        });

        if (html === '') {
            html = '<div class="text-muted small text-center py-2" style="grid-column:1/-1;">Sin transcripciones en este rango.</div>';
        }

        $('#m_stt_desglose').html(html);
    }

    // ─────────────── LISTADO ───────────────
    function destruirDT() {
        const $tabla = $('#tablaControlIa');

        if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
            $tabla.DataTable().clear().destroy();
        }

        dtInstance = null;
    }

    function initDT() {
        const $tabla = $('#tablaControlIa');

        if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
            $tabla.DataTable().clear().destroy();
        }

        dtInstance = $tabla.DataTable({
            order: [],
            retrieve: false,
            destroy: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                emptyTable: 'Sin registros.',
                zeroRecords: 'Sin registros encontrados.'
            }
        });
    }

    function cargarListado(modo) {
        const requestId = ++listadoRequestId;

        destruirDT();

        $('#tablaControlIaHead').html('');
        $('#tablaControlIaBody').html('<tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-info"></div></td></tr>');

        $.post(URL, { action: 'listado', modo: modo, rango: rangoActual }, null, 'json')
            .done(function (resp) {
                if (requestId !== listadoRequestId) {
                    return;
                }

                if (!resp || resp.status !== 'success') {
                    $('#tablaControlIaBody').html('<tr><td colspan="9" class="text-center text-muted py-4">Error al cargar.</td></tr>');
                    return;
                }

                if (resp.modo === 'separado') {
                    pintarSeparado(resp.data || []);
                } else {
                    pintarAgrupado(resp.data || [], resp.sueltos || []);
                }

                initDT();

                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            })
            .fail(function () {
                if (requestId !== listadoRequestId) {
                    return;
                }

                $('#tablaControlIaBody').html('<tr><td colspan="9" class="text-center text-muted py-4">No se pudo conectar.</td></tr>');
            });
    }

    function accionesGrupo(g) {
        const cert = g.certificado_id || '';
        const flujo = g.flujo_id || '';

        let btns = '<button class="btn btn-sm btn-outline-info btn-ver-detalle" title="Ver"'
            + ' data-certificado="' + esc(cert) + '"'
            + ' data-flujo="' + esc(flujo) + '">'
            + '<i class="fas fa-eye"></i></button> ';

        if (PERMS.eliminar) {
            btns += '<button class="btn btn-sm btn-outline-danger btn-eliminar-grupo" title="Eliminar"'
                + ' data-certificado="' + esc(cert) + '"'
                + ' data-flujo="' + esc(flujo) + '">'
                + '<i class="fas fa-trash"></i></button>';
        }

        return btns;
    }

    function accionesSuelto(grupo, id) {
        let btns = '<button class="btn btn-sm btn-outline-info btn-ver-suelto" title="Ver" data-grupo="' + esc(grupo) + '" data-id="' + esc(id) + '"><i class="fas fa-eye"></i></button> ';
        if (PERMS.eliminar) {
            btns += '<button class="btn btn-sm btn-outline-danger btn-eliminar-suelto" title="Eliminar" data-grupo="' + esc(grupo) + '" data-id="' + esc(id) + '"><i class="fas fa-trash"></i></button>';
        }
        return btns;
    }

    function labelGrupo(g) {
        if (g.certificado_id) {
            return '<strong>#' + esc(g.certificado_id) + '</strong>';
        }

        if (g.flujo_id) {
            const corto = String(g.flujo_id).slice(-8);
            return '<span class="badge-tipo badge-informe">Flujo</span> '
                + '<small class="text-muted" style="font-family:monospace">' + esc(corto) + '</small>';
        }

        return '<span class="text-muted">—</span>';
    }

    function badge(activo, texto, clase) {
        return activo
            ? '<span class="badge-tipo ' + clase + '">' + texto + '</span>'
            : '<span class="badge-tipo badge-off">' + texto + '</span>';
    }

    // ---------- AGRUPADO ----------
    function pintarAgrupado(grupos, sueltos) {
        const head = '<tr>'
            + '<th>N</th><th>Certificado</th><th>Contenido</th><th>Plantilla</th>'
            + '<th>Proveedores</th><th>Tokens</th><th>Costo (USD)</th><th>Fecha</th><th>Acciones</th>'
            + '</tr>';

        $('#tablaControlIaHead').html(head);

        const items = [];

        (grupos || []).forEach(function (g) {
            items.push({
                tipo_fila: 'grupo',
                created_at: g.created_at || '',
                data: g
            });
        });

        (sueltos || []).forEach(function (s) {
            items.push({
                tipo_fila: 'suelto',
                created_at: s.created_at || '',
                data: s
            });
        });

        items.sort(function (a, b) {
            return String(b.created_at || '').localeCompare(String(a.created_at || ''));
        });

        let filas = '';
        let i = 1;

        items.forEach(function (item) {
            if (item.tipo_fila === 'grupo') {
                const g = item.data;

                const contenido =
                    badge(g.tiene_transcripcion, 'Transcripción', 'badge-trans') + ' ' +
                    badge(g.tiene_informe, 'Informe', 'badge-informe') + ' ' +
                    badge(g.tiene_revision, 'Revisión', 'badge-revision');

                const provs = (g.providers || []).join(', ') || '-';

                filas += '<tr>'
                    + '<td>' + i + '</td>'
                    + '<td>' + labelGrupo(g) + '</td>'
                    + '<td>' + contenido + '</td>'
                    + '<td>' + (g.plantilla_id != null ? esc(g.plantilla_id) : '-') + '</td>'
                    + '<td>' + esc(provs) + '</td>'
                    + '<td>' + fmtNum(g.tokens) + '</td>'
                    + '<td>' + fmtUsd(g.costo) + '</td>'
                    + '<td>' + esc(g.created_at) + '</td>'
                    + '<td align="center">' + accionesGrupo(g) + '</td>'
                    + '</tr>';

                i++;
                return;
            }

            const s = item.data;

            const tipoBadge = s.grupo === 'transcripcion'
                ? '<span class="badge-tipo badge-trans">transcripción</span>'
                : '<span class="badge-tipo ' + (s.tipo === 'informe' ? 'badge-informe' : 'badge-revision') + '">' + esc(s.tipo) + '</span>';

            filas += '<tr>'
                + '<td>' + i + '</td>'
                + '<td><span class="text-muted">—</span></td>'
                + '<td><span class="badge-tipo badge-sininf">Sin informe</span> ' + tipoBadge + '</td>'
                + '<td>' + (s.plantilla_id != null ? esc(s.plantilla_id) : '-') + '</td>'
                + '<td>' + esc(s.detalle || '-') + '</td>'
                + '<td>' + fmtNum(s.tokens) + '</td>'
                + '<td>' + fmtUsd(s.costo) + '</td>'
                + '<td>' + esc(s.created_at) + '</td>'
                + '<td align="center">' + accionesSuelto(s.grupo, s.op_id) + '</td>'
                + '</tr>';

            i++;
        });

        if (filas === '') {
            $('#tablaControlIaBody').html('');
            return;
        }

        $('#tablaControlIaBody').html(filas);
    }

    // ---------- SEPARADO ----------
    function pintarSeparado(filasData) {
        const head = '<tr>'
            + '<th>N</th><th>Tipo</th><th>Certificado</th><th>Detalle</th>'
            + '<th>Plantilla</th><th>Tokens</th><th>Costo (USD)</th><th>Fecha</th><th>Acciones</th>'
            + '</tr>';
        $('#tablaControlIaHead').html(head);

        let filas = '';
        let i = 1;

        filasData.forEach(function (f) {
            let tipoBadge;
            if (f.tipo === 'transcripcion') tipoBadge = '<span class="badge-tipo badge-trans">transcripción</span>';
            else if (f.tipo === 'informe')  tipoBadge = '<span class="badge-tipo badge-informe">informe</span>';
            else tipoBadge = '<span class="badge-tipo badge-revision">revisión</span>';

            const cert = f.certificado_id != null
                ? '#' + esc(f.certificado_id)
                : '<span class="badge-tipo badge-sininf">sin informe</span>';

            filas += '<tr>'
                + '<td>' + i + '</td>'
                + '<td>' + tipoBadge + '</td>'
                + '<td>' + cert + '</td>'
                + '<td>' + esc(f.detalle || '-') + '</td>'
                + '<td>' + (f.plantilla_id != null ? esc(f.plantilla_id) : '-') + '</td>'
                + '<td>' + fmtNum(f.tokens) + '</td>'
                + '<td>' + fmtUsd(f.costo) + '</td>'
                + '<td>' + esc(f.created_at) + '</td>'
                + '<td align="center">' + accionesSuelto(f.grupo, f.op_id) + '</td>'
                + '</tr>';
            i++;
        });

        if (filas === '') {
            $('#tablaControlIaBody').html('');
            return;
        }

        $('#tablaControlIaBody').html(filas);
    }

    // ─────────────── DETALLE ───────────────
    function pintarDetalle(tipo) {
        const d = detalleData[tipo];
        const $cont = $('#detalleContenido');
        const $vacio = $('#detalleVacio');

        if (!d) {
            $cont.hide();
            $vacio.show();
            return;
        }

        $vacio.hide();
        $cont.show();

        if (tipo === 'transcripcion') {
            pintarDetalleTranscripcion(d);
            return;
        }

        $('#d_provider').text(d.provider || '-');
        $('#d_model').text(d.model || '-');
        $('#d_plantilla').text(d.plantilla_id || '-');
        $('#d_tokens').text(d.total_tokens || '0');
        $('#d_cost').text(fmtUsd(d.cost_usd));
        $('#d_rid').text(d.rid || '-');
        $('#d_datetime').text(d.datetime_ia || '-');
        $('#d_created').text(d.created_at || '-');

        $('#d_final_render').html(d.content_final || '');
        $('#d_final_raw').text(d.content_final || '');

        let inputTxt = d.input_json || '';
        try {
            inputTxt = JSON.stringify(JSON.parse(d.input_json), null, 2);
        } catch (e) {}

        $('#d_input').text(inputTxt);
        $('#d_prompt').text(d.prompt_text || '');
        $('#d_system').text(d.system_text || '');

        let transcripcion = '';
        try {
            const inp = JSON.parse(d.input_json);
            transcripcion = inp.texto || inp.dictado || '';
        } catch (e) {}

        $('#d_det_transcripcion').text(transcripcion);
        $('#d_det_resultado').html(d.content_final || '');
    }

    function pintarDetalleTranscripcion(d) {
        const motores = [d.motor_a, d.motor_b].filter(Boolean).join(' + ') || '-';
        const textoA = d.texto_a || '';
        const textoB = d.texto_b || '';
        const textoDoble = d.texto_doble || '';
        const textoPrincipal = textoDoble || textoA || textoB || '';

        $('#d_provider').text(motores);
        $('#d_model').text('Transcripción');
        $('#d_plantilla').text(d.certificado_id ? '#' + d.certificado_id : '-');
        $('#d_tokens').text('0');
        $('#d_cost').text(fmtUsd(d.cost_total));
        $('#d_rid').text('STT-' + (d.id || '-'));
        $('#d_datetime').text('-');
        $('#d_created').text(d.created_at || '-');

        let discrepancias = d.discrepancias_json || '';
        try {
            discrepancias = JSON.stringify(JSON.parse(d.discrepancias_json), null, 2);
        } catch (e) {}

        const inputResumen = {
            audio_tmp: d.audio_tmp || '',
            certificado_id: d.certificado_id || null,
            motor_a: d.motor_a || '',
            motor_b: d.motor_b || '',
            duracion_seg_a: d.duracion_seg_a || 0,
            duracion_seg_b: d.duracion_seg_b || 0,
            cost_a: d.cost_a || 0,
            cost_b: d.cost_b || 0,
            cost_total: d.cost_total || 0,
            discrepancias_json: discrepancias
        };

        $('#d_input').text(JSON.stringify(inputResumen, null, 2));
        $('#d_prompt').text('');
        $('#d_system').text('');

        $('#d_final_render').html(
            '<pre style="white-space:pre-wrap;font-size:13px;background:#f8fafc;padding:10px;border-radius:6px">' +
            esc(textoPrincipal) +
            '</pre>'
        );
        $('#d_final_raw').text(textoPrincipal);

        $('#d_det_transcripcion').text(textoPrincipal);

        let html = '';

        html += '<div class="mb-3">';
        html += '<strong>Motor A: ' + esc(d.motor_a || '-') + '</strong>';
        html += '<div class="text-muted small mb-1">Duración: ' + esc(d.duracion_seg_a || '0') + ' seg · Costo: ' + fmtUsd(d.cost_a) + '</div>';
        html += '<pre style="white-space:pre-wrap;font-size:12px;background:#f8fafc;padding:8px;border-radius:6px">' + esc(textoA || '-') + '</pre>';
        html += '</div>';

        html += '<div class="mb-3">';
        html += '<strong>Motor B: ' + esc(d.motor_b || '-') + '</strong>';
        html += '<div class="text-muted small mb-1">Duración: ' + esc(d.duracion_seg_b || '0') + ' seg · Costo: ' + fmtUsd(d.cost_b) + '</div>';
        html += '<pre style="white-space:pre-wrap;font-size:12px;background:#f8fafc;padding:8px;border-radius:6px">' + esc(textoB || '-') + '</pre>';
        html += '</div>';

        html += '<div class="mb-3">';
        html += '<strong>Texto doble / resultado</strong>';
        html += '<pre style="white-space:pre-wrap;font-size:12px;background:#ecfdf5;padding:8px;border-radius:6px">' + esc(textoDoble || '-') + '</pre>';
        html += '</div>';

        if (discrepancias) {
            html += '<div>';
            html += '<strong>Discrepancias</strong>';
            html += '<pre style="white-space:pre-wrap;font-size:11px;background:#fffbeb;padding:8px;border-radius:6px">' + esc(discrepancias) + '</pre>';
            html += '</div>';
        }

        $('#d_det_resultado').html(html);
    }

    function actualizarSelector() {
        const $sel = $('#detalleSelector');
        $sel.empty();

        if (detalleData.informe) {
            $sel.append('<option value="informe">Informe</option>');
        }

        if (detalleData.revision) {
            $sel.append('<option value="revision">Revisión</option>');
        }

        if (detalleData.transcripcion) {
            $sel.append('<option value="transcripcion">Transcripción</option>');
        }

        const primero = detalleData.informe
            ? 'informe'
            : (detalleData.revision ? 'revision' : (detalleData.transcripcion ? 'transcripcion' : ''));

        if (primero) {
            $sel.val(primero);
            pintarDetalle(primero);
        } else {
            pintarDetalle('informe');
        }
    }

    function abrirDetalle(payload) {
        $('#detalleLoading').show();
        $('#detalleContenido').hide();
        $('#detalleVacio').hide();

        const el = document.getElementById('modalDetalleIa');
        bootstrap.Modal.getOrCreateInstance(el).show();

        $.post(URL, payload, null, 'json')
            .done(function (resp) {
                $('#detalleLoading').hide();
                if (!resp || resp.status !== 'success') {
                    $('#detalleVacio').text(resp && resp.message ? resp.message : 'Error al cargar.').show();
                    return;
                }
                detalleData = resp.data || { informe: null, revision: null };
                actualizarSelector();
            })
            .fail(function () {
                $('#detalleLoading').hide();
                $('#detalleVacio').text('No se pudo conectar.').show();
            });
    }

    $(document).on('click', '.btn-ver-detalle', function (e) {
        e.preventDefault();

        abrirDetalle({
            action: 'detalle_grupo',
            certificado_id: $(this).data('certificado') || '',
            flujo_id: $(this).data('flujo') || ''
        });
    });

    $(document).on('click', '.btn-ver-suelto', function (e) {
        e.preventDefault();
        const grupo = $(this).data('grupo');
        const id = $(this).data('id');
        if (grupo === 'transcripcion') {
            abrirDetalle({ action: 'detalle_transcripcion', id: id });
        } else {
            abrirDetalle({ action: 'detalle_suelto', id: id });
        }
    });

    $(document).on('change', '#detalleSelector', function () {
        pintarDetalle($(this).val());
    });

    // Copiar transcripción + resultado.
    $(document).on('click', '#btnCopiarDetalles', function () {
        const trans = $('#d_det_transcripcion').text() || '';
        const res = $('#d_det_resultado').text() || '';
        const texto = '===== TRANSCRIPCIÓN (DICTADO) =====\n' + trans +
            '\n\n===== RESULTADO (INFORME) =====\n' + res + '\n';
        navigator.clipboard.writeText(texto).then(function () {
            Swal.fire({ icon: 'success', title: 'Copiado', timer: 1200, showConfirmButton: false });
        }).catch(function () {
            Swal.fire('Error', 'No se pudo copiar.', 'error');
        });
    });

    // ─────────────── ELIMINAR ───────────────
    function recargar() { cargarListado(modoActual); cargarMetricas(rangoActual); }

    $(document).on('click', '.btn-eliminar-grupo', function (e) {
        e.preventDefault();

        const cert = $(this).data('certificado') || '';
        const flujo = $(this).data('flujo') || '';

        const texto = cert
            ? '¿Eliminar todos los registros IA del certificado #' + cert + '?'
            : '¿Eliminar todos los registros IA de este flujo?';

        confirmarEliminar(texto, {
            action: 'eliminar_grupo',
            certificado_id: cert,
            flujo_id: flujo
        });
    });

    $(document).on('click', '.btn-eliminar-suelto', function (e) {
        e.preventDefault();
        const grupo = $(this).data('grupo');
        const id = $(this).data('id');
        const act = grupo === 'transcripcion' ? 'eliminar_transcripcion' : 'eliminar_suelto';
        confirmarEliminar('¿Eliminar este registro suelto?', { action: act, id: id });
    });

    function confirmarEliminar(texto, payload) {
        Swal.fire({
            title: '¿Estás seguro?', text: texto, icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(URL, payload, null, 'json')
                .done(function (resp) {
                    if (resp && resp.status === 'success') {
                        Swal.fire('Eliminado', resp.message, 'success');
                        recargar();
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'Error.', 'error');
                    }
                })
                .fail(function () { Swal.fire('Error', 'No se pudo conectar.', 'error'); });
        });
    }

    // ─────────────── MÉTRICAS ───────────────
    let rangoActual = 'todo';

    function cargarMetricas(rango) {
        $.post(URL, { action: 'metricas', rango: rango }, null, 'json')
            .done(function (resp) {
                if (!resp || resp.status !== 'success') return;

                const d = resp.data;

                const costoIa = parseFloat(d.costo_ia || 0);
                const costoAudio = parseFloat(d.costo_transcripcion || 0);
                const costoTotal = parseFloat(d.costo_total || 0);

                const pctIa = costoTotal > 0 ? ((costoIa / costoTotal) * 100).toFixed(1) : '0.0';
                const pctAudio = costoTotal > 0 ? ((costoAudio / costoTotal) * 100).toFixed(1) : '0.0';

                $('#m_informes').text(fmtNum(d.informes_generados));
                $('#m_consultas').text(fmtNum(d.consultas_informe));
                $('#m_revisiones').text(fmtNum(d.revisiones));
                $('#m_transcripciones').text(fmtNum(d.transcripciones));

                $('#m_tokens').text(fmtNum(d.tokens));
                $('#m_costo_ia').text(fmtUsd(costoIa));

                $('#m_minutos_trans').text(fmtNum(d.minutos_transcripcion));
                $('#m_costo_trans').text(fmtUsd(costoAudio));

                $('#m_costo_total').text(fmtUsd(costoTotal));
                $('#m_total_ia').text(fmtUsd(costoIa));
                $('#m_total_audio').text(fmtUsd(costoAudio));
                $('#m_total_ia_pct').text(pctIa + '%');
                $('#m_total_audio_pct').text(pctAudio + '%');

                pintarDesgloseIa(d.ia_desglose || []);
                pintarDesgloseStt(d.stt_desglose || []);
            });
    }

    $(document).on('click', '#metricasRango .btn', function () {
        $('#metricasRango .btn').removeClass('active btn-info').addClass('btn-outline-info');
        $(this).removeClass('btn-outline-info').addClass('active btn-info');

        rangoActual = $(this).data('rango');

        cargarMetricas(rangoActual);
        cargarListado(modoActual);
    });

    // ─────────────── PRECIOS ───────────────
    let precioTipoActual = 'ia';

    function precioHoy() {
        return new Date().toISOString().slice(0, 10);
    }

    function precioResetForm() {
        $('#precio_id').val('');
        $('#precio_model').val('');
        $('#precio_in').val('');
        $('#precio_out').val('');
        $('#precio_min').val('');
        $('#precio_vigente').val(precioHoy());
        $('#precio_activo').val('1');
        $('#precioForm').hide();
    }

    function precioConfigurarVista() {
        $('#preciosTipo .btn').removeClass('active btn-info').addClass('btn-outline-info');
        $('#preciosTipo .btn[data-tipo="' + precioTipoActual + '"]').removeClass('btn-outline-info').addClass('active btn-info');

        if (precioTipoActual === 'ia') {
            $('#precio_model_label').text('Modelo');
            $('#precio_model').attr('placeholder', 'gpt-5.4');

            $('.precio-ia-field').show();
            $('.precio-stt-field').hide();
        } else {
            $('#precio_model_label').text('Motor');
            $('#precio_model').attr('placeholder', 'deepgram');

            $('.precio-ia-field').hide();
            $('.precio-stt-field').show();
        }
    }

    function cargarPrecios() {
        precioConfigurarVista();
        precioResetForm();

        $('#preciosLoading').show();
        $('#preciosTbody').html('');

        const action = precioTipoActual === 'ia' ? 'precios_listar' : 'precios_stt_listar';

        $.post(URL, { action: action }, null, 'json')
            .done(function (resp) {
                $('#preciosLoading').hide();

                if (!resp || resp.status !== 'success') {
                    $('#preciosTbody').html('<tr><td colspan="7" class="text-center text-muted py-3">Error al cargar precios.</td></tr>');
                    return;
                }

                pintarPrecios(resp.data || []);
                if (typeof feather !== 'undefined') feather.replace();
            })
            .fail(function () {
                $('#preciosLoading').hide();
                $('#preciosTbody').html('<tr><td colspan="7" class="text-center text-muted py-3">No se pudo conectar.</td></tr>');
            });
    }

    function pintarPrecios(rows) {
        let head = '';
        let html = '';

        if (precioTipoActual === 'ia') {
            head = '<tr>'
                + '<th>Modelo</th>'
                + '<th width="110">In</th>'
                + '<th width="110">Out</th>'
                + '<th width="110">Vigente</th>'
                + '<th width="110">Hasta</th>'
                + '<th width="70">Activo</th>'
                + '<th width="110">Acciones</th>'
                + '</tr>';

            rows.forEach(function (r) {
                html += '<tr>'
                    + '<td>' + esc(r.model) + '</td>'
                    + '<td>' + fmtUsd(r.price_in) + '</td>'
                    + '<td>' + fmtUsd(r.price_out) + '</td>'
                    + '<td>' + esc(r.vigente_desde || '-') + '</td>'
                    + '<td>' + esc(r.vigente_hasta || '-') + '</td>'
                    + '<td>' + (parseInt(r.activo, 10) === 1 ? '<span class="badge-tipo badge-ok">Sí</span>' : '<span class="badge-tipo badge-off">No</span>') + '</td>'
                    + '<td align="center">'
                    + '<button type="button" class="btn btn-sm btn-outline-info btn-editar-precio"'
                    + ' data-tipo="ia"'
                    + ' data-id="' + esc(r.id) + '"'
                    + ' data-model="' + esc(r.model) + '"'
                    + ' data-in="' + esc(r.price_in) + '"'
                    + ' data-out="' + esc(r.price_out) + '"'
                    + ' data-vigente="' + esc(r.vigente_desde) + '"'
                    + ' data-activo="' + esc(r.activo) + '">'
                    + '<i class="fas fa-edit"></i></button> '
                    + (PERMS.eliminar ? '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-precio" data-tipo="ia" data-id="' + esc(r.id) + '"><i class="fas fa-trash"></i></button>' : '')
                    + '</td>'
                    + '</tr>';
            });
        } else {
            head = '<tr>'
                + '<th>Motor</th>'
                + '<th width="140">Precio minuto</th>'
                + '<th width="110">Vigente</th>'
                + '<th width="110">Hasta</th>'
                + '<th width="70">Activo</th>'
                + '<th width="110">Acciones</th>'
                + '</tr>';

            rows.forEach(function (r) {
                html += '<tr>'
                    + '<td>' + esc(r.motor) + '</td>'
                    + '<td>' + fmtUsd(r.price_min) + '</td>'
                    + '<td>' + esc(r.vigente_desde || '-') + '</td>'
                    + '<td>' + esc(r.vigente_hasta || '-') + '</td>'
                    + '<td>' + (parseInt(r.activo, 10) === 1 ? '<span class="badge-tipo badge-ok">Sí</span>' : '<span class="badge-tipo badge-off">No</span>') + '</td>'
                    + '<td align="center">'
                    + '<button type="button" class="btn btn-sm btn-outline-info btn-editar-precio"'
                    + ' data-tipo="stt"'
                    + ' data-id="' + esc(r.id) + '"'
                    + ' data-motor="' + esc(r.motor) + '"'
                    + ' data-min="' + esc(r.price_min) + '"'
                    + ' data-vigente="' + esc(r.vigente_desde) + '"'
                    + ' data-activo="' + esc(r.activo) + '">'
                    + '<i class="fas fa-edit"></i></button> '
                    + (PERMS.eliminar ? '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-precio" data-tipo="stt" data-id="' + esc(r.id) + '"><i class="fas fa-trash"></i></button>' : '')
                    + '</td>'
                    + '</tr>';
            });
        }

        if (html === '') {
            const cols = precioTipoActual === 'ia' ? 7 : 6;
            html = '<tr><td colspan="' + cols + '" class="text-center text-muted py-3">Sin precios registrados.</td></tr>';
        }

        $('#preciosThead').html(head);
        $('#preciosTbody').html(html);
    }

    $(document).on('click', '#btnVerPrecios', function () {
        const el = document.getElementById('modalPreciosIa');
        bootstrap.Modal.getOrCreateInstance(el).show();
        cargarPrecios();
    });

    $(document).on('click', '#preciosTipo .btn', function () {
        precioTipoActual = $(this).data('tipo');
        cargarPrecios();
    });

    $(document).on('click', '#btnNuevoPrecio', function () {
        precioResetForm();
        precioConfigurarVista();
        $('#precioForm').show();
        $('#precio_model').focus();
    });

    $(document).on('click', '#btnCancelarPrecio', function () {
        precioResetForm();
    });

    $(document).on('click', '.btn-editar-precio', function () {
        const tipo = $(this).data('tipo');
        precioTipoActual = tipo;

        precioConfigurarVista();

        $('#precio_id').val($(this).data('id'));
        $('#precio_vigente').val($(this).data('vigente'));
        $('#precio_activo').val(String($(this).data('activo')));

        if (tipo === 'ia') {
            $('#precio_model').val($(this).data('model'));
            $('#precio_in').val($(this).data('in'));
            $('#precio_out').val($(this).data('out'));
            $('#precio_min').val('');
        } else {
            $('#precio_model').val($(this).data('motor'));
            $('#precio_min').val($(this).data('min'));
            $('#precio_in').val('');
            $('#precio_out').val('');
        }

        $('#precioForm').show();
        $('#precio_model').focus();
    });

    $(document).on('click', '#btnGuardarPrecio', function () {
        const id = $('#precio_id').val();
        const nombre = $.trim($('#precio_model').val());
        const vigente = $('#precio_vigente').val();
        const activo = $('#precio_activo').val();

        if (nombre === '' || vigente === '') {
            Swal.fire('Faltan datos', 'Debes completar nombre y vigencia.', 'warning');
            return;
        }

        let payload;

        if (precioTipoActual === 'ia') {
            const priceIn = parseFloat($('#precio_in').val() || 0);
            const priceOut = parseFloat($('#precio_out').val() || 0);

            if (priceIn < 0 || priceOut < 0) {
                Swal.fire('Dato inválido', 'Los precios no pueden ser negativos.', 'warning');
                return;
            }

            payload = {
                action: id ? 'precio_modificar' : 'precio_ingresar',
                id: id,
                model: nombre,
                price_in: priceIn,
                price_out: priceOut,
                vigente_desde: vigente,
                activo: activo
            };
        } else {
            const priceMin = parseFloat($('#precio_min').val() || 0);

            if (priceMin < 0) {
                Swal.fire('Dato inválido', 'El precio por minuto no puede ser negativo.', 'warning');
                return;
            }

            payload = {
                action: id ? 'precio_stt_modificar' : 'precio_stt_ingresar',
                id: id,
                motor: nombre,
                price_min: priceMin,
                vigente_desde: vigente,
                activo: activo
            };
        }

        $.post(URL, payload, null, 'json')
            .done(function (resp) {
                if (resp && resp.status === 'success') {
                    Swal.fire({ icon: 'success', title: resp.message, timer: 1300, showConfirmButton: false });
                    cargarPrecios();
                } else {
                    Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo guardar.', 'error');
                }
            })
            .fail(function () {
                Swal.fire('Error', 'No se pudo conectar.', 'error');
            });
    });

    $(document).on('click', '.btn-eliminar-precio', function () {
        const id = $(this).data('id');
        const tipo = $(this).data('tipo');

        Swal.fire({
            title: '¿Eliminar precio?',
            text: 'Esta acción eliminará el precio seleccionado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const action = tipo === 'ia' ? 'precio_eliminar' : 'precio_stt_eliminar';

            $.post(URL, { action: action, id: id }, null, 'json')
                .done(function (resp) {
                    if (resp && resp.status === 'success') {
                        Swal.fire({ icon: 'success', title: resp.message, timer: 1300, showConfirmButton: false });
                        cargarPrecios();
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo eliminar.', 'error');
                    }
                })
                .fail(function () {
                    Swal.fire('Error', 'No se pudo conectar.', 'error');
                });
        });
    });

    // Limpia backdrops huérfanos.
    $(document).on('hidden.bs.modal', '#modalDetalleIa, #modalPreciosIa', function () {
        if ($('.modal.show').length === 0) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });
        }
    });

    // ─────────────── INIT ───────────────
    cargarMetricas('todo');
    cargarListado('agrupado');
})();