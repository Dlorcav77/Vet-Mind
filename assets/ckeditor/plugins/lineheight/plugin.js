CKEDITOR.plugins.add('lineheight', {
    icons: 'lineheight2', // ✅ esta línea es crucial
    requires: 'richcombo',
    lang: 'en,es',
    init: function (editor) {
        var config = editor.config;
        var lang = editor.lang.lineheight;

        var lineHeights = (config.line_height || '1;1.15;1.5;2;2.5;3').split(';');
        var style = new CKEDITOR.style({ element: 'span', styles: { 'line-height': '#(lineheight)' } });

        editor.ui.addRichCombo('LineHeight', {
            label: lang.label,
            title: lang.label,
            toolbar: 'styles,30',
            icon: this.path + 'icons/lineheight2.png', // también puede ir aquí para asegurarlo
            panel: {
                css: [CKEDITOR.skin.getPath('editor')].concat(config.contentsCss),
                multiSelect: false,
                attributes: { 'aria-label': lang.label }
            },
            init: function () {
                // console.log("Valores de lineheight:", lineHeights); 
                for (var i = 0; i < lineHeights.length; i++) {
                    var value = lineHeights[i];
                    this.add(value, value, value);
                }
            },
            onClick: function (value) {
                editor.focus();
                editor.fire('saveSnapshot');
                var appliedStyle = new CKEDITOR.style({ element: 'div', styles: { 'line-height': value } });
                editor.applyStyle(appliedStyle);
                editor.fire('saveSnapshot');
            }
        });
    }
});
