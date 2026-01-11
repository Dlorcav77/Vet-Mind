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
<style>
  .dataTables_wrapper .dt-buttons {
    float: none;
    text-align: center;
  }

  table.dataTable thead th,
  table.dataTable tfoot th {
    font-family: Arial, sans-serif;
    font-size: 14px;
  }

  table.dataTable tbody td {
    font-family: Arial, sans-serif;
    font-size: 12px;
  }

  .dataTables_wrapper .dt-buttons {
    float: none;
    text-align: center;
  }
</style>
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
                  <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar perfil
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
                    <th>Acciones</th>
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
                    <td align='center'>
                      <div class="dropdown position-relative">
                        <button  class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="align-middle" data-feather="more-vertical"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                          <?php if (in_array('modificar', $acceso_aplicaciones['perfil'] ?? [])): ?>
                            <a class="dropdown-item ajax-link" href="perfil/perfiles.php?action=modificar&id=<?php echo $id; ?>">Modificar</a>
                          <?php endif; if (in_array('eliminar', $acceso_aplicaciones['perfil'] ?? [])): ?>
                            <a class="dropdown-item" href="#" onclick="confirmDelete('<?php echo $id; ?>')">Eliminar</a>
                          <?php endif; ?>
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
  $(document).ready(function() {
    $('#ventas').DataTable({
      responsive: true,
      dom: 'Bfrtip',
      buttons: [{
          extend: 'excelHtml5',
          text: 'Excel',
          title: 'Perfiles', 
          exportOptions: {
            columns: [0, 1, 2],
            format: {
              body: function (data, row, column, node) {
                return $(node).data('content') ? $(node).data('content') : data;
              }
            }
          }
        },
        {
          extend: 'pdfHtml5',
          title: 'Perfiles', 
          text: 'PDF',
          exportOptions: {
            columns: [0, 1, 2],
            format: {
              body: function (data, row, column, node) {
                return $(node).data('content') ? $(node).data('content') : data;
              }
            }
          }
        },
        {
          extend: 'print',
          title: 'Perfiles', 
          text: 'Imprimir',
          exportOptions: {
            columns: [0, 1, 2],
            format: {
              body: function (data, row, column, node) {
                return $(node).data('content') ? $(node).data('content') : data;
              }
            }
          }
        }
      ],
      language: {
        "decimal": "",
        "emptyTable": "No hay informaci&oacute;n",
        "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
        "infoEmpty": "Mostrando 0 a 0 de 0 registros",
        "infoFiltered": "(Filtrado de _MAX_ total registros)",
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": "Mostrar _MENU_ registros",
        "loadingRecords": "Cargando...",
        "processing": "Procesando...",
        "search": "Buscar:",
        "zeroRecords": "No se encontraron resultados",
        "paginate": {
          "first": "Primero",
          "last": "Ultimo",
          "next": "Siguiente",
          "previous": "Anterior"
        },
        "aria": {
          "sortAscending": ": Activar para ordenar la columna de manera ascendente",
          "sortDescending": ": Activar para ordenar la columna de manera descendente"
        },
        "buttons": {
          "copy": "Copiar",
          "colvis": "Visibilidad",
          "collection": "Coleccion",
          "colvisRestore": "Restaurar visibilidad",
          "copyKeys": "Presione ctrl o cmd + C para copiar los datos de la tabla al portapapeles. <br><br>Para cancelar, haga clic en este mensaje o presione escape.",
          "copySuccess": {
            "1": "Copiada 1 fila al portapapeles",
            "_": "Copiadas %d filas al portapapeles"
          },
          "copyTitle": "Copiar al portapapeles",
          "csv": "CSV",
          "excel": "Excel",
          "pageLength": {
            "-1": "Mostrar todas las filas",
            "_": "Mostrar %d filas"
          },
          "pdf": "PDF",
          "print": "Imprimir"
        }
      }
    });
  });
</script>
<script>
  feather.replace();

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