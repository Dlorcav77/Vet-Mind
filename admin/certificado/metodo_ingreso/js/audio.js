//admin/certificado/metodo_ingreso/js/audio.js

window.canvas = document.getElementById('recorderWave');
window.ctx = window.canvas ? window.canvas.getContext('2d') : null;
window.audioCtx = null;
window.analyser = null;
window.dataArray = null;
window.source = null;
window.micStream = null;
window.waveAnim = null;
window.audioIsRecording = window.audioIsRecording || false;
window.audioRecState = 'idle';

$('#subir-tab').on('shown.bs.tab', () => {
  if (window.audioRecState !== 'idle') toggleRecording();
});

(() => {
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

            drawWave();

            let mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'audio/wav';
            }

            window.audioBlob = null;
            window.recorder = new MediaRecorder(stream, {
                mimeType: mimeType,
                audioBitsPerSecond: 128000
            });

            window.recorder.ondataavailable = e => {
                window.audioBlob = e.data;
            };

            window.recorder.start();

            window.audioRecState = 'recording';
            window.seconds = 0;
            $('#recordingStatus').html('🎙 <strong>Grabando...</strong>');
            $('#timer').text('00:00');

            window.timerInterval = setInterval(() => {
                window.seconds++;
                $('#timer').text(formatTime(window.seconds));
            }, 1000);

            $('#btnPause').show().removeClass('paused');
            $('#pauseIcon').text('pause');
            $('#timer').removeClass('paused');

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

                            $('#audio_tmp').val(data.audio_tmp || '');
                            $('#bloque-audio').data('audioFilename', data.filename || '');
                            $('#bloque-audio').data('audioTmp', data.audio_tmp || '');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Error al guardar audio: ' + err.message, 'error');
                    });
            }
        };

        try { window.recorder.stop(); } catch (e) {}

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

        clearInterval(window.timerInterval);

        if (window.waveAnim) cancelAnimationFrame(window.waveAnim);

        if (window.audioCtx && window.audioCtx.state === 'running') {
            window.audioCtx.suspend().catch(() => {});
        }

        $('#pauseIcon').text('play_arrow');
        $('#btnPause').addClass('paused');
        $('#timer').addClass('paused');
        $('#recordingStatus').html('⏸ <em>Grabación en pausa</em>');
    };

    window.resumeRecording = function () {
        if (!window.recorder || window.recorder.state !== 'paused') return;

        try { window.recorder.resume(); } catch (e) {}
        window.audioRecState = 'recording';

        window.timerInterval = setInterval(() => {
            window.seconds++;
            $('#timer').text(formatTime(window.seconds));
        }, 1000);

        if (window.audioCtx && window.audioCtx.state === 'suspended') {
            window.audioCtx.resume().catch(() => {});
        }

        drawWave();

        $('#pauseIcon').text('pause');
        $('#btnPause').removeClass('paused');
        $('#timer').removeClass('paused');
        $('#recordingStatus').html('🎙 <strong>Grabando...</strong>');
    };

    window.deleteRecording = function () {
        $('#audioPlayback').hide().attr('src', '');
        $('#audioInfo').html('');
        $('#recordingStatus').html('🎤 <span class="text-muted">Listo para grabar</span>');

        $('#audio_tmp').val('');
        $('#bloque-audio').removeData('audioFilename');
        $('#bloque-audio').removeData('audioTmp');
    };
})();

window.toggleRecording = function () {
  const micBtn = document.getElementById('btnMic');
  const micIcon = document.getElementById('micIcon');

  if (window.audioRecState === 'idle') {
    window.audioIsRecording = true;
    micBtn.classList.add('recording');
    micIcon.innerText = 'stop';
    startRecording();
  } else {
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

$(document).on('change', '#archivo_audio', function () {
  const file = this.files && this.files[0];
  if (file) {
    $('#btnClearUpload').show();
  } else {
    $('#btnClearUpload').hide();
  }
});

$(document).on('click', '#btnClearUpload', function () {
  $('#archivo_audio').val('');
  $('#btnClearUpload').hide();
});