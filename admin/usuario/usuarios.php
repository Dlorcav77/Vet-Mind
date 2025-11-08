<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if($action == "modificar"){
  credenciales('usuario', 'modificar');
  $accion  = "Modificar";

  $id      = $_GET['id'];
  $sel = "SELECT * FROM usuarios WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();
}else{
  credenciales('usuario', 'ingresar');
  $accion  = "Ingresar";
  $fila = [
    'rut' => '',
    'nombres' => '',
    'apellidos' => '',
    'telefono' => '',
    'email' => '',
    'estado' => '',
    // 'cargo' => '',
    // 'empresa_id' => ''
  ];
}
?>
<script src="../assets/js/validarRut.js"></script>
<div class="card" id="usuario" data-page-id="usuario">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?php echo $accion; ?> Usuario</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
    <div class="card-body">
      <form method="post" action="usuario/updUsuarios.php">
        <div class="row mb-3">
          <div class="col-md-6  mb-2">
            <label for="rutp" class="form-label">Rut</label>
            <input type="text" class="form-control" id="rut" name="rut" oninput="checkRut(this)" autocomplete="off" maxlength="12" value="<?php echo $fila['rut']; ?>" required>
          </div>
          <div class="col-md-6">
          </div>
          <div class="col-md-6 mb-2">
            <label for="nombre" class="form-label">Nombres</label>
            <input type="text" class="form-control" id="nombres" name="nombres" value="<?php echo $fila['nombres']; ?>" required>
          </div>
          <div class="col-md-6 mb-2">
            <label for="nombre" class="form-label">Apellidos</label>
            <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo $fila['apellidos']; ?>" required>
          </div>
          <div class="col-md-6 mb-2">
            <label for="contacto" class="form-label">email</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo $fila['email']; ?>" required>
          </div>
          <div class="col-md-6 mb-2">
            <label for="contacto" class="form-label">Telefono</label>
            <input type="text" class="form-control" id="telefono" name="telefono" maxlength="100" value="<?php echo $fila['telefono']; ?>" required>
          </div>

          <div class="col-md-6 mb-2">
            <label for="direccion" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-control" required>
              <option value="activo" <?php echo $fila['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
              <option value="inactivo" <?php echo $fila['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
            </select>
          </div>
          <div class="col-md-6 mb-2">
            <label for="direccion" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" maxlength="80" value="">
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
  </div>
</div>
</div>
</div>
</body>

<script>
$(document).ready(function() {
  $('form').on('submit', function(e) {
    e.preventDefault();

    const $form = $(this);
    const formData = $form.serialize();

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: formData,
      dataType: 'json', // <- jQuery parsea JSON por ti
      success: function(jsonResponse) {
        // jsonResponse ya es objeto
        if (jsonResponse.status === 'success') {
          $('#content').load('usuario/lisUsuarios.php');
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
            text: jsonResponse.message || 'Ocurrió un error.',
            confirmButtonText: 'OK'
          });
        }
      },
      error: function(xhr, status, err) {
        // Muestra lo que realmente devolvió el servidor
        console.error('AJAX error:', status, err);
        console.error('Response text:', xhr.responseText); // <- aquí verás el <br /><b>Notice...
        Swal.fire({
          icon: 'error',
          title: 'Error',
          html: 'La respuesta no es JSON válido.<br><small>Revisa la consola/Network para ver el detalle.</small>',
          confirmButtonText: 'OK'
        });
      }
    });
  });
});
</script>


</html>
