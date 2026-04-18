<?php
// admin/certificado/metodo_ingreso/manual.php
$contenidoInforme = isset($fila['contenido_html']) ? (string)$fila['contenido_html'] : '';
?>
<style>
    .vm-toolbar-icon-btn {
        min-width: 42px;
        height: 38px;
        padding: 0 .7rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        border-radius: 12px;
        font-weight: 600;
        color: #334155;
        background: #fff;
        border: 1px solid #bfc9d6;
        transition: all .15s ease;
    }

    .vm-toolbar-icon-btn:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }

    .vm-toolbar-icon-btn.active {
        background: #2f6fec;
        border-color: #2f6fec;
        color: #fff;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
    }

    .vm-toolbar-icon-btn svg {
        width: 18px;
        height: 18px;
        display: block;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .vm-toolbar-icon-btn .vm-icon-text {
        font-size: 1rem;
        line-height: 1;
        display: inline-block;
        color: currentColor;
    }

    .vm-toolbar-icon-btn .vm-icon-bold {
        font-weight: 900;
        font-size: 1.1rem;
    }

    .vm-toolbar-icon-btn .vm-icon-italic {
        font-style: italic;
        font-weight: 700;
        font-size: 1.05rem;
    }

    .vm-toolbar-icon-btn .vm-icon-underline {
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 2px;
        font-weight: 700;
        font-size: 1rem;
    }

    .vm-toolbar-icon-btn.vm-toolbar-list-btn {
        min-width: 54px;
    }

    #contenido_html_editor.tiptap-editor-content,
    #contenido_html_editor .tiptap-editor-content,
    #contenido_html_editor {
        font-size: 15px;
        line-height: 1.45;
    }

#contenido_html_editor p {
    margin-top: 0;
    margin-bottom: 0;
}

    #contenido_html_editor h1,
    #contenido_html_editor h2,
    #contenido_html_editor h3 {
        line-height: 1.25;
        margin-bottom: .6rem;
    }

    #contenido_html_editor ul,
    #contenido_html_editor ol {
        padding-left: 1.4rem;
        margin-bottom: .65rem;
    }
</style>
<div id="bloque-manual" class="col-12 mb-1" style="<?= $isManualInitial ? '' : 'display:none;' ?>">
    <label for="contenido_html_editor" class="form-label fw-bold">Contenido del Informe</label>

    <div id="contenido_html_editor_wrapper" class="vm-tiptap-wrapper">
        <div id="contenido_html_toolbar" class="vm-tiptap-toolbar" style="display:none;">
            <div class="vm-toolbar-group">
                <select
                    id="contenido_html_heading"
                    class="form-select form-select-sm vm-toolbar-select"
                    data-editor-target="contenido_html"
                    title="Formato"
                >
                    <option value="paragraph">Párrafo</option>
                    <option value="h1">Título 1</option>
                    <option value="h2">Título 2</option>
                    <option value="h3">Título 3</option>
                </select>

                <select
                    id="contenido_html_font_size"
                    class="form-select form-select-sm vm-toolbar-select vm-toolbar-select-sm"
                    data-editor-target="contenido_html"
                    title="Tamaño"
                >
                    <option value="10px">10</option>
                    <option value="11px">11</option>
                    <option value="12px">12</option>
                    <option value="14px" selected>14</option>
                    <option value="16px">16</option>
                    <option value="18px">18</option>
                    <option value="20px">20</option>
                    <option value="24px">24</option>
                    <option value="28px">28</option>
                    <option value="32px">32</option>
                </select>

                <select
                    id="contenido_html_line_height"
                    class="form-select form-select-sm vm-toolbar-select vm-toolbar-select-sm"
                    data-editor-target="contenido_html"
                    title="Espaciado"
                >
                    <option value="1">1</option>
                    <option value="1.15" selected>1.15</option>
                    <option value="1.5">1.5</option>
                    <option value="2">2</option>
                    <option value="2.5">2.5</option>
                    <option value="3">3</option>
                </select>
            </div>

            <div class="vm-toolbar-divider"></div>

            <div class="vm-toolbar-group">
                <button type="button" class="vm-toolbar-icon-btn" data-command="bold" title="Negrita" aria-label="Negrita">
                    <span class="vm-icon-text vm-icon-bold">B</span>
                </button>

                <button type="button" class="vm-toolbar-icon-btn" data-command="italic" title="Cursiva" aria-label="Cursiva">
                    <span class="vm-icon-text vm-icon-italic">I</span>
                </button>

                <button type="button" class="vm-toolbar-icon-btn" data-command="underline" title="Subrayado" aria-label="Subrayado">
                    <span class="vm-icon-text vm-icon-underline">U</span>
                </button>
            </div>

            <div class="vm-toolbar-divider"></div>

            <div class="vm-toolbar-group">
                <button type="button" class="vm-toolbar-icon-btn" data-command="alignLeft" title="Alinear a la izquierda" aria-label="Alinear a la izquierda">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="10" x2="15" y2="10"></line>
                        <line x1="4" y1="14" x2="20" y2="14"></line>
                        <line x1="4" y1="18" x2="15" y2="18"></line>
                    </svg>
                </button>

                <button type="button" class="vm-toolbar-icon-btn" data-command="alignCenter" title="Centrar" aria-label="Centrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="7" y1="10" x2="17" y2="10"></line>
                        <line x1="4" y1="14" x2="20" y2="14"></line>
                        <line x1="7" y1="18" x2="17" y2="18"></line>
                    </svg>
                </button>

                <button type="button" class="vm-toolbar-icon-btn" data-command="alignRight" title="Alinear a la derecha" aria-label="Alinear a la derecha">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="9" y1="10" x2="20" y2="10"></line>
                        <line x1="4" y1="14" x2="20" y2="14"></line>
                        <line x1="9" y1="18" x2="20" y2="18"></line>
                    </svg>
                </button>

                <button type="button" class="vm-toolbar-icon-btn" data-command="alignJustify" title="Justificar" aria-label="Justificar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="10" x2="20" y2="10"></line>
                        <line x1="4" y1="14" x2="20" y2="14"></line>
                        <line x1="4" y1="18" x2="20" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="vm-toolbar-divider"></div>

            <div class="vm-toolbar-group">
                <button type="button" class="vm-toolbar-icon-btn vm-toolbar-list-btn" data-command="bulletList" title="Lista con viñetas" aria-label="Lista con viñetas">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="6" cy="7" r="1.4" fill="currentColor" stroke="none"></circle>
                        <circle cx="6" cy="12" r="1.4" fill="currentColor" stroke="none"></circle>
                        <circle cx="6" cy="17" r="1.4" fill="currentColor" stroke="none"></circle>
                        <line x1="10" y1="7" x2="20" y2="7"></line>
                        <line x1="10" y1="12" x2="20" y2="12"></line>
                        <line x1="10" y1="17" x2="20" y2="17"></line>
                    </svg>
                </button>

                <button type="button" class="vm-toolbar-icon-btn vm-toolbar-list-btn" data-command="orderedList" title="Lista numerada" aria-label="Lista numerada">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <text x="4.2" y="8.4" font-size="5.4" font-family="Arial, sans-serif" fill="currentColor" stroke="none">1.</text>
                        <text x="4.2" y="13.4" font-size="5.4" font-family="Arial, sans-serif" fill="currentColor" stroke="none">2.</text>
                        <text x="4.2" y="18.4" font-size="5.4" font-family="Arial, sans-serif" fill="currentColor" stroke="none">3.</text>
                        <line x1="10" y1="7" x2="20" y2="7"></line>
                        <line x1="10" y1="12" x2="20" y2="12"></line>
                        <line x1="10" y1="17" x2="20" y2="17"></line>
                    </svg>
                </button>
            </div>

            <div class="vm-toolbar-divider"></div>

            <div class="vm-toolbar-group">
                <button type="button" class="vm-toolbar-icon-btn" data-command="undo" title="Deshacer" aria-label="Deshacer">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 7L6 11L10 15"></path>
                        <path d="M7 11H14.5C17.54 11 20 13.46 20 16.5"></path>
                    </svg>
                </button>

                <button type="button" class="vm-toolbar-icon-btn" data-command="redo" title="Rehacer" aria-label="Rehacer">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14 7L18 11L14 15"></path>
                        <path d="M17 11H9.5C6.46 11 4 13.46 4 16.5"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div
            id="contenido_html_editor"
            class="form-control vm-tiptap-editor"
            data-placeholder="Escriba o edite el contenido del Informe..."
        ></div>
    </div>

    <textarea
        class="d-none"
        name="contenido_html"
        id="contenido_html"
        rows="10"
    ><?= htmlspecialchars($contenidoInforme, ENT_QUOTES, 'UTF-8') ?></textarea>
</div>