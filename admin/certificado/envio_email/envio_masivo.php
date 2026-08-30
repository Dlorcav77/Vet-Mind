<?php
// admin/certificado/envio_email/envio_masivo.php
?>

<style>
    #listaInformesEnvioMasivo {
        max-height: 260px;
        overflow-y: auto;
    }

    .envio-masivo-item {
        padding: .65rem .75rem;
        border-bottom: 1px solid rgba(0,0,0,.07);
    }

    .envio-masivo-item:last-child {
        border-bottom: 0;
    }
</style>

<div class="modal fade" id="modalEnvioMasivo" tabindex="-1" aria-labelledby="modalEnvioMasivoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalEnvioMasivoLabel">
                    <i class="fas fa-envelope"></i>
                    <span>Enviar informes por correo</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body pt-0">

                <div class="section-title">Destinatario</div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="tipo_destinatario_masivo" id="destMasivoClinica" value="clinica" checked>
                    <label class="form-check-label fw-semibold" for="destMasivoClinica">Clínica</label>
                </div>

                <select id="selectClinicaMasivo" class="form-select mb-3">
                    <option value="">— Selecciona una clínica —</option>
                </select>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="tipo_destinatario_masivo" id="destMasivoManual" value="manual">
                    <label class="form-check-label fw-semibold" for="destMasivoManual">Otro correo</label>
                </div>

                <input type="email" id="correoMasivoManual" class="form-control mb-4" placeholder="correo@dominio.cl" disabled>

                <div class="section-title d-flex justify-content-between align-items-center">
                    <span>Informes seleccionados</span>
                    <span id="cantidadInformesMasivo" class="badge bg-secondary">0</span>
                </div>

                <div id="listaInformesEnvioMasivo" class="border rounded bg-light-subtle"></div>

            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

                <button type="button" class="btn btn-success" id="btnConfirmarEnvioMasivo">
                    <i class="fas fa-paper-plane me-2"></i>
                    <span>Enviar informes</span>
                </button>
            </div>

        </div>
    </div>
</div>

<script>
(function () {

    let informesMasivosActuales = [];

    function escapeHtmlMasivo(value) {
        return $('<div>').text(String(value || '')).html();
    }

    function renderInformesMasivos() {
        const $lista = $('#listaInformesEnvioMasivo');

        $lista.empty();
        $('#cantidadInformesMasivo').text(informesMasivosActuales.length);

        informesMasivosActuales.forEach(function (informe) {
            $lista.append(
                '<div class="envio-masivo-item">' +
                    '<div class="fw-semibold">' + escapeHtmlMasivo(informe.paciente || 'Sin nombre') + '</div>' +
                    '<div class="small text-muted">' +
                        escapeHtmlMasivo(informe.tipo_examen || '-') +
                        ' · ' +
                        escapeHtmlMasivo(informe.fecha || '-') +
                    '</div>' +
                    '<div class="small">' + escapeHtmlMasivo(informe.propietario || '-') + '</div>' +
                '</div>'
            );
        });
    }

    async function cargarClinicasMasivo() {
        const $select = $('#selectClinicaMasivo');

        $select
            .prop('disabled', true)
            .empty()
            .append('<option value="">Cargando clínicas...</option>');

        try {
            const clinicas = await ensureClinicasCache();

            $select
                .empty()
                .append('<option value="">— Selecciona una clínica —</option>');

            clinicas.forEach(function (clinica) {
                const correo = String(clinica.correo || '').trim();
                if (!correo) return;

                $select.append(
                    $('<option>', {
                        value: correo,
                        text: String(clinica.nombre_clinica || 'Clínica') + ' (' + correo + ')'
                    })
                );
            });

            $select.prop('disabled', false);

            if (!clinicas.length) {
                $('#destMasivoManual').prop('checked', true).trigger('change');
            }

        } catch (error) {
            console.error('Error cargando clínicas:', error);

            $select
                .empty()
                .append('<option value="">No se pudieron cargar las clínicas</option>')
                .prop('disabled', true);

            $('#destMasivoManual').prop('checked', true).trigger('change');
        }
    }

    function obtenerCorreoMasivo() {
        const tipo = $('input[name="tipo_destinatario_masivo"]:checked').val();

        if (tipo === 'manual') {
            return String($('#correoMasivoManual').val() || '').trim();
        }

        return String($('#selectClinicaMasivo').val() || '').trim();
    }

    window.abrirModalEnvioMasivoCertificados = async function (informes) {
        informesMasivosActuales = Array.isArray(informes) ? informes : [];

        if (!informesMasivosActuales.length) {
            Swal.fire('Atención', 'Selecciona al menos un informe.', 'warning');
            return;
        }

        renderInformesMasivos();

        $('#destMasivoClinica').prop('checked', true);
        $('#correoMasivoManual').val('').prop('disabled', true);
        $('#selectClinicaMasivo').prop('disabled', false);

        const modalEl = document.getElementById('modalEnvioMasivo');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        modal.show();
        await cargarClinicasMasivo();
    };

    $(document)
        .off('change.envioMasivoTipo', 'input[name="tipo_destinatario_masivo"]')
        .on('change.envioMasivoTipo', 'input[name="tipo_destinatario_masivo"]', function () {
            const tipo = $('input[name="tipo_destinatario_masivo"]:checked').val();

            $('#selectClinicaMasivo').prop('disabled', tipo !== 'clinica');
            $('#correoMasivoManual').prop('disabled', tipo !== 'manual');

            if (tipo === 'manual') {
                $('#correoMasivoManual').trigger('focus');
            }
        });

    $(document)
        .off('click.envioMasivoConfirmar', '#btnConfirmarEnvioMasivo')
        .on('click.envioMasivoConfirmar', '#btnConfirmarEnvioMasivo', function () {
            const destinatario = obtenerCorreoMasivo();

            if (!destinatario || !isEmail(destinatario)) {
                Swal.fire('Atención', 'Selecciona o ingresa un correo válido.', 'warning');
                return;
            }

            const ids = informesMasivosActuales
                .map(function (informe) {
                    return parseInt(informe.id, 10) || 0;
                })
                .filter(function (id) {
                    return id > 0;
                });

            if (!ids.length) {
                Swal.fire('Error', 'No hay informes válidos para enviar.', 'error');
                return;
            }

            Swal.fire({
                title: 'Enviando informes...',
                text: 'Preparando ' + ids.length + ' archivo(s) PDF.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'certificado/envio_email/send_certificados_masivo.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    certificado_ids: ids,
                    destinatario: destinatario
                },

                success: function (resp) {
                    Swal.close();

                    if (!resp || resp.status !== 'success') {
                        Swal.fire(
                            'Error',
                            (resp && resp.message) || 'No se pudieron enviar los informes.',
                            'error'
                        );
                        return;
                    }

                    const modalEl = document.getElementById('modalEnvioMasivo');
                    const modal = bootstrap.Modal.getInstance(modalEl);

                    if (modal) modal.hide();

                    if (typeof window.vmCancelarModoEnvioMasivo === 'function') {
                        window.vmCancelarModoEnvioMasivo();
                    }

                    Swal.fire(
                        'Listo',
                        resp.message || 'Informes enviados correctamente.',
                        'success'
                    );
                },

                error: function (xhr) {
                    Swal.close();

                    console.error(
                        'Error envío masivo:',
                        xhr.responseText
                    );

                    Swal.fire(
                        'Error',
                        xhr.responseJSON?.message || 'Error de red o del servidor al enviar.',
                        'error'
                    );
                }
            });
        });

})();
</script>