function destroyTiptapEditors() {
    if (window.VetmindTiptap && typeof window.VetmindTiptap.destroyModalEditor === 'function') {
        window.VetmindTiptap.destroyModalEditor();
    }
}

function mostrarModalIA(content) {
    const $modal = $('#modalProcesarIA');

    $modal.off('shown.bs.modal.vmIA').on('shown.bs.modal.vmIA', function () {
        $('#debug-host').hide().empty();
        $('#editorIA_wrapper').show();

        if (window.VetmindTiptap && typeof window.VetmindTiptap.initModalEditor === 'function') {
            window.VetmindTiptap.initModalEditor(content || '');
            setTimeout(function () {
                if (window.VetmindTiptap && typeof window.VetmindTiptap.focusModalEditor === 'function') {
                    window.VetmindTiptap.focusModalEditor();
                }
            }, 60);
        } else {
            $('#editorIA').val(content || '');
        }
    });

    $modal.off('hidden.bs.modal.vmIA').on('hidden.bs.modal.vmIA', function () {
        if (window.VetmindTiptap && typeof window.VetmindTiptap.destroyModalEditor === 'function') {
            window.VetmindTiptap.destroyModalEditor();
        }

        $('#debug-host').empty().hide();
        $('#editorIA_wrapper').show();
    });

    $modal.modal('show');
}

function mostrarModalDebug(html) {
    const $modal = $('#modalProcesarIA');

    if (window.VetmindTiptap && typeof window.VetmindTiptap.destroyModalEditor === 'function') {
        window.VetmindTiptap.destroyModalEditor();
    }

    $('#editorIA_wrapper').hide();

    let $host = $modal.find('#debug-host');
    if (!$host.length) {
        $host = $('<div id="debug-host" style="max-height:60vh; overflow:auto; padding:8px;"></div>');
        $modal.find('#editorIA_wrapper').after($host);
    }

    $host.html(html).show();

    $modal.off('hidden.bs.modal.vmIADebug').on('hidden.bs.modal.vmIADebug', function () {
        $host.empty().hide();
        $('#editorIA_wrapper').show();
    });

    $modal.modal('show');
}