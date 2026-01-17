<?php
###########################################
require_once("../config.php");
credenciales('tutor', 'listar');
###########################################

$mysqli = conn();
global $acceso_aplicaciones;

// Obtener el tutor_id recibido por GET
$tutor_id = intval($_GET['tutor_id'] ?? 0);

if ($tutor_id <= 0) {
    echo "<div class='alert alert-danger'>Tutor no válido.</div>";
    exit;
}

// Consultar pacientes asociados a este tutor
$query = "SELECT id, nombre, codigo_paciente, n_chip, especie, sexo, raza, fecha_nacimiento, created_at
          FROM pacientes
          WHERE tutor_id = ?
          ORDER BY id DESC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="mb-3 d-flex justify-content-between">
  <?php if (in_array('ingresar', $acceso_aplicaciones['tutor'] ?? [])): ?>
    <button class="btn btn-primary" onclick="agregarPaciente(<?= $tutor_id ?>)">
      <i class="fas fa-plus"></i> Agregar Mascota
    </button>
  <?php endif; ?>
</div>

<div class="table-responsive">
  <table class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
    <thead>
      <tr>
        <th>#</th>
        <th>Nombre</th>
        <th>Código</th>
        <th>Especie</th>
        <th>Sexo</th>
        <th>Raza</th>
        <th>Edad</th>
        <th>Chip</th>
        <!-- <th>Fecha de Registro</th> -->
        <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['tutor'] ?? [])): ?>
          <th>Acciones</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
     <?php
      $i = 1;
      while ($row = $result->fetch_assoc()):
        $especie_txt = trim((string)($row['especie'] ?? ''));
        $sexo_txt    = trim((string)($row['sexo'] ?? ''));
      ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['nombre']) ?></td>
          <td><?= htmlspecialchars($row['codigo_paciente'] ?: '-') ?></td>
          <td><?= htmlspecialchars($especie_txt !== '' ? ucfirst($especie_txt) : '-') ?></td>
          <td><?= htmlspecialchars($sexo_txt !== '' ? ucfirst($sexo_txt) : '-') ?></td>
          <td><?= htmlspecialchars($row['raza'] ?: '-') ?></td>
          <td><?= $row['fecha_nacimiento'] ? calcular_edad($row['fecha_nacimiento']) : '-' ?></td>
          <td><?= htmlspecialchars($row['n_chip'] ?: '-') ?></td>

          <?php if (array_intersect(['modificar', 'eliminar'], $acceso_aplicaciones['tutor'] ?? [])): ?>
            <td>
              <div class="dropdown">
                <button class="btn btn-outline-info dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <?php if (in_array('modificar', $acceso_aplicaciones['tutor'] ?? [])): ?>
                    <li><a class="dropdown-item" href="#" onclick="modificarPaciente(<?= $row['id'] ?>)">Modificar</a></li>
                  <?php endif; ?>
                  <?php if (in_array('eliminar', $acceso_aplicaciones['tutor'] ?? [])): ?>
                    <li><a class="dropdown-item text-danger" href="#" onclick="eliminarPaciente(<?= $row['id'] ?>, <?= $tutor_id ?>)">Eliminar</a></li>
                  <?php endif; ?>
                </ul>
              </div>
            </td>
          <?php endif; ?>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php
function calcular_edad($fecha_nacimiento) {
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha_nacimiento);
    $edad = $hoy->diff($nacimiento);
    return $edad->y . " años, " . $edad->m . " meses";
}
?>
<script>
function agregarPaciente(tutorId) {
  $.ajax({
    url: 'paciente/pacientes.php',
    type: 'GET',
    data: { tutor_id: tutorId },
    success: function(data) {
      $('#modalPacientes .modal-body').html(data);
    },
    error: function() {
      Swal.fire('Error', 'No se pudo cargar el formulario.', 'error');
    }
  });
}

function modificarPaciente(pacienteId) {
  $.ajax({
    url: 'paciente/pacientes.php?action=modificar',
    type: 'GET',
    data: { id: pacienteId },
    success: function(data) {
      $('#modalPacientes .modal-body').html(data);
    },
    error: function() {
      Swal.fire('Error', 'No se pudo cargar el formulario.', 'error');
    }
  });
}

function eliminarPaciente(pacienteId, tutorId) {
  Swal.fire({
    title: '¿Eliminar Mascota?',
    text: 'Esta acción no se puede deshacer.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'paciente/updPacientes.php',
        type: 'POST',
        data: { action: 'eliminar', id: pacienteId },
        success: function(response) {
          let json = JSON.parse(response);
          if (json.status === 'success') {
            $('#modalPacientes .modal-body').load(
              'paciente/lisPacientes.php?tutor_id=' + tutorId
            );
            Swal.fire('Eliminado', json.message, 'success');
          } else {
            Swal.fire('Error', json.message, 'error');
          }
        },
        error: function() {
          Swal.fire('Error', 'No se pudo eliminar la mascota.', 'error');
        }
      });
    }
  });
}
</script>
