// admin/certificado/common/js/destacado.js

$(function () {
    const $btn = $('#btnToggleDestacado');
    const $estado = $('#es_destacado');
    const $wrap = $('#destacadoTituloWrap');
    const $titulo = $('#destacado_titulo');

    if (!$btn.length || !$estado.length || !$wrap.length) {
        return;
    }

    function aplicarEstadoDestacado(animar = false) {
        const activo = $estado.val() === '1';

        $btn
            .toggleClass('text-warning', activo)
            .toggleClass('text-secondary', !activo)
            .attr(
                'title',
                activo
                    ? 'Quitar destacado'
                    : 'Destacar informe'
            )
            .attr(
                'aria-label',
                activo
                    ? 'Quitar destacado'
                    : 'Destacar informe'
            );

        if (!animar) {
            $wrap.toggle(activo);
            return;
        }

        if (activo) {
            $wrap
                .stop(true, true)
                .slideDown(150);

            setTimeout(function () {
                $titulo.trigger('focus');
            }, 170);

        } else {
            $wrap
                .stop(true, true)
                .slideUp(150);
        }
    }

    $btn
        .off('click.toggleDestacado')
        .on('click.toggleDestacado', function () {
            const activo = $estado.val() === '1';

            $estado
                .val(activo ? '0' : '1')
                .trigger('change');

            aplicarEstadoDestacado(true);
        });

    aplicarEstadoDestacado(false);
});