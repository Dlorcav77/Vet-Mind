<?php
###########################################
require_once("../config.php");
credenciales('clinicas', 'listar');
###########################################

$mysqli = conn();
global $usuario_id, $acceso_aplicaciones;

// Si quieres TODAS las clínicas, elimina "AND veterinario_id = ?" y el bind_param.
$sel = "SELECT id, nombre_clinica, correo, telefono
        FROM clinicas
        WHERE veterinario_id = ?
        ORDER BY id DESC";

$stmt = $mysqli->prepare($sel);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<style>
  .dataTables_wrapper .dt-buttons { float: none; text-align: center; }
  table.dataTable thead th, table.dataTable tfoot th { font-family: Arial, sans-serif; font-size: 14px; }
  table.dataTable tbody td { font-family: Arial, sans-serif; font-size: 12px; }
  .dataTables_wrapper .dt-buttons { float: none; text-align: center; }
</style>

<div id="clinicas" data-page-id="clinicas">
  <h1 class="h3 mb-3"><strong>Clínicas</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
                <a href="clinicas/clinicas.php" class="btn btn-primary ajax-link">
                  <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar Clínica
                </a>
            </div>
          </div>

          <div class="table-responsive">
            <table id="tblClinicas" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th width="50">N</th>
                  <th>Nombre clínica</th>
                  <th>Correo</th>
                  <th>Teléfono</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php
              $i = 1;
              while ($fila = $res->fetch_assoc()):
                $id            = (int)$fila['id'];
                $nombre        = htmlspecialchars($fila['nombre_clinica'] ?? '', ENT_QUOTES, 'UTF-8');
                $correo        = htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8');
                $telefono      = htmlspecialchars($fila['telefono'] ?? '', ENT_QUOTES, 'UTF-8');
              ?>
                <tr>
                  <td><?php echo $i; ?></td>
                  <td><?php echo $nombre; ?></td>
                  <td><?php echo $correo; ?></td>
                  <td><?php echo $telefono; ?></td>

                  <td align="center">
                    <div class="dropdown position-relative">
                      <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                          <a class="dropdown-item ajax-link" href="clinicas/clinicas.php?action=modificar&id=<?php echo $id; ?>">Modificar</a>
                          <a class="dropdown-item" href="#" onclick="confirmDeleteClinica('<?php echo $id; ?>')">Eliminar</a>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php
                $i++;
              endwhile;
              ?>
              </tbody>
            </table>
          </div> <!-- table-responsive -->
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDeleteClinica(id) {
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
        url: 'clinicas/updClinicas.php',
        type: 'POST',
        data: { action: 'eliminar', id: id },
        success: function(response) {
          let jsonResponse;
          try { jsonResponse = JSON.parse(response); } catch(e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Respuesta inválida del servidor.' });
            return;
          }
          if (jsonResponse.status === 'success') {
            $('#content').load('clinicas/lisClinicas.php');
            Swal.fire({ icon: 'success', title: 'Eliminado', text: jsonResponse.message, confirmButtonText: 'OK' });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: jsonResponse.message || 'No se pudo eliminar.' });
          }
        },
        error: function() {
          Swal.fire({ icon: 'error', title: 'Error', text: 'Hubo un problema al eliminar la clínica.' });
        }
      });
    }
  });
}
</script>
