<?php
// admin/certificado/lisCertificados.php

###########################################
require_once("../config.php");
credenciales('certificado', 'listar');
###########################################

$mysqli = conn();
global $usuario_id, $acceso_aplicaciones;

// Traer certificados del veterinario actual
$sel = "SELECT 
        c.id, 
        p.nombre AS paciente, 
        p.codigo_paciente,
        t.nombre_completo AS propietario, 
        t.email AS email,  
        c.fecha_examen, 
        c.created_at, 
        c.medico_solicitante, 
        c.recinto, 
        pi.nombre AS tipo_examen,
        c.archivo_pdf,
        c.manual_data,
        c.tipo_ingreso,
        c.es_destacado,
        c.destacado_titulo
      FROM certificados c
      LEFT JOIN pacientes p ON c.paciente_id = p.id
      LEFT JOIN tutores t ON p.tutor_id = t.id
      LEFT JOIN plantilla_informe pi ON c.tipo_estudio = pi.id
      WHERE c.veterinario_id = ?
      ORDER BY c.fecha_examen DESC, c.id DESC
      ";

$stmt = $mysqli->prepare($sel);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<link rel="stylesheet" href="certificado/ver/css/ver.css?v=1">
<style>

    .cert-numero-wrap {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .cert-destacado-star {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        background: transparent;
        color: #d39e00;
        font-size: 12px;
        line-height: 1;
        cursor: pointer;
        transition: transform 0.12s ease, opacity 0.12s ease;
    }

    .cert-destacado-star:hover {
        transform: scale(1.15);
        opacity: 0.8;
    }

    #btnFiltroDestacados {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        font-size: 15px;
        color: #6c757d;
    }

    #btnFiltroDestacados:hover {
        color: #d39e00;
    }

    #btnFiltroDestacados.is-active {
        color: #d39e00;
    }

    #tablaCertificados_wrapper .dataTables_filter {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    #tablaCertificados_wrapper .dataTables_filter label {
        margin-bottom: 0;
    }

</style>

<div id="certificado" data-page-id="certificado">
  <h1 class="h3 mb-3"><strong>Informes Generados</strong></h1>

  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
              <a href="certificado/subir_informe/subir_informe.php" class="btn btn-outline-primary ajax-link">
                <i class="fas fa-upload me-1"></i> Subir Informe
              </a>

              <?php if (in_array('ingresar', $acceso_aplicaciones['certificado'] ?? [])): ?>
                <a href="certificado/certificados.php" class="btn btn-primary ajax-link">
                  <i class="fas fa-plus me-1"></i> Nuevo Informe
                </a>
              <?php endif; ?>
            </div>
          </div>

          <div class="table-responsive">
            <table id="tablaCertificados" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Paciente</th>
                  <th>Propietario</th>
                  <th>Tipo Examen</th>
                  <th>M. Solicitante</th>
                  <th>Recinto</th>
                  <th>Fecha Examen</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php $i = 1; ?>
                <?php while ($fila = $res->fetch_assoc()): ?>
                <?php
                  $paciente = $fila['paciente'] ?? '';
                  $propietario = $fila['propietario'] ?? '';
                  $tipo_ingreso = $fila['tipo_ingreso'] ?? 'sistema';

                  $manual = [];

                  if (!empty($fila['manual_data'])) {
                      $manualTmp = json_decode($fila['manual_data'], true);

                      if (is_array($manualTmp)) {
                          $manual = $manualTmp;
                      }
                  }

                  if (empty($paciente)) {
                      $paciente = $manual['paciente'] ?? 'Sin nombre';
                  }

                  if (empty($propietario)) {
                      $propietario = $manual['propietario'] ?? '-';
                  }

                  $medicoListado = trim((string)($fila['medico_solicitante'] ?? ''));

                  if ($medicoListado === '') {
                      $medicoListado = trim((string)($manual['m_tratante'] ?? ''));
                  }

                  if ($medicoListado === '') {
                      $medicoListado = '-';
                  }

                  $esDestacado = (
                      isset($fila['es_destacado']) &&
                      (int)$fila['es_destacado'] === 1
                  );

                  $destacadoTitulo = trim(
                      (string)($fila['destacado_titulo'] ?? '')
                  );

                  $numeroListado = $i++;

                  $textoBusquedaNumero = (string)$numeroListado;

                  if ($esDestacado) {
                      $textoBusquedaNumero .= ' destacado';

                      if ($destacadoTitulo !== '') {
                          $textoBusquedaNumero .= ' ' . $destacadoTitulo;
                      }
                  }
                ?>
                  <tr data-destacado="<?= $esDestacado ? '1' : '0' ?>">

                    <td
                        data-search="<?= htmlspecialchars($textoBusquedaNumero, ENT_QUOTES) ?>"
                        data-filter="<?= htmlspecialchars($textoBusquedaNumero, ENT_QUOTES) ?>"
                    >
                        <span class="cert-numero-wrap">

                            <span><?= $numeroListado ?></span>

                            <?php if ($esDestacado): ?>

                                <button
                                    type="button"
                                    class="cert-destacado-star"
                                    data-destacado-titulo="<?= htmlspecialchars($destacadoTitulo, ENT_QUOTES) ?>"
                                    title="Ver referencia destacada"
                                    aria-label="Ver referencia destacada"
                                >
                                    <i class="fas fa-bookmark"></i>
                                </button>

                            <?php endif; ?>

                        </span>
                    </td>
                    <td>
                      <div class="d-flex justify-content-between">
                        <span><?= htmlspecialchars($paciente) ?></span>
                        <?php if (!empty($fila['codigo_paciente'])): ?>
                          <small class="text-muted"><?= htmlspecialchars($fila['codigo_paciente']) ?></small>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($propietario) ?></td>
                    <td><?= htmlspecialchars($fila['tipo_examen'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($medicoListado) ?></td>
                    <td><?= htmlspecialchars($fila['recinto'] ?? '-') ?></td>
                    <td><?= date('d-m-Y', strtotime($fila['fecha_examen'])) ?></td>
                    <td>
                      <div class="dropdown">
                        <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fas fa-ellipsis-v"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end">
                          <a
                              class="dropdown-item btn-ver-informe"
                              href="#"
                              data-id="<?= (int)$fila['id'] ?>"
                          >
                              <i class="fas fa-eye me-2 text-info"></i>
                              Ver
                          </a>
                          <?php if ($tipo_ingreso === 'manual'): ?>
                            <a class="dropdown-item ajax-link" href="certificado/subir_informe/subir_informe.php?action=modificar&id=<?= (int)$fila['id'] ?>">
                              <i class="fas fa-edit me-2 text-primary"></i>Editar
                            </a>
                          <?php else: ?>
                            <a class="dropdown-item ajax-link" href="certificado/certificados.php?action=modificar&id=<?= (int)$fila['id'] ?>">
                              <i class="fas fa-edit me-2 text-primary"></i>Editar
                            </a>
                          <?php endif; ?>
                          <div class="dropdown-divider"></div>
                          <a
                              class="dropdown-item btn-ver-pdf-informe"
                              href="#"
                              data-id="<?= (int)$fila['id'] ?>"
                          >
                              <i class="fas fa-file-pdf me-2 text-danger"></i>
                              Ver PDF
                          </a>
                          <a class="dropdown-item" href="certificado/pdf/descargar.php?id=<?= (int)$fila['id'] ?>&dl=1">
                            <i class="fas fa-download me-2 text-primary"></i>Descargar PDF
                          </a>
                          <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="javascript:void(0)"
                                onclick="event.preventDefault(); event.stopPropagation(); abrirModalCorreo(this, <?= (int)$fila['id'] ?>); return false;"
                                data-id="<?= (int)$fila['id'] ?>"
                                data-paciente="<?= htmlspecialchars($paciente, ENT_QUOTES, 'UTF-8') ?>"
                                data-propietario="<?= htmlspecialchars($propietario, ENT_QUOTES, 'UTF-8') ?>"
                                data-tipo_examen="<?= htmlspecialchars($fila['tipo_examen'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                data-email="<?= htmlspecialchars($fila['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-envelope me-2 text-success"></i> Enviar por correo
                          </a>
                          <a class="dropdown-item" href="#" onclick="confirmDelete(<?= (int)$fila['id'] ?>, '<?= htmlspecialchars($tipo_ingreso, ENT_QUOTES) ?>')">
                            <i class="fas fa-trash-alt me-2 text-danger"></i> Eliminar
                          </a>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div
    class="modal fade"
    id="modalDestacadoCertificado"
    tabindex="-1"
    aria-labelledby="modalDestacadoCertificadoLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5
                    class="modal-title"
                    id="modalDestacadoCertificadoLabel"
                >
                    <i class="fas fa-star text-warning me-2"></i>
                    Informe destacado
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                ></button>
            </div>

            <div class="modal-body">

                <div
                    id="modalDestacadoContenido"
                    class="fs-6"
                ></div>

            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/modal_ver_informe.php'; ?>
<?php include 'envio_email/envio_email.php'; ?>

<script src="certificado/ver/js/ver.js?v=1"></script>
<script>
function confirmDelete(id, tipo) {
    Swal.fire({
        title: '¿Eliminar Informe?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const url = tipo === 'manual'
            ? 'certificado/subir_informe/updSubirInforme.php'
            : 'certificado/updCertificados.php';

      $.ajax({
          url: url,
          type: 'POST',
          dataType: 'json',
          data: { action: 'eliminar', id: id },
          success: function (response) {
              const jsonResponse = response;

              if (jsonResponse.status === 'success') {
                  if (typeof destroyTiptapEditors === 'function') {
                      destroyTiptapEditors();
                  }

                  Swal.fire('Eliminado', jsonResponse.message, 'success').then(() => {
                      $('#content').load('certificado/lisCertificados.php');
                  });
              } else {
                  Swal.fire('Error', jsonResponse.message || 'No se pudo eliminar el informe.', 'error');
              }
          },
          error: function (xhr) {
              console.error('Error AJAX al eliminar:', xhr.responseText);
              Swal.fire('Error', 'No se pudo eliminar el Informe.', 'error');
          }
      });
    });
}
</script>
<script>
(function () {

    window.vmCertificadosSoloDestacados =
        window.vmCertificadosSoloDestacados || false;

    /*
     * Eliminamos un filtro anterior si esta pantalla
     * ya había sido cargada anteriormente por AJAX.
     */
    if (
        window.vmFiltroDestacadosCertificados &&
        $.fn.dataTable &&
        $.fn.dataTable.ext
    ) {
        const filtros = $.fn.dataTable.ext.search;

        const indice = filtros.indexOf(
            window.vmFiltroDestacadosCertificados
        );

        if (indice !== -1) {
            filtros.splice(indice, 1);
        }
    }

    /*
     * Filtro DataTables.
     *
     * Solo afecta a #tablaCertificados.
     * Las demás tablas del sistema siguen funcionando
     * normalmente.
     */
    window.vmFiltroDestacadosCertificados = function (
        settings,
        data,
        dataIndex
    ) {
        if (
            !settings.nTable ||
            settings.nTable.id !== 'tablaCertificados'
        ) {
            return true;
        }

        if (!window.vmCertificadosSoloDestacados) {
            return true;
        }

        const filaData = settings.aoData[dataIndex];

        if (!filaData || !filaData.nTr) {
            return false;
        }

        return (
            filaData.nTr.getAttribute('data-destacado') === '1'
        );
    };

    $.fn.dataTable.ext.search.push(
        window.vmFiltroDestacadosCertificados
    );


    function actualizarBotonDestacados() {
        const $btn = $('#btnFiltroDestacados');

        if (!$btn.length) {
            return;
        }

        const activo =
            window.vmCertificadosSoloDestacados === true;

        $btn
            .toggleClass('is-active', activo)
            .attr('aria-pressed', activo ? 'true' : 'false')
            .attr(
                'title',
                activo
                    ? 'Mostrar todos los informes'
                    : 'Mostrar solo casos de referencia'
            )
            .attr(
                'aria-label',
                activo
                    ? 'Mostrar todos los informes'
                    : 'Mostrar solo casos de referencia'
            );

        $btn.html(
            activo
                ? '<i class="fas fa-bookmark"></i>'
                : '<i class="far fa-bookmark"></i>'
        );
    }

    function insertarBotonFiltroDestacados() {
        const $tabla = $('#tablaCertificados');

        if (
            !$tabla.length ||
            !$.fn.DataTable ||
            !$.fn.DataTable.isDataTable($tabla[0])
        ) {
            return;
        }

        const $wrapper =
            $('#tablaCertificados_wrapper');

        const $filter =
            $wrapper.find('.dataTables_filter');

        if (!$filter.length) {
            return;
        }

        if (!$('#btnFiltroDestacados').length) {

          const $boton = $(
              '<button>',
              {
                  type: 'button',
                  id: 'btnFiltroDestacados',
                  title:
                      'Mostrar solo casos de referencia',
                  'aria-label':
                      'Mostrar solo casos de referencia',
                  'aria-pressed':
                      'false'
              }
          );

          $boton.html(
              '<i class="far fa-bookmark"></i>'
          );

            $filter.append($boton);
        }

        actualizarBotonDestacados();

        /*
         * Si DataTables fue reinicializado por global.js
         * mientras el filtro estaba activo,
         * volvemos a dibujarlo.
         */
        if (window.vmCertificadosSoloDestacados) {
            $tabla.DataTable().draw(false);
        }
    }


    /*
     * Clic en estrella junto al buscador.
     */
    $(document)
        .off(
            'click.certFiltroDestacados',
            '#btnFiltroDestacados'
        )
        .on(
            'click.certFiltroDestacados',
            '#btnFiltroDestacados',
            function () {

                window.vmCertificadosSoloDestacados =
                    !window.vmCertificadosSoloDestacados;

                actualizarBotonDestacados();

                const $tabla =
                    $('#tablaCertificados');

                if (
                    $tabla.length &&
                    $.fn.DataTable.isDataTable(
                        $tabla[0]
                    )
                ) {
                    $tabla.DataTable().draw();
                }
            }
        );


    /*
     * Clic en la estrella de un informe.
     */
    $(document)
        .off(
            'click.certDestacadoModal',
            '.cert-destacado-star'
        )
        .on(
            'click.certDestacadoModal',
            '.cert-destacado-star',
            function (e) {

                e.preventDefault();
                e.stopPropagation();

                const titulo =
                    String(
                        $(this).attr(
                            'data-destacado-titulo'
                        ) || ''
                    ).trim();

                if (titulo !== '') {

                    $('#modalDestacadoContenido')
                        .text(titulo);

                } else {

                    $('#modalDestacadoContenido')
                        .html(
                            '<span class="text-muted">' +
                            'Este informe fue marcado como destacado, ' +
                            'pero todavía no tiene un título.' +
                            '</span>'
                        );
                }

                const modalEl =
                    document.getElementById(
                        'modalDestacadoCertificado'
                    );

                if (!modalEl) {
                    return;
                }

                const modal =
                    bootstrap.Modal.getOrCreateInstance(
                        modalEl
                    );

                modal.show();
            }
        );


    /*
     * Primera inicialización.
     *
     * global.js crea DataTables, por eso damos
     * un pequeño margen para colocar nuestro botón.
     */
    setTimeout(function () {
        insertarBotonFiltroDestacados();
    }, 100);


    /*
     * global.js reinicializa DataTables después
     * de llamadas AJAX.
     *
     * Volvemos a insertar solamente nuestro botón
     * cuando corresponda.
     */
    $(document)
        .off(
            'ajaxComplete.certDestacados'
        )
        .on(
            'ajaxComplete.certDestacados',
            function () {

                setTimeout(function () {
                    insertarBotonFiltroDestacados();
                }, 50);

            }
        );

})();
</script>