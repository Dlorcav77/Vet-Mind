<?php
require_once(__DIR__ . '../../../config.php');
$mysqli = conn();

$campos = [];
$stmt = $mysqli->prepare(
  "SELECT cp.campo, cp.etiqueta
    FROM configuracion_informe_campos cic
    JOIN campos_permitidos cp ON cic.campo_id = cp.id
    WHERE cic.veterinario_id = ?
      AND cic.visible = 1
      AND cp.campo NOT IN ('m_solicitante', 'recinto', 'antecedentes')
    ORDER BY cic.orden ASC
  ");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $campos[] = $row;
}

$sexos_manual = [
  'Macho' => 'Macho',
  'Macho Castrado' => 'Macho Castrado',
  'Hembra' => 'Hembra',
  'Hembra Esterilizada' => 'Hembra Esterilizada',
];
?>
<!-- 🔥 Datos del Paciente -->
<div class="row g-2 mb-3">
  <div class="col-md-9">
    <span class="form-label fw-bold">Datos del Paciente</span>
    <div class="d-flex  mt-2">
      <div class="input-group flex-grow-1" id="pacienteSeleccion">
        <input type="text" class="form-control" id="paciente_seleccionado"
          placeholder="Seleccione un paciente..." readonly
          value="<?php 
            if (!empty($fila['paciente_id'])) {
              echo htmlspecialchars(($fila['paciente'] ?? '') . 
                    (isset($fila['especie']) ? ', '.$fila['especie'] : '') . 
                    (isset($fila['raza']) ? ', '.$fila['raza'] : '') . 
                    (isset($fila['propietario']) ? ' - Tutor: '.$fila['propietario'] : ''));
            }
          ?>"
        >
        <button type="button" class="btn btn-outline-primary" <?= empty($fila['paciente_id']) ? '' : '' ?>>
          <i class="fas fa-search"></i> Buscar Paciente
        </button>
      </div>
      <!-- Botón tipo pill Manual a la derecha -->
      <?php
        $toggleManualInitial = empty($fila['paciente_id']) && !empty($fila['manual_data']);
        $isModificar = isset($action) && $action === 'modificar';
        // si estoy modificando y tengo manual_data => NO guardar (unchecked)
        $guardarInitial = ($isModificar && !empty($fila['manual_data'])) ? 0 : 1;
      ?>
      <div class="ms-2 align-self-center">
        <input type="checkbox" class="btn-check" id="toggle_manual" name="toggle_manual" value="1" autocomplete="off" <?= $toggleManualInitial ? 'checked' : '' ?>>
        <label class="btn btn-outline-secondary d-flex align-items-center gap-2" for="toggle_manual" style="min-width: 90px;">
          <i class="fas fa-keyboard"></i>
          <span>Manual</span>
        </label>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <label for="fecha_examen" class="form-label fw-bold">Fecha del Examen</label>
    <input type="date" class="form-control" name="fecha_examen" id="fecha_examen" value="<?= htmlspecialchars($fila['fecha_examen'] ?? '') ?>" required>
  </div>
  <!-- El input hidden fuera de las columnas -->
  <input type="hidden" name="paciente_id" id="paciente_id" value="<?= htmlspecialchars($fila['paciente_id'] ?? '') ?>">
</div>





<!-- 🔥 Bloque Manual -->
<div id="paciente-manual" class="my-3 border rounded p-3 bg-light" style="<?= $toggleManualInitial ? '' : 'display:none;' ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Ingreso Manual</h5>
    <div class="form-check form-switch mb-0">
      <input class="form-check-input"
            type="checkbox"
            id="guardarMascota"
            name="guardar_mascota"
            value="1"
            <?= $guardarInitial ? 'checked' : '' ?>
            data-initial="<?= $guardarInitial ? '1' : '0' ?>">      
      <label class="form-check-label fw-semibold ms-2" for="guardarMascota">
        Guardar
      </label>
    </div>
  </div>
<div class="row">
  <?php foreach ($campos as $campo): ?>

    <?php if ($campo['campo'] === 'especie'): ?>
      <?php continue;?>
    <?php endif; ?>

    <div class="col-md-4 mb-3">
      <label class="form-label fw-semibold"><?= htmlspecialchars($campo['etiqueta']); ?></label>
      <?php if ($campo['campo'] === 'raza'): ?>
        <select id="manual_raza_select" class="select2 form-select" style="width:100%;">
          <?php lisRazas(); ?>
        </select>
        <input type="hidden" id="manual_raza"      name="manual_raza">
        <input type="hidden" id="manual_especie"   name="manual_especie">
      <?php elseif ($campo['campo'] === 'sexo'): ?>
        <select class="form-select" id="manual_sexo" name="manual_sexo">
          <option value="">Seleccione...</option>
          <?php foreach ($sexos_manual as $val => $label): ?>
            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif ($campo['campo'] === 'fecha_nacimiento'): ?>
        <input type="date" class="form-control" name="manual_<?= htmlspecialchars($campo['campo']); ?>">
      <?php else: ?>
        <input type="text" class="form-control" name="manual_<?= htmlspecialchars($campo['campo']); ?>">
      <?php endif; ?>
    </div>

  <?php endforeach; ?>
</div>
</div>


<div class="modal fade" id="modalBuscarPaciente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-search mx-2"></i> Buscar Paciente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control mb-3" id="buscarPacienteInput" placeholder="Ingrese RUT, Nombre del Tutor o Mascota...">
        <div id="resultadosBuscarPaciente" class="table-responsive">
          <p class="text-muted">Comience a escribir para ver resultados.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times"></i> Cerrar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
$(function () {
  const $toggle = $('#toggle_manual');

  // Si está visible al cargar, inicializa select2 antes de cualquier setData()
  if ($toggle.is(':checked')) {
    initSelect2RazaManual();
  }

  $toggle.on('change', function () {
    if (this.checked) {
      $('#paciente-manual').slideDown();
      // $('#guardarMascota').prop('checked', true);
      $('#paciente_seleccionado').prop('readonly', true);
      $('#paciente_id').val('');
      $('#paciente_seleccionado').val('').removeData();
      $('#paciente-manual input').val('');

      // init select2 después de mostrar
      setTimeout(initSelect2RazaManual, 0);
    } else {
      $('#paciente-manual').slideUp();
      $('#paciente_seleccionado').prop('readonly', false);
      $('#guardarMascota').prop('checked', false);
    }
  });
});


// Abrir modal búsqueda
function abrirModalBuscarPaciente() {
  $('#modalBuscarPaciente').modal('show');
  $('#buscarPacienteInput').val('');
  $('#resultadosBuscarPaciente').html('<p class="text-muted">Comience a escribir para ver resultados.</p>');
}

// Buscar paciente AJAX
$('#buscarPacienteInput').on('input', function() {
  let query = $(this).val().trim();
  if (query.length < 3) {
    $('#resultadosBuscarPaciente').html('<p class="text-muted">Ingrese al menos 3 caracteres.</p>');
    return;
  }
  $('#resultadosBuscarPaciente').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>');
  $.ajax({
    url: 'certificado/pacientes/buscar.php',
    type: 'GET',
    data: { q: query },
    success: function(data) {
      $('#resultadosBuscarPaciente').html(data);
    },
    error: function() {
      $('#resultadosBuscarPaciente').html('<div class="alert alert-danger">Error al buscar.</div>');
    }
  });
});

// Seleccionar paciente desde modal
function seleccionarPaciente(id, mascota, tutor, especie, raza, edad, sexo) {
  $('#paciente_id').val(id);
  $('#paciente_seleccionado').val(`${mascota}, ${especie}, ${raza} - Tutor: ${tutor}`)
    .data('especie', especie)
    .data('raza', raza)
    .data('edad', edad)
    .data('fecha_nacimiento', edad) // 👈 AÑADIR ESTA LÍNEA
    .data('sexo', sexo);
  $('#modalBuscarPaciente').modal('hide');
}


$('#pacienteSeleccion').on('click', function () {
    // Evitar que se abra si está activado el modo manual
    if (!$('#toggle_manual').is(':checked')) {
        abrirModalBuscarPaciente();
    }
});


</script>
<script>
// Normaliza tildes (por si luego quieres agregar matcher por grupos)
function sinAcentos(s){ return (s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

function initSelect2RazaManual(){
  const $sel = $('#manual_raza_select');
  if (!$sel.length) return;

  if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');

  $sel.select2({
    placeholder: 'Seleccione raza...',
    allowClear: true,
    minimumResultsForSearch: 0,
    width: 'resolve'
  });

  $sel.on('change', function(){
    const $opt     = $(this).find('option:selected');
    const razaNom  = ($opt.text() || '').trim();
    const especie  = ($opt.closest('optgroup').attr('label') || '').trim();
    const razaId   = ($opt.val() || '').trim();

    // 🔁 Hiddens con los nombres "viejos"
    $('#manual_raza').val(razaNom);
    $('#manual_especie').val(especie);
    $('#manual_raza_id').val(razaId); // opcional
  });

  // sincroniza por si hay valor precargado
  $sel.trigger('change');
}

// Cuando activas el modo manual, inicializamos el select
$('#toggle_manual').on('change', function () {
  if (this.checked) {
    // Espera un tick para que el select ya esté visible y calcule bien el ancho
    setTimeout(initSelect2RazaManual, 0);
  }
});

// Si ya está visible al cargar (edge), inicializa
$(function(){
  if ($('#toggle_manual').is(':checked')) initSelect2RazaManual();
});


</script>


<script>
// ===== 1) Exponer manual_data a JS =====
window.MANUAL_DATA = <?php
  // Si estás en "modificar" y existe manual_data, lo pasamos a JS
  $md = $fila['manual_data'] ?? null;
  if ($md) {
    // asegurar JSON válido y escapado
    $arr = json_decode($md, true);
    echo json_encode($arr ?? null, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
  } else {
    echo 'null';
  }
?>;

// ===== Helpers =====
function preselectRazaByTextAndEspecie(nombreRaza, nombreEspecie){
  const $sel = $('#manual_raza_select');
  if (!$sel.length) return;

  let found = null;
  $sel.find('optgroup').each(function(){
    const $og = $(this);
    const label = ($og.attr('label') || '').trim().toLowerCase();
    if (nombreEspecie && label !== (nombreEspecie || '').trim().toLowerCase()) return; // filtra por especie

    $og.children('option').each(function(){
      const txt = ($(this).text() || '').trim().toLowerCase();
      if (txt === (nombreRaza || '').trim().toLowerCase()) {
        found = $(this).val();
        return false; // break
      }
    });
    if (found) return false; // break outer
  });

  if (found !== null) {
    $sel.val(found).trigger('change'); // esto pobla manual_raza + manual_especie (por tu handler)
  }
}

// Rellena inputs manual_* según el objeto data
function prefillManualFromData(data){
  if (!data) return;

  // paciente, propietario, n_chip, fecha_nacimiento, etc.
  // Coincide las claves con tus names manual_*
  const map = {
    paciente: 'manual_paciente',
    propietario: 'manual_propietario',
    n_chip: 'manual_n_chip',
    fecha_nacimiento: 'manual_fecha_nacimiento'
  };
  Object.keys(map).forEach(k=>{
    if (data[k] != null) {
      $('input[name="'+map[k]+'"]').val(String(data[k]));
    }
  });

  // Sexo
  if (data.sexo) {
    $('#manual_sexo').val(data.sexo).trigger('change');
  }

  // Raza + especie (por texto + optgroup)
  if (data.raza) {
    // si viene especie, mejor aún para acotar
    preselectRazaByTextAndEspecie(data.raza, data.especie || null);
  }

  // Asegura hiddens si por algún motivo no se disparó change
  if (data.raza && !$('input[name="manual_raza"]').val()) {
    $('input[name="manual_raza"]').val(data.raza);
  }
  if (data.especie && !$('input[name="manual_especie"]').val()) {
    $('input[name="manual_especie"]').val(data.especie);
  }
}

// ===== 2) Auto-abrir Manual y precargar si hay manual_data =====
$(function(){
  // Solo si NO hay paciente_id y SÍ hay MANUAL_DATA
  const noPacienteSeleccionado = !($('#paciente_id').val() || '').trim();
  if (window.MANUAL_DATA && noPacienteSeleccionado) {
    // Activa modo manual (tu handler limpia campos, por eso rellenamos luego)
    $('#toggle_manual').prop('checked', true).trigger('change');

    // Espera a que se muestre y Select2 esté listo, luego prefill
    setTimeout(function(){
      // Asegura Select2 inicializado
      if ($('#manual_raza_select').length && !$('#manual_raza_select').hasClass('select2-hidden-accessible')) {
        if (typeof initSelect2RazaManual === 'function') initSelect2RazaManual();
      }
      prefillManualFromData(window.MANUAL_DATA);
    }, 50);
  }
});
</script>
