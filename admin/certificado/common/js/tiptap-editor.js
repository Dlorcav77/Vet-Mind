//admin/certificado/common/tiptap-editor.js
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

(function () {
  if (window.VetmindTiptap) {
    return;
  }

  let mainEditor = null;
  let modalEditor = null;

  function getMainElements() {
    return {
      editorElement: document.getElementById('contenido_html_editor'),
      textareaElement: document.getElementById('contenido_html'),
      toolbarElement: document.getElementById('contenido_html_toolbar'),
      wrapperElement: document.getElementById('contenido_html_editor_wrapper'),
      headingSelect: document.getElementById('contenido_html_heading'),
      fontSizeSelect: document.getElementById('contenido_html_font_size'),
      lineHeightSelect: document.getElementById('contenido_html_line_height'),
      tableActionSelect: document.getElementById('contenido_html_table_actions')
    };
  }

  function getModalElements() {
    return {
      editorElement: document.getElementById('editorIA_editor'),
      textareaElement: document.getElementById('editorIA'),
      toolbarElement: document.getElementById('editorIA_toolbar'),
      wrapperElement: document.getElementById('editorIA_wrapper'),
      headingSelect: document.getElementById('editorIA_heading'),
      fontSizeSelect: document.getElementById('editorIA_font_size'),
      lineHeightSelect: document.getElementById('editorIA_line_height'),
      tableActionSelect: document.getElementById('editorIA_table_actions')
    };
  }

  function setTextareaContent(textareaElement, html) {
    if (textareaElement) {
      textareaElement.value = html || '';
    }
  }

  function resolveEditorKey(target) {
    return target === 'editorIA' ? 'modal' : 'main';
  }

  function getEditorByTarget(target) {
    return resolveEditorKey(target) === 'modal' ? modalEditor : mainEditor;
  }

  function getElementsByTarget(target) {
    return resolveEditorKey(target) === 'modal' ? getModalElements() : getMainElements();
  }

  function syncEditorToTextarea(target) {
    const editor = getEditorByTarget(target);
    const elements = getElementsByTarget(target);

    if (!elements.textareaElement || !editor) {
      return;
    }

    setTextareaContent(elements.textareaElement, editor.getHTML());
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

  function updateHeadingSelect(target) {
    const editor = getEditorByTarget(target);
    const elements = getElementsByTarget(target);

    if (!editor || !elements.headingSelect) {
      return;
    }

    let value = 'paragraph';

    if (editor.isActive('heading', { level: 1 })) value = 'h1';
    else if (editor.isActive('heading', { level: 2 })) value = 'h2';
    else if (editor.isActive('heading', { level: 3 })) value = 'h3';

    elements.headingSelect.value = value;
  }

  function updateFontSizeSelect(target) {
    const editor = getEditorByTarget(target);
    const elements = getElementsByTarget(target);

    if (!editor || !elements.fontSizeSelect) {
      return;
    }

    elements.fontSizeSelect.value = getCurrentFontSize(editor);
  }

  function updateLineHeightSelect(target) {
    const editor = getEditorByTarget(target);
    const elements = getElementsByTarget(target);

    if (!editor || !elements.lineHeightSelect) {
      return;
    }

    elements.lineHeightSelect.value = getCurrentLineHeight(editor);
  }

  function updateTableActionVisibility(target) {
    const editor = getEditorByTarget(target);
    const elements = getElementsByTarget(target);

    if (!editor || !elements.tableActionSelect) {
      return;
    }

    const html = (editor.getHTML() || '').toLowerCase();
    const hasTable = html.indexOf('<table') !== -1;

    elements.tableActionSelect.style.display = hasTable ? '' : 'none';

    if (!hasTable) {
      elements.tableActionSelect.value = '';
    }
  }

  function updateToolbarState(target) {
    const editor = getEditorByTarget(target);
    const elements = getElementsByTarget(target);

    if (!elements.toolbarElement || !editor) {
      return;
    }

    elements.toolbarElement.querySelectorAll('[data-command]').forEach((button) => {
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

    updateHeadingSelect(target);
    updateFontSizeSelect(target);
    updateLineHeightSelect(target);
    updateTableActionVisibility(target);
  }

  async function runCommand(editor, command) {
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
      await insertTable(editor);
    }
  }

  function applyHeading(editor, value) {
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
  }

  function applyFontSize(editor, value) {
    if (!editor) {
      return;
    }

    if (!value || value === '14px') {
      editor.chain().focus().unsetFontSize().run();
      return;
    }

    editor.chain().focus().setFontSize(value).run();
  }

  function applyLineHeight(editor, value) {
    if (!editor) {
      return;
    }

    if (!value || value === '1.15') {
      editor.chain().focus().unsetLineHeight().run();
      return;
    }

    editor.chain().focus().setLineHeight(value).run();
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

      setTimeout(() => {
        updateToolbarState('main');
        updateToolbarState('editorIA');
      }, 0);

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

    setTimeout(() => {
      updateToolbarState('main');
      updateToolbarState('editorIA');
    }, 0);
  }

  function applyTableAction(editor, value) {
    if (!editor || !value) {
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
    } else if (value === 'fixTables') {
      chain.fixTables().run();
    }
  }

  function bindToolbar(toolbarElement, target) {
    if (!toolbarElement) {
      return;
    }

    toolbarElement.querySelectorAll('[data-command]').forEach((button) => {
      if (button.dataset.bound === '1') {
        return;
      }
      button.dataset.bound = '1';

      button.addEventListener('mousedown', function (e) {
        e.preventDefault();
      });

      button.addEventListener('click', async function (e) {
        e.preventDefault();

        const command = this.getAttribute('data-command');
        const buttonTarget = this.getAttribute('data-editor-target') || target;
        const editor = getEditorByTarget(buttonTarget);

        if (!editor) {
          return;
        }

        await runCommand(editor, command);
        syncEditorToTextarea(buttonTarget);
        updateToolbarState(buttonTarget);
        editor.commands.focus();
      });
    });

    const elements = getElementsByTarget(target);

    if (elements.headingSelect && elements.headingSelect.dataset.bound !== '1') {
      elements.headingSelect.dataset.bound = '1';

      elements.headingSelect.addEventListener('mousedown', function (e) {
        e.stopPropagation();
      });

      elements.headingSelect.addEventListener('change', function () {
        const selectTarget = this.getAttribute('data-editor-target') || target;
        const editor = getEditorByTarget(selectTarget);

        applyHeading(editor, this.value);
        syncEditorToTextarea(selectTarget);
        updateToolbarState(selectTarget);

        if (editor) {
          editor.commands.focus();
        }
      });
    }

    if (elements.fontSizeSelect && elements.fontSizeSelect.dataset.bound !== '1') {
      elements.fontSizeSelect.dataset.bound = '1';

      elements.fontSizeSelect.addEventListener('mousedown', function (e) {
        e.stopPropagation();
      });

      elements.fontSizeSelect.addEventListener('change', function () {
        const selectTarget = this.getAttribute('data-editor-target') || target;
        const editor = getEditorByTarget(selectTarget);

        applyFontSize(editor, this.value);
        syncEditorToTextarea(selectTarget);
        updateToolbarState(selectTarget);

        if (editor) {
          editor.commands.focus();
        }
      });
    }

    if (elements.lineHeightSelect && elements.lineHeightSelect.dataset.bound !== '1') {
      elements.lineHeightSelect.dataset.bound = '1';

      elements.lineHeightSelect.addEventListener('mousedown', function (e) {
        e.stopPropagation();
      });

      elements.lineHeightSelect.addEventListener('change', function () {
        const selectTarget = this.getAttribute('data-editor-target') || target;
        const editor = getEditorByTarget(selectTarget);

        applyLineHeight(editor, this.value);
        syncEditorToTextarea(selectTarget);
        updateToolbarState(selectTarget);

        if (editor) {
          editor.commands.focus();
        }
      });
    }

    const tableActionSelect = getElementsByTarget(target).tableActionSelect;
    if (tableActionSelect && tableActionSelect.dataset.bound !== '1') {
      tableActionSelect.dataset.bound = '1';

      tableActionSelect.addEventListener('mousedown', function (e) {
        e.stopPropagation();
      });

      tableActionSelect.addEventListener('change', function () {
        const selectTarget = this.getAttribute('data-editor-target') || target;
        const editor = getEditorByTarget(selectTarget);
        const action = this.value;

        if (!editor || !action) {
          this.value = '';
          return;
        }

        applyTableAction(editor, action);
        syncEditorToTextarea(selectTarget);
        updateToolbarState(selectTarget);

        this.value = '';

        if (editor) {
          editor.commands.focus();
        }
      });
    }
  }

  function buildEditor(element, placeholderText, initialContent, onUpdateCallback) {
    return new Editor({
      element,
      extensions: [
        StarterKit.configure({
          heading: {
            levels: [1, 2, 3]
          }
        }),
        Placeholder.configure({
          placeholder: placeholderText
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
      content: initialContent || '<p></p>',
      editorProps: {
        attributes: {
          class: 'tiptap-editor-content'
        }
      },
      onCreate() {
        if (typeof onUpdateCallback === 'function') {
          onUpdateCallback();
        }
      },
      onUpdate() {
        if (typeof onUpdateCallback === 'function') {
          onUpdateCallback();
        }
      },
      onSelectionUpdate() {
        if (typeof onUpdateCallback === 'function') {
          onUpdateCallback(true);
        }
      }
    });
  }

  function initMainEditor(options = {}) {
    const { editorElement, toolbarElement, wrapperElement, textareaElement } = getMainElements();

    if (!editorElement) {
      return null;
    }

    if (mainEditor) {
      return mainEditor;
    }

    const initialContent = options.content ?? (textareaElement ? (textareaElement.value || '') : '');

    mainEditor = buildEditor(
      editorElement,
      'Escriba o edite el contenido del Informe...',
      initialContent,
      function (onlyToolbar) {
        if (!onlyToolbar) {
          syncEditorToTextarea('main');
        }
        updateToolbarState('main');
      }
    );

    if (toolbarElement) {
      toolbarElement.style.display = '';
      bindToolbar(toolbarElement, 'main');
    }

    if (wrapperElement) {
      wrapperElement.style.display = '';
    }

    syncEditorToTextarea('main');
    updateToolbarState('main');

    return mainEditor;
  }

  function destroyMainEditor() {
    if (mainEditor) {
      syncEditorToTextarea('main');
      mainEditor.destroy();
      mainEditor = null;
    }
  }

  function initModalEditor(content = '') {
    const { editorElement, toolbarElement, wrapperElement, textareaElement } = getModalElements();

    if (!editorElement) {
      return null;
    }

    if (modalEditor) {
      modalEditor.commands.setContent(content || '<p></p>', false);
      syncEditorToTextarea('editorIA');
      updateToolbarState('editorIA');
      return modalEditor;
    }

    const initialContent = content || (textareaElement ? (textareaElement.value || '') : '');

    modalEditor = buildEditor(
      editorElement,
      'Aquí se mostrará el contenido procesado por IA...',
      initialContent,
      function (onlyToolbar) {
        if (!onlyToolbar) {
          syncEditorToTextarea('editorIA');
        }
        updateToolbarState('editorIA');
      }
    );

    if (toolbarElement) {
      toolbarElement.style.display = '';
      bindToolbar(toolbarElement, 'editorIA');
    }

    if (wrapperElement) {
      wrapperElement.style.display = '';
    }

    syncEditorToTextarea('editorIA');
    updateToolbarState('editorIA');

    return modalEditor;
  }

  function destroyModalEditor() {
    if (modalEditor) {
      syncEditorToTextarea('editorIA');
      modalEditor.destroy();
      modalEditor = null;
    }
  }

  function getMainEditor() {
    return mainEditor;
  }

  function getModalEditor() {
    return modalEditor;
  }

  function getMainEditorHTML() {
    if (!mainEditor) {
      const { textareaElement } = getMainElements();
      return textareaElement ? (textareaElement.value || '') : '';
    }

    return mainEditor.getHTML();
  }

  function getModalEditorHTML() {
    if (!modalEditor) {
      const { textareaElement } = getModalElements();
      return textareaElement ? (textareaElement.value || '') : '';
    }

    return modalEditor.getHTML();
  }

  function setMainEditorHTML(html) {
    const value = html || '<p></p>';

    if (mainEditor) {
      mainEditor.commands.setContent(value, false);
      syncEditorToTextarea('main');
      updateToolbarState('main');
      return;
    }

    const { textareaElement } = getMainElements();
    setTextareaContent(textareaElement, value);
  }

  function setModalEditorHTML(html) {
    const value = html || '<p></p>';

    if (modalEditor) {
      modalEditor.commands.setContent(value, false);
      syncEditorToTextarea('editorIA');
      updateToolbarState('editorIA');
      return;
    }

    const { textareaElement } = getModalElements();
    setTextareaContent(textareaElement, value);
  }

  function insertPlantillaIfEmpty(html) {
    const plantilla = String(html || '').trim();
    if (!plantilla) {
      return;
    }

    if (mainEditor) {
      const currentHtml = (mainEditor.getHTML() || '').trim();
      const currentText = (mainEditor.getText() || '').trim();

      if (!currentText && (currentHtml === '<p></p>' || currentHtml === '<p></p>\n')) {
        mainEditor.commands.setContent(plantilla, false);
        syncEditorToTextarea('main');
        updateToolbarState('main');
      }
      return;
    }

    const { textareaElement } = getMainElements();
    if (textareaElement && !String(textareaElement.value || '').trim()) {
      textareaElement.value = plantilla;
    }
  }

  function focusMainEditor() {
    if (mainEditor) {
      mainEditor.commands.focus();
    }
  }

  function focusModalEditor() {
    if (modalEditor) {
      modalEditor.commands.focus();
    }
  }

  function syncMainEditorToTextarea() {
    syncEditorToTextarea('main');
  }

  function syncModalEditorToTextarea() {
    syncEditorToTextarea('editorIA');
  }

  window.VetmindTiptap = {
    initMainEditor,
    destroyMainEditor,
    getMainEditor,
    getMainEditorHTML,
    setMainEditorHTML,
    syncMainEditorToTextarea,
    insertPlantillaIfEmpty,
    focusMainEditor,

    initModalEditor,
    destroyModalEditor,
    getModalEditor,
    getModalEditorHTML,
    setModalEditorHTML,
    syncModalEditorToTextarea,
    focusModalEditor
  };
})();