<?php
// admin/certificado/metodo_ingreso/metodo_ingreso.php
$isModificar = isset($action) && $action === 'modificar';
$initialMode = $modo_ingreso_contenido_inicial ?? ($isModificar ? 'manual' : 'audio');
$isManualInitial = $initialMode === 'manual';
?>


<link rel="stylesheet" href="certificado/metodo_ingreso/css/metodo_ingreso.css?v=2">
<div class="d-flex justify-content-between align-items-center mb-2">
    <div id="audio_manual_segmented" class="btn-group" role="group" aria-label="Modo de ingreso">
        <button type="button" class="btn btn-outline-info <?= $isManualInitial ? '' : 'active' ?>" id="audio_manual_audioBtn">🎤 Audio</button>
        <button type="button" class="btn btn-outline-info <?= $isManualInitial ? 'active' : '' ?>" id="audio_manual_manualBtn">📝 Manual</button>
    </div>

    <input type="checkbox" id="toggle_audio_manual" class="d-none" <?= $isManualInitial ? 'checked' : '' ?> />

    <button type="button" class="btn btn-info btn-lg rounded-pill shadow-sm px-4" id="procesarIA">
        ✨ Procesar IA
    </button>
</div>

<?php include __DIR__ . '/audio.php'; ?>
<?php include __DIR__ . '/manual.php'; ?>

<script type="module" src="certificado/common/js/tiptap-editor.js?v=11"></script>
<script src="certificado/metodo_ingreso/js/audio.js?v=2"></script>
<script src="certificado/metodo_ingreso/js/metodo_ingreso.js?v=10"></script>