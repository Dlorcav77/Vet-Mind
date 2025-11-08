<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if($action == "modificar"){
  credenciales('perfil', 'modificar');
  $accion  = "Modificar";

  $id = $_GET['id'];

  // Obtener los datos del perfil
  $sel = "SELECT * FROM perfiles WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();

  // Obtener los permisos asignados al perfil desde la nueva tabla
  $selPermisos = "SELECT permiso_id FROM perfiles_permisos WHERE perfil_id = ?";
  $stmt = $mysqli->prepare($selPermisos);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $resPermisos = $stmt->get_result();

  $permisos = [];
  while ($filaPermiso = $resPermisos->fetch_assoc()) {
      $permisos[] = $filaPermiso['permiso_id'];
  }
}else{
  credenciales('perfil', 'ingresar');
  $accion  = "Ingresar";
  $fila = [
    'nombre' => '',
    'descripcion' => '',
  ];

}
?>
<div class="card" id="perfil" data-page-id="perfil">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?php echo $accion; ?> Perfil</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
    <div class="card-body">
      <form method="post" action="perfil/updPerfiles.php">
        <div class="row mb-3">
          
          <div class="col-md-8">
            <label for="nombre" class="form-label">Nombres</label>
            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $fila['nombre']; ?>" maxlength="100">
          </div>
          <div class="clearfix"></div>

          <div class="col-md-8">
            <label for="nombre" class="form-label">Descripcion</label>
            <textarea name="descripcion" class="form-control"><?php echo $fila['descripcion']; ?></textarea>
          </div>
          <div class="clearfix"></div>
          <div class="form-group col-md-10">
            <label for="inputSuccess1">Aplicaciones</label><br>
            <select name="aplicaciones[]" multiple class="form-control select2" style="width:21rem;" placeholder="Elija una opción">
              <?php 
                if ($action == 'modificar') {
                    lisAplicaciones($permisos);
                } else {
                    lisAplicaciones();
                }
              ?>
            </select>
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
  $('.select2').select2({
    closeOnSelect: false,
    placeholder: "Elija una opción",
    width: 'resolve',
  });
});
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
              $('#content').load('perfil/lisPerfiles.php');
              Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: jsonResponse.message,
                    confirmButtonText: 'OK'
              });
          } else if (jsonResponse.status === 'error') {
              // Mostrar el error recibido desde PHP
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
                text: 'Hubo un problema al enviar el formulario.',
                confirmButtonText: 'OK'
          });
        }
    });
  });
});

</script>
</html>
