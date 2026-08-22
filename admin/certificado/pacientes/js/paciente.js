//admin/certificado/pacientes/js/paciente.js

function sinAcentos(s) {
  return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function limpiarCampoContenedor($wrap) {
  $wrap
    .find(
      'input[type="text"], ' +
      'input[type="date"], ' +
      'input[type="number"], ' +
      'input[type="hidden"], ' +
      'textarea'
    )
    .val('');

  $wrap.find('select').each(function () {
    $(this).val('').trigger('change');
  });

  $wrap
    .find('.manual-fecha-edad')
    .hide();

  $wrap
    .find('.manual-fecha-normal')
    .css('display', 'flex');
}

function sincronizarHabilitacionPorInterno() {
  $('.campo-manual-item').each(function () {
    const $item = $(this);

    const habilitado =
      $item.attr('data-campo-habilitado') === '1';

    const especieDerivada =
      $item.attr('data-especie-derivada') === '1';

    if (especieDerivada) {
      $item
        .find('input, select, textarea')
        .prop('disabled', true);

      $item
        .find('input[type="hidden"][name="manual_especie"]')
        .prop('disabled', false);

      return;
    }

    $item
      .find('input, select, textarea')
      .prop('disabled', !habilitado);
  });

  $('[data-campo-general]').each(function () {
    const $item = $(this);

    const habilitado =
      $item.attr('data-campo-habilitado') === '1';

    $item
      .find('input, select, textarea')
      .prop('disabled', !habilitado);
  });
}

function aplicarCamposVisiblesFormulario(camposVisibles) {
  const visibles = Array.isArray(camposVisibles)
    ? camposVisibles
    : [];

  window.CERT_CAMPOS_VISIBLES = visibles;

  let razaVisible = false;
  let especieVisible = false;

  $('.campo-manual-item').each(function () {
    const $item = $(this);
    const campo = String($item.data('campo') || '').trim();
    const interno = String($item.data('interno') || '').trim();
    const visible = visibles.includes(campo);

    if (!visible) {
      return;
    }

    if (interno === 'raza') {
      razaVisible = true;
    }

    if (interno === 'especie') {
      especieVisible = true;
    }
  });

  const combinarRazaEspecie =
    razaVisible && especieVisible;

  window.CERT_RAZA_ESPECIE_COMBINADAS =
    combinarRazaEspecie;

  $('.campo-manual-item').each(function () {
    const $item = $(this);
    const campo = String($item.data('campo') || '').trim();
    const interno = String($item.data('interno') || '').trim();
    const visible = visibles.includes(campo);

    const especieDerivada =
      visible &&
      combinarRazaEspecie &&
      interno === 'especie';

    $item.attr(
      'data-campo-habilitado',
      visible ? '1' : '0'
    );

    $item.attr(
      'data-especie-derivada',
      especieDerivada ? '1' : '0'
    );

    if (especieDerivada) {
      $item.stop(true, true).hide();
      return;
    }

    if (visible) {
      $item.stop(true, true).slideDown(150);
    } else {
      limpiarCampoContenedor($item);
      $item.stop(true, true).slideUp(150);
    }
  });

  $('[data-campo-general]').each(function () {
    const $item = $(this);
    const campo = String(
      $item.data('campo-general') || ''
    ).trim();

    const visible = visibles.includes(campo);

    $item.attr(
      'data-campo-habilitado',
      visible ? '1' : '0'
    );

    if (visible) {
      $item.stop(true, true).show();
    } else {
      limpiarCampoContenedor($item);
      $item.stop(true, true).hide();
    }
  });

  setTimeout(function () {
    sincronizarHabilitacionPorInterno();

    if (combinarRazaEspecie) {
      const $razaSelect = $('#manual_raza_select');
      const razaId = String($razaSelect.val() || '').trim();
      const razaTexto = String(
        $('#manual_raza').val() || ''
      ).trim();

      const especieTexto = String(
        $('#manual_especie').val() || ''
      ).trim();

      if (razaId) {
        $razaSelect.trigger('change');
      } else if (razaTexto) {
        preselectRazaByTextAndEspecie(
          razaTexto,
          especieTexto
        );
      } else {
        $('#manual_especie').val('');
      }

      return;
    }

    if (especieVisible) {
      const especieTexto = String(
        $('#manual_especie').val() || ''
      ).trim();

      if (especieTexto) {
        preselectEspecieByText(especieTexto);
      }
    }
  }, 160);
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

function preselectEspecieByText(nombreEspecie) {
  const $sel = $('#manual_especie_select');
  if (!$sel.length) return;

  const objetivo = sinAcentos(String(nombreEspecie || ''))
    .trim()
    .toLowerCase();

  let found = null;

  $sel.find('option').each(function () {
    const texto = sinAcentos($(this).text() || '')
      .trim()
      .toLowerCase();

    if (objetivo && texto === objetivo) {
      found = $(this).val();
      return false;
    }
  });

  if (found !== null) {
    $sel.val(found).trigger('change');
  }
}

function initSelect2EspecieManual() {
  const $sel = $('#manual_especie_select');
  if (!$sel.length) return;

  const especieActualHidden = ($('#manual_especie').val() || '').trim();
  const especieActualData = String($sel.data('current-text') || '').trim();
  const especieObjetivo = especieActualHidden || especieActualData;

  if ($sel.hasClass('select2-hidden-accessible')) {
    $sel.select2('destroy');
  }

  $sel.select2({
    placeholder: 'Seleccione especie...',
    allowClear: true,
    minimumResultsForSearch: 0,
    width: 'resolve'
  });

  $sel.off('change.certEspecie').on('change.certEspecie', function () {
    const $opt = $(this).find('option:selected');
    const especieId = String($(this).val() || '').trim();
    const especieNombre = ($opt.text() || '').trim();

    $('#manual_especie').val(
      especieId && especieNombre !== 'Seleccione especie...'
        ? especieNombre
        : ''
    );
  });

  if (especieObjetivo) {
    preselectEspecieByText(especieObjetivo);
  } else {
    $sel.trigger('change');
  }
}

function initSelect2RazaManual() {
  const $sel = $('#manual_raza_select');
  if (!$sel.length) return;

  const razaActualHidden = String(
    $('#manual_raza').val() || ''
  ).trim();

  const razaActualData = String(
    $sel.data('current-text') || ''
  ).trim();

  const razaObjetivo =
    razaActualHidden || razaActualData;

  if ($sel.hasClass('select2-hidden-accessible')) {
    $sel.select2('destroy');
  }

  $sel.select2({
    placeholder: 'Seleccione raza...',
    allowClear: true,
    minimumResultsForSearch: 0,
    width: 'resolve'
  });

  $sel
    .off('change.certRaza')
    .on('change.certRaza', function () {
      const $opt = $(this).find('option:selected');
      const razaId = String($(this).val() || '').trim();
      const razaNombre = ($opt.text() || '').trim();

      const especieNombre = (
        $opt.closest('optgroup').attr('label') || ''
      ).trim();

      $('#manual_raza').val(
        razaId && razaNombre !== 'Seleccione raza...'
          ? razaNombre
          : ''
      );

      if (
        window.CERT_RAZA_ESPECIE_COMBINADAS === true &&
        $('#manual_especie').length
      ) {
        $('#manual_especie').val(
          razaId ? especieNombre : ''
        );
      }
    });

  if (razaObjetivo) {
    preselectRazaByTextAndEspecie(
      razaObjetivo,
      String($('#manual_especie').val() || '').trim()
    );
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

function seleccionarPaciente(
  id,
  mascota,
  tutor,
  especie,
  raza,
  edad,
  sexo,
  fecha_nacimiento,
  codigo_paciente = ''
) {
  const nombreMascota = String(
    mascota || ''
  ).trim();

  const codigoPaciente = String(
    codigo_paciente || ''
  ).trim();

  const razaPaciente = String(
    raza || ''
  ).trim();

  const tutorPaciente = String(
    tutor || ''
  ).trim();

  let textoPaciente = nombreMascota;

  if (codigoPaciente) {
    textoPaciente += ' (' + codigoPaciente + ')';
  }

  if (razaPaciente) {
    textoPaciente += ', ' + razaPaciente;
  }

  if (tutorPaciente) {
    textoPaciente += ' - Tutor: ' + tutorPaciente;
  }

  $('#paciente_id').val(id);

  $('#paciente_seleccionado')
    .val(textoPaciente)
    .data('codigo_paciente', codigoPaciente)
    .data('especie', especie)
    .data('raza', raza)
    .data('edad', edad)
    .data('fecha_nacimiento', fecha_nacimiento || '')
    .data('sexo', sexo);

  const inputFueraModal =
    document.getElementById('paciente_seleccionado');

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

function mostrarAvisoEdadManual(mensaje) {
  if (window.Swal) {
    Swal.fire(
      'Edad no válida',
      mensaje,
      'warning'
    );

    return;
  }

  window.alert(mensaje);
}

function calcularFechaNacimientoDesdeEdad(anios, meses) {
  const hoy = new Date();

  const anioActual = hoy.getFullYear();
  const mesActual = hoy.getMonth();
  const diaActual = hoy.getDate();

  const mesesTotales =
    (anios * 12) + meses;

  const mesAbsolutoActual =
    (anioActual * 12) + mesActual;

  const mesAbsolutoNacimiento =
    mesAbsolutoActual - mesesTotales;

  const anioNacimiento = Math.floor(
    mesAbsolutoNacimiento / 12
  );

  const mesNacimiento =
    ((mesAbsolutoNacimiento % 12) + 12) % 12;

  const ultimoDiaMes = new Date(
    anioNacimiento,
    mesNacimiento + 1,
    0
  ).getDate();

  const diaNacimiento = Math.min(
    diaActual,
    ultimoDiaMes
  );

  const anioTexto = String(
    anioNacimiento
  ).padStart(4, '0');

  const mesTexto = String(
    mesNacimiento + 1
  ).padStart(2, '0');

  const diaTexto = String(
    diaNacimiento
  ).padStart(2, '0');

  return (
    anioTexto +
    '-' +
    mesTexto +
    '-' +
    diaTexto
  );
}

function initCalculadoraEdadManual() {
  $(document)
    .off(
      'click.certMostrarEdadManual',
      '.btn-calcular-edad-manual'
    )
    .on(
      'click.certMostrarEdadManual',
      '.btn-calcular-edad-manual',
      function () {
        const $campo = $(this).closest(
          '.campo-manual-item'
        );

        const $normal = $campo.find(
          '.manual-fecha-normal'
        ).first();

        const $edad = $campo.find(
          '.manual-fecha-edad'
        ).first();

        if (
          !$normal.length ||
          !$edad.length
        ) {
          return;
        }

        $normal.hide();
        $edad.css('display', 'flex');

        const $anios = $edad.find(
          '.manual-edad-anios'
        ).first();

        setTimeout(function () {
          $anios.trigger('focus');
        }, 0);
      }
    );

  $(document)
    .off(
      'click.certAplicarEdadManual',
      '.btn-aplicar-edad-manual'
    )
    .on(
      'click.certAplicarEdadManual',
      '.btn-aplicar-edad-manual',
      function () {
        const $campo = $(this).closest(
          '.campo-manual-item'
        );

        const $anios = $campo.find(
          '.manual-edad-anios'
        ).first();

        const $meses = $campo.find(
          '.manual-edad-meses'
        ).first();

        const $fecha = $campo.find(
          'input[name="manual_fecha_nacimiento"]'
        ).first();

        const $normal = $campo.find(
          '.manual-fecha-normal'
        ).first();

        const $edad = $campo.find(
          '.manual-fecha-edad'
        ).first();

        if (!$fecha.length) {
          return;
        }

        const valorAnios = String(
          $anios.val() || ''
        ).trim();

        const valorMeses = String(
          $meses.val() || ''
        ).trim();

        if (!valorAnios && !valorMeses) {
          mostrarAvisoEdadManual(
            'Ingresa los años o meses del paciente.'
          );

          $anios.trigger('focus');

          return;
        }

        const anios = valorAnios
          ? Number(valorAnios)
          : 0;

        const meses = valorMeses
          ? Number(valorMeses)
          : 0;

        if (
          !Number.isInteger(anios) ||
          anios < 0
        ) {
          mostrarAvisoEdadManual(
            'Los años deben ser un número entero igual o mayor a 0.'
          );

          $anios.trigger('focus');

          return;
        }

        if (
          !Number.isInteger(meses) ||
          meses < 0 ||
          meses > 11
        ) {
          mostrarAvisoEdadManual(
            'Los meses deben estar entre 0 y 11.'
          );

          $meses.trigger('focus');

          return;
        }

        const fechaNacimiento =
          calcularFechaNacimientoDesdeEdad(
            anios,
            meses
          );

        $fecha
          .val(fechaNacimiento)
          .trigger('input')
          .trigger('change');

        $edad.hide();
        $normal.css('display', 'flex');
      }
    );

  $(document)
    .off(
      'keydown.certEdadManual',
      '.manual-edad-anios, .manual-edad-meses'
    )
    .on(
      'keydown.certEdadManual',
      '.manual-edad-anios, .manual-edad-meses',
      function (e) {
        if (e.key !== 'Enter') {
          return;
        }

        e.preventDefault();

        $(this)
          .closest('.campo-manual-item')
          .find('.btn-aplicar-edad-manual')
          .first()
          .trigger('click');
      }
    );
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

  if (data.especie) {
    preselectEspecieByText(data.especie);
  }

  if (data.sexo && $('#manual_sexo').length) {
    $('#manual_sexo').val(data.sexo).trigger('change');
  }

  if (data.raza) {
    preselectRazaByTextAndEspecie(
      data.raza,
      data.especie || null
    );
  }

  if (data.raza && !$('#manual_raza').val()) {
    $('#manual_raza').val(data.raza);
  }
}

window.CERT_PACIENTE_MANUAL_STATE =
  window.CERT_PACIENTE_MANUAL_STATE || {};

if (
  typeof window.CERT_PACIENTE_MANUAL_STATE.codigoTimer === 'undefined'
) {
  window.CERT_PACIENTE_MANUAL_STATE.codigoTimer = null;
}

if (
  typeof window.CERT_PACIENTE_MANUAL_STATE.codigoRequest === 'undefined'
) {
  window.CERT_PACIENTE_MANUAL_STATE.codigoRequest = null;
}

function getManualCodigoPacienteInput() {
  const $inputs = $('[id="manual_codigo_paciente"]');

  if (!$inputs.length) {
    return $();
  }

  const $visible = $inputs.filter(':visible').first();

  if ($visible.length) {
    return $visible;
  }

  return $inputs.first();
}

function asegurarContenedorCoincidenciasCodigoPaciente() {
  const $manualVisible = $('[id="paciente-manual"]')
    .filter(':visible')
    .first();

  if (!$manualVisible.length) {
    return $();
  }

  return $manualVisible
    .find('.manual-codigo-paciente-coincidencias-header')
    .first();
}

function limpiarCoincidenciasCodigoPaciente() {
  const $contenedor = asegurarContenedorCoincidenciasCodigoPaciente();

  if ($contenedor.length) {
    $contenedor.empty();
  }
}

function formatearFechaPacienteManual(fecha) {
  const valor = String(fecha || '').trim();

  if (!valor) {
    return '';
  }

  const partes = valor.split('-');

  if (partes.length !== 3) {
    return valor;
  }

  return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function mostrarCoincidenciasCodigoPaciente(matches) {
  const $contenedor =
    asegurarContenedorCoincidenciasCodigoPaciente();

  if (!$contenedor.length) {
    return;
  }

  $contenedor.empty();

  if (!Array.isArray(matches) || !matches.length) {
    return;
  }

  matches.forEach(function (paciente) {
    const nombre = String(
      paciente.paciente || 'Paciente'
    ).trim();

    const codigo = String(
      paciente.codigo_paciente || ''
    ).trim();

    const raza = String(
      paciente.raza || ''
    ).trim();

    const propietario = String(
      paciente.propietario || ''
    ).trim();

    let texto = nombre;

    if (codigo) {
      texto += ' (' + codigo + ')';
    }

    if (raza) {
      texto += ' · ' + raza;
    }

    if (propietario) {
      texto += ' - Tutor: ' + propietario;
    }

    const $coincidencia = $('<div>', {
      class:
        'alert alert-warning ' +
        'manual-codigo-coincidencia ' +
        'd-flex align-items-center ' +
        'gap-2 mb-1'
    });

    const $texto = $('<span>', {
      class:
        'manual-codigo-coincidencia-texto ' +
        'small flex-grow-1'
    }).text(texto);

    const $boton = $('<button>', {
      type: 'button',
      class:
        'btn btn-success ' +
        'btn-usar-paciente-codigo ' +
        'manual-codigo-btn-usar ' +
        'flex-shrink-0'
    }).html(
      '<i class="fas fa-check"></i> Usar'
    );

    $boton.data('paciente', paciente);

    $coincidencia.append(
      $texto,
      $boton
    );

    $contenedor.append($coincidencia);
  });
}

function limpiarFormularioManualParaPacienteExistente() {
  const $manual = $('#paciente-manual');

  if (!$manual.length) {
    return;
  }

  $manual
  .find(
    'input[type="text"], ' +
    'input[type="date"], ' +
    'input[type="number"], ' +
    'input[type="hidden"], ' +
    'textarea'
  )
  .val('');

  $manual
    .find('.manual-fecha-edad')
    .hide();

  $manual
    .find('.manual-fecha-normal')
    .css('display', 'flex');

  $manual
    .find('select')
    .val('')
    .trigger('change');

  $('#guardarMascota').prop('checked', false);
}

function usarPacienteDesdeCoincidenciaCodigo(paciente) {
  if (!paciente) {
    return;
  }

  const pacienteId = parseInt(
    paciente.paciente_id,
    10
  ) || 0;

  if (pacienteId <= 0) {
    return;
  }

  const nombre = String(
    paciente.paciente || ''
  ).trim();

  const propietario = String(
    paciente.propietario || ''
  ).trim();

  const especie = String(
    paciente.especie || ''
  ).trim();

  const raza = String(
    paciente.raza || ''
  ).trim();

  const sexo = String(
    paciente.sexo || ''
  ).trim();

  const fechaNacimiento = String(
    paciente.fecha_nacimiento || ''
  ).trim();

  seleccionarPaciente(
    pacienteId,
    nombre,
    propietario,
    especie,
    raza,
    '',
    sexo,
    fechaNacimiento,
    String(paciente.codigo_paciente || '').trim()
  );

  limpiarFormularioManualParaPacienteExistente();

  const $toggleManual = $('[id="toggle_manual"]')
    .filter(':visible')
    .first();

  if ($toggleManual.length) {
    $toggleManual
      .prop('checked', false)
      .trigger('change');
  }

  limpiarCoincidenciasCodigoPaciente();
}

function buscarCoincidenciasCodigoPaciente(codigo) {
  const state = window.CERT_PACIENTE_MANUAL_STATE;

  const codigoBuscado = String(codigo || '').trim();

  if (!codigoBuscado) {
    limpiarCoincidenciasCodigoPaciente();
    return;
  }

  if (state.codigoRequest) {
    state.codigoRequest.abort();
    state.codigoRequest = null;
  }

  const $contenedor =
    asegurarContenedorCoincidenciasCodigoPaciente();

  if (!$contenedor.length) {
    return;
  }

  $contenedor.html(
    '<span class="small text-muted text-nowrap">' +
      '<i class="fas fa-spinner fa-spin me-1"></i>' +
      'Buscando código...' +
    '</span>'
  );

  const request = $.ajax({
    url: 'certificado/pacientes/buscar_codigo.php',
    type: 'GET',
    dataType: 'json',

    data: {
      q: codigoBuscado
    },

    success: function (response) {
      const $inputActual =
        getManualCodigoPacienteInput();

      const codigoActual = String(
        $inputActual.val() || ''
      ).trim();

      if (codigoActual !== codigoBuscado) {
        return;
      }

      if (!$('#toggle_manual').is(':checked')) {
        limpiarCoincidenciasCodigoPaciente();
        return;
      }

      if (
        !response ||
        response.status !== 'success' ||
        !Array.isArray(response.matches)
      ) {
        limpiarCoincidenciasCodigoPaciente();
        return;
      }

      mostrarCoincidenciasCodigoPaciente(
        response.matches
      );
    },

    error: function (xhr, status) {
      if (status === 'abort') {
        return;
      }

      const $inputActual =
        getManualCodigoPacienteInput();

      const codigoActual = String(
        $inputActual.val() || ''
      ).trim();

      if (codigoActual !== codigoBuscado) {
        return;
      }

      $contenedor.html(
        '<div class="small text-danger">' +
          'No se pudo comprobar el código.' +
        '</div>'
      );
    },

    complete: function () {
      if (state.codigoRequest === request) {
        state.codigoRequest = null;
      }
    }
  });

  state.codigoRequest = request;
}

function initBusquedaCodigoPacienteManual() {
  const state = window.CERT_PACIENTE_MANUAL_STATE;

  if (state.codigoTimer) {
    clearTimeout(state.codigoTimer);
    state.codigoTimer = null;
  }

  if (state.codigoRequest) {
    state.codigoRequest.abort();
    state.codigoRequest = null;
  }

  asegurarContenedorCoincidenciasCodigoPaciente();

  $(document)
    .off(
      'click.certUsarPacienteCodigo',
      '.btn-usar-paciente-codigo'
    )
    .on(
      'click.certUsarPacienteCodigo',
      '.btn-usar-paciente-codigo',
      function () {
        const paciente = $(this).data('paciente');

        usarPacienteDesdeCoincidenciaCodigo(
          paciente
        );
      }
    );

  $(document)
    .off(
      'input.certCodigoPaciente',
      '[id="manual_codigo_paciente"]'
    )
    .on(
      'input.certCodigoPaciente',
      '[id="manual_codigo_paciente"]',
      function () {
        const codigo = String(
          $(this).val() || ''
        ).trim();

        if (state.codigoTimer) {
          clearTimeout(state.codigoTimer);
          state.codigoTimer = null;
        }

        if (state.codigoRequest) {
          state.codigoRequest.abort();
          state.codigoRequest = null;
        }

        if (
          !$('#toggle_manual').is(':checked') ||
          !codigo
        ) {
          limpiarCoincidenciasCodigoPaciente();
          return;
        }

        state.codigoTimer = setTimeout(function () {
          state.codigoTimer = null;

          buscarCoincidenciasCodigoPaciente(
            codigo
          );
        }, 500);
      }
    );

  $(document)
    .off(
      'change.certCodigoPaciente',
      '#toggle_manual'
    )
    .on(
      'change.certCodigoPaciente',
      '#toggle_manual',
      function () {
        if (!this.checked) {
          limpiarCoincidenciasCodigoPaciente();

          if (state.codigoTimer) {
            clearTimeout(state.codigoTimer);
            state.codigoTimer = null;
          }

          if (state.codigoRequest) {
            state.codigoRequest.abort();
            state.codigoRequest = null;
          }

          return;
        }

        const $input =
          getManualCodigoPacienteInput();

        const codigo = String(
          $input.val() || ''
        ).trim();

        if (codigo) {
          $input.trigger('input');
        }
      }
    );

  const $inputInicial =
    getManualCodigoPacienteInput();

  const codigoInicial = String(
    $inputInicial.val() || ''
  ).trim();

  if (
    codigoInicial &&
    $('#toggle_manual').is(':checked')
  ) {
    $inputInicial.trigger('input');
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

    if (
      $('#manual_raza_select').length &&
      !$('#manual_raza_select').hasClass('select2-hidden-accessible')
    ) {
      initSelect2RazaManual();
    }

    if (
      $('#manual_especie_select').length &&
      !$('#manual_especie_select').hasClass('select2-hidden-accessible')
    ) {
      initSelect2EspecieManual();
    }

    aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
  }

  function abrirManualLimpiando() {
    $('#paciente-manual').slideDown();
    $('#paciente_seleccionado').prop('readonly', true);
    $('#paciente_id').val('');
    $('#paciente_seleccionado').val('').removeData();

    $('#paciente-manual')
      .find(
        'input[type="text"], ' +
        'input[type="date"], ' +
        'input[type="number"], ' +
        'input[type="hidden"]'
      )
      .val('');

    $('#paciente-manual')
      .find('.manual-fecha-edad')
      .hide();

    $('#paciente-manual')
      .find('.manual-fecha-normal')
      .css('display', 'flex');

    $('#paciente-manual')
      .find('select')
      .val('')
      .trigger('change');

    setTimeout(function () {
      initSelect2RazaManual();
      initSelect2EspecieManual();
      aplicarCamposVisiblesFormulario(window.CERT_CAMPOS_VISIBLES);
    }, 0);
  }

  function restaurarManualDesdeData(data) {
    if (!hasManualData(data)) {
      return;
    }

    $toggle.prop('checked', true);
    abrirManualSinLimpiar();

    if (
      $('#manual_raza_select').length &&
      !$('#manual_raza_select').hasClass('select2-hidden-accessible')
    ) {
      initSelect2RazaManual();
    }

    if (
      $('#manual_especie_select').length &&
      !$('#manual_especie_select').hasClass('select2-hidden-accessible')
    ) {
      initSelect2EspecieManual();
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

$(function () {
  initBusquedaCodigoPacienteManual();
  initCalculadoraEdadManual();
});