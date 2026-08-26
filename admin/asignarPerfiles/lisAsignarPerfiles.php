<?php
###########################################
require_once("../config.php");
credenciales('asignarPerfiles', 'listar');
###########################################

$mysqli = conn();

$sel = "SELECT 
            up.id,
            u.rut,
            u.email,
            u.nombres,
            u.apellidos,
            up.perfiles_id,
            up.fecha_inicio,
            up.fecha_termino,
            up.estado,
            p.nombre AS perfil_nombre
        FROM usuarios u
        INNER JOIN usuarios_perfil up ON u.id = up.usuario_id
        LEFT JOIN perfiles p ON up.perfiles_id = p.id
        WHERE u.deleted_at IS NULL 
          AND up.deleted_at IS NULL
          ";
$stmt = $mysqli->prepare($sel);
$stmt->execute();
$res = $stmt->get_result();

?>
<div id="asignarPerfiles" data-page-id="asignarPerfiles">
  <h1 class="h3 mb-3"><strong>Asignar Perfil</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
              <a href="asignarPerfiles/asignarPerfiles.php" class="btn btn-primary ajax-link">
                <i class="fas fa-plus me-1"></i> Agregar perfil
              </a>
            </div>
          </div>
          <div class="table-responsive">
            <table id="ventas" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
              <thead>
                <tr>
                  <th>N</th>
                  <th>Rut</th>
                  <th>Email</th>
                  <th>Nombres</th>
                  <th>Apellidos</th>
                  <th>Perfil</th>
                  <th>Fecha Inicio</th>
                  <th>Fecha Termino</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                $fecha_actual = date('Y-m-d');
                while ($fila = $res->fetch_assoc()) {
                  $id              = $fila['id'];
                  $rut             = $fila['rut'];
                  $email           = $fila['email'];
                  $nombres         = $fila['nombres'];
                  $apellidos       = $fila['apellidos'];
                  $perfil_nombre   = $fila['perfil_nombre'];
                  $fecha_inicio    = $fila['fecha_inicio'];
                  $fecha_termino   = $fila['fecha_termino'];
                  $estado          = $fila['estado'];


                  if ($fecha_actual >= $fecha_inicio && (is_null($fecha_termino) || $fecha_actual <= $fecha_termino)) {
                    // $estado = "Activo";
                  } else {
                    $upd = "UPDATE usuarios_perfiles SET estado = 'inactivo' WHERE id = ?";
                    $stmtU = $mysqli->prepare($upd);
                    $stmtU->bind_param('i', $id);
                    $stmtU->execute();
                    $stmtU->close();
                  }

                ?>
                  <tr>
                    <td><?php print "$i"?></td>
                    <td><?php print "$rut"?></td>
                    <td><?php print "$email"?></td>
                    <td><?php print "$nombres"?></td>
                    <td><?php print "$apellidos"?></td>
                    <td><?php echo   $perfil_nombre; ?></td>
                    <td><?php print "$fecha_inicio"?></td>
                    <td><?php print "$fecha_termino"?></td>
                    <td><?php print "$estado"?></td>
                    <td align='center'>
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
                          <a class="dropdown-item ajax-link" href="asignarPerfiles/asignarPerfiles.php?action=modificar&id=<?php echo $id; ?>">Modificar</a>
                          <a class="dropdown-item" href="#" onclick="confirmDelete('<?php echo $id; ?>')">Eliminar</a>
                        </div>
                    </td>
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
$(document).ready(function () {

    const $tabla = $('#ventas');

    if ($.fn.DataTable.isDataTable($tabla[0])) {
        return;
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
                text: '<i class="fas fa-file-excel me-1"></i> Excel',
                title: 'Perfiles Asignados',
                exportOptions: {
                    columns: [0, 1, 5, 2, 3, 6, 7, 8]
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                title: 'Perfiles Asignados',
                exportOptions: {
                    columns: [0, 1, 5, 2, 3, 6, 7, 8]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i> Imprimir',
                title: 'Perfiles Asignados',
                exportOptions: {
                    columns: [0, 1, 5, 2, 3, 6, 7, 8]
                }
            }
        ],

        columnDefs: [
            {
                targets: -1,
                className: 'dt-col-acciones',
                orderable: false
            }
        ],

        language: window.VETMIND_DATATABLE_LANGUAGE
    });

});
</script>
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
          url: 'asignarPerfiles/updAsignarPerfiles.php',
          type: 'POST',
          data: {
            action: 'eliminar',
            id: id
          },
          success: function(response) {
            console.log(response);
            let jsonResponse = JSON.parse(response);
            if (jsonResponse.status === 'success') {
              $('#content').load('asignarPerfiles/lisAsignarPerfiles.php');
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