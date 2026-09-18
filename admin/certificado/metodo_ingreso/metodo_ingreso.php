<?php
// admin/certificado/metodo_ingreso/metodo_ingreso.php
$isModificar = isset($action) && $action === 'modificar';
$initialMode = $modo_ingreso_contenido_inicial ?? ($isModificar ? 'manual' : 'audio');
$isManualInitial = $initialMode === 'manual';
?>


<link rel="stylesheet" href="certificado/metodo_ingreso/css/metodo_ingreso.css?v=10">
<div class="vm-ingreso-topbar mb-2">
    <div id="audio_manual_segmented" class="btn-group vm-ingreso-modo" role="group" aria-label="Modo de ingreso">
        <button type="button" class="btn btn-outline-info <?= $isManualInitial ? '' : 'active' ?>" id="audio_manual_audioBtn">🎤 Audio</button>
        <button type="button" class="btn btn-outline-info <?= $isManualInitial ? 'active' : '' ?>" id="audio_manual_manualBtn">📝 Manual</button>
    </div>

    <input type="checkbox" id="toggle_audio_manual" class="d-none" <?= $isManualInitial ? 'checked' : '' ?> />

    <div id="revision_audio_barra" class="vm-review-audio" role="group" aria-label="Reproductor del dictado">
        <audio id="revision_audio" preload="metadata"></audio>

        <button type="button" id="revision_audio_back" class="vm-review-audio-btn vm-review-audio-skip" title="Retroceder 5 segundos" aria-label="Retroceder 5 segundos">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M11 7L6 12L11 17"></path>
                <path d="M18 7L13 12L18 17"></path>
            </svg>
        </button>

        <button type="button" id="revision_audio_play" class="vm-review-audio-btn vm-review-audio-play" title="Reproducir" aria-label="Reproducir">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <polygon points="9,7 18,12 9,17"></polygon>
            </svg>
        </button>

        <button type="button" id="revision_audio_forward" class="vm-review-audio-btn vm-review-audio-skip" title="Avanzar 5 segundos" aria-label="Avanzar 5 segundos">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 7L11 12L6 17"></path>
                <path d="M13 7L18 12L13 17"></path>
            </svg>
        </button>

        <div class="vm-review-audio-progress">
            <input type="range" id="revision_audio_seek" class="vm-review-audio-seek" min="0" max="100" value="0" step="0.1" aria-label="Posición del audio">
            <span id="revision_audio_tiempo" class="vm-review-audio-time">00:00 / 00:00</span>
        </div>

        <div class="vm-review-audio-volume">
            <button type="button" id="revision_audio_mute" class="vm-review-audio-volume-btn" title="Silenciar" aria-label="Silenciar">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 10H3v4h3l4 3V7l-4 3z"></path>
                    <path d="M14 9c1.3 1.7 1.3 4.3 0 6"></path>
                    <path d="M17 7c2.7 2.8 2.7 7.2 0 10"></path>
                </svg>
            </button>
            <input type="range" id="revision_audio_volume" min="0" max="100" value="100" step="1" aria-label="Volumen">
        </div>

        <select id="revision_audio_speed" class="vm-review-audio-speed" aria-label="Velocidad de reproducción">
            <option value="0.75">0.75x</option>
            <option value="1" selected>1x</option>
            <option value="1.25">1.25x</option>
            <option value="1.5">1.5x</option>
            <option value="2">2x</option>
        </select>
    </div>

    <button type="button" class="btn btn-info btn-lg rounded-pill shadow-sm px-4 vm-procesar-ia" id="procesarIA">
        ✨ Procesar IA
    </button>
</div>

<?php include __DIR__ . '/audio.php'; ?>
<?php include __DIR__ . '/manual.php'; ?>

<script type="module" src="certificado/common/js/tiptap-editor.bundle.js?v=3"></script>
<script type="module" src="certificado/common/js/revision-visual.js?v=7"></script>
<script src="certificado/metodo_ingreso/js/audio.js?v=2"></script>
<script src="certificado/metodo_ingreso/js/metodo_ingreso.js?v=11"></script>