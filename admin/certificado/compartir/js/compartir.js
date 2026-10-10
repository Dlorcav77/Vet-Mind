(function () {
    function limpiarCompartir() {
        $('#compartir_usuario_id').val('');
        $('#compartir_email').val('');
        $('#compartir_usuario_nombre').text('');
        $('#compartir_usuario_email').text('');
        $('#compartir_mensaje').text('').removeClass();
        $('#compartir_usuario_resultado').addClass('d-none');

        $('#compartir_agregar_usuario')
            .prop('checked', true);

        $('#compartir_favoritos_wrap')
            .removeClass('d-none');

        $('#compartir_agregados_wrap')
            .removeClass('col-md-12')
            .addClass('col-md-6');

        $('#compartir_usuarios_agregados').html(
            '<div class="small text-muted p-2 border rounded">Cargando...</div>'
        );

        $('#compartir_usuarios_favoritos').html(
            '<div class="small text-muted p-2 border rounded">Cargando...</div>'
        );

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

    function cargarUsuariosAgregados(certificadoId) {
        const $contenedor =
            $('#compartir_usuarios_agregados');

        if (!certificadoId) {
            return;
        }

        $contenedor.html(
            '<div class="col-12"><div class="small text-muted p-3 border rounded">Cargando...</div></div>'
        );

        $.ajax({
            url: 'certificado/compartir/compartir.php',
            type: 'POST',
            dataType: 'json',
            data: {
                accion: 'listar_compartidos',
                certificado_id: certificadoId
            },

            success: function (response) {
                if (
                    !response ||
                    response.status !== 'success'
                ) {
                    $contenedor.html(
                        '<div class="small text-danger p-3">' +
                        'No se pudieron cargar los usuarios.' +
                        '</div>'
                    );
                    return;
                }

                const usuarios =
                    Array.isArray(response.usuarios)
                        ? response.usuarios
                        : [];

                if (!usuarios.length) {
                    $contenedor.html(
                        '<div class="col-12"><div class="small text-muted p-3 border rounded">' +
                        'Sin usuarios agregados.' +
                        '</div></div>'
                    );
                    return;
                }

                const html = usuarios.map(function (usuario) {
                    const esClonacion =
                        usuario.tipo_solicitud === 'clonar';

                    const estado =
                        usuario.estado === 'activo'
                            ? 'Compartido'
                            : (
                                esClonacion
                                    ? 'Clonación pendiente'
                                    : 'Pendiente'
                            );

                    const claseEstado =
                        usuario.estado === 'activo'
                            ? 'bg-success'
                            : (
                                esClonacion
                                    ? 'bg-info text-dark'
                                    : 'bg-warning text-dark'
                            );

                    let permiso = '';

                    if (
                        !esClonacion &&
                        usuario.puede_editar
                    ) {
                        permiso = ' · Puede editar';
                    }

                    if (
                        esClonacion &&
                        usuario.incluir_comentarios
                    ) {
                        permiso = ' · Incluye comentarios';
                    }

                    return `
                        <div
                            class="border rounded px-2 py-2
                                   d-flex justify-content-between
                                   align-items-center gap-2"
                        >
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">
                                        ${$('<div>')
                                            .text(usuario.nombre)
                                            .html()}
                                    </div>

                                    <div class="small text-muted text-truncate">
                                        ${$('<div>')
                                            .text(usuario.email)
                                            .html()}
                                        ${permiso}
                                    </div>
                                </div>

                                <div
                                    class="d-flex flex-column
                                           align-items-end gap-1
                                           flex-shrink-0"
                                >
                                    <span class="badge ${claseEstado}">
                                        ${estado}
                                    </span>

                                    <button
                                        type="button"
                                        class="btn btn-link btn-sm
                                               text-danger p-0
                                               btn-revocar-compartido"
                                        data-id="${usuario.id}"
                                        data-estado="${usuario.estado}"
                                    >
                                        ${
                                            usuario.estado === 'activo'
                                                ? 'Dejar de compartir'
                                                : 'Cancelar invitación'
                                        }
                                    </button>
                                </div>
                        </div>
                    `;
                }).join('');

                $contenedor.html(html);
            },

            error: function () {
                $contenedor.html(
                    '<div class="small text-danger p-3">' +
                    'No se pudieron cargar los usuarios.' +
                    '</div>'
                );
            }
        });
    }

    function mostrarModoUsuarioSeleccionado() {
        $('#compartir_favoritos_wrap')
            .addClass('d-none');

        $('#compartir_agregados_wrap')
            .removeClass('col-md-6')
            .addClass('col-md-12');
    }

    function seleccionarUsuarioCompartir(usuario) {
        const id =
            parseInt(usuario.id, 10) || 0;

        if (!id) {
            return;
        }

        $('#compartir_usuario_id').val(id);

        $('#compartir_email')
            .val(usuario.email || '');

        $('#compartir_usuario_nombre')
            .text(usuario.nombre || '');

        $('#compartir_usuario_email')
            .text(usuario.email || '');

        $('#compartir_agregar_usuario')
            .prop('checked', true);

        $('#compartir_usuario_resultado')
            .removeClass('d-none');

        mostrarModoUsuarioSeleccionado();

        $('#compartir_mensaje')
            .text('Usuario seleccionado.')
            .removeClass()
            .addClass('small mb-3 text-success');

        $('#btnGuardarCompartir')
            .prop('disabled', false);
    }

    function cargarUsuariosFavoritos(certificadoId) {
        const $contenedor =
            $('#compartir_usuarios_favoritos');

        if (!certificadoId) {
            return;
        }

        $contenedor.html(
            '<div class="small text-muted p-2 border rounded">Cargando...</div>'
        );

        $.ajax({
            url: 'certificado/compartir/compartir.php',
            type: 'POST',
            dataType: 'json',
            data: {
                accion: 'listar_favoritos',
                certificado_id: certificadoId
            },

            success: function (response) {
                const usuarios =
                    response &&
                    response.status === 'success' &&
                    Array.isArray(response.usuarios)
                        ? response.usuarios
                        : [];

                if (!usuarios.length) {
                    $contenedor.html(
                        '<div class="small text-muted p-2 border rounded">' +
                        'Sin favoritos.' +
                        '</div>'
                    );
                    return;
                }

                const html = usuarios.map(function (usuario) {
                    const nombre =
                        $('<div>')
                            .text(usuario.nombre || '')
                            .html();

                    const email =
                        $('<div>')
                            .text(usuario.email || '')
                            .html();

                    return `
                        <button
                            type="button"
                            class="btn btn-light border text-start
                                   px-2 py-2 w-100
                                   btn-usuario-favorito-compartir"
                            data-id="${usuario.id}"
                            data-nombre="${nombre}"
                            data-email="${email}"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="d-inline-block rounded-circle
                                           bg-warning flex-shrink-0"
                                    style="width:7px;height:7px"
                                ></span>

                                <div style="min-width:0">
                                    <div class="fw-semibold small text-truncate">
                                        ${nombre}
                                    </div>

                                    <div class="text-muted text-truncate"
                                         style="font-size:11px">
                                        ${email}
                                    </div>
                                </div>
                            </div>
                        </button>
                    `;
                }).join('');

                $contenedor.html(html);
            },

            error: function () {
                $contenedor.html(
                    '<div class="small text-danger p-2 border rounded">' +
                    'No se pudieron cargar los favoritos.' +
                    '</div>'
                );
            }
        });
    }

    $(document)
        .off(
            'click.certCompartirFavorito',
            '.btn-usuario-favorito-compartir'
        )
        .on(
            'click.certCompartirFavorito',
            '.btn-usuario-favorito-compartir',
            function () {
                seleccionarUsuarioCompartir({
                    id: $(this).data('id'),
                    nombre: $(this).data('nombre'),
                    email: $(this).data('email')
                });
            }
        );

    $(document)
        .off(
            'click.certCompartirRevocar',
            '.btn-revocar-compartido'
        )
        .on(
            'click.certCompartirRevocar',
            '.btn-revocar-compartido',
            function () {
                const certificadoId =
                    parseInt(
                        $('#compartir_certificado_id').val(),
                        10
                    ) || 0;

                const usuarioId =
                    parseInt($(this).data('id'), 10) || 0;

                const estado =
                    String($(this).data('estado') || '');

                if (!certificadoId || !usuarioId) {
                    return;
                }

                const esActivo = estado === 'activo';

                Swal.fire({
                    title: esActivo
                        ? 'Dejar de compartir'
                        : 'Cancelar invitación',
                    text: esActivo
                        ? 'El usuario dejará de tener acceso a este informe.'
                        : 'La solicitud pendiente será cancelada.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: esActivo
                        ? 'Dejar de compartir'
                        : 'Cancelar invitación',
                    cancelButtonText: 'Volver'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: 'certificado/compartir/compartir.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            accion: 'revocar',
                            certificado_id: certificadoId,
                            usuario_id: usuarioId
                        },

                        success: function (response) {
                            if (
                                !response ||
                                response.status !== 'success'
                            ) {
                                Swal.fire(
                                    'Error',
                                    response?.message ||
                                        'No se pudo retirar el acceso.',
                                    'error'
                                );
                                return;
                            }

                            cargarUsuariosAgregados(certificadoId);

                            Swal.fire({
                                icon: 'success',
                                title: esActivo
                                    ? 'Acceso retirado'
                                    : 'Invitación cancelada',
                                timer: 1000,
                                showConfirmButton: false
                            });
                        },

                        error: function (xhr) {
                            Swal.fire(
                                'Error',
                                xhr.responseJSON?.message ||
                                    'No se pudo retirar el acceso.',
                                'error'
                            );
                        }
                    });
                });
            }
        );

    window.abrirModalCompartirInforme = function (certificadoId) {
        limpiarCompartir();

        $('#compartir_certificado_id').val(certificadoId);

        cargarUsuariosAgregados(certificadoId);
        cargarUsuariosFavoritos(certificadoId);

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

                    mostrarModoUsuarioSeleccionado();

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

    function responderSolicitudCompartida(
        certificadoId,
        accion,
        tipoSolicitud,
        $boton
    ) {
        if (!certificadoId || !['aceptar', 'rechazar'].includes(accion)) {
            return;
        }

        const esClonacion =
            tipoSolicitud === 'clonar';

        let textoAccion;
        let textoConfirmacion;
        let textoBoton;

        if (accion === 'rechazar') {
            textoAccion = 'Rechazar solicitud';
            textoConfirmacion = 'La solicitud dejará de aparecer.';
            textoBoton = 'Rechazar';

        } else if (esClonacion) {
            textoAccion = 'Clonar informe';
            textoConfirmacion =
                'Se creará una copia independiente del informe en tu cuenta.';
            textoBoton = 'Clonar';

        } else {
            textoAccion = 'Aceptar informe';
            textoConfirmacion =
                'El informe compartido aparecerá en tu listado.';
            textoBoton = 'Aceptar';
        }

        Swal.fire({
            title: textoAccion,
            text: textoConfirmacion,
            icon: accion === 'aceptar' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: textoBoton,
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $boton.prop('disabled', true);

            $.ajax({
                url: 'certificado/compartir/compartir.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    accion: accion,
                    certificado_id: certificadoId
                },

                success: function (response) {
                    if (!response || response.status !== 'success') {
                        Swal.fire(
                            'Error',
                            response?.message ||
                                'No se pudo responder la solicitud.',
                            'error'
                        );

                        $boton.prop('disabled', false);
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title:
                            accion === 'aceptar'
                                ? 'Informe aceptado'
                                : 'Solicitud rechazada',
                        text: response.message,
                        timer: 1200,
                        showConfirmButton: false
                    }).then(function () {
                        $('#content').load(
                            'certificado/lisCertificados.php'
                        );
                    });
                },

                error: function (xhr) {
                    Swal.fire(
                        'Error',
                        xhr.responseJSON?.message ||
                            'No se pudo responder la solicitud.',
                        'error'
                    );

                    $boton.prop('disabled', false);
                }
            });
        });
    }

    $(document)
        .off(
            'click.certCompartirAceptar',
            '.btn-aceptar-compartido'
        )
        .on(
            'click.certCompartirAceptar',
            '.btn-aceptar-compartido',
            function () {
                responderSolicitudCompartida(
                    parseInt($(this).data('id'), 10) || 0,
                    'aceptar',
                    String($(this).data('tipo') || 'compartir'),
                    $(this)
                );
            }
        );

    $(document)
        .off(
            'click.certCompartirRechazar',
            '.btn-rechazar-compartido'
        )
        .on(
            'click.certCompartirRechazar',
            '.btn-rechazar-compartido',
            function () {
                responderSolicitudCompartida(
                    parseInt($(this).data('id'), 10) || 0,
                    'rechazar',
                    String($(this).data('tipo') || 'compartir'),
                    $(this)
                );
            }
        );

    $(document)
        .off('click.certCompartirGuardar', '#btnGuardarCompartir')
        .on('click.certCompartirGuardar', '#btnGuardarCompartir', function () {
            const certificadoId = parseInt($('#compartir_certificado_id').val(), 10) || 0;
            const usuarioId = parseInt($('#compartir_usuario_id').val(), 10) || 0;
            const clonar = $('#compartir_clonar').is(':checked');
            const $boton = $(this);

            if (!certificadoId || !usuarioId) {
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
                    tipo_solicitud:
                        clonar ? 'clonar' : 'compartir',
                    puede_editar:
                        $('#compartir_puede_editar').is(':checked') ? 1 : 0,
                    incluir_comentarios:
                        $('#compartir_copia_comentarios').is(':checked') ? 1 : 0,
                    favorito:
                        $('#compartir_agregar_usuario').is(':checked') ? 1 : 0
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

                    $('#compartir_usuario_id').val('');
                    $('#compartir_email').val('');
                    $('#compartir_usuario_nombre').text('');
                    $('#compartir_usuario_email').text('');
                    $('#compartir_usuario_resultado')
                        .addClass('d-none');
                    $('#compartir_mensaje').text('');

                    $('#btnGuardarCompartir')
                        .prop('disabled', true)
                        .text('Compartir');

                    cargarUsuariosAgregados(certificadoId);

                    Swal.fire({
                        icon: 'success',
                        title: 'Listo',
                        text:
                            response.message ||
                            'Solicitud enviada correctamente.',
                        timer: 1100,
                        showConfirmButton: false
                    });
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
