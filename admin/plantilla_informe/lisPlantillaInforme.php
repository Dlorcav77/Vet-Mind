<?php
###########################################
require_once("../config.php");
credenciales('plantilla_informe', 'listar');
###########################################

$mysqli = conn();
global $acceso_aplicaciones;

// Consulta plantilla_informe (ignora eliminados)
$sel = "SELECT p.id, p.nombre, t.nombre AS tipo_examen, p.estado, p.updated_at
        FROM plantilla_informe p
        JOIN tipo_examen t ON p.tipo_examen_id = t.id
        WHERE p.veterinario_id = ? AND p.deleted_at IS NULL
        ORDER BY p.id DESC";

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

<div id="plantilla_informe" data-page-id="plantilla_informe">
  <h1 class="h3 mb-3"><strong>Plantillas de Informes</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
              <?php if (in_array('ingresar', $acceso_aplicaciones['plantilla_informe'] ?? [])): ?>
                <a href="plantilla_informe/plantillaInforme.php" class="btn btn-primary ajax-link">
                  <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar Plantilla
                </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="table-responsive">
            <table id="tablaPlantillas" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th width="50">N°</th>
                  <th>Nombre</th>
                  <th>Tipo de Examen</th>
                  <th>Estado</th>
                  <th>Última Actualización</th>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['plantilla_informe'] ?? [])): ?>
                    <th>Acciones</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
              <?php
              $i = 1;
              while ($fila = $res->fetch_assoc()) {
                $id           = $fila['id'];
                $nombre       = htmlspecialchars($fila['nombre']);
                $tipoExamen   = htmlspecialchars($fila['tipo_examen']);
                $estado       = ucfirst($fila['estado']);
                $updated_at   = htmlspecialchars($fila['updated_at']);
                ?>
                <tr>
                  <td><?= $i ?></td>
                  <td><?= $nombre ?></td>
                  <td><?= $tipoExamen ?></td>
                  <td><?= $estado ?></td>
                  <td><?= $updated_at ?></td>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['plantilla_informe'] ?? [])): ?>
                  <td align="center">
                    <div class="dropdown position-relative">
                      <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if (in_array('modificar', $acceso_aplicaciones['plantilla_informe'] ?? [])): ?>
                          <a class="dropdown-item ajax-link" href="plantilla_informe/plantillaInforme.php?action=modificar&id=<?= $id ?>">Modificar</a>
                        <?php endif; ?>
                        <?php if (in_array('eliminar', $acceso_aplicaciones['plantilla_informe'] ?? [])): ?>
                          <a class="dropdown-item" href="#" onclick="confirmDelete('<?= $id ?>')">Eliminar</a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="verEjemplos(<?= $id ?>, '<?= addslashes($nombre) ?>')">
                          <i class="fas fa-book me-2"></i> Ejemplos
                        </a>
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


<div class="modal fade" id="modalEjemplos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-book mx-2"></i> Ejemplos de <span id="ejemploTitulo"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="bodyEjemplos">
        <p class="text-muted">Cargando ejemplos...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times"></i> Cerrar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDelete(id) {
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Esta acción eliminará la plantilla permanentemente.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'plantilla_informe/updPlantillaInforme.php',
        type: 'POST',
        data: { action: 'eliminar', id: id },
        success: function(response) {
        // console.log(response);
          let jsonResponse = JSON.parse(response);
          if (jsonResponse.status === 'success') {
            $('#content').load('plantilla_informe/lisPlantillaInforme.php');
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
            text: 'Hubo un problema al eliminar la plantilla.',
            confirmButtonText: 'OK'
          });
        }
      });
    }
  });
}


function verEjemplos(plantillaId, nombrePlantilla) {
  $('#ejemploTitulo').text(nombrePlantilla);
  $('#bodyEjemplos').html('<p class="text-muted">Cargando ejemplos...</p>');
  $('#modalEjemplos').modal('show');
  $.ajax({
    url: 'plantilla_informe/ver_ejemplo.php',
    type: 'GET',
    data: { plantilla_id: plantillaId },
    success: function(data) {
      $('#bodyEjemplos').html(data);
    },
    error: function() {
      $('#bodyEjemplos').html('<div class="alert alert-danger">No se pudieron cargar los ejemplos.</div>');
    }
  });
}

</script>
