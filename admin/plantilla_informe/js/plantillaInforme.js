import { Editor, Extension } from 'https://esm.sh/@tiptap/core@2.22.3';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.22.3';
import Placeholder from 'https://esm.sh/@tiptap/extension-placeholder@2.22.3';
import Underline from 'https://esm.sh/@tiptap/extension-underline@2.22.3';
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@2.22.3';
import { TextStyle } from 'https://esm.sh/@tiptap/extension-text-style@2.22.3';
import Table from 'https://esm.sh/@tiptap/extension-table@2.22.3';
import TableRow from 'https://esm.sh/@tiptap/extension-table-row@2.22.3';
import TableHeader from 'https://esm.sh/@tiptap/extension-table-header@2.22.3';
import TableCell from 'https://esm.sh/@tiptap/extension-table-cell@2.22.3';

const FontSize = Extension.create({
    name: 'fontSize',

    addOptions() {
        return {
            types: ['textStyle']
        };
    },

    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    fontSize: {
                        default: null,
                        parseHTML: element => element.style.fontSize || null,
                        renderHTML: attributes => {
                            if (!attributes.fontSize) {
                                return {};
                            }

                            return {
                                style: `font-size: ${attributes.fontSize}`
                            };
                        }
                    }
                }
            }
        ];
    },

    addCommands() {
        return {
            setFontSize: fontSize => ({ chain }) => {
                return chain().setMark('textStyle', { fontSize }).run();
            },
            unsetFontSize: () => ({ chain }) => {
                return chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run();
            }
        };
    }
});

const LineHeight = Extension.create({
    name: 'lineHeight',

    addOptions() {
        return {
            types: ['paragraph', 'heading']
        };
    },

    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    lineHeight: {
                        default: null,
                        parseHTML: element => element.style.lineHeight || null,
                        renderHTML: attributes => {
                            if (!attributes.lineHeight) {
                                return {};
                            }

                            return {
                                style: `line-height: ${attributes.lineHeight}`
                            };
                        }
                    }
                }
            }
        ];
    },

    addCommands() {
        return {
            setLineHeight: lineHeight => ({ commands }) => {
                return this.options.types.every(type => commands.updateAttributes(type, { lineHeight }));
            },
            unsetLineHeight: () => ({ commands }) => {
                return this.options.types.every(type => commands.resetAttributes(type, 'lineHeight'));
            }
        };
    }
});

(function initPlantillaInformeTiptap() {
    const root = document.getElementById('plantilla_informe');
    if (!root) {
        return;
    }

    if (window.__plantillaInformeEditor && typeof window.__plantillaInformeEditor.destroy === 'function') {
        window.__plantillaInformeEditor.destroy();
        window.__plantillaInformeEditor = null;
    }

    const form = document.getElementById('formPlantillaInforme');
    const editorElement = document.getElementById('contenido_editor');
    const textareaElement = document.getElementById('contenido');
    const toolbarElement = document.getElementById('contenido_toolbar');
    const headingSelect = document.getElementById('contenido_heading');
    const fontSizeSelect = document.getElementById('contenido_font_size');
    const lineHeightSelect = document.getElementById('contenido_line_height');
    const tableActionSelect = document.getElementById('contenido_table_actions');

    if (!form || !editorElement || !textareaElement || !toolbarElement) {
        return;
    }

    function syncEditorToTextarea() {
        if (window.__plantillaInformeEditor) {
            textareaElement.value = window.__plantillaInformeEditor.getHTML();
        }
    }

    function getCurrentFontSize(editor) {
        if (!editor) return '14px';
        const attrs = editor.getAttributes('textStyle') || {};
        return attrs.fontSize || '14px';
    }

    function getCurrentLineHeight(editor) {
        if (!editor) return '1.15';

        if (editor.isActive('heading')) {
            const attrs = editor.getAttributes('heading') || {};
            return attrs.lineHeight || '1.15';
        }

        const attrs = editor.getAttributes('paragraph') || {};
        return attrs.lineHeight || '1.15';
    }

    function updateHeadingSelect(editor) {
        if (!editor || !headingSelect) {
            return;
        }

        let value = 'paragraph';

        if (editor.isActive('heading', { level: 1 })) value = 'h1';
        else if (editor.isActive('heading', { level: 2 })) value = 'h2';
        else if (editor.isActive('heading', { level: 3 })) value = 'h3';

        headingSelect.value = value;
    }

    function updateFontSizeSelect(editor) {
        if (!editor || !fontSizeSelect) {
            return;
        }

        fontSizeSelect.value = getCurrentFontSize(editor);
    }

    function updateLineHeightSelect(editor) {
        if (!editor || !lineHeightSelect) {
            return;
        }

        lineHeightSelect.value = getCurrentLineHeight(editor);
    }

    function updateTableActionVisibility(editor) {
        if (!editor || !tableActionSelect) {
            return;
        }

        const html = (editor.getHTML() || '').toLowerCase();
        const hasTable = html.indexOf('<table') !== -1;

        tableActionSelect.style.display = hasTable ? '' : 'none';

        if (!hasTable) {
            tableActionSelect.value = '';
        }
    }

    function updateToolbarState() {
        const editor = window.__plantillaInformeEditor;
        if (!editor) {
            return;
        }

        toolbarElement.querySelectorAll('[data-command]').forEach((button) => {
            const command = button.getAttribute('data-command');
            let isActive = false;

            if (command === 'bold') {
                isActive = editor.isActive('bold');
            } else if (command === 'italic') {
                isActive = editor.isActive('italic');
            } else if (command === 'underline') {
                isActive = editor.isActive('underline');
            } else if (command === 'bulletList') {
                isActive = editor.isActive('bulletList');
            } else if (command === 'orderedList') {
                isActive = editor.isActive('orderedList');
            } else if (command === 'alignLeft') {
                isActive = editor.isActive({ textAlign: 'left' });
            } else if (command === 'alignCenter') {
                isActive = editor.isActive({ textAlign: 'center' });
            } else if (command === 'alignRight') {
                isActive = editor.isActive({ textAlign: 'right' });
            } else if (command === 'alignJustify') {
                isActive = editor.isActive({ textAlign: 'justify' });
            } else if (command === 'insertTable') {
                isActive = editor.isActive('table');
            }

            button.classList.toggle('active', isActive);
        });

        updateHeadingSelect(editor);
        updateFontSizeSelect(editor);
        updateLineHeightSelect(editor);
        updateTableActionVisibility(editor);
    }

    async function insertTable(editor) {
        if (!editor) {
            return;
        }

        if (window.Swal && typeof window.Swal.fire === 'function') {
            const result = await window.Swal.fire({
                title: 'Insertar tabla',
                html: `
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; text-align:left;">
                        <div>
                            <label for="vm_table_cols" style="display:block; font-weight:600; margin-bottom:6px;">Columnas</label>
                            <input
                                id="vm_table_cols"
                                type="number"
                                min="1"
                                max="12"
                                value="3"
                                class="swal2-input"
                                style="margin:0; width:100%;"
                            >
                        </div>
                        <div>
                            <label for="vm_table_rows" style="display:block; font-weight:600; margin-bottom:6px;">Filas</label>
                            <input
                                id="vm_table_rows"
                                type="number"
                                min="1"
                                max="50"
                                value="3"
                                class="swal2-input"
                                style="margin:0; width:100%;"
                            >
                        </div>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Insertar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const cols = parseInt(document.getElementById('vm_table_cols')?.value || '', 10);
                    const rows = parseInt(document.getElementById('vm_table_rows')?.value || '', 10);

                    if (!Number.isInteger(cols) || cols < 1 || cols > 12) {
                        window.Swal.showValidationMessage('La cantidad de columnas debe ser entre 1 y 12.');
                        return false;
                    }

                    if (!Number.isInteger(rows) || rows < 1 || rows > 50) {
                        window.Swal.showValidationMessage('La cantidad de filas debe ser entre 1 y 50.');
                        return false;
                    }

                    return { cols, rows };
                }
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            editor.chain().focus().insertTable({
                rows: result.value.rows,
                cols: result.value.cols,
                withHeaderRow: true
            }).run();

            syncEditorToTextarea();
            updateToolbarState();
            return;
        }

        const colsInput = window.prompt('Cantidad de columnas', '3');
        if (colsInput === null) {
            return;
        }

        const rowsInput = window.prompt('Cantidad de filas', '3');
        if (rowsInput === null) {
            return;
        }

        const cols = parseInt(colsInput, 10);
        const rows = parseInt(rowsInput, 10);

        if (!Number.isInteger(cols) || cols < 1 || cols > 12) {
            window.alert('La cantidad de columnas debe ser entre 1 y 12.');
            return;
        }

        if (!Number.isInteger(rows) || rows < 1 || rows > 50) {
            window.alert('La cantidad de filas debe ser entre 1 y 50.');
            return;
        }

        editor.chain().focus().insertTable({
            rows,
            cols,
            withHeaderRow: true
        }).run();

        syncEditorToTextarea();
        updateToolbarState();
    }

    function runCommand(command) {
        const editor = window.__plantillaInformeEditor;
        if (!editor) {
            return;
        }

        if (command === 'bold') {
            editor.chain().focus().toggleBold().run();
        } else if (command === 'italic') {
            editor.chain().focus().toggleItalic().run();
        } else if (command === 'underline') {
            editor.chain().focus().toggleUnderline().run();
        } else if (command === 'bulletList') {
            editor.chain().focus().toggleBulletList().run();
        } else if (command === 'orderedList') {
            editor.chain().focus().toggleOrderedList().run();
        } else if (command === 'alignLeft') {
            editor.chain().focus().setTextAlign('left').run();
        } else if (command === 'alignCenter') {
            editor.chain().focus().setTextAlign('center').run();
        } else if (command === 'alignRight') {
            editor.chain().focus().setTextAlign('right').run();
        } else if (command === 'alignJustify') {
            editor.chain().focus().setTextAlign('justify').run();
        } else if (command === 'undo') {
            editor.chain().focus().undo().run();
        } else if (command === 'redo') {
            editor.chain().focus().redo().run();
        } else if (command === 'insertTable') {
            insertTable(editor);
            return;
        }

        syncEditorToTextarea();
        updateToolbarState();
        editor.commands.focus();
    }

    function applyHeading(value) {
        const editor = window.__plantillaInformeEditor;
        if (!editor) {
            return;
        }

        if (value === 'paragraph') {
            editor.chain().focus().setParagraph().run();
        } else if (value === 'h1') {
            editor.chain().focus().toggleHeading({ level: 1 }).run();
        } else if (value === 'h2') {
            editor.chain().focus().toggleHeading({ level: 2 }).run();
        } else if (value === 'h3') {
            editor.chain().focus().toggleHeading({ level: 3 }).run();
        }

        syncEditorToTextarea();
        updateToolbarState();
        editor.commands.focus();
    }

    function applyFontSize(value) {
        const editor = window.__plantillaInformeEditor;
        if (!editor) {
            return;
        }

        if (!value || value === '14px') {
            editor.chain().focus().unsetFontSize().run();
        } else {
            editor.chain().focus().setFontSize(value).run();
        }

        syncEditorToTextarea();
        updateToolbarState();
        editor.commands.focus();
    }

    function applyLineHeight(value) {
        const editor = window.__plantillaInformeEditor;
        if (!editor) {
            return;
        }

        if (!value || value === '1.15') {
            editor.chain().focus().unsetLineHeight().run();
        } else {
            editor.chain().focus().setLineHeight(value).run();
        }

        syncEditorToTextarea();
        updateToolbarState();
        editor.commands.focus();
    }

    function applyTableAction(value) {
        const editor = window.__plantillaInformeEditor;
        if (!editor || !value) {
            if (tableActionSelect) {
                tableActionSelect.value = '';
            }
            return;
        }

        const chain = editor.chain().focus();

        if (value === 'addColumnBefore') {
            chain.addColumnBefore().run();
        } else if (value === 'addColumnAfter') {
            chain.addColumnAfter().run();
        } else if (value === 'deleteColumn') {
            chain.deleteColumn().run();
        } else if (value === 'addRowBefore') {
            chain.addRowBefore().run();
        } else if (value === 'addRowAfter') {
            chain.addRowAfter().run();
        } else if (value === 'deleteRow') {
            chain.deleteRow().run();
        } else if (value === 'toggleHeaderRow') {
            chain.toggleHeaderRow().run();
        } else if (value === 'toggleHeaderColumn') {
            chain.toggleHeaderColumn().run();
        } else if (value === 'toggleHeaderCell') {
            chain.toggleHeaderCell().run();
        } else if (value === 'mergeCells') {
            chain.mergeCells().run();
        } else if (value === 'splitCell') {
            chain.splitCell().run();
        } else if (value === 'deleteTable') {
            chain.deleteTable().run();
        }

        syncEditorToTextarea();
        updateToolbarState();

        if (tableActionSelect) {
            tableActionSelect.value = '';
        }

        editor.commands.focus();
    }

    toolbarElement.querySelectorAll('[data-command]').forEach((button) => {
        if (button.dataset.bound === '1') {
            return;
        }

        button.dataset.bound = '1';

        button.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });

        button.addEventListener('click', function (e) {
            e.preventDefault();
            runCommand(this.getAttribute('data-command'));
        });
    });

    if (headingSelect && headingSelect.dataset.bound !== '1') {
        headingSelect.dataset.bound = '1';

        headingSelect.addEventListener('mousedown', function (e) {
            e.stopPropagation();
        });

        headingSelect.addEventListener('change', function () {
            applyHeading(this.value);
        });
    }

    if (fontSizeSelect && fontSizeSelect.dataset.bound !== '1') {
        fontSizeSelect.dataset.bound = '1';

        fontSizeSelect.addEventListener('mousedown', function (e) {
            e.stopPropagation();
        });

        fontSizeSelect.addEventListener('change', function () {
            applyFontSize(this.value);
        });
    }

    if (lineHeightSelect && lineHeightSelect.dataset.bound !== '1') {
        lineHeightSelect.dataset.bound = '1';

        lineHeightSelect.addEventListener('mousedown', function (e) {
            e.stopPropagation();
        });

        lineHeightSelect.addEventListener('change', function () {
            applyLineHeight(this.value);
        });
    }

    if (tableActionSelect && tableActionSelect.dataset.bound !== '1') {
        tableActionSelect.dataset.bound = '1';

        tableActionSelect.addEventListener('mousedown', function (e) {
            e.stopPropagation();
        });

        tableActionSelect.addEventListener('change', function () {
            applyTableAction(this.value);
        });
    }

    window.__plantillaInformeEditor = new Editor({
        element: editorElement,
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3]
                }
            }),
            Placeholder.configure({
                placeholder: 'Escriba o edite el contenido de la plantilla...'
            }),
            Underline,
            TextStyle,
            FontSize.configure({
                types: ['textStyle']
            }),
            LineHeight.configure({
                types: ['paragraph', 'heading']
            }),
            TextAlign.configure({
                types: ['heading', 'paragraph']
            }),
            Table.configure({
                resizable: true,
                HTMLAttributes: {
                    class: 'vm-tiptap-table'
                }
            }),
            TableRow,
            TableHeader,
            TableCell
        ],
        content: textareaElement.value || '<p></p>',
        editorProps: {
            attributes: {
                class: 'tiptap-editor-content'
            }
        },
        onCreate() {
            syncEditorToTextarea();
            updateToolbarState();
        },
        onUpdate() {
            syncEditorToTextarea();
            updateToolbarState();
        },
        onSelectionUpdate() {
            updateToolbarState();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        syncEditorToTextarea();

        const formData = $(this).serialize();

        $.ajax({
            url: this.action,
            type: 'POST',
            data: formData,
            success: function (response) {
                let jsonResponse = null;

                try {
                    jsonResponse = JSON.parse(response);
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'La respuesta del servidor no es válida.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                if (jsonResponse.status === 'success') {
                    if (window.__plantillaInformeEditor && typeof window.__plantillaInformeEditor.destroy === 'function') {
                        window.__plantillaInformeEditor.destroy();
                        window.__plantillaInformeEditor = null;
                    }

                    $('#content').load('plantilla_informe/lisPlantillaInforme.php');

                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: jsonResponse.message,
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: jsonResponse.message,
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un problema al guardar la plantilla.',
                    confirmButtonText: 'OK'
                });
            }
        });
    }, { once: true });
})();