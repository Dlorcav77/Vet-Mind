<?php
// admin/certificado/metodo_ingreso/audio.php
/**
 *
 * @var string $isManualInitial
 */
?>
<div id="bloque-audio" class="col-12" style="<?= $isManualInitial ? 'display:none;' : '' ?>">
    <input type="hidden" name="audio_tmp" id="audio_tmp" value="">
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
                        accept="audio/mpeg,audio/mp3,audio/mp4,audio/wav,audio/webm,audio/*">
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