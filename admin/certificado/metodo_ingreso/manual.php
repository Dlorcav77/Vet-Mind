<?php
// admin/certificado/metodo_ingreso/manual.php
/**
 *
 * @var string $isManualInitial
 */

$contenidoInforme = isset($fila['contenido_html']) ? (string)$fila['contenido_html'] : '';
?>
<div id="bloque-manual" class="col-12 mb-1" style="<?= $isManualInitial ? '' : 'display:none;' ?>">
    <div class="vm-informe-cabecera">
        <div class="vm-informe-cabecera-izq">
            <label for="contenido_html_editor" class="form-label fw-bold mb-0">Contenido del Informe</label>
        </div>

        <div class="vm-informe-cabecera-centro">
            <div id="revision_ia_leyenda" class="vm-revision-leyenda" style="display:none;">
                <span><i class="vm-leyenda-color vm-leyenda-plantilla"></i>Plantilla</span>
                <span><i class="vm-leyenda-color vm-leyenda-dictado"></i>Dictado</span>
                <span><i class="vm-leyenda-color vm-leyenda-desconocido"></i>Origen dudoso</span>
                <span class="vm-leyenda-discrepancia">Duda transcripción</span>
            </div>
        </div>

        <div class="vm-informe-cabecera-der">
            <div id="revision_ia_bloque" style="display:none;">
                <button type="button" id="revision_ia_toggle" class="vm-revision-status vm-revision-status-pending" disabled>
                    <span id="revision_ia_estado">Revisión IA pendiente</span>
                </button>
            </div>
        </div>
    </div>

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
                    <option value="11.5px">11.5</option>
                    <option value="12px">12</option>
                    <option value="12.5px">12.5</option>
                    <option value="13px" selected>13</option>
                    <option value="13.5px">13.5</option>
                    <option value="14px">14</option>
                    <option value="14.5px">14.5</option>
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

                <div class="vm-color-control" title="Color de texto">
                    <input
                        type="color"
                        id="contenido_html_text_color"
                        class="vm-color-input"
                        data-editor-target="contenido_html"
                        value="#000000"
                        aria-label="Color de texto"
                    >
                </div>
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
                <button
                    type="button"
                    class="vm-toolbar-icon-btn"
                    data-command="insertPageBreak"
                    title="Insertar salto de página"
                    aria-label="Insertar salto de página"
                >
                    <span class="vm-icon-text">↧</span>
                </button>
                <button
                    type="button"
                    class="vm-toolbar-icon-btn"
                    data-command="insertTable"
                    title="Insertar tabla"
                    aria-label="Insertar tabla"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="4" y="5" width="16" height="14" rx="1"></rect>
                        <line x1="4" y1="10" x2="20" y2="10"></line>
                        <line x1="4" y1="14.5" x2="20" y2="14.5"></line>
                        <line x1="9.33" y1="5" x2="9.33" y2="19"></line>
                        <line x1="14.66" y1="5" x2="14.66" y2="19"></line>
                    </svg>
                </button>

                <select
                    id="contenido_html_table_actions"
                    class="form-select form-select-sm vm-toolbar-select vm-toolbar-select-table"
                    data-table-action-select="1"
                    data-editor-target="contenido_html"
                    title="Acciones de tabla"
                    style="display:none;"
                >
                    <option value="">Tabla</option>

                    <optgroup label="Agregar">
                        <option value="addRowBefore">Fila arriba</option>
                        <option value="addRowAfter">Fila abajo</option>
                        <option value="addColumnBefore">Columna izquierda</option>
                        <option value="addColumnAfter">Columna derecha</option>
                    </optgroup>

                    <optgroup label="Eliminar">
                        <option value="deleteRow">Fila</option>
                        <option value="deleteColumn">Columna</option>
                    </optgroup>

                    <optgroup label="Títulos">
                        <option value="toggleHeaderRow">Fila</option>
                        <option value="toggleHeaderColumn">Columna</option>
                        <option value="toggleHeaderCell">Celda</option>
                    </optgroup>

                    <optgroup label="Tabla">
                        <option value="mergeCells">Combinar celdas</option>
                        <option value="splitCell">Dividir celda</option>
                        <option value="deleteTable">Eliminar tabla</option>
                    </optgroup>
                </select>
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

            <div class="vm-toolbar-divider"></div>

            <div class="vm-toolbar-visual" title="Zoom visual del editor">
                <svg class="vm-toolbar-zoom-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="10.5" cy="10.5" r="6.5"></circle>
                    <line x1="15.5" y1="15.5" x2="20" y2="20"></line>
                </svg>

                <select
                    id="contenido_html_zoom"
                    class="form-select form-select-sm vm-toolbar-select vm-toolbar-select-zoom"
                    aria-label="Zoom visual del informe"
                >
                    <option value="0.9">90%</option>
                    <option value="1" selected>100%</option>
                    <option value="1.1">110%</option>
                    <option value="1.25">125%</option>
                </select>
                <button
                    type="button"
                    id="vm_copiar_informe_revision"
                    class="vm-toolbar-copy-btn"
                    title="Copiar informe con revisión para VetMind o Word"
                    aria-label="Copiar informe con revisión"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="8" y="8" width="12" height="12" rx="2"></rect>
                        <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
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

<div class="modal fade" id="modalRevisionIA" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vm-revision-modal">
            <div class="modal-header vm-revision-modal-header">
                <div>
                    <h5 class="modal-title mb-1">Revisión del informe</h5>
                    <div class="vm-revision-modal-subtitulo">Observaciones detectadas durante la revisión automática</div>
                </div>
                <button type="button" id="revision_ia_modal_cerrar_x" class="vm-revision-modal-close" aria-label="Cerrar">×</button>
            </div>

            <div id="revision_ia_modal_body" class="modal-body vm-revision-modal-body"></div>

            <div class="modal-footer vm-revision-modal-footer">
                <button type="button" id="revision_ia_modal_cerrar" class="btn btn-secondary">Cerrar</button>
            </div>
        </div>
    </div>
</div>