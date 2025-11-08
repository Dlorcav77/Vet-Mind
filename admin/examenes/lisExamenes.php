<?php
###########################################
require_once("../config.php");
credenciales('examenes', 'listar');
###########################################

$mysqli = conn();

$sel = "SELECT id, nombre, descripcion, estado
        FROM tipo_examen
        WHERE veterinario_id = ?
        ORDER BY id DESC";
$stmt = $mysqli->prepare($sel);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<style>
    .dataTables_wrapper .dt-buttons {
        float: none;
        text-align: center;
    }
    table.dataTable thead th, table.dataTable tfoot th {
        font-family: Arial, sans-serif;
        font-size: 14px;
    }
    table.dataTable tbody td {
        font-family: Arial, sans-serif;
        font-size: 12px;
    }
</style>

<div id="examenes" data-page-id="examenes">
  <h1 class="h3 mb-3"><strong>Tipos de Examen</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
              <?php if (in_array('ingresar', $acceso_aplicaciones['examenes'] ?? [])): ?>
                <a href="examenes/examenes.php" class="btn btn-primary ajax-link">
                  <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar Tipo de Examen
                </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="table-responsive">
            <table id="tablaExamenes" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th width="50">N°</th>
                  <th>Nombre</th>
                  <th>Descripción</th>
                  <th>Estado</th>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['examenes'] ?? [])): ?>
                    <th>Acciones</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
              <?php
              $i = 1;
              while ($fila = $res->fetch_assoc()) {
                $id          = $fila['id'];
                $nombre      = htmlspecialchars($fila['nombre']);
                $descripcion = htmlspecialchars($fila['descripcion']);
                $estado      = htmlspecialchars($fila['estado']);
                ?>
                <tr>
                  <td><?= $i ?></td>
                  <td><?= $nombre ?></td>
                  <td><?= $descripcion ?></td>
                  <td><?= $estado ?></td>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['examenes'] ?? [])): ?>
                  <td align='center'>
                    <div class="dropdown position-relative">
                      <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if (in_array('modificar', $acceso_aplicaciones['examenes'] ?? [])): ?>
                          <a class="dropdown-item ajax-link" href="examenes/examenes.php?action=modificar&id=<?= $id ?>">Modificar</a>
                        <?php endif; ?>
                        <?php if (in_array('eliminar', $acceso_aplicaciones['examenes'] ?? [])): ?>
                          <a class="dropdown-item" href="#" onclick="confirmDelete('<?= $id ?>')">Eliminar</a>
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

<script>
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
        url: 'examenes/updExamenes.php',
        type: 'POST',
        data: { action: 'eliminar', id: id },
        success: function(response) {
          let jsonResponse = JSON.parse(response);
          if (jsonResponse.status === 'success') {
            $('#content').load('examenes/lisExamenes.php');
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
            text: 'Hubo un problema al eliminar el tipo de examen.',
            confirmButtonText: 'OK'
          });
        }
      });
    }
  });
}
</script>
