//admin/certificado/metodo_ingreso/metodo_ingreso.js
function audio_manual_isManual() {
  return $('#toggle_audio_manual').is(':checked');
}

window.__audioManualCurrentMode = null;
window.__audioManualFirstRenderDone = false;

function vmMainEditorExists() {
  if (
    !window.VetmindTiptap ||
    typeof window.VetmindTiptap.getMainEditor !== 'function'
  ) {
    return false;
  }

  const editor = window.VetmindTiptap.getMainEditor();
  if (!editor) {
    return false;
  }

  const editorElement = document.getElementById('contenido_html_editor');
  if (!editorElement) {
    if (typeof window.VetmindTiptap.destroyMainEditor === 'function') {
      window.VetmindTiptap.destroyMainEditor();
    }
    return false;
  }

  const editorDom =
    editor.view && editor.view.dom ? editor.view.dom : null;

  const editorSigueEnElDom =
    !!editorDom &&
    editorDom.isConnected &&
    (editorDom === editorElement || editorElement.contains(editorDom));

  if (!editorSigueEnElDom) {
    if (typeof window.VetmindTiptap.destroyMainEditor === 'function') {
      window.VetmindTiptap.destroyMainEditor();
    }
    return false;
  }

  return true;
}

function vmInitMainEditorIfNeeded() {
  const plantilla = ($('#plantillaBase').val() || '').trim();
  const contenidoActual = ($('#contenido_html').val() || '').trim();
  const esNuevoSinContenido = !ES_MODIFICAR && !contenidoActual && !plantilla;

  if (!window.VetmindTiptap || typeof window.VetmindTiptap.initMainEditor !== 'function') {
    return;
  }

  if (typeof window.VetmindTiptap.destroyMainEditor === 'function') {
    window.VetmindTiptap.destroyMainEditor();
  }

  if (esNuevoSinContenido) {
    $('#contenido_html').val('');
  }

  window.VetmindTiptap.initMainEditor({
    content: esNuevoSinContenido ? '' : contenidoActual
  });

  if (ES_MODIFICAR) {
    if (typeof window.VetmindTiptap.setMainEditorHTML === 'function') {
      window.VetmindTiptap.setMainEditorHTML(contenidoActual || '<p></p>');
    }
  } else if (contenidoActual) {
    if (typeof window.VetmindTiptap.setMainEditorHTML === 'function') {
      window.VetmindTiptap.setMainEditorHTML(contenidoActual);
    }
  } else if (plantilla) {
    const editor = (typeof window.VetmindTiptap.getMainEditor === 'function')
      ? window.VetmindTiptap.getMainEditor()
      : null;

    let contenidoEditor = '';
    if (editor && typeof editor.getHTML === 'function') {
      contenidoEditor = (editor.getHTML() || '').trim();
    }

    const editorVacio =
      !contenidoEditor ||
      contenidoEditor === '<p></p>' ||
      contenidoEditor === '<p class="is-editor-empty"></p>';

    if (editorVacio) {
      if (typeof window.VetmindTiptap.setMainEditorHTML === 'function') {
        window.VetmindTiptap.setMainEditorHTML(plantilla);
      } else if (typeof window.VetmindTiptap.insertPlantillaIfEmpty === 'function') {
        window.VetmindTiptap.insertPlantillaIfEmpty(plantilla);
      }
    }
  } else {
    if (typeof window.VetmindTiptap.setMainEditorHTML === 'function') {
      window.VetmindTiptap.setMainEditorHTML('<p></p>');
    }
  }

  if (typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
    window.VetmindTiptap.syncMainEditorToTextarea();
  }
}

function vmDestroyMainEditorIfNeeded() {
  if (window.VetmindTiptap && typeof window.VetmindTiptap.destroyMainEditor === 'function') {
    window.VetmindTiptap.destroyMainEditor();
  }
}

function vmSyncMainEditorIfNeeded() {
  if (window.VetmindTiptap && typeof window.VetmindTiptap.syncMainEditorToTextarea === 'function') {
    window.VetmindTiptap.syncMainEditorToTextarea();
  }
}

function audio_manual_setMode(mode) {
  const toManual = (mode === 'manual');
  const newMode = toManual ? 'manual' : 'audio';
  const useAnim = window.__audioManualFirstRenderDone;

  const sameMode = (window.__audioManualCurrentMode === newMode);

  if (sameMode) {
    if (toManual) {
      $('#toggle_audio_manual').prop('checked', true);
      $('#audio_manual_audioBtn').toggleClass('active', false);
      $('#audio_manual_manualBtn').toggleClass('active', true);
      $('#bloque-audio').hide();
      $('#bloque-manual').show();

      if (!vmMainEditorExists()) {
        vmInitMainEditorIfNeeded();
      }
    } else {
      $('#toggle_audio_manual').prop('checked', false);
      $('#audio_manual_audioBtn').toggleClass('active', true);
      $('#audio_manual_manualBtn').toggleClass('active', false);
      $('#bloque-manual').hide();
      $('#bloque-audio').show();
    }

    return;
  }

  window.__audioManualCurrentMode = newMode;

  $('#audio_manual_audioBtn').toggleClass('active', !toManual);
  $('#audio_manual_manualBtn').toggleClass('active', toManual);
  $('#toggle_audio_manual').prop('checked', toManual);

  if (toManual) {
    if (useAnim) {
      $('#bloque-audio').stop(true, true).slideUp(120);
      $('#bloque-manual').stop(true, true).slideDown(120, function () {
        vmInitMainEditorIfNeeded();
      });
    } else {
      $('#bloque-audio').hide();
      $('#bloque-manual').show();
      vmInitMainEditorIfNeeded();
    }
  } else {
    if (window.recorder && window.recorder.state === 'recording') {
      try { stopRecording(); } catch (e) {}
    }

    if (useAnim) {
      $('#bloque-manual').stop(true, true).slideUp(120, function () {
        vmDestroyMainEditorIfNeeded();
      });
      $('#bloque-audio').stop(true, true).slideDown(120);
    } else {
      $('#bloque-manual').hide();
      vmDestroyMainEditorIfNeeded();
      $('#bloque-audio').show();
    }
  }

  $('#procesarIA').text('✨ Procesar IA');
  window.__audioManualFirstRenderDone = true;
}

$(function () {
  window.__audioManualCurrentMode = null;
  window.__audioManualFirstRenderDone = false;

  $('#audio_manual_audioBtn').off('click.audioManual').on('click.audioManual', () => audio_manual_setMode('audio'));
  $('#audio_manual_manualBtn').off('click.audioManual').on('click.audioManual', () => audio_manual_setMode('manual'));

  const aplicarModoInicial = function () {
    const toManual = $('#toggle_audio_manual').is(':checked');
    audio_manual_setMode(toManual ? 'manual' : 'audio');
  };

  const waitForTiptap = setInterval(function () {
    if (window.VetmindTiptap && typeof window.VetmindTiptap.initMainEditor === 'function') {
      clearInterval(waitForTiptap);
      aplicarModoInicial();
    }
  }, 80);

  setTimeout(function () {
    clearInterval(waitForTiptap);
    aplicarModoInicial();
  }, 5000);
});

window.vmInitMainEditorIfNeeded = vmInitMainEditorIfNeeded;
window.vmDestroyMainEditorIfNeeded = vmDestroyMainEditorIfNeeded;
window.vmSyncMainEditorIfNeeded = vmSyncMainEditorIfNeeded;