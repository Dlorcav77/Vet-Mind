<?php
// admin/certificado/envio_email/historial_envios.php
?>

<style>
    #historialEnviosLista {
        max-height: 520px;
        overflow-y: auto;
    }

    .hist-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }

    .hist-toolbar-search {
        position: relative;
        flex: 1;
    }

    .hist-toolbar-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #8b95a1;
        font-size: 13px;
        pointer-events: none;
    }

    .hist-toolbar-search input {
        padding-left: 34px;
    }

    #historialEnviosOrden {
        width: 155px;
        flex: 0 0 155px;
    }

    .hist-envio {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 8px;
        margin-bottom: 8px;
        overflow: hidden;
        background: #fff;
    }

    .hist-envio-header {
        width: 100%;
        border: 0;
        background: transparent;
        padding: 11px 14px;
        text-align: left;
        cursor: pointer;
    }

    .hist-envio-header:hover {
        background: rgba(0,0,0,.025);
    }

    .hist-envio-grid {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) 100px 65px 95px 80px 18px;
        gap: 12px;
        align-items: center;
    }

    .hist-col-label {
        display: block;
        margin-bottom: 2px;
        color: #8a929b;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .hist-correo {
        min-width: 0;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .hist-fecha,
    .hist-hora,
    .hist-cantidad {
        font-size: 12px;
        color: #59636e;
        white-space: nowrap;
    }

    .hist-envio-detalle {
        display: none;
        padding: 0 14px 12px;
        border-top: 1px solid rgba(0,0,0,.06);
    }

    .hist-envio.is-open .hist-envio-detalle {
        display: block;
    }

    .hist-informe {
        padding: 8px 0;
        border-bottom: 1px solid rgba(0,0,0,.06);
    }

    .hist-informe:last-child {
        border-bottom: 0;
    }

    .hist-envio-tipo {
        margin-top: 10px;
        font-size: 11px;
        color: #6c757d;
    }

    @media (max-width: 767.98px) {
        .hist-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        #historialEnviosOrden {
            width: 100%;
            flex-basis: auto;
        }

        .hist-envio-grid {
            grid-template-columns: 1fr 75px 45px 18px;
            gap: 8px;
        }

        .hist-grid-cantidad,
        .hist-grid-estado {
            margin-top: 4px;
        }

        .hist-grid-cantidad {
            grid-column: 1 / 3;
        }

        .hist-grid-estado {
            grid-column: 3 / 4;
        }
    }
</style>

<div class="modal fade" id="modalHistorialEnvios" tabindex="-1" aria-labelledby="modalHistorialEnviosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalHistorialEnviosLabel">
                    <i class="far fa-clock"></i>
                    <span>Historial de envíos</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body pt-0">

                <div class="hist-toolbar">
                    <div class="hist-toolbar-search">
                        <i class="fas fa-search"></i>
                        <input
                            type="search"
                            class="form-control form-control-sm"
                            id="historialEnviosBuscar"
                            placeholder="Buscar por correo..."
                            autocomplete="off"
                        >
                    </div>

                    <select class="form-select form-select-sm" id="historialEnviosOrden" aria-label="Ordenar historial">
                        <option value="desc">Más recientes</option>
                        <option value="asc">Más antiguos</option>
                    </select>
                </div>

                <div id="historialEnviosLoading" class="text-center py-5">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                    <div class="small text-muted mt-2">Cargando historial...</div>
                </div>

                <div id="historialEnviosVacio" class="text-center text-muted py-5" style="display:none;">
                    <i class="far fa-envelope fa-2x mb-2"></i>
                    <div id="historialEnviosVacioTexto">Todavía no existen envíos registrados.</div>
                </div>

                <div id="historialEnviosLista" style="display:none;"></div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>

<script>
(function () {

    let historialEnviosCache = [];

    function escapeHistorial(value) {
        return $('<div>').text(String(value ?? '')).html();
    }

    function obtenerPartesFechaHora(fecha) {
        if (!fecha) {
            return { fecha: '-', hora: '-' };
        }

        const partes = String(fecha).split(' ');
        const fechaIso = partes[0] || '';
        const horaCompleta = partes[1] || '';

        const f = fechaIso.split('-');

        return {
            fecha: f.length === 3 ? f[2] + '-' + f[1] + '-' + f[0] : fechaIso || '-',
            hora: horaCompleta ? horaCompleta.substring(0, 5) : '-'
        };
    }

    function obtenerFechaOrdenEnvio(envio) {
        return String(envio.fecha_envio || envio.creado_en || '');
    }

    function renderHistorialEnvios() {
        const $lista = $('#historialEnviosLista');
        const buscar = String($('#historialEnviosBuscar').val() || '').trim().toLowerCase();
        const orden = $('#historialEnviosOrden').val() === 'asc' ? 'asc' : 'desc';

        let envios = historialEnviosCache.filter(function (envio) {
            if (!buscar) return true;

            const destinatarios = Array.isArray(envio.destinatarios)
                ? envio.destinatarios.join(' ')
                : '';

            return destinatarios.toLowerCase().includes(buscar);
        });

        envios.sort(function (a, b) {
            const fechaA = obtenerFechaOrdenEnvio(a);
            const fechaB = obtenerFechaOrdenEnvio(b);

            return orden === 'asc'
                ? fechaA.localeCompare(fechaB)
                : fechaB.localeCompare(fechaA);
        });

        $lista.empty();

        if (!envios.length) {
            $('#historialEnviosLoading').hide();
            $('#historialEnviosLista').hide();

            $('#historialEnviosVacioTexto').text(
                buscar
                    ? 'No se encontraron envíos para ese correo.'
                    : 'Todavía no existen envíos registrados.'
            );

            $('#historialEnviosVacio').show();
            return;
        }

        envios.forEach(function (envio) {
            const destinatarios = Array.isArray(envio.destinatarios)
                ? envio.destinatarios.join(', ')
                : '-';

            const fechaHora = obtenerPartesFechaHora(
                envio.fecha_envio || envio.creado_en
            );

            const exitoso = envio.estado === 'success';

            const estado = exitoso
                ? '<span class="badge bg-success">Enviado</span>'
                : '<span class="badge bg-danger">Error</span>';

            const tipo = envio.tipo_envio === 'masivo'
                ? 'Envío múltiple'
                : 'Envío individual';

            let informesHtml = '';

            (envio.informes || []).forEach(function (informe) {
                const fechaInforme = informe.fecha_examen
                    ? obtenerPartesFechaHora(informe.fecha_examen).fecha
                    : '-';

                informesHtml +=
                    '<div class="hist-informe">' +
                        '<div class="fw-semibold">' + escapeHistorial(informe.paciente || 'Sin nombre') + '</div>' +
                        '<div class="small text-muted">' +
                            escapeHistorial(informe.tipo_examen || '-') +
                            ' · ' + escapeHistorial(fechaInforme) +
                        '</div>' +
                        (informe.propietario
                            ? '<div class="small">' + escapeHistorial(informe.propietario) + '</div>'
                            : '') +
                    '</div>';
            });

            let errorHtml = '';

            if (!exitoso && envio.error_mensaje) {
                errorHtml =
                    '<div class="alert alert-danger py-2 px-3 mt-3 mb-0 small">' +
                        escapeHistorial(envio.error_mensaje) +
                    '</div>';
            }

            const html =
                '<div class="hist-envio">' +
                    '<button type="button" class="hist-envio-header">' +
                        '<div class="hist-envio-grid">' +

                            '<div class="hist-correo">' +
                                '<span class="hist-col-label">Correo</span>' +
                                escapeHistorial(destinatarios) +
                            '</div>' +

                            '<div class="hist-fecha">' +
                                '<span class="hist-col-label">Fecha</span>' +
                                escapeHistorial(fechaHora.fecha) +
                            '</div>' +

                            '<div class="hist-hora">' +
                                '<span class="hist-col-label">Hora</span>' +
                                escapeHistorial(fechaHora.hora) +
                            '</div>' +

                            '<div class="hist-grid-cantidad hist-cantidad">' +
                                '<span class="hist-col-label">Informes</span>' +
                                envio.cantidad_informes +
                            '</div>' +

                            '<div class="hist-grid-estado">' +
                                '<span class="hist-col-label">Estado</span>' +
                                estado +
                            '</div>' +

                            '<div>' +
                                '<i class="fas fa-chevron-down small text-muted"></i>' +
                            '</div>' +

                        '</div>' +
                    '</button>' +

                    '<div class="hist-envio-detalle">' +
                        '<div class="hist-envio-tipo">' +
                            escapeHistorial(tipo) +
                            (envio.asunto
                                ? ' · ' + escapeHistorial(envio.asunto)
                                : '') +
                        '</div>' +

                        '<div class="small text-muted text-uppercase fw-semibold mt-3 mb-1">' +
                            'Informes enviados' +
                        '</div>' +

                        informesHtml +
                        errorHtml +
                    '</div>' +
                '</div>';

            $lista.append(html);
        });

        $('#historialEnviosLoading').hide();
        $('#historialEnviosVacio').hide();
        $('#historialEnviosLista').show();
    }

    window.abrirHistorialEnviosCertificados = function () {
        historialEnviosCache = [];

        $('#historialEnviosBuscar').val('');
        $('#historialEnviosOrden').val('desc');

        $('#historialEnviosLista').empty().hide();
        $('#historialEnviosVacio').hide();
        $('#historialEnviosLoading').show();

        const modalEl = document.getElementById('modalHistorialEnvios');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        modal.show();

        $.ajax({
            url: 'certificado/envio_email/get_historial.php',
            type: 'GET',
            dataType: 'json',

            success: function (resp) {
                if (!resp || resp.status !== 'success') {
                    $('#historialEnviosLoading').hide();

                    Swal.fire(
                        'Error',
                        resp?.message || 'No se pudo cargar el historial.',
                        'error'
                    );
                    return;
                }

                historialEnviosCache = Array.isArray(resp.envios)
                    ? resp.envios
                    : [];

                renderHistorialEnvios();
            },

            error: function (xhr) {
                $('#historialEnviosLoading').hide();

                console.error('Error historial de correos:', xhr.responseText);

                Swal.fire(
                    'Error',
                    'No se pudo cargar el historial de envíos.',
                    'error'
                );
            }
        });
    };

    $(document)
        .off('input.historialEnviosBuscar', '#historialEnviosBuscar')
        .on('input.historialEnviosBuscar', '#historialEnviosBuscar', function () {
            renderHistorialEnvios();
        });

    $(document)
        .off('change.historialEnviosOrden', '#historialEnviosOrden')
        .on('change.historialEnviosOrden', '#historialEnviosOrden', function () {
            renderHistorialEnvios();
        });

    $(document)
        .off('click.historialEnvioDetalle', '.hist-envio-header')
        .on('click.historialEnvioDetalle', '.hist-envio-header', function () {
            const $envio = $(this).closest('.hist-envio');

            $envio.toggleClass('is-open');

            $(this)
                .find('.fa-chevron-down, .fa-chevron-up')
                .toggleClass('fa-chevron-down fa-chevron-up');
        });

})();
</script>