<?php
###########################################
require_once("../config.php");
credenciales('tutor', 'listar');
###########################################

$mysqli = conn();
global $usuario_id, $acceso_aplicaciones;

$sel = "SELECT id, rut, nombre_completo, telefono, email, direccion
        FROM tutores 
        WHERE veterinario_id = ? 
        ORDER BY id DESC";

$stmt = $mysqli->prepare($sel);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<div id="tutor" data-page-id="tutor">
  <h1 class="h3 mb-3"><strong>Tutores</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-md-5">
              <?php if (in_array('ingresar', $acceso_aplicaciones['tutor'] ?? [])): ?>
                <a href="tutor/tutores.php" class="btn btn-primary ajax-link">
                  <i style="width:20px;height:20px;" data-feather="plus"></i> Agregar Tutor
                </a>
              <?php endif; ?>
              <button class="btn btn-outline-secondary ms-2" onclick="abrirBuscador()">
                <i class="fas fa-search"></i> Buscar Tutor/Mascota
              </button>
            </div>

          </div>
          <div class="table-responsive">
            <table id="tablaTutores" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th width="50">#</th>
                  <th>RUT</th>
                  <th>Nombre Completo</th>
                  <th>Email</th>
                  <th>Teléfono</th>
                  <th>Dirección</th>
                  <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['tutor'] ?? [])): ?>
                    <th>Acciones</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                while ($fila = $res->fetch_assoc()):
                  ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($fila['rut'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($fila['nombre_completo']) ?></td>
                    <td><?= htmlspecialchars($fila['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($fila['telefono'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($fila['direccion'] ?? '-') ?></td>
                    <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['tutor'] ?? [])): ?>
                      <td align="center">
                        <div class="dropdown">
                          <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (in_array('modificar', $acceso_aplicaciones['tutor'] ?? [])): ?>
                              <li><a class="dropdown-item ajax-link" href="tutor/tutores.php?action=modificar&id=<?= $fila['id'] ?>">Modificar</a></li>
                            <?php endif; ?>
                            <?php if (in_array('eliminar', $acceso_aplicaciones['tutor'] ?? [])): ?>
                              <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?= $fila['id'] ?>)">Eliminar</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="verPacientes(<?= $fila['id'] ?>)">Mascotas</a></li>
                          </ul>
                        </div>
                      </td>
                    <?php endif; ?>
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

<!-- Modal para mostrar pacientes -->
<div class="modal fade" id="modalPacientes" tabindex="-1" aria-labelledby="modalPacientesLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="modalPacientesLabel"><i class="fas fa-paw mx-3"></i> Mascotas</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <!-- Contenido de pacientes cargado vía AJAX -->
        <div id="pacientesContent">Cargando...</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Buscar -->
<div class="modal fade" id="modalBuscar" tabindex="-1" aria-labelledby="modalBuscarLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-search mx-2"></i> Buscar Tutor y Mascota</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="buscarInput" placeholder="Ingrese RUT, Nombre del Tutor o Mascota...">
        </div>
        <div id="resultadosBuscar" class="table-responsive">
          <p class="text-muted">Comience a escribir para ver resultados.</p>
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
          url: 'tutor/updTutores.php',
          type: 'POST',
          data: { action: 'eliminar', id: id },
          success: function(response) {
            let jsonResponse = JSON.parse(response);
            if (jsonResponse.status === 'success') {
              $('#content').load('tutor/lisTutores.php');
              Swal.fire('Eliminado', jsonResponse.message, 'success');
            } else {
              Swal.fire('Error', jsonResponse.message, 'error');
            }
          },
          error: function() {
            Swal.fire('Error', 'No se pudo eliminar el tutor.', 'error');
          }
        });
      }
    });
  }

  function verPacientes(tutorId) {
    $('#modalPacientes').modal('show');
    $('#pacientesContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando pacientes...</div>');
    $.ajax({
      url: 'paciente/lisPacientes.php',
      type: 'GET',
      data: { tutor_id: tutorId },
      success: function(data) {
        $('#pacientesContent').html(data);
      },
      error: function() {
        $('#pacientesContent').html('<div class="alert alert-danger">Error al cargar los pacientes.</div>');
      }
    });
  }

  function abrirBuscador() {
    $('#modalBuscar').modal('show');
    $('#buscarInput').val('');
    $('#resultadosBuscar').html('<p class="text-muted">Comience a escribir para ver resultados.</p>');
  }

  $('#buscarInput').on('input', function() {
    let query = $(this).val().trim();
    if (query.length < 3) {
      $('#resultadosBuscar').html('<p class="text-muted">Ingrese al menos 3 caracteres.</p>');
      return;
    }
    $('#resultadosBuscar').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>');

    $.ajax({
      url: 'tutor/buscarTutorMascota.php',
      type: 'GET',
      data: { q: query },
      success: function(data) {
        $('#resultadosBuscar').html(data);
      },
      error: function() {
        $('#resultadosBuscar').html('<div class="alert alert-danger">Error al buscar.</div>');
      }
    });
  });

</script>
