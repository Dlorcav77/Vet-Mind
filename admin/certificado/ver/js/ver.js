// admin/certificado/ver/js/ver.js

(function () {

    let informeActual = null;
    let modoInicial = 'informe';
    let requestInforme = null;

    function textoValor(valor, defecto = '-') {
        const texto = String(valor ?? '').trim();
        return texto !== '' ? texto : defecto;
    }

    function formatearFecha(fecha) {
        const valor = String(fecha || '').trim();

        if (!valor) {
            return '-';
        }

        const partes = valor.split('-');

        if (partes.length !== 3) {
            return valor;
        }

        return partes[2] + '-' + partes[1] + '-' + partes[0];
    }

    function formatearValorCampo(campo, valor) {
        const nombreCampo =
            String(campo || '')
                .trim()
                .toLowerCase();

        const texto =
            String(valor ?? '').trim();

        if (texto === '') {
            return '-';
        }

        /*
        * Estos campos vienen normalmente desde MySQL
        * con formato YYYY-MM-DD.
        *
        * fecha_emision NO se incluye porque el backend
        * ya la devuelve en formato DD-MM-YYYY,
        * igual que funcionesCertificado.php.
        */
        if (
            nombreCampo === 'fecha_nacimiento' ||
            nombreCampo === 'fecha_examen'
        ) {
            return formatearFecha(texto);
        }

        return texto;
    }

    function obtenerCamposFallback(informe) {
        /*
        * Fallback para informes antiguos sin una
        * configuración recuperable.
        */
        const campos = [
            {
                campo: 'paciente',
                etiqueta: 'Paciente',
                ambito: 'paciente',
                valor: informe.paciente
            },
            {
                campo: 'codigo_paciente',
                etiqueta: 'Código paciente',
                ambito: 'paciente',
                valor: informe.codigo_paciente
            },
            {
                campo: 'propietario',
                etiqueta: 'Propietario',
                ambito: 'paciente',
                valor: informe.propietario
            },
            {
                campo: 'especie',
                etiqueta: 'Especie',
                ambito: 'paciente',
                valor: informe.especie
            },
            {
                campo: 'raza',
                etiqueta: 'Raza',
                ambito: 'paciente',
                valor: informe.raza
            },
            {
                campo: 'sexo',
                etiqueta: 'Sexo',
                ambito: 'paciente',
                valor: informe.sexo
            },
            {
                campo: 'fecha_examen',
                etiqueta: 'Fecha examen',
                ambito: 'general',
                valor: informe.fecha_examen
            },
            {
                campo: 'tipo_examen',
                etiqueta: 'Tipo de examen',
                ambito: 'general',
                valor: informe.tipo_examen
            },
            {
                campo: 'medico_solicitante',
                etiqueta: 'Médico solicitante',
                ambito: 'general',
                valor: informe.medico_solicitante
            },
            {
                campo: 'recinto',
                etiqueta: 'Recinto',
                ambito: 'general',
                valor: informe.recinto
            }
        ];

        const motivo =
            String(informe.motivo || '').trim();

        if (motivo !== '') {
            campos.push({
                campo: 'motivo',
                etiqueta: 'Motivo del examen',
                ambito: 'general',
                valor: motivo
            });
        }

        return campos;
    }

    function renderizarCamposInforme(informe) {
        const $paciente =
            $('#verInformeCamposPaciente');

        const $general =
            $('#verInformeCamposGeneral');

        $paciente.empty();
        $general.empty();

        $('#verInformePacienteWrap').hide();
        $('#verInformeGeneralWrap').hide();

        let campos =
            Array.isArray(informe.campos)
                ? informe.campos.slice()
                : [];

        /*
        * Informes antiguos o configuraciones
        * que ya no puedan recuperarse.
        */
        if (!campos.length) {
            campos =
                obtenerCamposFallback(informe);
        }

        const tieneReferencia =
            parseInt(
                informe.es_destacado,
                10
            ) === 1;

        const claseColumnaPaciente =
            tieneReferencia
                ? 'col-md-6 vm-ver-campo'
                : 'col-md-4 vm-ver-campo';


        /*
        * Separamos por ámbito.
        */
        let camposPaciente = [];
        const camposGeneral = [];

        campos.forEach(function (campo, indiceOriginal) {
            const ambito =
                String(
                    campo.ambito || ''
                )
                    .trim()
                    .toLowerCase();

            const item = Object.assign(
                {},
                campo,
                {
                    __indiceOriginal:
                        indiceOriginal
                }
            );

            if (ambito === 'paciente') {
                camposPaciente.push(item);
            } else {
                camposGeneral.push(item);
            }
        });


        /*
        * Mismo orden utilizado en
        * paciente_manual.php.
        *
        * N_ficha utiliza codigo_paciente.
        * Edad utiliza fecha_nacimiento.
        */
        const ordenPaciente = {
            codigo_paciente: 1,
            paciente: 2,
            especie: 3,
            raza: 4,
            sexo: 5,
            propietario: 6,
            fecha_nacimiento: 7,
            n_chip: 8
        };

        function obtenerCampoInternoOrden(campo) {
            let interno =
                String(
                    campo.campo_interno ||
                    campo.campo ||
                    ''
                )
                    .trim()
                    .toLowerCase();

            /*
            * Alias visuales.
            */
            if (interno === 'n_ficha') {
                interno = 'codigo_paciente';
            }

            if (interno === 'edad') {
                interno = 'fecha_nacimiento';
            }

            return interno;
        }

        camposPaciente.sort(function (a, b) {
            const internoA =
                obtenerCampoInternoOrden(a);

            const internoB =
                obtenerCampoInternoOrden(b);

            const prioridadA =
                ordenPaciente[internoA] ?? 1000;

            const prioridadB =
                ordenPaciente[internoB] ?? 1000;

            if (prioridadA !== prioridadB) {
                return prioridadA - prioridadB;
            }

            return (
                a.__indiceOriginal -
                b.__indiceOriginal
            );
        });


        /*
        * Especie + Raza.
        *
        * Si existen las dos, se muestran juntas.
        * Si existe solamente una, permanece sola.
        */
        let indiceEspecie = -1;
        let indiceRaza = -1;

        camposPaciente.forEach(
            function (campo, indice) {
                const interno =
                    obtenerCampoInternoOrden(
                        campo
                    );

                if (interno === 'especie') {
                    indiceEspecie = indice;
                }

                if (interno === 'raza') {
                    indiceRaza = indice;
                }
            }
        );

        const combinarEspecieRaza =
            indiceEspecie !== -1 &&
            indiceRaza !== -1;

        const indiceCombinado =
            combinarEspecieRaza
                ? Math.min(
                    indiceEspecie,
                    indiceRaza
                )
                : -1;


        /*
        * Render de campos de paciente.
        *
        * col-md-6:
        * dos campos por fila.
        */
        camposPaciente.forEach(
            function (campo, indice) {

                if (
                    combinarEspecieRaza &&
                    (
                        indice === indiceEspecie ||
                        indice === indiceRaza
                    )
                ) {
                    if (indice !== indiceCombinado) {
                        return;
                    }

                    const campoEspecie =
                        camposPaciente[
                            indiceEspecie
                        ];

                    const campoRaza =
                        camposPaciente[
                            indiceRaza
                        ];

                    const etiquetaEspecie =
                        String(
                            campoEspecie.etiqueta ||
                            'Especie'
                        ).trim();

                    const etiquetaRaza =
                        String(
                            campoRaza.etiqueta ||
                            'Raza'
                        ).trim();

                    const valorEspecie =
                        formatearValorCampo(
                            campoEspecie.campo,
                            campoEspecie.valor
                        );

                    const valorRaza =
                        formatearValorCampo(
                            campoRaza.campo,
                            campoRaza.valor
                        );

                    const $columna =
                        $('<div>', {
                            class:
                                claseColumnaPaciente
                        });

                    const $label =
                        $('<div>', {
                            class:
                                'vm-ver-label'
                        }).text(
                            etiquetaEspecie +
                            ', ' +
                            etiquetaRaza
                        );

                    const $valor =
                        $('<div>', {
                            class:
                                'vm-ver-value'
                        }).text(
                            valorEspecie +
                            ', ' +
                            valorRaza
                        );

                    $columna.append(
                        $label,
                        $valor
                    );

                    $paciente.append(
                        $columna
                    );

                    return;
                }


                /*
                * Campo normal de paciente.
                */
                const etiqueta =
                    String(
                        campo.etiqueta ||
                        campo.campo ||
                        'Campo'
                    ).trim();

                const valor =
                    formatearValorCampo(
                        campo.campo,
                        campo.valor
                    );

                const $columna =
                    $('<div>', {
                        class:
                            claseColumnaPaciente
                    });

                const $label =
                    $('<div>', {
                        class:
                            'vm-ver-label'
                    }).text(
                        etiqueta
                    );

                const $valor =
                    $('<div>', {
                        class:
                            'vm-ver-value'
                    }).text(
                        valor
                    );

                $columna.append(
                    $label,
                    $valor
                );

                $paciente.append(
                    $columna
                );
            }
        );

        /*
        * Fecha del informe siempre primero.
        *
        * El resto conserva exactamente el orden
        * que venía desde la configuración.
        */
        camposGeneral.sort(function (a, b) {
            function esFecha(campo) {
                const interno =
                    String(
                        campo.campo_interno ||
                        campo.campo ||
                        ''
                    )
                        .trim()
                        .toLowerCase();

                return (
                    interno === 'fecha_emision' ||
                    interno === 'fecha_examen'
                );
            }

            const fechaA = esFecha(a);
            const fechaB = esFecha(b);

            if (fechaA && !fechaB) {
                return -1;
            }

            if (!fechaA && fechaB) {
                return 1;
            }

            return (
                a.__indiceOriginal -
                b.__indiceOriginal
            );
        });

        /*
        * Campos generales.
        *
        * Con referencia:
        * mantenemos exactamente las 3 columnas actuales.
        *
        * Sin referencia:
        * Fecha arriba completa.
        * Los demás campos abajo, 2 por fila.
        */
        camposGeneral.forEach(
            function (campo) {
                const etiqueta =
                    String(
                        campo.etiqueta ||
                        campo.campo ||
                        'Campo'
                    ).trim();

                const valor =
                    formatearValorCampo(
                        campo.campo,
                        campo.valor
                    );

                const interno =
                    String(
                        campo.campo_interno ||
                        campo.campo ||
                        ''
                    )
                        .trim()
                        .toLowerCase();

                let claseColumna =
                    'col-md-4 vm-ver-campo';

                if (!tieneReferencia) {
                    const esFecha =
                        interno === 'fecha_emision' ||
                        interno === 'fecha_examen';

                    claseColumna =
                        esFecha
                            ? 'col-12 vm-ver-campo'
                            : 'col-md-6 vm-ver-campo';
                }

                const $columna =
                    $('<div>', {
                        class: claseColumna
                    });

                const $label =
                    $('<div>', {
                        class: 'vm-ver-label'
                    }).text(
                        etiqueta
                    );

                const $valor =
                    $('<div>', {
                        class: 'vm-ver-value'
                    }).text(
                        valor
                    );

                $columna.append(
                    $label,
                    $valor
                );

                $general.append(
                    $columna
                );
            }
        );


        if ($paciente.children().length) {
            $('#verInformePacienteWrap')
                .show();
        }

        if ($general.children().length) {
            $('#verInformeGeneralWrap')
                .show();
        }
    }

    function obtenerModal() {
        const modalEl = document.getElementById('modalVerInforme');

        if (!modalEl) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function limpiarVisor() {
        informeActual = null;

        $('#verInformeTitulo')
            .text('Informe');

        $('#verInformeCamposPaciente')
            .empty();

        $('#verInformeCamposGeneral')
            .empty();

        $('#verInformePacienteWrap')
            .hide();

        $('#verInformeGeneralWrap')
            .hide();

        $('#verInformeDestacadoTitulo')
            .text('');

        $('#verInformeDestacadoWrap')
            .hide();

        $('#verInformeContenido')
            .empty();

        /*
        * Quitamos el iframe al cerrar/cambiar de informe
        * para que el PDF no continúe cargado internamente.
        */
        $('#verInformePdfContenido')
            .empty();

        $('#btnVerInformeDescargar')
            .attr('href', '#');

        $('#btnVerInformeEditar')
            .attr('href', '#');

        $('#verInformeLoading')
            .show();

        $('#verInformeVistaSistema')
            .hide();

        $('#verInformeVistaPdf')
            .hide();

        activarBotonVista('informe');
    }

    function cargarDatosInforme(informe) {
        informeActual = informe;

        const paciente = textoValor(
            informe.paciente,
            'Sin nombre'
        );

        const tipoExamen = textoValor(
            informe.tipo_examen,
            'Sin tipo de examen'
        );

        /*
        * Cabecera del visor.
        *
        * Se mantiene independiente de los campos
        * configurables del cuerpo.
        */
        $('#verInformeTitulo').text(
            paciente + ' · ' + tipoExamen
        );

        const tieneReferencia =
            parseInt(
                informe.es_destacado,
                10
            ) === 1;

        $('#verInformeResumenGrid')
            .toggleClass(
                'is-with-referencia',
                tieneReferencia
            )
            .toggleClass(
                'is-no-referencia',
                !tieneReferencia
            );


        /*
        * Campos configurables.
        *
        * getInforme.php ya los entrega según:
        *
        * configuracion_informe_id
        * visible
        * etiqueta
        * orden
        */
        renderizarCamposInforme(
            informe
        );


        /*
        * Caso de referencia.
        *
        * Es una propiedad del certificado y no forma
        * parte de la configuración de campos del PDF.
        */
        if (
            parseInt(
                informe.es_destacado,
                10
            ) === 1
        ) {
            const tituloDestacado =
                String(
                    informe.destacado_titulo || ''
                ).trim();

            $('#verInformeDestacadoTitulo')
                .text(
                    tituloDestacado !== ''
                        ? tituloDestacado
                        : 'Este informe fue marcado como caso de referencia.'
                );

            $('#verInformeDestacadoWrap')
                .show();

        } else {

            $('#verInformeDestacadoWrap')
                .hide();

            $('#verInformeDestacadoTitulo')
                .text('');
        }


        /*
        * Contenido almacenado del informe.
        */
        const contenido =
            String(
                informe.contenido_html || ''
            ).trim();

        if (contenido !== '') {

            $('#verInformeContenido')
                .html(contenido);

        } else {

            $('#verInformeContenido')
                .html(
                    '<div class="text-muted text-center py-4">' +
                        '<i class="fas fa-file-pdf mb-2 d-block"></i>' +
                        'Este informe no tiene contenido de lectura almacenado.<br>' +
                        'Puedes consultarlo desde la vista PDF.' +
                    '</div>'
                );
        }


        /*
        * Descargar PDF.
        */
        $('#btnVerInformeDescargar')
            .attr(
                'href',
                informe.pdf_download_url || '#'
            );


        /*
        * Editar.
        *
        * tipo_ingreso sí corresponde aquí porque determina
        * qué formulario se utiliza para modificar el informe.
        *
        * No se utiliza para decidir el origen de los datos
        * del paciente.
        */
        let editarUrl = '';

        if (
            String(
                informe.tipo_ingreso || ''
            ) === 'manual'
        ) {
            editarUrl =
                'certificado/subir_informe/subir_informe.php' +
                '?action=modificar&id=' +
                encodeURIComponent(
                    informe.id
                );

        } else {

            editarUrl =
                'certificado/certificados.php' +
                '?action=modificar&id=' +
                encodeURIComponent(
                    informe.id
                );
        }

        $('#btnVerInformeEditar')
            .attr(
                'href',
                editarUrl
            );
    }

    function activarBotonVista(vista) {
        const esInforme =
            vista === 'informe';

        $('#btnVerInformeSistema')
            .toggleClass('vm-btn-accent', esInforme)
            .toggleClass('vm-btn-accent-outline', !esInforme);

        $('#btnVerInformePdf')
            .toggleClass('vm-btn-accent', !esInforme)
            .toggleClass('vm-btn-accent-outline', esInforme);
    }

    function mostrarVistaInforme() {
        if (!informeActual) {
            return;
        }

        activarBotonVista('informe');

        $('#verInformeVistaPdf').hide();
        $('#verInformeVistaSistema').show();
    }

    function mostrarVistaPdf() {
        if (!informeActual) {
            return;
        }

        activarBotonVista('pdf');

        $('#verInformeVistaSistema').hide();
        $('#verInformeVistaPdf').show();

        const $contenedor =
            $('#verInformePdfContenido');

        /*
         * Solo construimos el iframe la primera vez.
         */
        if (!$contenedor.find('iframe').length) {

            const pdfUrl =
                String(
                    informeActual.pdf_url || ''
                ).trim();

            if (!pdfUrl) {
                $contenedor.html(
                    '<div class="text-muted text-center py-5">' +
                        'No hay PDF disponible para este informe.' +
                    '</div>'
                );

                return;
            }

            const iframe =
                $('<iframe>', {
                    src: pdfUrl,
                    title:
                        'PDF informe ' +
                        informeActual.id
                }).css({
                    width: '100%',
                    height: '75vh',
                    border: '0'
                });

            $contenedor
                .empty()
                .append(iframe);
        }
    }

    function abrirVisorInforme(id, vistaInicial = 'informe') {
        const certificadoId =
            parseInt(id, 10);

        if (!certificadoId) {
            Swal.fire(
                'Error',
                'Informe inválido.',
                'error'
            );

            return;
        }

        modoInicial =
            vistaInicial === 'pdf'
                ? 'pdf'
                : 'informe';

        limpiarVisor();

        const modal =
            obtenerModal();

        if (!modal) {
            Swal.fire(
                'Error',
                'No se encontró el visor de informes.',
                'error'
            );

            return;
        }

        modal.show();

        if (requestInforme) {
            requestInforme.abort();
            requestInforme = null;
        }

        requestInforme = $.ajax({
            url:
                'certificado/ver/getInforme.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: certificadoId
            },

            success: function (response) {

                if (
                    !response ||
                    response.status !== 'success' ||
                    !response.informe
                ) {
                    $('#verInformeLoading').hide();

                    Swal.fire(
                        'Error',
                        response?.message ||
                        'No se pudo cargar el informe.',
                        'error'
                    );

                    modal.hide();
                    return;
                }

                cargarDatosInforme(
                    response.informe
                );

                $('#verInformeLoading').hide();

                if (modoInicial === 'pdf') {
                    mostrarVistaPdf();
                } else {
                    mostrarVistaInforme();
                }
            },

            error: function (xhr, status) {

                if (status === 'abort') {
                    return;
                }

                $('#verInformeLoading').hide();

                let mensaje =
                    'No se pudo cargar el informe.';

                if (
                    xhr &&
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    mensaje =
                        xhr.responseJSON.message;
                }

                modal.hide();

                Swal.fire(
                    'Error',
                    mensaje,
                    'error'
                );
            },

            complete: function () {
                requestInforme = null;
            }
        });
    }


    /*
     * Menú → Ver informe.
     */
    $(document)
        .off(
            'click.verInforme',
            '.btn-ver-informe'
        )
        .on(
            'click.verInforme',
            '.btn-ver-informe',
            function (e) {
                e.preventDefault();

                abrirVisorInforme(
                    $(this).data('id'),
                    'informe'
                );
            }
        );


    /*
     * Menú → Ver PDF.
     */
    $(document)
        .off(
            'click.verPdfInforme',
            '.btn-ver-pdf-informe'
        )
        .on(
            'click.verPdfInforme',
            '.btn-ver-pdf-informe',
            function (e) {
                e.preventDefault();

                abrirVisorInforme(
                    $(this).data('id'),
                    'pdf'
                );
            }
        );


    /*
     * Botones internos del modal.
     */
    $(document)
        .off(
            'click.verInformeSistema',
            '#btnVerInformeSistema'
        )
        .on(
            'click.verInformeSistema',
            '#btnVerInformeSistema',
            function () {
                mostrarVistaInforme();
            }
        );


    $(document)
        .off(
            'click.verInformePdf',
            '#btnVerInformePdf'
        )
        .on(
            'click.verInformePdf',
            '#btnVerInformePdf',
            function () {
                mostrarVistaPdf();
            }
        );


    /*
     * Antes de ir a editar cerramos el modal.
     * El enlace conserva .ajax-link para utilizar
     * la navegación habitual del sistema.
     */
    $(document)
        .off(
            'click.verInformeEditar',
            '#btnVerInformeEditar'
        )
        .on(
            'click.verInformeEditar',
            '#btnVerInformeEditar',
            function () {
                const modal =
                    obtenerModal();

                if (modal) {
                    modal.hide();
                }
            }
        );


    /*
     * Limpiar al cerrar.
     */
    $(document)
        .off(
            'hidden.bs.modal.verInforme',
            '#modalVerInforme'
        )
        .on(
            'hidden.bs.modal.verInforme',
            '#modalVerInforme',
            function () {

                if (requestInforme) {
                    requestInforme.abort();
                    requestInforme = null;
                }

                $('#verInformePdfContenido')
                    .empty();

                informeActual = null;
            }
        );


    /*
     * Dejamos disponible la función por si después
     * queremos abrir el visor desde otro lugar.
     */
    window.abrirVisorInforme =
        abrirVisorInforme;

})();