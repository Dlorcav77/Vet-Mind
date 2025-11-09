<?php
  $isModificar = isset($action) && $action === 'modificar';
  $initialMode = $isModificar ? 'manual' : 'audio';
  $isManualInitial = $initialMode === 'manual';
?>
<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');

.btn-mic {
  width: 58px;            /* antes: 70px */
  height: 58px;           /* antes: 70px */
  font-size: 1.4rem; 
  border-radius: 50%;
  background: linear-gradient(145deg, #0055ff 30%, #36cfc9 100%);
  color: #fff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  box-shadow: 0 4px 24px #0007;
  transition: background 0.3s, box-shadow 0.2s;
  outline: none;
}
.btn-mic.recording {
  background: linear-gradient(145deg, #ff1744 60%, #ff8c99 100%);
  animation: pulse 1s infinite alternate;
}
@keyframes pulse {
  to { box-shadow: 0 0 22px 7px #ff174422, 0 0 10px #d500f9; }
}
.btn-mic:active {
  box-shadow: 0 2px 8px #0005;
}


.card-audio-dark {
    backdrop-filter: blur(4px);
    background: rgba(13, 17, 23, 0.82) !important;
    color: #e3eaf3 !important;
    border: none;
    box-shadow: 0 6px 32px 0 #000a !important;
    padding: 1.4rem 1.2rem !important;
}

/* Tabs dark, más pequeñas, centradas */
.audio-tabs-custom {
    width: 80%;
    margin: 0 auto 1rem auto !important;
    justify-content: center !important;
}
.card-audio-dark .audio-tabs-custom .nav-link {
    min-width: 160px;
    padding: 0.5rem 0.5rem;
    font-size: 1rem;
    border-radius: 2rem !important;
    text-align: center;
}
.card-audio-dark .audio-tabs-custom .nav-link.active {
    font-weight: 600;
    background: linear-gradient(90deg, #36cfc9 0%, #1890ff 100%);
    color: #fff !important;
    box-shadow: 0 3px 16px #1b1b2a33;
    border: 1px solid #0e1821 !important;
}
.card-audio-dark .audio-tabs-custom .nav-link {
    background: #232e3b !important;
    color: #36cfc9 !important;
    border: 1px solid #21262c !important;
    transition: all 0.2s;
}
.card-audio-dark .audio-tabs-custom .nav-link:not(.active):hover {
    background: #003a4d !important;
    color: #36cfc9 !important;
}

/* Botones largos y juntos */
.card-audio-dark .audio-btns-row {
    margin-bottom: 0.5rem;
}
.card-audio-dark .audio-btns-row .btn {
    width: 100%;
    font-size: 1.25rem;
    border-radius: 2.5rem !important;
    letter-spacing: 0.5px;
    font-weight: 500;
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
}
.card-audio-dark .audio-btns-row .col-6:first-child {
    padding-right: 0.3rem;
}
.card-audio-dark .audio-btns-row .col-6:last-child {
    padding-left: 0.3rem;
}

.card-audio-dark .btn-primary {
    background: linear-gradient(90deg, #0055ff 0%, #36cfc9 100%) !important;
    border: none;
    color: #fff;
}
.card-audio-dark .btn-primary:hover, 
.card-audio-dark .btn-primary:focus {
    background: linear-gradient(90deg, #36cfc9 0%, #0055ff 100%) !important;
}
.card-audio-dark .btn-danger {
    background: linear-gradient(90deg, #e74c3c 60%, #ff7875 100%) !important;
    border: none;
    color: #fff;
}
.card-audio-dark .btn-danger:hover, 
.card-audio-dark .btn-danger:focus {
    background: linear-gradient(90deg, #ff7875 0%, #e74c3c 100%) !important;
}

/* Audio y feedback */
.card-audio-dark audio {
    background: #2c313a !important;
    border-radius: 1.5rem;
    box-shadow: 0 2px 8px #000a;
    padding: 8px 0;
}
.card-audio-dark .form-control, 
.card-audio-dark input[type="file"] {
    background: #232e3b;
    color: #e3eaf3;
    border: 1px solid #353b43;
}
.card-audio-dark .form-text {
    color: #b0bfcf !important;
}
.card-audio-dark .fs-6, .card-audio-dark .text-secondary {
    color: #b0bfcf !important;
}

.audio-center-wrap {
  max-width: 70%;      /* O el ancho que tú quieras: prueba 400px, 480px, 540px */
  margin-left: auto;
  margin-right: auto;
  width: 100%;
}

.btn-mic:active {
  transform: scale(0.93);
  transition: transform 0.13s;
}

#recorderWave {
    background: linear-gradient(90deg, #a0a2f2ff 0%, #1e2430 100%);
}

.btn-mic-secondary {
  width: 58px;
  height: 58px;
  background: linear-gradient(145deg, #34495e 20%, #2c3e50 100%);
  box-shadow: 0 4px 16px #0007;
}
.btn-mic-secondary.paused { /* cuando está en pausa, mostramos "play" */
  background: linear-gradient(145deg, #36cfc9 20%, #0055ff 100%);
}
#timer.paused { color: #ff9800 !important; } /* naranja en pausa */

</style>
<div class="d-flex justify-content-between align-items-center mb-2">
  <!-- Segmented control -->
  <div id="audio_manual_segmented" class="btn-group" role="group" aria-label="Modo de ingreso">
    <button type="button" class="btn btn-outline-info <?= $isManualInitial ? '' : 'active' ?>" id="audio_manual_audioBtn">🎤 Audio</button>
    <button type="button" class="btn btn-outline-info <?= $isManualInitial ? 'active' : '' ?>" id="audio_manual_manualBtn">📝 Manual</button>
  </div>

  <input type="checkbox" id="toggle_audio_manual" class="d-none" <?= $isManualInitial ? 'checked' : '' ?> />

  <!-- Botón de procesar (el texto cambia según el modo) -->
  <button type="button" class="btn btn-info btn-lg rounded-pill shadow-sm px-4" id="procesarIA">
    ✨ Procesar IA
  </button>

  <!-- Compatibilidad con el JS (reemplaza al antiguo #toggle_manual / #metodoSwitch) -->
  <input type="checkbox" id="toggle_audio_manual" class="d-none" />
</div>

<!-- Bloque Subir Audio -->
<div id="bloque-audio" class="col-12" style="<?= $isManualInitial ? 'display:none;' : '' ?>">
    <div class="card card-audio-dark border-0 shadow-lg rounded-4 p-1 audio-center-wrap mx-auto ">
        <ul class="nav nav-pills audio-tabs-custom mb-0 gap-1" id="audioTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="grabar-tab" data-bs-toggle="tab" data-bs-target="#grabar" type="button" role="tab" aria-controls="grabar" aria-selected="true">
                🎙 Grabar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="subir-tab" data-bs-toggle="tab" data-bs-target="#subir" type="button" role="tab" aria-controls="subir" aria-selected="false">
                📁 Subir archivo
                </button>
            </li>
        </ul>
        <div class="tab-content" id="audioTabContent">
            <!-- Grabar Audio -->
            <div class="tab-pane fade show active" id="grabar" role="tabpanel" aria-labelledby="grabar-tab">
                <div class="row g-0 audio-btns-row">
                    <!-- Botón circular centrado -->
                    <div class="d-flex flex-column align-items-center" style="gap:6px;">
                        <canvas id="recorderWave" width="540" height="100" style="display:none;max-width:98%;background:transparent;border-radius:10px;box-shadow:0 1.5px 7px #001c;"></canvas>
                        <span id="timer" class="text-danger fw-bold mt-1" style="font-size:1.1rem;">00:00</span>
                    </div>
                    <div class="d-flex justify-content-center mt-2">
                        <button type="button" id="btnMic" class="btn-mic shadow-lg" onclick="toggleRecording()">
                            <span id="micIcon" class="material-icons" style="font-size:2.3rem;">mic</span>
                        </button>
                        <button type="button" id="btnPause" class="btn-mic btn-mic-secondary ms-2" style="display:none;">
                            <span id="pauseIcon" class="material-icons" style="font-size:2.0rem;">pause</span>
                        </button>
                    </div>
                </div>
                <div id="audioInfo" class="mb-1"></div>
                <audio id="audioPlayback" class="w-100 rounded shadow" controls style="display:none;"></audio>
            </div>
            <div class="tab-pane fade" id="subir" role="tabpanel" aria-labelledby="subir-tab">
            <div class="input-group mb-2">
                <input type="file"
                    class="form-control"
                    name="archivo_audio"
                    id="archivo_audio"
                    accept="audio/mpeg,audio/mp3,audio/wav,audio/webm,audio/*">
                <button type="button"
                        class="btn btn-outline-danger"
                        id="btnClearUpload"
                        style="display:none;">
                Quitar
                </button>
            </div>
            <small class="form-text text-muted">
                Puede subir un archivo de audio en formato MP3, WAV o WEBM.
            </small>
            </div>

        </div>
    </div>
</div>
<div id="bloque-manual" class="col-12 mb-1" style="<?= $isManualInitial ? '' : 'display:none;' ?>">
    <label for="contenido_html" class="form-label fw-bold">Contenido del Informe</label>
    <textarea class="form-control" name="contenido_html" id="contenido_html" rows="10" 
    placeholder="Escriba o edite el contenido del Informe..." 
    data-editor="ckeditor"><?= htmlspecialchars($fila['contenido_html']) ?></textarea>
</div>
<!-- <script src="../assets/ckeditor/ckeditor.js"></script> -->
<script>
window.canvas     = document.getElementById('recorderWave');
window.ctx        = window.canvas ? window.canvas.getContext('2d') : null;
window.audioCtx   = null;
window.analyser   = null;
window.dataArray  = null;
window.source     = null;
window.micStream  = null;
window.waveAnim   = null;
window.audioIsRecording = window.audioIsRecording || false;
window.audioRecState = 'idle'; // 'idle' | 'recording' | 'paused'

$('#subir-tab').on('shown.bs.tab', () => {
  if (window.audioRecState !== 'idle') toggleRecording();
});


(() => {
    let recorder = null;
    let audioBlob = null;
    let timerInterval = null;
    let seconds = 0;

    function formatTime(s) {
        const m = Math.floor(s / 60).toString().padStart(2, '0');
        const sec = (s % 60).toString().padStart(2, '0');
        return `${m}:${sec}`;
    }

    window.startRecording = function () {
        navigator.mediaDevices.getUserMedia({
            audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
            sampleRate: 44100
            }
        })
        .then(stream => {
            window.canvas.style.display = "block";

            window.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            window.micStream = stream;
            window.source = window.audioCtx.createMediaStreamSource(stream);
            window.analyser = window.audioCtx.createAnalyser();
            window.analyser.fftSize = 512;
            window.dataArray = new Uint8Array(window.analyser.fftSize);

            window.source.connect(window.analyser);

            drawWave(); // animación

            // MediaRecorder
            let mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
            mimeType = 'audio/wav';
            }
            window.audioBlob = null;
            window.recorder = new MediaRecorder(stream, {
            mimeType: mimeType,
            audioBitsPerSecond: 128000
            });

            // eventos (por si quieres loguear algo)
            window.recorder.onpause  = () => { /* opcional: console.log('paused'); */ };
            window.recorder.onresume = () => { /* opcional: console.log('resumed'); */ };

            // dataavailable se disparará al stop (y/o periódicamente si usaras timeslice)
            window.recorder.ondataavailable = e => {
            window.audioBlob = e.data;
            };

            window.recorder.start(); // puedes pasar timeslice ms si quieres chunks periódicos

            // UI/timer/estado
            window.audioRecState = 'recording';
            window.seconds = 0;
            $('#recordingStatus').html('🎙 <strong>Grabando...</strong>');
            $('#timer').text('00:00');  // usa el timer único que ya tienes arriba
            window.timerInterval = setInterval(() => {
            window.seconds++;
            $('#timer').text(formatTime(window.seconds));
            }, 1000);

            // mostrar botón de Pausa
            $('#btnPause').show().removeClass('paused');
            $('#pauseIcon').text('pause');       // icono pausa
            $('#timer').removeClass('paused');   // color normal

            // handler del botón Pausa/Reanudar
            $('#btnPause').off('click').on('click', function () {
            if (window.audioRecState === 'recording') {
                pauseRecording();
            } else if (window.audioRecState === 'paused') {
                resumeRecording();
            }
            });
        })
        .catch(err => {
            Swal.fire('Error', 'No se pudo acceder al micrófono: ' + err.message, 'error');
        });
    };

    window.stopRecording = function () {
      if (!window.recorder || window.recorder.state === 'inactive') return;

      // ✅ handler onstop ANTES de llamar a stop()
      window.recorder.onstop = () => {
        if (window.audioBlob) {
          let formData = new FormData();
          formData.append('audio', window.audioBlob, 'grabacion.webm');
          fetch('/funciones/guardar_audio.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
              if (data.status === 'success') {
                $('#recordingStatus').html('✅ <strong>Grabación guardada</strong>');
                $('#audioPlayback').attr('src', data.audio_url).show();
                $('#audioInfo').html(`
                  <div class="mt-0">
                    <button class="btn btn-sm btn-danger ms-2" onclick="deleteRecording()">🗑 Eliminar</button>
                  </div>
                `);
                $('#bloque-audio').data('audioFilename', data.filename);
              } else {
                Swal.fire('Error', data.message, 'error');
              }
            })
            .catch(err => {
              console.log(err);
              Swal.fire('Error', 'Error al guardar audio: ' + err.message, 'error');
            });
        }
      };

      // ⛔️ parar recorder
      try { window.recorder.stop(); } catch (e) {}

      // … (lo demás igual: limpiar timer, onda, audioCtx, stream, UI, estado) …
      clearInterval(window.timerInterval);
      if (window.waveAnim) cancelAnimationFrame(window.waveAnim);
      if (window.canvas) {
        window.ctx.clearRect(0, 0, window.canvas.width, window.canvas.height);
        window.canvas.style.display = "none";
      }
      if (window.audioCtx) {
        try { window.audioCtx.close(); } catch (e) {}
        window.audioCtx = null;
      }
      if (window.micStream) {
        window.micStream.getTracks().forEach(track => track.stop());
        window.micStream = null;
      }

      $('#recordingStatus').html('⏳ <em>Guardando audio...</em>');
      $('#btnPause').hide().removeClass('paused');
      $('#timer').removeClass('paused');
      window.audioRecState = 'idle';
    };

    window.pauseRecording = function () {
        if (!window.recorder || window.recorder.state !== 'recording') return;

        try { window.recorder.pause(); } catch (e) {}
        window.audioRecState = 'paused';

        // detener timer y animación
        clearInterval(window.timerInterval);
        if (window.waveAnim) cancelAnimationFrame(window.waveAnim);
        if (window.audioCtx && window.audioCtx.state === 'running') {
            window.audioCtx.suspend().catch(()=>{});
        }

        // UI
        $('#pauseIcon').text('play_arrow'); // ahora botón reanuda
        $('#btnPause').addClass('paused');
        $('#timer').addClass('paused');     // color naranja
        $('#recordingStatus').html('⏸ <em>Grabación en pausa</em>');
    };

    window.resumeRecording = function () {
        if (!window.recorder || window.recorder.state !== 'paused') return;

        try { window.recorder.resume(); } catch (e) {}
        window.audioRecState = 'recording';

        // reanudar timer y animación
        window.timerInterval = setInterval(() => {
            window.seconds++;
            $('#timer').text(formatTime(window.seconds));
        }, 1000);

        if (window.audioCtx && window.audioCtx.state === 'suspended') {
            window.audioCtx.resume().catch(()=>{});
        }
        drawWave();

        // UI
        $('#pauseIcon').text('pause');
        $('#btnPause').removeClass('paused');
        $('#timer').removeClass('paused');
        // (si existe este contenedor)
        $('#recordingStatus').html('🎙 <strong>Grabando...</strong>');
    };


    window.deleteRecording = function () {
        $('#audioPlayback').hide().attr('src', '');
        $('#audioInfo').html('');
        $('#recordingStatus').html('🎤 <span class="text-muted">Listo para grabar</span>');
        $('#bloque-audio').removeData('audioFilename');
    };
})();

window.audioIsRecording = window.audioIsRecording || false;

window.toggleRecording = function () {
  const micBtn  = document.getElementById('btnMic');
  const micIcon = document.getElementById('micIcon');

  if (window.audioRecState === 'idle') {
    // Iniciar
    window.audioIsRecording = true;
    micBtn.classList.add('recording');
    micIcon.innerText = 'stop';
    startRecording();
  } else {
    // Parar (tanto si estaba recordando como en pausa)
    window.audioIsRecording = false;
    micBtn.classList.remove('recording');
    micIcon.innerText = 'mic';
    stopRecording();
  }
};


function drawWave() {
    if (!window.analyser || !window.ctx) return;

    let grad = window.ctx.createLinearGradient(0, 0, window.canvas.width, 0);
    grad.addColorStop(0, "#9748ffff");
    grad.addColorStop(1, "#ff6ec4");
    window.ctx.strokeStyle = grad;
    window.ctx.fillRect(0, 0, window.canvas.width, window.canvas.height);

    window.analyser.getByteTimeDomainData(window.dataArray);
    window.ctx.lineWidth = 2.8;
    window.ctx.strokeStyle = window.waveGradient;
    window.ctx.beginPath();

    const sliceWidth = window.canvas.width / window.dataArray.length;
    let x = 0;
    for (let i = 0; i < window.dataArray.length; i++) {
        const v = window.dataArray[i] / 128.0;
        const y = (v * window.canvas.height) / 2;
        if (i === 0) {
            window.ctx.moveTo(x, y);
        } else {
            window.ctx.lineTo(x, y);
        }
        x += sliceWidth;
    }
    window.ctx.lineTo(window.canvas.width, window.canvas.height / 2);
    window.ctx.stroke();

    window.waveAnim = requestAnimationFrame(drawWave);
}


// ✅ helper: ¿modo manual activo?
function audio_manual_isManual() {
  return $('#toggle_audio_manual').is(':checked');
}

// estado global simple
window.__audioManualCurrentMode = null;
window.__audioManualFirstRenderDone = false;

function audio_manual_setMode(mode) {
  const toManual = (mode === 'manual');
  const newMode  = toManual ? 'manual' : 'audio';

  // 🛡️ Si ya estamos en ese modo, no hagas nada
  if (window.__audioManualCurrentMode === newMode) return;
  window.__audioManualCurrentMode = newMode;

  // Botones activos
  $('#audio_manual_audioBtn').toggleClass('active', !toManual);
  $('#audio_manual_manualBtn').toggleClass('active', toManual);

  // Flag oculto
  $('#toggle_audio_manual').prop('checked', toManual);

  // ¿Usamos animación o cambio directo?
  const useAnim = window.__audioManualFirstRenderDone;

  if (toManual) {
    if (useAnim) {
      $('#bloque-audio').stop(true, true).slideUp(120);
      $('#bloque-manual').stop(true, true).slideDown(120, ensureCKE);
    } else {
      $('#bloque-audio').hide();
      $('#bloque-manual').show();
      ensureCKE();
    }
  } else {
    // Detén grabación si corresponde
    if (window.recorder && window.recorder.state === 'recording') {
      try { stopRecording(); } catch(e) {}
    }

    if (useAnim) {
      $('#bloque-manual').stop(true, true).slideUp(120, destroyCKE);
      $('#bloque-audio').stop(true, true).slideDown(120);
    } else {
      $('#bloque-manual').hide();
      destroyCKE();
      $('#bloque-audio').show();
    }
  }

  $('#procesarIA').text('✨ Procesar IA');

  // Marca que ya pasamos el primer render (a partir de ahora sí animamos)
  window.__audioManualFirstRenderDone = true;

  function ensureCKE() {
    const id = 'contenido_html';
    const plantilla = $('#plantillaBase').val() || '';

    if ($('#' + id).length && !CKEDITOR.instances[id]) {
      CKEDITOR.replace(id, {
        height: 300,
        allowedContent: true,
        extraAllowedContent: 'span{*}(*)'
      });

      // 💥 cuando recién creo el editor y NO estoy modificando,
      // si hay plantilla y el editor está vacío, la pongo
      setTimeout(() => {
        if (!ES_MODIFICAR && plantilla) {
          const actual = CKEDITOR.instances[id].getData().trim();
          if (!actual) {
              CKEDITOR.instances[id].setData(plantilla);
          }
        }
      }, 80);
    } else if (!ES_MODIFICAR && plantilla && CKEDITOR.instances[id]) {
      // por si ya estaba creado pero vacío
      const actual = CKEDITOR.instances[id].getData().trim();
      if (!actual) {
          CKEDITOR.instances[id].setData(plantilla);
      }
    }
  }

  function destroyCKE() {
    if (CKEDITOR.instances['contenido_html']) {
      CKEDITOR.instances['contenido_html'].destroy(true);
    }
  }
}


$(function () {
  // 1) bind de botones
  $('#audio_manual_audioBtn').on('click', () => audio_manual_setMode('audio'));
  $('#audio_manual_manualBtn').on('click', () => audio_manual_setMode('manual'));

  // 2) lee estado inicial del checkbox (pintado por PHP)
  const toManual = $('#toggle_audio_manual').is(':checked');

  // 3) establece el modo UNA sola vez, sin animación (Paso 2 ya lo maneja)
  audio_manual_setMode(toManual ? 'manual' : 'audio');
});


$(document).on('change', '#archivo_audio', function () {
  const file = this.files && this.files[0];
  if (file) {
    $('#btnClearUpload').show();
  } else {
    $('#btnClearUpload').hide();
  }
});

$(document).on('click', '#btnClearUpload', function () {
  const $file = $('#archivo_audio');

  $file.val('');               
  $('#btnClearUpload').hide();
});

</script>