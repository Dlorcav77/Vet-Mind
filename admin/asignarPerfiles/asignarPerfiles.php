<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if($action == "modificar"){
  credenciales('asignarPerfiles', 'modificar');
  $accion  = "Modificar";
  $id      = $_GET['id'];
// print"$id";
  $sel = "SELECT * FROM usuarios_perfil WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param("i", $id); 
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();

  $usuario_id    = $fila['usuario_id'];
  $perfiles_id   = $fila['perfiles_id'];
  $fecha_inicio  = $fila['fecha_inicio'];
  $fecha_termino = $fila['fecha_termino'];
  $estado        = $fila['estado'];

  $selU = "SELECT * FROM usuarios WHERE id = ?";
  $stmtU = $mysqli->prepare($selU);
  $stmtU->bind_param("i", $usuario_id); 
  $stmtU->execute();
  $resU = $stmtU->get_result();
  $filaU = $resU->fetch_assoc();
  $nombres    = $filaU['nombres'];
  $apellidos  = $filaU['apellidos'];
  
}else{
  credenciales('asignarPerfiles', 'ingresar');
  $accion  = "Ingresar";
  $fila = [
    'fecha_inicio' => '',
    'fecha_termino' => '',
    'estado' => '',
  ];
  $filaU = [
    'nombres' => '',
    'apellidos' => '',
  ];
}
?>
<script src="../assets/js/validarRut.js"></script>
<div class="card" id="lisAsignarPerfiles" data-page-id="lisAsignarPerfiles">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?php echo $accion; ?> Perfil</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
    <div class="card-body">
      <form method="post" action="asignarPerfiles/updAsignarPerfiles.php">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="control-label">Usuario</label>
            <?php if ($action == "modificar"): ?>
                <select name="usuario_disabled" class="form-control select2" disabled>
                    <?php lisUsuarios($usuario_id); ?>
                </select>
                <input type="hidden" name="usuario" value="<?php echo $usuario_id; ?>">
            <?php else: ?>
                <select name="usuario" class="form-control select2">
                    <option value=''>Seleccione un Usuario</option>
                    <?php lisUsuarios(); ?>
                </select>
            <?php endif; ?>
          </div>
          <div class="clearfix"></div>
          <div class="col-md-6">
            <label class="control-label" for="inputWarning1">Perfil</label>
            <select name="perfil" class="form-control">
              <?php 
              if($action == "ingresar"){
                echo"<option value=''>Seleccione un perfil</option>";
                lisPerfiles(); 
              }
              if($action == "modificar"){
                lisPerfiles($perfiles_id); 
              }
              ?>
            </select> 
          </div>
          <div class="col-md-6">
            <label class="control-label">Estado</label>
            <select name="estado" class="form-control">
                <option value="activo" <?php if($fila['fecha_termino'] == 'activo') echo 'selected'; ?>>Activo</option>
                <option value="inactivo" <?php if($fila['fecha_termino'] == 'inactivo') echo 'selected'; ?>>Inactivo</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="control-label" for="inputSuccess1">Fecha Inicio</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fila['fecha_inicio']; ?>">
          </div>
          <div class="col-md-6">
            <label class="control-label" for="inputSuccess1">Fecha Termino (opcional)</label>
            <input type="date" name="fecha_termino" class="form-control" value="<?php echo $fila['fecha_termino']; ?>">
            <small class="form-text text-muted">Puede dejarse vacío si no se requiere una fecha de término.</small>
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
  $('.select2').select2();
});
$('#usuario').select2({
  placeholder: 'Seleccionar Usuario',
  allowClear: true
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
          console.log(response);
          let jsonResponse = JSON.parse(response);
          if (jsonResponse.status === 'success') {
              $('#content').load('asignarPerfiles/lisAsignarPerfiles.php');
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
