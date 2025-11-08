<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if($action == "modificar"){
  credenciales('tutor', 'modificar');
  $accion  = "Modificar";

  $id = intval($_GET['id']);
  $sel = "SELECT * FROM tutores WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();
}else{
  credenciales('tutor', 'ingresar');
  $accion  = "Ingresar";
  $fila = [
    'nombre_completo' => '',
    'rut' => '',
    'telefono' => '',
    'email' => '',
    'direccion' => ''
  ];
}

global $usuario_id;
?>
<!-- <script src="../assets/js/validarRut.js"></script> -->
<div class="card" id="tutor" data-page-id="tutor">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?php echo $accion; ?> Tutor</strong></h1>
    <div class="col-xl-12 col-xxl-10 d-flex">
    <div class="card-body">
      <form method="post" action="tutor/updTutores.php">
        <div class="row mb-3">
          <div class="col-md-6 mb-2">
            <label for="rut" class="form-label">RUT</label>
            <!-- <input type="text" class="form-control" id="rut" name="rut" oninput="checkRut(this)" maxlength="12" autocomplete="off" value="<?php echo htmlspecialchars($fila['rut']); ?>"> -->
            <input type="text" class="form-control" id="rut" name="rut" value="<?php echo htmlspecialchars($fila['rut']); ?>">
          </div>
          <div class="col-md-6 mb-2">
            <label for="nombre_completo" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" maxlength="150" value="<?php echo htmlspecialchars($fila['nombre_completo']); ?>" required>
          </div>
          <div class="col-md-6 mb-2">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" class="form-control" id="telefono" name="telefono" maxlength="20" value="<?php echo htmlspecialchars($fila['telefono']); ?>">
          </div>
          <div class="col-md-6 mb-2">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($fila['email']); ?>">
          </div>
          <div class="col-md-12 mb-2">
            <label for="direccion" class="form-label">Dirección</label>
            <input type="text" class="form-control" id="direccion" name="direccion" maxlength="200" value="<?php echo htmlspecialchars($fila['direccion']); ?>">
          </div>
        </div>
        <?php if ($action == 'modificar'): ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php endif; ?>
        <input type="hidden" name="veterinario_id" value="<?php echo $usuario_id; ?>">
        <input type="hidden" name="action" value="<?php echo $action; ?>">
        <button type="submit" class="btn btn-primary"><?php echo $accion; ?> Tutor</button>
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
          // console.log(response);
          let jsonResponse = JSON.parse(response);
          if (jsonResponse.status === 'success') {
              $('#content').load('tutor/lisTutores.php');
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
                text: 'Hubo un problema al guardar el tutor.',
                confirmButtonText: 'OK'
          });
        }
    });
  });
});
</script>
