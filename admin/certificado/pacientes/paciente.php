<?php
// admin/certificado/pacientes/paciente.php
require_once(__DIR__ . '../../../config.php');
$mysqli = conn();

$camposCatalogo = $campos_permitidos_catalogo ?? [];
$camposVisiblesActuales = $campos_visibles_actuales ?? [];

$camposGenerales = ['m_solicitante', 'recinto', 'antecedentes'];

$sexos_manual = [
    'Macho' => 'Macho',
    'Macho Castrado' => 'Macho Castrado',
    'Hembra' => 'Hembra',
    'Hembra Esterilizada' => 'Hembra Esterilizada',
];
?>
<div class="row g-2 mb-3">
  <div class="col-md-9">
    <span class="form-label fw-bold">Datos del Paciente</span>
    <div class="d-flex mt-2">
      <div class="input-group flex-grow-1" id="pacienteSeleccion">
        <input
          type="text"
          class="form-control"
          id="paciente_seleccionado"
          placeholder="Seleccione un paciente..."
          readonly
          value="<?php
            if (!empty($fila['paciente_id'])) {
              echo htmlspecialchars(
                ($fila['paciente'] ?? '') .
                (isset($fila['especie']) ? ', ' . $fila['especie'] : '') .
                (isset($fila['raza']) ? ', ' . $fila['raza'] : '') .
                (isset($fila['propietario']) ? ' - Tutor: ' . $fila['propietario'] : '')
              );
            }
          ?>"
        >
        <button type="button" class="btn btn-outline-primary">
          <i class="fas fa-search"></i> Buscar Paciente
        </button>
      </div>

      <?php
        $toggleManualInitial = empty($fila['paciente_id']) && !empty($fila['manual_data']);
        $isModificar = isset($action) && $action === 'modificar';
        $guardarInitial = ($isModificar && !empty($fila['manual_data'])) ? 0 : 1;
      ?>

      <div class="ms-2 align-self-center">
        <input
          type="checkbox"
          class="btn-check"
          id="toggle_manual"
          name="toggle_manual"
          value="1"
          autocomplete="off"
          <?= $toggleManualInitial ? 'checked' : '' ?>
        >
        <label class="btn btn-outline-secondary d-flex align-items-center gap-2" for="toggle_manual" style="min-width: 90px;">
          <i class="fas fa-keyboard"></i>
          <span>Manual</span>
        </label>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <label for="fecha_examen" class="form-label fw-bold">Fecha del Examen</label>
    <input
      type="date"
      class="form-control"
      name="fecha_examen"
      id="fecha_examen"
      value="<?= htmlspecialchars($fila['fecha_examen'] ?? '') ?>"
      required
    >
  </div>

  <input type="hidden" name="paciente_id" id="paciente_id" value="<?= htmlspecialchars($fila['paciente_id'] ?? '') ?>">
</div>

<div id="paciente-manual" class="my-3 border rounded p-3 bg-light" style="<?= $toggleManualInitial ? '' : 'display:none;' ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Ingreso Manual</h5>
    <div class="form-check form-switch mb-0">
      <input
        class="form-check-input"
        type="checkbox"
        id="guardarMascota"
        name="guardar_mascota"
        value="1"
        <?= $guardarInitial ? 'checked' : '' ?>
        data-initial="<?= $guardarInitial ? '1' : '0' ?>"
      >
      <label class="form-check-label fw-semibold ms-2" for="guardarMascota">
        Guardar
      </label>
    </div>
  </div>

  <div class="row">
    <?php foreach ($camposCatalogo as $campo): ?>
      <?php
        if (in_array($campo['campo'], $camposGenerales, true)) {
            continue;
        }

        $campoKey = $campo['campo'];
        $campoLabel = $campo['etiqueta'];
        $visibleInicial = in_array($campoKey, $camposVisiblesActuales, true);
      ?>
      <div
        class="col-md-4 mb-3 campo-manual-item"
        data-campo="<?= htmlspecialchars($campoKey) ?>"
        style="<?= $visibleInicial ? '' : 'display:none;' ?>"
      >
        <label class="form-label fw-semibold">
          <?= htmlspecialchars($campoLabel) ?>
        </label>

        <?php if ($campoKey === 'raza'): ?>
          <select id="manual_raza_select" class="select2 form-select" style="width:100%;">
            <option value="">Seleccione raza...</option>
            <?php lisRazas(); ?>
          </select>
          <input type="hidden" id="manual_raza" name="manual_raza">

        <?php elseif ($campoKey === 'sexo'): ?>
          <select class="form-select" id="manual_sexo" name="manual_sexo">
            <option value="">Seleccione...</option>
            <?php foreach ($sexos_manual as $val => $label): ?>
              <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($campoKey === 'fecha_nacimiento'): ?>
          <input type="date" class="form-control" name="manual_fecha_nacimiento" id="manual_fecha_nacimiento">

        <?php else: ?>
          <input
            type="text"
            class="form-control"
            name="manual_<?= htmlspecialchars($campoKey) ?>"
            id="manual_<?= htmlspecialchars($campoKey) ?>"
          >
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
window.CERT_CAMPOS_VISIBLES = <?= json_encode(array_values($camposVisiblesActuales), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.CERT_CAMPOS_GENERALES = ['antecedentes', 'm_solicitante', 'recinto'];

function sinAcentos(s) {
  return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function limpiarCampoContenedor($wrap) {
  $wrap.find('input[type="text"], input[type="date"], input[type="hidden"], textarea').val('');
  $wrap.find('select').each(function () {
    $(this).val('').trigger('change');
  });
}

function aplicarCamposVisiblesFormulario(camposVisibles) {
  window.CERT_CAMPOS_VISIBLES = Array.isArray(camposVisibles) ? camposVisibles : [];

  $('.campo-manual-item').each(function () {
    const $item = $(this);
    const campo = String($item.data('campo') || '').trim();
    const visible = window.CERT_CAMPOS_VISIBLES.includes(campo);

    if (visible) {
      $item.stop(true, true).slideDown(150);
    } else {
      limpiarCampoContenedor($item);
      $item.stop(true, true).slideUp(150);
    }
  });

  $('[data-campo-general]').each(function () {
    const $item = $(this);
    const campo = String($item.data('campo-general') || '').trim();
    const visible = window.CERT_CAMPOS_VISIBLES.includes(campo);

    if (visible) {
      $item.stop(true, true).show();
    } else {
      limpiarCampoContenedor($item);
      $item.stop(true, true).hide();
    }
  });
}

function getCamposManualesVisibles() {
  return $('.campo-manual-item')
    .filter(function () {
      return $(this).is(':visible');
    })
    .map(function () {
      return String($(this).data('campo') || '').trim();
    })
    .get();
}

function validarPacienteManualUI() {
  if (!$('#toggle_manual').is(':checked')) {
    return true;
  }

  const camposVisibles = getCamposManualesVisibles();

  for (let i = 0; i < camposVisibles.length; i++) {
    const campo = camposVisibles[i];
    let valor = '';

    if (campo === 'raza') {
      valor = ($('#manual_raza').val() || '').trim();
    } else if (campo === 'sexo') {
      valor = ($('#manual_sexo').val() || '').trim();
    } else {
      valor = ($('#manual_' + campo).val() || '').trim();
    }

    if (!valor) {
      return false;
    }
  }

  return true;
}

function initSelect2RazaManual() {
  const $sel = $('#manual_raza_select');
  if (!$sel.length) return;

  if ($sel.hasClass('select2-hidden-accessible')) {
    $sel.select2('destroy');
  }

  $sel.select2({
    placeholder: 'Seleccione raza...',
    allowClear: true,
    minimumResultsForSearch: 0,
    width: 'resolve'
  });

  $sel.off('change.certRaza').on('change.certRaza', function () {
    const $opt = $(this).find('option:selected');
    const razaNom = ($opt.text() || '').trim();
    const especie = ($opt.closest('optgroup').attr('label') || '').trim();

    $('#manual_raza').val(razaNom && razaNom !== 'Seleccione raza...' ? razaNom : '');

    if ($('#manual_especie').length) {
      if (especie) {
        $('#manual_especie').val(especie);
      }
    }
  });

  $sel.trigger('change');
}

$(function () {
  const $toggle = $('#toggle_manual');

  if ($toggle.is(':checked')) {
    initSelect2RazaManual();
  }

  aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);

  $toggle.on('change', function () {
    if (this.checked) {
      $('#paciente-manual').slideDown();
      $('#paciente_seleccionado').prop('readonly', true);
      $('#paciente_id').val('');
      $('#paciente_seleccionado').val('').removeData();

      $('#paciente-manual').find('input[type="text"], input[type="date"], input[type="hidden"]').val('');
      $('#paciente-manual').find('select').val('').trigger('change');

      setTimeout(function () {
        initSelect2RazaManual();
        aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
      }, 0);
    } else {
      $('#paciente-manual').slideUp();
      $('#paciente_seleccionado').prop('readonly', false);
      $('#guardarMascota').prop('checked', false);
    }
  });
});

function abrirModalBuscarPaciente() {
  $('#modalBuscarPaciente').modal('show');
  $('#buscarPacienteInput').val('');
  $('#resultadosBuscarPaciente').html('<p class="text-muted">Comience a escribir para ver resultados.</p>');
}

$('#buscarPacienteInput').on('input', function () {
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
    success: function (data) {
      $('#resultadosBuscarPaciente').html(data);
    },
    error: function () {
      $('#resultadosBuscarPaciente').html('<div class="alert alert-danger">Error al buscar.</div>');
    }
  });
});

function seleccionarPaciente(id, mascota, tutor, especie, raza, edad, sexo) {
  $('#paciente_id').val(id);
  $('#paciente_seleccionado')
    .val(`${mascota}, ${especie}, ${raza} - Tutor: ${tutor}`)
    .data('especie', especie)
    .data('raza', raza)
    .data('edad', edad)
    .data('fecha_nacimiento', edad)
    .data('sexo', sexo);

  $('#modalBuscarPaciente').modal('hide');
}

$('#pacienteSeleccion').on('click', function () {
  if (!$('#toggle_manual').is(':checked')) {
    abrirModalBuscarPaciente();
  }
});

window.MANUAL_DATA = <?php
  $md = $fila['manual_data'] ?? null;
  if ($md) {
    $arr = json_decode($md, true);
    echo json_encode($arr ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  } else {
    echo 'null';
  }
?>;

function preselectRazaByTextAndEspecie(nombreRaza, nombreEspecie) {
  const $sel = $('#manual_raza_select');
  if (!$sel.length) return;

  let found = null;

  $sel.find('optgroup').each(function () {
    const $og = $(this);
    const label = ($og.attr('label') || '').trim().toLowerCase();

    if (nombreEspecie && label !== (nombreEspecie || '').trim().toLowerCase()) {
      return;
    }

    $og.children('option').each(function () {
      const txt = ($(this).text() || '').trim().toLowerCase();
      if (txt === (nombreRaza || '').trim().toLowerCase()) {
        found = $(this).val();
        return false;
      }
    });

    if (found) return false;
  });

  if (found !== null) {
    $sel.val(found).trigger('change');
  }
}

function prefillManualFromData(data) {
  if (!data) return;

  const map = {
    paciente: 'manual_paciente',
    especie: 'manual_especie',
    propietario: 'manual_propietario',
    n_chip: 'manual_n_chip',
    codigo_paciente: 'manual_codigo_paciente',
    fecha_nacimiento: 'manual_fecha_nacimiento'
  };

  Object.keys(map).forEach(function (k) {
    if (data[k] != null && $('#' + map[k]).length) {
      $('#' + map[k]).val(String(data[k]));
    }
  });

  if (data.sexo && $('#manual_sexo').length) {
    $('#manual_sexo').val(data.sexo).trigger('change');
  }

  if (data.raza) {
    preselectRazaByTextAndEspecie(data.raza, data.especie || null);
  }

  if (data.raza && !$('#manual_raza').val()) {
    $('#manual_raza').val(data.raza);
  }
}

$(function () {
  const noPacienteSeleccionado = !($('#paciente_id').val() || '').trim();

  if (window.MANUAL_DATA && noPacienteSeleccionado) {
    $('#toggle_manual').prop('checked', true).trigger('change');

    setTimeout(function () {
      if ($('#manual_raza_select').length && !$('#manual_raza_select').hasClass('select2-hidden-accessible')) {
        initSelect2RazaManual();
      }

      aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
      prefillManualFromData(window.MANUAL_DATA);
    }, 50);
  }
});
</script>