<?php
###########################################
require_once("../config.php");
credenciales('perfil', 'listar');
###########################################

$mysqli = conn();

$sel = "SELECT * 
        FROM perfiles 
        WHERE deleted_at IS NULL 
        ORDER BY id DESC";
$stmt = $mysqli->prepare($sel);
$stmt->execute();
$res = $stmt->get_result();

?>
<div id="perfil" data-page-id="perfil">
  <h1 class="h3 mb-3"><strong>Perfiles</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
              <?php 
              if (in_array('ingresar', $acceso_aplicaciones['perfil'] ?? [])): ?>
                <a href="perfil/perfiles.php" class="btn btn-primary ajax-link">
                  <i class="fas fa-plus me-1"></i> Agregar perfil
                </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="table-responsive">
            <table id="ventas" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
              <thead>
                <tr>
                  <th>N</th>
                  <th>Nombre</th>
                  <th>Descripcion</th>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['perfil'] ?? [])): ?>
                    <th
                        class="dt-col-acciones"
                        data-orderable="false"
                    >
                        Acciones
                    </th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                while ($fila = $res->fetch_assoc()) {
                  $id              = $fila['id'];
                  $nombre          = $fila['nombre'];
                  $descripcion     = $fila['descripcion'];

                ?>
                  <tr>
                    <td><?php print "$i"?></td>
                    <td data-content="<?php echo htmlspecialchars($nombre); ?>">
                      <?php echo contenidoMax($nombre); ?>
                    </td>
                    <td data-content="<?php echo htmlspecialchars($descripcion); ?>">
                      <?php echo contenidoMax($descripcion); ?>
                    </td>
                    <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['perfil'] ?? [])): ?>
                    <td class="dt-col-acciones">
                      <div class="dropdown position-relative">

                        <button
                            class="btn btn-outline-info dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                          <i class="fas fa-ellipsis-v"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end">

                          <?php if (in_array('modificar', $acceso_aplicaciones['perfil'] ?? [])): ?>

                            <a
                                class="dropdown-item ajax-link"
                                href="perfil/perfiles.php?action=modificar&id=<?php echo $id; ?>"
                            >
                              Modificar
                            </a>

                          <?php endif; ?>

                          <?php if (in_array('eliminar', $acceso_aplicaciones['perfil'] ?? [])): ?>

                            <a
                                class="dropdown-item"
                                href="#"
                                onclick="confirmDelete('<?php echo $id; ?>')"
                            >
                              Eliminar
                            </a>

                          <?php endif; ?>

                        </div>
                      </div>
                    </td>
                    <?php endif; ?>
                  </tr>
                <?php
                  $i++;
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>



<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="infoModalLabel">Información completa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
  function showModal(content) {
    document.getElementById('modalContent').textContent = content;
    var myModal = new bootstrap.Modal(document.getElementById('infoModal'));
    myModal.show();
  }
</script>


<script>
  $(document).ready(function () {

      const $tabla = $('#ventas');

      if (
          !$tabla.length ||
          $.fn.DataTable.isDataTable($tabla[0])
      ) {
          return;
      }

      function contenidoExportacion(data, row, column, node) {
          const contenido = $(node).data('content');

          return (
              contenido !== undefined &&
              contenido !== null
          )
              ? contenido
              : data;
      }

      $tabla.DataTable({
          responsive: true,

          dom:
              '<"dt-toolbar"Bf>'
              + 'rt'
              + '<"dt-footer"ip>',

          buttons: [
              {
                  extend: 'excelHtml5',
                  text:
                      '<i class="fas fa-file-excel me-1"></i> Excel',
                  title: 'Perfiles',

                  exportOptions: {
                      columns: [0, 1, 2],

                      format: {
                          body: contenidoExportacion
                      }
                  }
              },

              {
                  extend: 'pdfHtml5',
                  text:
                      '<i class="fas fa-file-pdf me-1"></i> PDF',
                  title: 'Perfiles',

                  exportOptions: {
                      columns: [0, 1, 2],

                      format: {
                          body: contenidoExportacion
                      }
                  }
              },

              {
                  extend: 'print',
                  text:
                      '<i class="fas fa-print me-1"></i> Imprimir',
                  title: 'Perfiles',

                  exportOptions: {
                      columns: [0, 1, 2],

                      format: {
                          body: contenidoExportacion
                      }
                  }
              }
          ],

          language:
              window.VETMIND_DATATABLE_LANGUAGE
      });

      const $inputBuscar =
          $tabla
              .closest('.dataTables_wrapper')
              .find(
                  '.dataTables_filter input[type="search"]'
              );

      $inputBuscar.attr({
          id: 'inputBuscar_perfiles',
          name: 'inputBuscar_perfiles'
      });

  });


  function confirmDelete(id) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esta acción no se puede deshacer',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: 'perfil/updPerfiles.php',
          type: 'POST',
          data: {
            action: 'eliminar',
            id: id
          },
          success: function(response) {
            let jsonResponse = JSON.parse(response);
            if (jsonResponse.status === 'success') {
              $('#content').load('perfil/lisPerfiles.php');
              Swal.fire({
                icon: 'success',
                title: 'Eliminado',
                text: jsonResponse.message,
                confirmButtonText: 'OK'
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: jsonResponse.message,
                confirmButtonText: 'OK'
              });
            }
          },
          error: function() {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Hubo un problema al eliminar el perfil.',
              confirmButtonText: 'OK'
            });
          }
        });
      }
    });
  }
</script>