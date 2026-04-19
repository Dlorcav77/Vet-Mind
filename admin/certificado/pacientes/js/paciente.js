//admin/certificado/pacientes/js/paciente.js

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

  const razaActualHidden = ($('#manual_raza').val() || '').trim();
  const razaActualData = ($sel.data('current-text') || '').trim();
  const razaObjetivo = razaActualHidden || razaActualData;

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

  if (razaObjetivo) {
    preselectRazaByTextAndEspecie(razaObjetivo, ($('#manual_especie').val() || '').trim());
  } else {
    $sel.trigger('change');
  }
}

window.ultimoTriggerModalPaciente = window.ultimoTriggerModalPaciente || null;

function abrirModalBuscarPaciente(triggerEl = null) {
  window.ultimoTriggerModalPaciente = triggerEl || document.getElementById('paciente_seleccionado');

  $('#buscarPacienteInput').val('');
  $('#resultadosBuscarPaciente').html('<p class="text-muted">Comience a escribir para ver resultados.</p>');
  $('#modalBuscarPaciente').modal('show');
}

function seleccionarPaciente(id, mascota, tutor, especie, raza, edad, sexo) {
  $('#paciente_id').val(id);
  $('#paciente_seleccionado')
    .val(`${mascota}, ${especie}, ${raza} - Tutor: ${tutor}`)
    .data('especie', especie)
    .data('raza', raza)
    .data('edad', edad)
    .data('fecha_nacimiento', edad)
    .data('sexo', sexo);

  const inputFueraModal = document.getElementById('paciente_seleccionado');
  if (inputFueraModal) {
    inputFueraModal.focus();
    inputFueraModal.blur();
  }

  $('#modalBuscarPaciente').modal('hide');
}

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
  const $toggle = $('#toggle_manual');

  function hasManualData(data) {
    if (!data || typeof data !== 'object') {
      return false;
    }

    return Object.keys(data).some(function (key) {
      const value = data[key];
      return value != null && String(value).trim() !== '';
    });
  }

  function abrirManualSinLimpiar() {
    $('#paciente-manual').show();
    $('#paciente_seleccionado').prop('readonly', true);

    if ($('#manual_raza_select').length && !$('#manual_raza_select').hasClass('select2-hidden-accessible')) {
      initSelect2RazaManual();
    }

    aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
  }

  function abrirManualLimpiando() {
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
  }

  function restaurarManualDesdeData(data) {
    if (!hasManualData(data)) {
      return;
    }

    $toggle.prop('checked', true);
    abrirManualSinLimpiar();

    if ($('#manual_raza_select').length && !$('#manual_raza_select').hasClass('select2-hidden-accessible')) {
      initSelect2RazaManual();
    }

    aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
    prefillManualFromData(data);
  }

  if ($toggle.is(':checked')) {
    abrirManualSinLimpiar();
  } else {
    aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
  }

  $toggle.off('change.certManual').on('change.certManual', function () {
    if (this.checked) {
      abrirManualLimpiando();
    } else {
      $('#paciente-manual').slideUp();
      $('#paciente_seleccionado').prop('readonly', false);
      $('#guardarMascota').prop('checked', false);
    }
  });

  const noPacienteSeleccionado = !($('#paciente_id').val() || '').trim();
  const manualDataDisponible = hasManualData(window.MANUAL_DATA);

  if (manualDataDisponible && noPacienteSeleccionado) {
    restaurarManualDesdeData(window.MANUAL_DATA);

    setTimeout(function () {
      restaurarManualDesdeData(window.MANUAL_DATA);
    }, 120);

    setTimeout(function () {
      restaurarManualDesdeData(window.MANUAL_DATA);
    }, 400);

    setTimeout(function () {
      restaurarManualDesdeData(window.MANUAL_DATA);
    }, 900);
  }
});

$('#buscarPacienteInput').off('input.certBuscarPaciente').on('input.certBuscarPaciente', function () {
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

$('#pacienteSeleccion').off('click.certModalPaciente').on('click.certModalPaciente', function (e) {
  if (!$('#toggle_manual').is(':checked')) {
    const triggerReal = e.target.closest('button, input, #pacienteSeleccion') || this;
    abrirModalBuscarPaciente(triggerReal);
  }
});

$('#modalBuscarPaciente')
  .off('show.bs.modal.certModalPaciente')
  .on('show.bs.modal.certModalPaciente', function () {
    const inputBusqueda = document.getElementById('buscarPacienteInput');
    setTimeout(function () {
      if (inputBusqueda) inputBusqueda.focus();
    }, 150);
  });

$('#modalBuscarPaciente')
  .off('hide.bs.modal.certModalPaciente')
  .on('hide.bs.modal.certModalPaciente', function () {
    const activo = document.activeElement;
    if (activo && this.contains(activo)) {
      activo.blur();
    }

    const destino = window.ultimoTriggerModalPaciente || document.getElementById('paciente_seleccionado');
    setTimeout(function () {
      if (destino && typeof destino.focus === 'function') {
        destino.focus();
        destino.blur();
      }
    }, 0);
  });