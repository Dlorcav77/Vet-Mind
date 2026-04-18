<?php
// admin/certificado/partials/modales.php
?>
<div class="modal fade" id="modalProcesarIA" tabindex="-1" aria-labelledby="procesarIALabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-magic"></i> Informe Procesado por IA
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div id="editorIA_wrapper" class="vm-tiptap-wrapper">
                    <div id="editorIA_toolbar" class="vm-tiptap-toolbar" style="display:none;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="paragraph" data-editor-target="editorIA" title="Párrafo">Párrafo</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="heading-2" data-editor-target="editorIA" title="Título">Título</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="bold" data-editor-target="editorIA" title="Negrita">B</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="italic" data-editor-target="editorIA" title="Cursiva"><i>I</i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="bulletList" data-editor-target="editorIA" title="Lista con viñetas">• Lista</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="orderedList" data-editor-target="editorIA" title="Lista numerada">1. Lista</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="undo" data-editor-target="editorIA" title="Deshacer">↶</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-command="redo" data-editor-target="editorIA" title="Rehacer">↷</button>
                    </div>

                    <div
                        id="editorIA_editor"
                        class="form-control vm-tiptap-editor"
                        data-placeholder="Aquí se mostrará el contenido procesado por IA..."
                    ></div>
                </div>

                <textarea id="editorIA" class="d-none" rows="15"></textarea>
                <div id="debug-host" style="display:none; max-height:60vh; overflow:auto; padding:8px;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="aceptarIA">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVistaPrevia" tabindex="-1" aria-labelledby="vistaPreviaLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vistaPreviaLabel">Vista Previa del Certificado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0" style="background: #eee;">
                <div id="contenidoVistaPrevia" style="min-height:60vh;padding:0;background:#fff;"></div>
            </div>
        </div>
    </div>
</div>