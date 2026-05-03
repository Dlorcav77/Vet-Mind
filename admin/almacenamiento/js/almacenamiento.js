// admin/almacenamiento/js/almacenamiento.js

(function () {
    let tablaAlmacenamiento = null;
    let gruposOriginales = [];
    let grabacionesOriginales = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getGrupoByKey(grupoKey) {
        return gruposOriginales.find(function (grupo) {
            return grupo.grupo_key === grupoKey;
        }) || null;
    }

    function renderContador(valor, label, clase) {
        return ''
            + '<span class="alm-badge ' + clase + '">'
            + escapeHtml(valor)
            + ' <span>' + escapeHtml(label) + '</span>'
            + '</span>';
    }

    function renderFaltantes(total) {
        total = parseInt(total || 0, 10);

        if (total > 0) {
            return '<span class="alm-badge alm-badge-missing"><i class="fas fa-exclamation-triangle"></i> ' + total + '</span>';
        }

        return '<span class="alm-badge alm-badge-ok"><i class="fas fa-check-circle"></i> 0</span>';
    }

    function renderPaciente(grupo) {
        return ''
            + '<div class="alm-paciente-title">' + escapeHtml(grupo.paciente) + '</div>'
            + '<div class="alm-paciente-sub">' + escapeHtml(grupo.propietario) + '</div>';
    }

    function renderAcciones(grupo) {
        const key = escapeHtml(grupo.grupo_key);

        let html = '<div class="btn-group btn-group-sm" role="group">';

        html += ''
            + '<button type="button" class="btn btn-outline-primary btn-alm-informes" data-grupo-key="' + key + '" title="Ver informes">'
            + '  <i class="fas fa-file-medical"></i>'
            + '</button>';

        html += ''
            + '<button type="button" class="btn btn-outline-danger btn-alm-pdfs" data-grupo-key="' + key + '" title="Ver PDFs" ' + ((grupo.pdf_total || 0) <= 0 ? 'disabled' : '') + '>'
            + '  <i class="fas fa-file-pdf"></i>'
            + '</button>';

        html += ''
            + '<button type="button" class="btn btn-outline-success btn-alm-imagenes" data-grupo-key="' + key + '" title="Ver imágenes" ' + ((grupo.imagenes_total || 0) <= 0 ? 'disabled' : '') + '>'
            + '  <i class="fas fa-images"></i>'
            + '</button>';

        html += '</div>';

        return html;
    }

    function pintarResumen(resumen) {
        $('#almTotalPeso').text(resumen.total_label || '0 B');
        $('#almTotalArchivos').text((resumen.total_archivos || 0) + ' archivos');

        $('#almTotalPdf').text(resumen.pdf_total || 0);
        $('#almPesoPdf').text(resumen.pdf_label || '0 B');

        $('#almTotalImagenes').text(resumen.imagenes_total || 0);
        $('#almPesoImagenes').text(resumen.imagenes_label || '0 B');

        $('#almTotalGrabaciones').text(resumen.grabaciones_total || 0);
        $('#almPesoGrabaciones').text(resumen.grabaciones_label || '0 B');

        $('#almTotalGrupos').text(resumen.total_grupos || 0);
        $('#almTotalFaltantes').text((resumen.faltantes_total || 0) + ' faltantes');

        $('#btnVerGrabaciones').prop('disabled', parseInt(resumen.grabaciones_total || 0, 10) <= 0);
    }

    function aplicarFiltros() {
        const filtro = $('#filtroEstadoArchivo').val();

        let filtrados = gruposOriginales.filter(function (grupo) {
            if (filtro === 'con_pdf') {
                return (grupo.pdf_total || 0) > 0;
            }

            if (filtro === 'con_imagen') {
                return (grupo.imagenes_total || 0) > 0;
            }

            if (filtro === 'con_faltantes') {
                return (grupo.faltantes_total || 0) > 0;
            }

            return true;
        });

        cargarTabla(filtrados);
    }

    function cargarTabla(grupos) {
        const filas = grupos.map(function (grupo) {
            return [
                renderPaciente(grupo),
                escapeHtml(grupo.ultima_fecha_label || '-'),
                renderContador(grupo.informes_total || 0, 'informes', 'alm-badge-total'),
                renderContador(grupo.pdf_total || 0, grupo.pdf_label || '0 B', 'alm-badge-pdf'),
                renderContador(grupo.imagenes_total || 0, grupo.imagenes_label || '0 B', 'alm-badge-img'),
                escapeHtml(grupo.total_label || '0 B'),
                renderFaltantes(grupo.faltantes_total || 0),
                renderAcciones(grupo),
                escapeHtml(grupo.ultima_fecha_sort || ''),
                parseInt(grupo.ultimo_certificado_id || 0, 10)
            ];
        });

        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tablaAlmacenamiento')) {
            tablaAlmacenamiento = $('#tablaAlmacenamiento').DataTable();
            tablaAlmacenamiento.clear();
            tablaAlmacenamiento.rows.add(filas);
            tablaAlmacenamiento.draw();
            return;
        }

        if ($.fn.DataTable) {
            tablaAlmacenamiento = $('#tablaAlmacenamiento').DataTable({
                data: filas,
                responsive: true,
                pageLength: 25,
                order: [[8, 'desc'], [9, 'desc']],
                columnDefs: [
                    {
                        targets: [8, 9],
                        visible: false,
                        searchable: false
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                }
            });
            return;
        }

        const $tbody = $('#tablaAlmacenamiento tbody');
        $tbody.empty();

        filas.forEach(function (fila) {
            $tbody.append(
                '<tr>'
                + fila.map(function (col, index) {
                    if (index === 8 || index === 9) {
                        return '<td style="display:none;">' + col + '</td>';
                    }

                    return '<td>' + col + '</td>';
                }).join('')
                + '</tr>'
            );
        });
    }

    function cargarAlmacenamiento() {
        $('#btnRecargarAlmacenamiento')
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-1"></i> Cargando');

        $.ajax({
            url: 'almacenamiento/api/listar_archivos.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (!response || response.status !== 'success') {
                    Swal.fire('Error', response?.message || 'No se pudo cargar el almacenamiento.', 'error');
                    return;
                }

                gruposOriginales = response.grupos || [];
                grabacionesOriginales = response.grabaciones || [];

                pintarResumen(response.resumen || {});
                aplicarFiltros();
            },
            error: function (xhr) {
                let msg = 'No se pudo cargar el almacenamiento.';

                if (xhr.responseText) {
                    msg += '\n' + xhr.responseText;
                }

                Swal.fire('Error', msg, 'error');
                console.error('Error almacenamiento:', xhr);
            },
            complete: function () {
                $('#btnRecargarAlmacenamiento')
                    .prop('disabled', false)
                    .html('<i class="fas fa-sync-alt me-1"></i> Recargar');
            }
        });
    }

    function abrirModalPdfs(grupoKey) {
        const grupo = getGrupoByKey(grupoKey);

        if (!grupo) {
            return;
        }

        $('#modalAlmPdfsSubtitulo').text(grupo.paciente + ' / ' + grupo.propietario);

        const $body = $('#modalAlmPdfsBody');
        $body.empty();

        if (!grupo.pdfs || !grupo.pdfs.length) {
            $body.html('<tr><td colspan="6" class="text-center text-muted">Sin PDFs asociados.</td></tr>');
        } else {
            grupo.pdfs.forEach(function (pdf) {
                const estado = pdf.existe
                    ? '<span class="alm-badge alm-badge-ok"><i class="fas fa-check-circle"></i> Existe</span>'
                    : '<span class="alm-badge alm-badge-missing"><i class="fas fa-exclamation-triangle"></i> No existe</span>';

                let acciones = '';

                if (pdf.existe) {
                    acciones += ''
                        + '<a href="' + escapeHtml(pdf.url_ver) + '" target="_blank" class="btn btn-outline-primary btn-sm me-1" title="Ver PDF">'
                        + '  <i class="fas fa-eye"></i>'
                        + '</a>';

                    acciones += ''
                        + '<a href="' + escapeHtml(pdf.url_descargar) + '" target="_blank" class="btn btn-outline-secondary btn-sm me-1" title="Descargar PDF">'
                        + '  <i class="fas fa-download"></i>'
                        + '</a>';
                }

                acciones += ''
                    + '<a href="' + escapeHtml(pdf.url_informe) + '" class="btn btn-outline-info btn-sm ajax-link" title="Ir al informe">'
                    + '  <i class="fas fa-file-medical"></i>'
                    + '</a>';

                $body.append(
                    '<tr>'
                    + '<td>' + escapeHtml(pdf.tipo_examen) + '</td>'
                    + '<td>' + escapeHtml(pdf.fecha_informe) + '</td>'
                    + '<td><span title="' + escapeHtml(pdf.ruta) + '">' + escapeHtml(pdf.nombre) + '</span></td>'
                    + '<td>' + escapeHtml(pdf.size_label) + '</td>'
                    + '<td>' + estado + '</td>'
                    + '<td>' + acciones + '</td>'
                    + '</tr>'
                );
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalAlmPdfs'));
        modal.show();
    }

    function abrirModalImagenes(grupoKey) {
        const grupo = getGrupoByKey(grupoKey);

        if (!grupo) {
            return;
        }

        $('#modalAlmImagenesSubtitulo').text(grupo.paciente + ' / ' + grupo.propietario);

        const $body = $('#modalAlmImagenesBody');
        $body.empty();

        if (!grupo.imagenes || !grupo.imagenes.length) {
            $body.html('<div class="col-12 text-center text-muted">Sin imágenes asociadas.</div>');
        } else {
            grupo.imagenes.forEach(function (img) {
                const estado = img.existe
                    ? '<span class="alm-badge alm-badge-ok"><i class="fas fa-check-circle"></i> Existe</span>'
                    : '<span class="alm-badge alm-badge-missing"><i class="fas fa-exclamation-triangle"></i> No existe</span>';

                let preview = ''
                    + '<div class="d-flex align-items-center justify-content-center bg-light" style="height:150px;">'
                    + '  <i class="fas fa-image text-muted fa-2x"></i>'
                    + '</div>';

                if (img.existe) {
                    preview = '<a href="' + escapeHtml(img.url_ver) + '" target="_blank"><img src="' + escapeHtml(img.url_ver) + '" alt=""></a>';
                }

                let acciones = '';

                if (img.existe) {
                    acciones += ''
                        + '<a href="' + escapeHtml(img.url_ver) + '" target="_blank" class="btn btn-outline-primary btn-sm">'
                        + '  <i class="fas fa-eye me-1"></i>Ver'
                        + '</a>';

                    acciones += ''
                        + '<a href="' + escapeHtml(img.url_descargar) + '" target="_blank" class="btn btn-outline-secondary btn-sm">'
                        + '  <i class="fas fa-download me-1"></i>Descargar'
                        + '</a>';
                }

                acciones += ''
                    + '<a href="' + escapeHtml(img.url_informe) + '" class="btn btn-outline-info btn-sm ajax-link">'
                    + '  <i class="fas fa-file-medical me-1"></i>Informe'
                    + '</a>';

                $body.append(
                    '<div class="col-12 col-sm-6 col-lg-4 col-xl-3">'
                    + '  <div class="alm-img-card">'
                    +        preview
                    + '    <div class="alm-img-card-body">'
                    + '      <div class="alm-img-name" title="' + escapeHtml(img.ruta) + '">' + escapeHtml(img.nombre) + '</div>'
                    + '      <div class="alm-img-meta mb-2">' + escapeHtml(img.tipo_examen) + ' · ' + escapeHtml(img.fecha_informe) + '</div>'
                    + '      <div class="d-flex justify-content-between align-items-center mb-2">'
                    + '        <span class="small text-muted">' + escapeHtml(img.size_label) + '</span>'
                    +          estado
                    + '      </div>'
                    + '      <div class="d-flex flex-wrap gap-1">'
                    +          acciones
                    + '      </div>'
                    + '    </div>'
                    + '  </div>'
                    + '</div>'
                );
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalAlmImagenes'));
        modal.show();
    }

    function abrirModalInformes(grupoKey) {
        const grupo = getGrupoByKey(grupoKey);

        if (!grupo) {
            return;
        }

        $('#modalAlmInformesSubtitulo').text(grupo.paciente + ' / ' + grupo.propietario);

        const $body = $('#modalAlmInformesBody');
        $body.empty();

        if (!grupo.informes || !grupo.informes.length) {
            $body.html('<tr><td colspan="4" class="text-center text-muted">Sin informes asociados.</td></tr>');
        } else {
            grupo.informes.forEach(function (informe) {
                $body.append(
                    '<tr>'
                    + '<td>' + escapeHtml(informe.tipo_examen) + '</td>'
                    + '<td>' + escapeHtml(informe.fecha_informe) + '</td>'
                    + '<td>' + escapeHtml(informe.tipo_ingreso) + '</td>'
                    + '<td>'
                    + '  <a href="' + escapeHtml(informe.url_informe) + '" class="btn btn-outline-info btn-sm ajax-link">'
                    + '    <i class="fas fa-file-medical me-1"></i>Abrir'
                    + '  </a>'
                    + '</td>'
                    + '</tr>'
                );
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalAlmInformes'));
        modal.show();
    }

    function abrirModalGrabaciones() {
        const $body = $('#modalAlmGrabacionesBody');
        $body.empty();

        $('#modalAlmGrabacionesSubtitulo').text(
            (grabacionesOriginales.length || 0) + ' grabación(es) detectada(s)'
        );

        if (!grabacionesOriginales.length) {
            $body.html('<tr><td colspan="5" class="text-center text-muted">Sin grabaciones detectadas.</td></tr>');
        } else {
            grabacionesOriginales.forEach(function (audio) {
                let acciones = '';

                acciones += ''
                    + '<a href="' + escapeHtml(audio.url_ver) + '" target="_blank" class="btn btn-outline-primary btn-sm me-1" title="Abrir audio">'
                    + '  <i class="fas fa-play"></i>'
                    + '</a>';

                acciones += ''
                    + '<a href="' + escapeHtml(audio.url_descargar) + '" target="_blank" class="btn btn-outline-secondary btn-sm me-1" title="Descargar audio">'
                    + '  <i class="fas fa-download"></i>'
                    + '</a>';

                $body.append(
                    '<tr>'
                    + '<td>' + escapeHtml(audio.fecha_archivo || '-') + '</td>'
                    + '<td>' + escapeHtml(audio.nombre || '-') + '</td>'
                    + '<td><span title="' + escapeHtml(audio.ruta || '') + '">' + escapeHtml(audio.ruta || '-') + '</span></td>'
                    + '<td>' + escapeHtml(audio.size_label || '0 B') + '</td>'
                    + '<td>' + acciones + '</td>'
                    + '</tr>'
                );
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalAlmGrabaciones'));
        modal.show();
    }

    $('#btnRecargarAlmacenamiento')
        .off('click.almacenamiento')
        .on('click.almacenamiento', function () {
            cargarAlmacenamiento();
        });

    $('#filtroEstadoArchivo')
        .off('change.almacenamiento')
        .on('change.almacenamiento', function () {
            aplicarFiltros();
        });

    $('#btnVerGrabaciones')
        .off('click.almGrabaciones')
        .on('click.almGrabaciones', function () {
            abrirModalGrabaciones();
        });

    $(document)
        .off('click.almPdfs', '.btn-alm-pdfs')
        .on('click.almPdfs', '.btn-alm-pdfs', function () {
            abrirModalPdfs($(this).data('grupo-key'));
        });

    $(document)
        .off('click.almImagenes', '.btn-alm-imagenes')
        .on('click.almImagenes', '.btn-alm-imagenes', function () {
            abrirModalImagenes($(this).data('grupo-key'));
        });

    $(document)
        .off('click.almInformes', '.btn-alm-informes')
        .on('click.almInformes', '.btn-alm-informes', function () {
            abrirModalInformes($(this).data('grupo-key'));
        });

    cargarAlmacenamiento();
})();