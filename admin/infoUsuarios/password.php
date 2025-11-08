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
<div class="card" id="password" data-page-id="password">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong> Nueva Contraseña</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
      <div class="card-body">
        <form method="post" action="infoUsuarios/updPassword.php">
          <div class="row mb-3">
            <div class="col-md-6 mb-3">
              <label for="password_actual" class="form-label">Contraseña Actual</label>
              <input type="password" class="form-control" id="password_actual" name="password_actual" required>
            </div>
            <div class="col-md-6">
            </div>
            <div class="col-md-6">
              <label for="password_nueva" class="form-label">Nueva Contraseña</label>
              <input type="password" class="form-control" id="password_nueva" name="password_nueva" required>
            </div>
            <div class="col-md-6">
              <label for="password_repetida" class="form-label">Repite Nueva Contraseña</label>
              <input type="password" class="form-control" id="password_repetida" name="password_repetida" required>
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
        console.log(response);
        let jsonResponse = JSON.parse(response);
        if (jsonResponse.status === 'success') {
          $('#content').load('noticias/noticiasInicio.php');
          Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: jsonResponse.message,
            confirmButtonText: 'OK'
          }).then(() => {
            location.reload();
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
          text: 'Hubo un problema al actualizar la contraseña.',
          confirmButtonText: 'OK'
        });
      }
    });
  });
});
</script>

</html>
