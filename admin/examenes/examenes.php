<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if ($action == "modificar") {
  credenciales('examenes', 'modificar');
  $accion = "Modificar";

  $id = $_GET['id'];
  $sel = "SELECT * FROM tipo_examen WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();
} else {
  credenciales('examenes', 'ingresar');
  $accion = "Ingresar";
  $fila = [
    'nombre'      => '',
    'descripcion' => '',
    'estado'      => 'activo'
  ];
}
?>
<div class="card" id="examenes" data-page-id="examenes">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?php echo $accion; ?> Tipo de Examen</strong></h1>
  </div>
  <div class="card-body">
    <form method="post" action="examenes/updExamenes.php">
      <div class="row mb-3">
        <div class="col-md-6 mb-2">
          <label for="nombre" class="form-label">Nombre</label>
          <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($fila['nombre']); ?>" required>
        </div>
        <div class="col-md-6 mb-2">
          <label for="estado" class="form-label">Estado</label>
          <select id="estado" name="estado" class="form-select" required>
            <option value="activo" <?php echo $fila['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
            <option value="inactivo" <?php echo $fila['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
          </select>
        </div>
        <div class="col-12 mb-3">
          <label for="descripcion" class="form-label">Descripción</label>
          <textarea class="form-control" id="descripcion" name="descripcion" rows="4" maxlength="500"><?php echo htmlspecialchars($fila['descripcion']); ?></textarea>
        </div>
      </div>
      <?php if ($action == 'modificar'): ?>
      <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php endif; ?>
      <input type="hidden" name="action" value="<?php echo $action; ?>">
      <button type="submit" class="btn btn-primary"><?php echo $accion; ?></button>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  $('form').on('submit', function(e) {
    e.preventDefault();

    var formData = $(this).serialize();

    $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: formData,
        success: function(response) {
          // console.log(response);
          let jsonResponse = JSON.parse(response);
          if (jsonResponse.status === 'success') {
              $('#content').load('examenes/lisExamenes.php');
              Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
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
                text: 'Hubo un problema al guardar el tipo de examen.',
                confirmButtonText: 'OK'
          });
        }
    });
  });
});
</script>
