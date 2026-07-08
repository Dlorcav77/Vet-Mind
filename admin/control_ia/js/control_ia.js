//admin/control_ia/js/control_ia.js
(function () {
    const URL = 'control_ia/updControlIa.php';

    // Cache del último detalle cargado (para el selector informe/revisión).
    let detalleData = { informe: null, revision: null };

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function fmtUsd(v) {
        const n = parseFloat(v || 0);
        return '$' + n.toFixed(6);
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

        $('#d_provider').text(d.provider || '-');
        $('#d_model').text(d.model || '-');
        $('#d_plantilla').text(d.plantilla_id || '-');
        $('#d_tokens').text(d.total_tokens || '0');
        $('#d_cost').text(fmtUsd(d.cost_usd));
        $('#d_rid').text(d.rid || '-');
        $('#d_datetime').text(d.datetime_ia || '-');
        $('#d_created').text(d.created_at || '-');

        // Resultado: render + crudo.
        $('#d_final_render').html(d.content_final || '');
        $('#d_final_raw').text(d.content_final || '');

        // Input: pretty JSON si se puede.
        let inputTxt = d.input_json || '';
        try {
            inputTxt = JSON.stringify(JSON.parse(d.input_json), null, 2);
        } catch (e) { /* deja crudo */ }
        $('#d_input').text(inputTxt);

        $('#d_prompt').text(d.prompt_text || '');
        $('#d_system').text(d.system_text || '');

        // Pestaña Detalles: transcripción (dictado) + resultado.
        let transcripcion = '';
        try {
            const inp = JSON.parse(d.input_json);
            transcripcion = inp.texto || inp.dictado || '';
        } catch (e) {
            transcripcion = '';
        }
        $('#d_det_transcripcion').text(transcripcion);
        $('#d_det_resultado').html(d.content_final || '');
    }

    function actualizarSelector() {
        const $sel = $('#detalleSelector');
        $sel.empty();
        if (detalleData.informe) $sel.append('<option value="informe">Informe</option>');
        if (detalleData.revision) $sel.append('<option value="revision">Revisión</option>');

        // Selecciona el primero disponible.
        const primero = detalleData.informe ? 'informe' : (detalleData.revision ? 'revision' : '');
        if (primero) {
            $sel.val(primero);
            pintarDetalle(primero);
        } else {
            pintarDetalle('informe'); // mostrará vacío
        }
    }

    function abrirDetalle(payload) {
        $('#detalleLoading').show();
        $('#detalleContenido').hide();
        $('#detalleVacio').hide();

        const el = document.getElementById('modalDetalleIa');
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();

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

    // Ver grupo (certificado con informe + revisión).
    $(document).on('click', '.btn-ver-detalle', function (e) {
        e.preventDefault();
        const cert = $(this).data('certificado');
        abrirDetalle({ action: 'detalle_grupo', certificado_id: cert });
    });

    // Ver suelto (request sin certificado).
    $(document).on('click', '.btn-ver-suelto', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        abrirDetalle({ action: 'detalle_suelto', id: id });
    });

    // Cambio de selector informe/revisión.
    $(document).on('change', '#detalleSelector', function () {
        pintarDetalle($(this).val());
    });

    // ─────────────── ELIMINAR ───────────────
    function recargarTabla() {
        $('#content').load('control_ia/control_ia.php');
    }

    $(document).on('click', '.btn-eliminar-grupo', function (e) {
        e.preventDefault();
        const cert = $(this).data('certificado');
        confirmarEliminar('¿Eliminar todos los registros IA del certificado #' + cert + '?',
            { action: 'eliminar_grupo', certificado_id: cert });
    });

    $(document).on('click', '.btn-eliminar-suelto', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        confirmarEliminar('¿Eliminar este registro suelto?',
            { action: 'eliminar_suelto', id: id });
    });

    function confirmarEliminar(texto, payload) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: texto,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(URL, payload, null, 'json')
                .done(function (resp) {
                    if (resp && resp.status === 'success') {
                        Swal.fire('Eliminado', resp.message, 'success');
                        recargarTabla();
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'Error.', 'error');
                    }
                })
                .fail(function () {
                    Swal.fire('Error', 'No se pudo conectar.', 'error');
                });
        });
    }

    // ─────────────── PRECIOS ───────────────
    function cargarPrecios() {
        $('#preciosLoading').show();
        $('#preciosTbody').empty();
        $('#precioForm').hide();

        $.post(URL, { action: 'precios_listar' }, null, 'json')
            .done(function (resp) {
                $('#preciosLoading').hide();
                if (!resp || resp.status !== 'success') return;

                let filas = '';
                (resp.data || []).forEach(function (p) {
                    const activoBadge = p.activo == 1
                        ? '<span class="badge bg-success">Sí</span>'
                        : '<span class="badge bg-secondary">No</span>';
                    filas += '<tr>'
                        + '<td>' + esc(p.model) + '</td>'
                        + '<td>' + esc(p.price_in) + '</td>'
                        + '<td>' + esc(p.price_out) + '</td>'
                        + '<td>' + esc(p.vigente_desde) + '</td>'
                        + '<td>' + activoBadge + '</td>'
                        + '<td>'
                        + '<button class="btn btn-sm btn-outline-primary btn-edit-precio" '
                        +   'data-id="' + esc(p.id) + '" data-model="' + esc(p.model) + '" '
                        +   'data-in="' + esc(p.price_in) + '" data-out="' + esc(p.price_out) + '" '
                        +   'data-vigente="' + esc(p.vigente_desde) + '" data-activo="' + esc(p.activo) + '">'
                        +   '<i class="fas fa-edit"></i></button> '
                        + '<button class="btn btn-sm btn-outline-danger btn-del-precio" data-id="' + esc(p.id) + '">'
                        +   '<i class="fas fa-trash"></i></button>'
                        + '</td>'
                        + '</tr>';
                });
                $('#preciosTbody').html(filas);
                if (typeof feather !== 'undefined') feather.replace();
            })
            .fail(function () {
                $('#preciosLoading').hide();
            });
    }
    
    $(document).on('click', '#btnVerPrecios', function () {
        const el = document.getElementById('modalPreciosIa');
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
        cargarPrecios();
    });

    function limpiarForm() {
        $('#precio_id').val('');
        $('#precio_model').val('');
        $('#precio_in').val('');
        $('#precio_out').val('');
        $('#precio_vigente').val('');
        $('#precio_activo').val('1');
    }

    $(document).on('click', '#btnNuevoPrecio', function () {
        limpiarForm();
        $('#precioForm').show();
    });

    $(document).on('click', '#btnCancelarPrecio', function () {
        $('#precioForm').hide();
    });

    $(document).on('click', '.btn-edit-precio', function () {
        $('#precio_id').val($(this).data('id'));
        $('#precio_model').val($(this).data('model'));
        $('#precio_in').val($(this).data('in'));
        $('#precio_out').val($(this).data('out'));
        $('#precio_vigente').val($(this).data('vigente'));
        $('#precio_activo').val($(this).data('activo'));
        $('#precioForm').show();
    });

    $(document).on('click', '#btnGuardarPrecio', function () {
        const id = $('#precio_id').val();
        const payload = {
            action: id ? 'precio_modificar' : 'precio_ingresar',
            id: id,
            model: $('#precio_model').val(),
            price_in: $('#precio_in').val(),
            price_out: $('#precio_out').val(),
            vigente_desde: $('#precio_vigente').val(),
            activo: $('#precio_activo').val()
        };

        $.post(URL, payload, null, 'json')
            .done(function (resp) {
                if (resp && resp.status === 'success') {
                    Swal.fire('Éxito', resp.message, 'success');
                    cargarPrecios();
                } else {
                    Swal.fire('Error', resp && resp.message ? resp.message : 'Error.', 'error');
                }
            })
            .fail(function () {
                Swal.fire('Error', 'No se pudo conectar.', 'error');
            });
    });

    $(document).on('click', '.btn-del-precio', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar precio?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(URL, { action: 'precio_eliminar', id: id }, null, 'json')
                .done(function (resp) {
                    if (resp && resp.status === 'success') {
                        Swal.fire('Eliminado', resp.message, 'success');
                        cargarPrecios();
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'Error.', 'error');
                    }
                });
        });
    });
    // ─────────────── MÉTRICAS ───────────────
    function cargarMetricas(rango) {
        $.post(URL, { action: 'metricas', rango: rango }, null, 'json')
            .done(function (resp) {
                if (!resp || resp.status !== 'success') return;
                const d = resp.data;
                $('#m_informes').text(d.informes_generados);
                $('#m_consultas').text(d.consultas_informe);
                $('#m_revisiones').text(d.revisiones);
                $('#m_tokens').text(Number(d.tokens).toLocaleString('es-CL'));
                $('#m_costo').text(fmtUsd(d.costo));

                // Costo por proveedor.
                let html = '';
                const provs = d.por_proveedor || {};
                Object.keys(provs).forEach(function (prov) {
                    html += '<span class="mpill mpill-prov">'
                        + '<i class="fas fa-robot"></i>'
                        + '<span class="mpill-k">' + esc(prov) + '</span>'
                        + '<span class="mpill-v">' + fmtUsd(provs[prov]) + '</span>'
                        + '</span>';
                });
                $('#m_costo_prov').html(html);
            });
    }

    $(document).on('click', '#metricasRango .btn', function () {
        $('#metricasRango .btn').removeClass('active btn-info').addClass('btn-outline-info');
        $(this).removeClass('btn-outline-info').addClass('active btn-info');
        cargarMetricas($(this).data('rango'));
    });

    // Carga inicial (rango "todo").
    cargarMetricas('todo');

    // Copiar transcripción + resultado (texto plano), separados.
    $(document).on('click', '#btnCopiarDetalles', function () {
        const trans = $('#d_det_transcripcion').text() || '';
        const res = $('#d_det_resultado').text() || '';
        const texto =
            '===== TRANSCRIPCIÓN (DICTADO) =====\n' + trans +
            '\n\n===== RESULTADO (INFORME) =====\n' + res + '\n';

        navigator.clipboard.writeText(texto).then(function () {
            Swal.fire({ icon: 'success', title: 'Copiado', timer: 1200, showConfirmButton: false });
        }).catch(function () {
            Swal.fire('Error', 'No se pudo copiar.', 'error');
        });
    });

    // Limpia backdrops huérfanos al cerrar cualquiera de los modales de esta vista.
    $(document).on('hidden.bs.modal', '#modalDetalleIa, #modalPreciosIa', function () {
        if ($('.modal.show').length === 0) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });
        }
    });
})();