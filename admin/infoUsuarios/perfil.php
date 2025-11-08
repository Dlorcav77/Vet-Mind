<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$sel = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $mysqli->prepare($sel);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$fila = $res->fetch_assoc();

?>
<div class="card" id="perfil" data-page-id="perfil">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong> Perfil</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
      <div class="card-body">
        <form method="post" action="infoUsuarios/updPerfil.php">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="nombres" class="form-label">Nombres</label>
              <input type="text" class="form-control" id="nombres" name="nombres" value="<?php echo $fila['nombres']; ?>" readonly>
            </div>
            <div class="col-md-6">
              <label for="apellidos" class="form-label">Apellidos</label>
              <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo $fila['apellidos']; ?>" readonly>
            </div>
            <div class="col-md-6">
              <label for="telefono" class="form-label">Teléfono</label>
              <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo $fila['telefono']; ?>" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Correo</label>
              <input type="email" class="form-control" id="email" name="email" value="<?php echo $fila['email']; ?>" required>
            </div>
          </div>
          <input type="hidden" name="id" value="<?php echo $usuario_id; ?>">
          <input type="hidden" name="action" value="modificar">
          <button type="submit" class="btn btn-primary">Modificar</button>
        </form>
      </div>
    </div>
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
        let jsonResponse = JSON.parse(response);
        if (jsonResponse.status === 'success') {
          $('#content').load('infoUsuarios/perfil.php');
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
          text: 'Hubo un problema al guardar los cambios.',
          confirmButtonText: 'OK'
        });
      }
    });
  });
});
</script>

</html>
