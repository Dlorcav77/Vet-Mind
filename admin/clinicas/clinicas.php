<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if ($action === "modificar") {
  // credenciales('clinica', 'modificar');
  $accion = "Modificar";

  $id  = intval($_GET['id'] ?? 0);
  $sel = "SELECT * FROM clinicas WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res  = $stmt->get_result();
  $fila = $res->fetch_assoc();
} else {
  // credenciales('clinica', 'ingresar');
  $accion = "Ingresar";
  $fila = [
    'nombre_clinica' => '',
    'correo'         => '',
    'telefono'       => '',
  ];
}
?>
<div class="card" id="clinica" data-page-id="clinica">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?php echo $accion; ?> Clínica</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
      <div class="card-body">
        <form method="post" action="clinicas/updClinicas.php" autocomplete="off">
          <div class="row mb-3">
            <div class="col-md-8 mb-2">
              <label for="nombre_clinica" class="form-label">Nombre de la clínica</label>
              <input type="text" class="form-control" id="nombre_clinica" name="nombre_clinica"
                     maxlength="150" value="<?php echo htmlspecialchars($fila['nombre_clinica'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="col-md-6 mb-2">
              <label for="correo" class="form-label">Correo</label>
              <input type="email" class="form-control" id="correo" name="correo"
                     maxlength="150" value="<?php echo htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="col-md-6 mb-2">
              <label for="telefono" class="form-label">Teléfono</label>
              <input type="text" class="form-control" id="telefono" name="telefono"
                     maxlength="30" value="<?php echo htmlspecialchars($fila['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <?php if ($action === 'modificar'): ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
          <?php endif; ?>

          <input type="hidden" name="action" value="<?php echo $action; ?>">
          <button type="submit" class="btn btn-primary"><?php echo $accion; ?></button>
          <a href="#" class="btn btn-outline-secondary ajax-link" onclick="$('#content').load('clinicas/lisClinicas.php'); return false;">Volver</a>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('form').on('submit', function (e) {
    e.preventDefault();

    var formData = $(this).serialize();

    $.ajax({
      url: $(this).attr('action'),
      type: 'POST',
      data: formData,
      success: function (response) {
        let jsonResponse;
        try {
          jsonResponse = JSON.parse(response);
        } catch (e) {
          console.log(e);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Respuesta inválida del servidor.'
          });
          return;
        }

        if (jsonResponse.status === 'success') {
          $('#content').load('clinicas/lisClinicas.php');
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
            text: jsonResponse.message || 'No se pudo guardar la clínica.',
            confirmButtonText: 'OK'
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Hubo un problema al guardar la clínica.',
          confirmButtonText: 'OK'
        });
      }
    });
  });
});
</script>
