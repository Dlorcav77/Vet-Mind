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
<div id="asignarPerfiles" data-page-id="asignarPerfiles">
  <h1 class="h3 mb-3"><strong>Asignar Perfil</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
              <a href="asignarPerfiles/asignarPerfiles.php" class="btn btn-primary ajax-link">
                <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar perfil
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
                        <button class="btn btn-outline-info" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="align-middle" data-feather="more-vertical"></i>
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
  $(document).ready(function() {
    $('#ventas').DataTable({
      responsive: true,
      dom: 'Bfrtip',
      buttons: [{
          extend: 'excelHtml5',
          text: 'Excel',
          title: 'Perfiles Asignados',
          exportOptions: {
            columns: [0, 1, 5, 2, 3, 6, 7, 8]
          }
        },
        {
          extend: 'pdfHtml5',
          title: 'Perfiles Asignados',
          text: 'PDF',
          exportOptions: {
            columns: [0, 1, 5, 2, 3, 6, 7, 8]
          }
        },
        {
          extend: 'print',
          title: 'Perfiles Asignados',
          text: 'Imprimir',
          exportOptions: {
            columns: [0, 1, 5, 2, 3, 6, 7, 8]
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