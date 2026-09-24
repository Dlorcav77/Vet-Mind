(function () {
    function limpiarCompartir() {
        $('#compartir_usuario_id').val('');
        $('#compartir_email').val('');
        $('#compartir_usuario_nombre').text('');
        $('#compartir_usuario_email').text('');
        $('#compartir_mensaje').text('').removeClass();
        $('#compartir_usuario_resultado').addClass('d-none');

        $('#compartir_puede_editar')
            .prop('checked', false)
            .prop('disabled', false);

        $('#compartir_clonar')
            .prop('checked', false);

        $('#compartir_copia_comentarios')
            .prop('checked', true);

        $('#compartir_clonar_comentarios_wrap')
            .addClass('d-none');

        $('#btnGuardarCompartir')
            .prop('disabled', true)
            .text('Compartir');
    }

    window.abrirModalCompartirInforme = function (certificadoId) {
        limpiarCompartir();

        $('#compartir_certificado_id').val(certificadoId);

        const modalEl = document.getElementById('modalCompartirInforme');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        modal.show();

        setTimeout(function () {
            $('#compartir_email').trigger('focus');
        }, 200);
    };

    $(document)
        .off('click.certCompartir', '.btn-compartir-informe')
        .on('click.certCompartir', '.btn-compartir-informe', function (e) {
            e.preventDefault();
            e.stopPropagation();

            abrirModalCompartirInforme(
                parseInt($(this).data('id'), 10) || 0
            );
        });

    $(document)
        .off('input.certCompartir', '#compartir_email')
        .on('input.certCompartir', '#compartir_email', function () {
            $('#compartir_usuario_id').val('');
            $('#compartir_usuario_resultado').addClass('d-none');
            $('#btnGuardarCompartir').prop('disabled', true);
            $('#compartir_mensaje').text('');
        });

    $(document)
        .off('keydown.certCompartir', '#compartir_email')
        .on('keydown.certCompartir', '#compartir_email', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#btnBuscarUsuarioCompartir').trigger('click');
            }
        });

    $(document)
        .off('click.certCompartirBuscar', '#btnBuscarUsuarioCompartir')
        .on('click.certCompartirBuscar', '#btnBuscarUsuarioCompartir', function () {
            const certificadoId = parseInt($('#compartir_certificado_id').val(), 10) || 0;
            const email = ($('#compartir_email').val() || '').trim();

            if (!email) return;

            $('#compartir_mensaje')
                .attr('class', 'small mb-3 text-muted')
                .text('Buscando...');

            $.ajax({
                url: 'certificado/compartir/compartir.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    accion: 'buscar_usuario',
                    certificado_id: certificadoId,
                    email: email
                },
                success: function (response) {
                    if (!response || response.status !== 'success') {
                        $('#compartir_mensaje')
                            .attr('class', 'small mb-3 text-danger')
                            .text(response?.message || 'No se pudo realizar la búsqueda.');
                        return;
                    }

                    if (!response.encontrado) {
                        $('#compartir_mensaje')
                            .attr('class', 'small mb-3 text-muted')
                            .text(response.message || 'Usuario no encontrado.');

                        $('#compartir_usuario_resultado').addClass('d-none');
                        $('#btnGuardarCompartir').prop('disabled', true);
                        return;
                    }

                    const usuario = response.usuario;

                    $('#compartir_usuario_id').val(usuario.id);
                    $('#compartir_usuario_nombre').text(usuario.nombre);
                    $('#compartir_usuario_email').text(usuario.email);

                    $('#compartir_puede_editar')
                        .prop('checked', !!usuario.puede_editar);

                    $('#compartir_usuario_resultado').removeClass('d-none');

                    $('#btnGuardarCompartir')
                        .prop('disabled', false)
                        .text(
                            usuario.compartido
                                ? 'Actualizar acceso'
                                : 'Compartir'
                        );

                    $('#compartir_mensaje')
                        .attr('class', 'small mb-3 text-success')
                        .text(
                            usuario.compartido
                                ? 'Este usuario ya tiene acceso al informe.'
                                : 'Usuario encontrado.'
                        );
                },
                error: function (xhr) {
                    $('#compartir_mensaje')
                        .attr('class', 'small mb-3 text-danger')
                        .text(
                            xhr.responseJSON?.message ||
                            'No se pudo realizar la búsqueda.'
                        );
                }
            });
        });

    $(document)
        .off('change.certCompartirClonar', '#compartir_clonar')
        .on('change.certCompartirClonar', '#compartir_clonar', function () {
            const clonar = $(this).is(':checked');

            $('#compartir_puede_editar')
                .prop('disabled', clonar);

            $('#compartir_puede_ver')
                .prop('disabled', true);

            $('#compartir_clonar_comentarios_wrap')
                .toggleClass('d-none', !clonar);

            if (clonar) {
                $('#btnGuardarCompartir').text('Clonar');
            } else {
                const usuarioCompartido =
                    $('#compartir_mensaje')
                        .text()
                        .includes('ya tiene acceso');

                $('#btnGuardarCompartir')
                    .text(
                        usuarioCompartido
                            ? 'Actualizar acceso'
                            : 'Compartir'
                    );
            }
        });

    $(document)
        .off('click.certCompartirGuardar', '#btnGuardarCompartir')
        .on('click.certCompartirGuardar', '#btnGuardarCompartir', function () {
            const certificadoId = parseInt($('#compartir_certificado_id').val(), 10) || 0;
            const usuarioId = parseInt($('#compartir_usuario_id').val(), 10) || 0;
            const clonar = $('#compartir_clonar').is(':checked');
            const $boton = $(this);

            if (!certificadoId || !usuarioId) return;

            if (clonar) {
                Swal.fire(
                    'Clonar informe',
                    'La función de clonación todavía no está conectada.',
                    'info'
                );
                return;
            }

            $boton.prop('disabled', true);

            $.ajax({
                url: 'certificado/compartir/compartir.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    accion: 'guardar',
                    certificado_id: certificadoId,
                    usuario_id: usuarioId,
                    puede_editar: $('#compartir_puede_editar').is(':checked') ? 1 : 0
                },
                success: function (response) {
                    if (!response || response.status !== 'success') {
                        Swal.fire(
                            'Error',
                            response?.message || 'No se pudo compartir el informe.',
                            'error'
                        );

                        $boton.prop('disabled', false);
                        return;
                    }

                    bootstrap.Modal
                        .getInstance(document.getElementById('modalCompartirInforme'))
                        ?.hide();

                    Swal.fire(
                        'Listo',
                        'Informe compartido correctamente.',
                        'success'
                    );
                },
                error: function (xhr) {
                    Swal.fire(
                        'Error',
                        xhr.responseJSON?.message || 'No se pudo compartir el informe.',
                        'error'
                    );

                    $boton.prop('disabled', false);
                }
            });
        });
})();
