<?php
###########################################
require_once("../config.php");
credenciales('usuario', 'listar');
###########################################

$mysqli = conn();
global $id_usu, $codsede, $acceso_aplicaciones;

$sel = "SELECT id, rut, nombres, apellidos, email, telefono, estado
        FROM usuarios 
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
      table.dataTable thead th, table.dataTable tfoot th {
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

<div id="usuario" data-page-id="usuario">
  <h1 class="h3 mb-3"><strong>Usuarios</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
                <?php if (in_array('ingresar', $acceso_aplicaciones['usuario'] ?? [])): ?>
                  <a href="usuario/usuarios.php" class="btn btn-primary ajax-link">
                    <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar Usuario
                  </a> 
                <?php endif; ?> 
            </div>
          </div>
          <div class="table-responsive">
            <table id="ventas" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th width="50">N</th>
                  <th>Rut</th>
                  <th>Nombre</th>
                  <!-- <th>Cargo</th> -->
                  <!-- <th>Empresa</th> -->
                  <th>Email</th>
                  <th>Telefono</th>
                  <th>Estado</th>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['usuario'] ?? [])): ?>
                    <th>Acciones</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
              <?php
                
              $i = 1;
              while ($fila = $res->fetch_assoc()) {
                $id         = $fila['id'];
                $rut        = $fila['rut'];
                $nombres    = $fila['nombres'];
                $apellidos  = $fila['apellidos'];
                $email      = $fila['email'];
                $telefono   = $fila['telefono'];
                $estado     = $fila['estado'];
                // $cargo      = $fila['cargo'] ?? 'Sin ingresar';
                // $razon_social = $fila['razon_social'] ?? 'Sin ingresar';
                ?>
                <tr>
                  <td><?php print "$i"?></td>
                  <td><?php print "$rut"?></td>
                  <td><?php print "$nombres $apellidos"?></td>
                  <!-- <td><?php echo $cargo; ?></td>
                  <td><?php echo $razon_social; ?></td> -->
                  <td><?php print "$email"?></td>
                  <td><?php print $telefono?></td>
                  <td><?php print "$estado"?></td>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['usuario'] ?? [])): ?>
                    <td align='center' ><div class="dropdown position-relative">
                      <button  class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                      <?php if (in_array('modificar', $acceso_aplicaciones['usuario'] ?? [])): ?>
                        <a class="dropdown-item ajax-link" href="usuario/usuarios.php?action=modificar&id=<?php echo $id; ?>">Modificar</a>
                      <?php endif; ?>
                      <?php if (in_array('eliminar', $acceso_aplicaciones['usuario'] ?? [])): ?>
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
        url: 'usuario/updUsuarios.php',
        type: 'POST',
        data: { action: 'eliminar', id: id },
        success: function(response) {
          let jsonResponse = JSON.parse(response);
          if (jsonResponse.status === 'success') {
            $('#content').load('usuario/lisUsuarios.php');
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
            text: 'Hubo un problema al eliminar el usuario.',
            confirmButtonText: 'OK'
          });
        }
      });
    }
  });
}
</script>
