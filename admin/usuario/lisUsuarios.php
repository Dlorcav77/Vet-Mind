<?php
//admin/usuario/lisUsuarios.php
###########################################
require_once("../config.php");
credenciales('usuario', 'listar');
###########################################

$mysqli = conn();
global $id_usu, $codsede, $acceso_aplicaciones;

$sel = "SELECT
            u.id,
            u.rut,
            u.nombres,
            u.apellidos,
            u.email,
            u.telefono,
            u.estado,
            MAX(us.ultima_actividad) AS ultima_actividad,
            SUM(
                CASE
                    WHEN us.cerrada_en IS NULL
                     AND us.ultima_actividad >= (NOW() - INTERVAL 2 MINUTE)
                    THEN 1
                    ELSE 0
                END
            ) AS conexiones_activas
        FROM usuarios u
        LEFT JOIN usuario_sesiones us
            ON us.usuario_id = u.id
        WHERE u.deleted_at IS NULL
        GROUP BY
            u.id,
            u.rut,
            u.nombres,
            u.apellidos,
            u.email,
            u.telefono,
            u.estado
        ORDER BY u.id DESC";


$stmt = $mysqli->prepare($sel);
$stmt->execute();
$res = $stmt->get_result();

?>
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
                    <i class="fas fa-plus me-1"></i> Agregar Usuario
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
                  <th>Conexión</th>
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
                $estado              = $fila['estado'];
                $ultimaActividad     = $fila['ultima_actividad'] ?? null;
                $conexionesActivas   = (int)($fila['conexiones_activas'] ?? 0);
                $estaEnLinea         = $conexionesActivas > 0;

                $cargo      = $fila['cargo'] ?? 'Sin ingresar';
                $razon_social = $fila['razon_social'] ?? 'Sin ingresar';
                
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
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if ($estaEnLinea): ?>
                        <span class="badge bg-success">
                          En línea<?= $conexionesActivas > 1 ? ' · ' . $conexionesActivas : '' ?>
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary">
                          Desconectado
                        </span>
                      <?php endif; ?>

                      <button
                        type="button"
                        class="btn btn-sm btn-outline-info"
                        onclick="verActividadUsuario(<?= (int)$id ?>)"
                        title="Ver actividad"
                        aria-label="Ver actividad"
                      >
                        <i class="fas fa-chart-line"></i>
                      </button>
                    </div>
                  </td>
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

<div class="modal fade" id="modalActividadUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Actividad de Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body" id="modalActividadUsuarioBody">
        <div class="text-center py-4 text-muted">
          Cargando actividad...
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function verActividadUsuario(id) {
  const modalEl = document.getElementById('modalActividadUsuario');
  const body = document.getElementById('modalActividadUsuarioBody');

  body.innerHTML = `
    <div class="text-center py-4 text-muted">
      Cargando actividad...
    </div>
  `;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  $.ajax({
    url: 'usuario/actividad/actividad_modal.php',
    type: 'GET',
    data: { id: id },
    success: function(html) {
      body.innerHTML = html;
    },
    error: function() {
      body.innerHTML = `
        <div class="alert alert-danger mb-0">
          No se pudo cargar la actividad del usuario.
        </div>
      `;
    }
  });
}

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
