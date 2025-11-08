<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();

$action = $_GET['action'] ?? 'ingresar';

if ($action == "modificar") {
  credenciales('tutor', 'modificar');
  $accion = "Modificar";

  $id = intval($_GET['id']);
  $sel = "SELECT * FROM pacientes WHERE id = ?";
  $stmt = $mysqli->prepare($sel);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();
} else {
  credenciales('tutor', 'ingresar');
  $accion = "Ingresar";

  $tutor_id = intval($_GET['tutor_id']); // importante: recibe tutor_id
  $fila = [
    'nombre' => '',
    'especie' => '',
    'raza' => '',
    'edad' => ''
  ];
}

$sexos = [
  'Macho' => 'Macho',
  'Macho Castrado' => 'Macho Castrado',
  'Hembra' => 'Hembra',
  'Hembra Esterilizada' => 'Hembra Esterilizada',
  // 'Otro' => 'Otro'
];

global $usuario_id;
?>
<style>
  #modalPacientes .select2-container { width: 22rem !important; }
</style>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><?php echo $accion; ?> Mascota</h5>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="volverListado(<?= $action == 'modificar' ? $fila['tutor_id'] : $tutor_id; ?>)">
      <i class="fas fa-arrow-left"></i> Volver
    </button>
  </div>
  <div class="card-body">
    <form method="post" action="paciente/updPacientes.php" id="formPaciente">
      <div class="row mb-3">
        <div class="col-md-6 mb-2">
          <label for="nombre" class="form-label">Nombre</label>
          <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($fila['nombre']); ?>" required>
        </div>
        <div class="col-md-6 mb-2">
        </div>
        <div class="col-md-6 mb-2">
          <label for="raza" class="form-label">Raza</label>
          <select name="raza" id="raza" class="select2 form-select" placeholder="Elija una opción">
            <?php 
              if ($action == 'modificar') {
                  lisRazas($fila['especie'], $fila['raza'] ?? null);
              } else {
                  lisRazas();
              }
            ?>
          </select>
        </div>
        <div class="col-md-6 mb-2">
          <label for="sexo" class="form-label">Sexo</label>
          <select class="form-control" id="sexo" name="sexo">
            <option value="">Seleccione...</option>
            <?php foreach ($sexos as $val => $label): ?>
              <option value="<?= htmlspecialchars($val) ?>" <?= (isset($fila['sexo']) && $fila['sexo'] == $val) ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 mb-2">
          <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
          <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                value="<?= htmlspecialchars($fila['fecha_nacimiento'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-2">
          <label for="n_chip" class="form-label">Número de Chip</label>
          <input type="text" class="form-control" id="n_chip" name="n_chip" maxlength="15"
                value="<?php echo htmlspecialchars($fila['n_chip'] ?? ''); ?>">
        </div>
      </div>
      <?php if ($action == 'modificar'): ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php endif; ?>
      <input type="hidden" name="tutor_id" value="<?php echo $tutor_id; ?>">
      <input type="hidden" name="veterinario_id" value="<?php echo $usuario_id; ?>">
      <input type="hidden" name="action" value="<?php echo $action; ?>">
      <button type="submit" class="btn btn-primary"><?php echo $accion; ?> Mascota</button>
    </form>
  </div>
</div>
<script>
$(document).ready(function() {
  $('#formPaciente').on('submit', function(e) {
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
              Swal.fire({
                  icon: 'success',
                  title: '¡Éxito!',
                  text: jsonResponse.message,
                  confirmButtonText: 'OK'
              }).then(() => {
                  // 👉 Al presionar OK, cargar lista de pacientes
                  $('#modalPacientes .modal-body').load(
                      'paciente/lisPacientes.php?tutor_id=<?= $action == 'modificar' ? $fila['tutor_id'] : $tutor_id; ?>'
                  );
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
          text: 'Hubo un problema al guardar el paciente.',
          confirmButtonText: 'OK'
        });
      }
    });
  });
});

function volverListado(tutorId) {
  $('#modalPacientes .modal-body').load('paciente/lisPacientes.php?tutor_id=' + tutorId);
}
function sinAcentos(s) { return (s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

function matcherConGrupos(params, data) {
  const term = sinAcentos((params.term||'').toLowerCase().trim());
  if (term === '') return data;

  if (data.children && data.children.length) {
    const match = $.extend(true, {}, data);
    match.children = [];

    for (let i = 0; i < data.children.length; i++) {
      const child = matcherConGrupos(params, data.children[i]);
      if (child != null) match.children.push(child);
    }

    const groupLabel = sinAcentos((data.text||'').toLowerCase());
    if (groupLabel.indexOf(term) > -1) return data;

    return (match.children.length > 0) ? match : null;
  }

  const text = sinAcentos((data.text||'').toLowerCase());

  let groupLabel = '';
  if (data.element && data.element.parentElement && data.element.parentElement.tagName === 'OPTGROUP') {
    groupLabel = sinAcentos((data.element.parentElement.getAttribute('label')||'').toLowerCase());
  }

  if (text.indexOf(term) > -1 || groupLabel.indexOf(term) > -1) return data;
  return null;
}

function initSelect2Raza() {
  var $sel = $('#raza');

  if (!$sel.length) return;          // por si esta vista se carga vía AJAX
  if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');

  $sel.attr('style','width:21rem;').select2({
    placeholder: 'Seleccione raza...',
    allowClear: true,
    minimumResultsForSearch: 0,      // siempre con buscador
    width: 'style',                  // respeta 21rem
    dropdownParent: $('#modalPacientes'),
    matcher: matcherConGrupos
  });
}

$('#modalPacientes').on('shown.bs.modal', function () {
  initSelect2Raza();
});

$(document).ajaxSuccess(function(e, xhr, settings){
  if (settings.url && settings.url.indexOf('paciente/pacientes.php') !== -1) {
    initSelect2Raza();
  }
});

$(function(){
  if ($('#modalPacientes').is(':visible')) initSelect2Raza();
});
</script>


