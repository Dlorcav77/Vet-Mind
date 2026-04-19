<?php
// admin/certificado/metodo_ingreso/metodo_ingreso.php
$isModificar = isset($action) && $action === 'modificar';
$initialMode = $modo_ingreso_contenido_inicial ?? ($isModificar ? 'manual' : 'audio');
$isManualInitial = $initialMode === 'manual';
?>

<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');

.vm-tiptap-wrapper {
    border: 1px solid #cfd6df;
    border-radius: 14px;
    background: #ffffff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
}

.vm-tiptap-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .55rem;
    padding: .8rem .9rem;
    border-bottom: 1px solid #e7ebf0;
    background: linear-gradient(180deg, #f8fafc 0%, #eef2f6 100%);
}

.vm-toolbar-group {
    display: flex;
    align-items: center;
    gap: .45rem;
}

.vm-toolbar-divider {
    width: 1px;
    height: 28px;
    background: #d7dee7;
    margin: 0 .1rem;
}

.vm-toolbar-select {
    min-width: 140px;
    border-radius: .7rem;
    border-color: #b8c3d1;
    box-shadow: none !important;
}

.vm-toolbar-select-sm {
    min-width: 86px;
    max-width: 86px;
}

.vm-tiptap-toolbar .btn {
    min-width: 42px;
    height: 38px;
    border-radius: .75rem;
    border-color: #9aa8b8;
    background: #fff;
    color: #334155;
    font-size: .95rem;
    line-height: 1.1;
    padding: .45rem .75rem;
    box-shadow: none !important;
}

.vm-tiptap-toolbar .btn:hover {
    background: #f8fafc;
    border-color: #7c8da1;
    color: #0f172a;
}

.vm-tiptap-toolbar .btn.active {
    background-color: #1d6ff2;
    border-color: #1d6ff2;
    color: #fff;
    box-shadow: 0 0 0 .14rem rgba(29, 111, 242, .14) !important;
}

.vm-tiptap-editor {
    min-height: 420px;
    max-height: 420px;
    overflow-y: auto;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    background: #fff;
}

.vm-tiptap-editor .ProseMirror {
    min-height: 100%;
    padding: 1.25rem 1.2rem 2rem;
    outline: none;
    line-height: 1.65;
    font-size: 1.03rem;
    color: #1f2937;
    word-break: break-word;
}

.vm-tiptap-editor .ProseMirror p {
    margin: 0 0 1rem;
}

.vm-tiptap-editor .ProseMirror h1,
.vm-tiptap-editor .ProseMirror h2,
.vm-tiptap-editor .ProseMirror h3 {
    margin: 1.25rem 0 .85rem;
    font-weight: 700;
    line-height: 1.3;
    color: #0f172a;
}

.vm-tiptap-editor .ProseMirror h1 {
    font-size: 1.7rem;
}

.vm-tiptap-editor .ProseMirror h2 {
    font-size: 1.4rem;
}

.vm-tiptap-editor .ProseMirror h3 {
    font-size: 1.18rem;
}

.vm-tiptap-editor .ProseMirror ul,
.vm-tiptap-editor .ProseMirror ol {
    padding-left: 1.7rem;
    margin: 0 0 1rem;
}

.vm-tiptap-editor .ProseMirror li {
    margin-bottom: .35rem;
}

.vm-tiptap-editor .ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    color: #94a3b8;
    float: left;
    height: 0;
    pointer-events: none;
}
</style>

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

<script type="module" src="certificado/common/js/tiptap-editor.js?v=10"></script>
<script src="certificado/metodo_ingreso/js/audio.js?v=1"></script>
<script src="certificado/metodo_ingreso/js/metodo_ingreso.js?v=7"></script>