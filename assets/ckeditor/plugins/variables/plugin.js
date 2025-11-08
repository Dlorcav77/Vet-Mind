CKEDITOR.plugins.add('variables', {
  init: function(editor) {
    editor.ui.addRichCombo('Variables', {
      label: 'Insertar variable',
      title: 'Insertar variable',
      toolbar: 'insert',
      className: 'cke_format',
      panel: {
        css: [CKEDITOR.skin.getPath('editor')].concat(editor.config.contentsCss),
        multiSelect: false,
        attributes: { 'aria-label': 'Insertar variable' }
      },

      init: function() {
        if (typeof VARIABLES_CONTRATO !== 'undefined') {
          VARIABLES_CONTRATO.forEach(variable => {
            this.add(variable, variable, variable);
          });
        } else {
          console.warn('VARIABLES_CONTRATO no está definido');
        }
      },

      onClick: function(value) {
        editor.insertText(value);
      }
    });
  }
});
