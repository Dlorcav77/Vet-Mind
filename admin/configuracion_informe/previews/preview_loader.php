<?php
// admin/configuracion_informe/previews/preview_loader.php

require_once __DIR__ . '/preview_clasico.php';
require_once __DIR__ . '/preview_clinica.php';

function renderVistaPreviaPlantilla($mysqli, $fila) {
    $layoutTipo = $fila['layout_tipo'] ?? 'clasico';

    switch ($layoutTipo) {
        case 'clinica':
            return renderVistaPreviaPlantillaClinica($mysqli, $fila);

        case 'clasico':
        default:
            return renderVistaPreviaPlantillaClasico($mysqli, $fila);
    }
}