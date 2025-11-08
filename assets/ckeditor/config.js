/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For complete reference see:
	// https://ckeditor.com/docs/ckeditor4/latest/api/CKEDITOR_config.html

	// The toolbar groups arrangement, optimized for two toolbar rows.

	config.toolbarGroups = [
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		{ name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
		{ name: 'links' },
		{ name: 'insert' },
		{ name: 'forms' },
		{ name: 'tools' },
		{ name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] },
		{ name: 'others' },
		'/',
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'styles' },
		{ name: 'colors' },
		{ name: 'about' }
	];

	// Remove some buttons provided by the standard plugins, which are
	// not needed in the Standard(s) toolbar.
	config.removeButtons = 'Underline,Subscript,Superscript';

	// Set the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';

  config.line_height = "1;1.15;1.5;2;2.5;3";

	// Simplify the dialog windows.
	config.removeDialogTabs = 'image:advanced;link:advanced';

	config.extraPlugins = 'justify,variables,lineheight';

  config.toolbar = [
    { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo'] },
    { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
    { name: 'styles', items: ['Format', 'FontSize'] },
    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
		{ name: 'paragraph', items: [
				'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 
				'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock',
				'LineHeight'
		] },
    { name: 'tools', items: ['Maximize'] },
    // { name: 'variables', items: ['Variables'] }
  ];

	CKEDITOR.on('notification', function(evt) {
			if (evt.data.message && evt.data.message.indexOf('This CKEditor 4.') === 0) {
					evt.cancel(); // Bloquearlo
			}
	}, null, null, 1); // Alta prioridad para ejecutarse primero
	

};
