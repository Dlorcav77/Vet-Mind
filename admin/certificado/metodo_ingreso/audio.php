<?php
// admin/certificado/metodo_ingreso/audio.php
?>
<style>
.btn-mic {
  width: 58px;
  height: 58px;
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
  transform: scale(0.93);
  transition: transform 0.13s;
}

.card-audio-dark {
    backdrop-filter: blur(4px);
    background: rgba(13, 17, 23, 0.82) !important;
    color: #e3eaf3 !important;
    border: none;
    box-shadow: 0 6px 32px 0 #000a !important;
    padding: 1.4rem 1.2rem !important;
}

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
    background: #232e3b !important;
    color: #36cfc9 !important;
    border: 1px solid #21262c !important;
    transition: all 0.2s;
}

.card-audio-dark .audio-tabs-custom .nav-link.active {
    font-weight: 600;
    background: linear-gradient(90deg, #36cfc9 0%, #1890ff 100%);
    color: #fff !important;
    box-shadow: 0 3px 16px #1b1b2a33;
    border: 1px solid #0e1821 !important;
}

.card-audio-dark .audio-tabs-custom .nav-link:not(.active):hover {
    background: #003a4d !important;
    color: #36cfc9 !important;
}

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

.card-audio-dark .fs-6,
.card-audio-dark .text-secondary {
    color: #b0bfcf !important;
}

.audio-center-wrap {
  max-width: 70%;
  margin-left: auto;
  margin-right: auto;
  width: 100%;
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

.btn-mic-secondary.paused {
  background: linear-gradient(145deg, #36cfc9 20%, #0055ff 100%);
}

#timer.paused {
  color: #ff9800 !important;
}
</style>

<div id="bloque-audio" class="col-12" style="<?= $isManualInitial ? 'display:none;' : '' ?>">
    <div class="card card-audio-dark border-0 shadow-lg rounded-4 p-1 audio-center-wrap mx-auto">
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
            <div class="tab-pane fade show active" id="grabar" role="tabpanel" aria-labelledby="grabar-tab">
                <div class="row g-0 audio-btns-row">
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
                    <input
                        type="file"
                        class="form-control"
                        name="archivo_audio"
                        id="archivo_audio"
                        accept="audio/mpeg,audio/mp3,audio/wav,audio/webm,audio/*">
                    <button type="button" class="btn btn-outline-danger" id="btnClearUpload" style="display:none;">
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