<?php
// admin/certificado/tipo_examen/tipo_examen.php

/**
 * Este archivo se incluye desde admin/certificado/certificados.php.
 * Las variables principales vienen preparadas desde certificado_form_data.php
 * y desde el flujo principal del formulario de certificados.
 *
 * @var mysqli $mysqli
 * @var int|string $usuario_id
 */

if (!isset($tipos_estudio)) {
    $tipos_estudio = [];
    $query = "
        SELECT te.id AS tipo_id, te.nombre AS tipo_nombre, pi.id AS plantilla_id, pi.nombre AS plantilla_nombre
        FROM tipo_examen te
        LEFT JOIN plantilla_informe pi 
            ON pi.tipo_examen_id = te.id
           AND pi.estado = 'activo'
           AND pi.deleted_at IS NULL
        WHERE te.veterinario_id = ?
        ORDER BY te.nombre ASC, pi.nombre ASC
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $tipo_id = $row['tipo_id'];
        $tipo_nombre = $row['tipo_nombre'];

        if (!isset($tipos_estudio[$tipo_id])) {
            $tipos_estudio[$tipo_id] = [
                'nombre' => $tipo_nombre,
                'plantillas' => []
            ];
        }

        if (!empty($row['plantilla_id'])) {
            $tipos_estudio[$tipo_id]['plantillas'][] = [
                'id' => $row['plantilla_id'],
                'nombre' => $row['plantilla_nombre']
            ];
        }
    }
}

$manual_data_certificado = [];

if (!empty($fila['manual_data'])) {
    $decoded_manual_data = json_decode((string)$fila['manual_data'], true);

    if (is_array($decoded_manual_data)) {
        $manual_data_certificado = $decoded_manual_data;
    }
}

if (!function_exists('valorManualCertificado')) {
    function valorManualCertificado(array $manual_data_certificado, string $campo): string
    {
        $valor = $manual_data_certificado[$campo] ?? '';

        if (is_array($valor)) {
            return '';
        }

        return trim((string)$valor);
    }
}
?>

<link rel="stylesheet" href="certificado/tipo_examen/css/tipo_examen.css?v=3">
<div class="col-12" id="bloque_tipo_examen">
    <div class="vm-campos-generales mb-1" id="fila_campos_generales">
        <?php foreach ($campos_permitidos_catalogo ?? [] as $campoInforme): ?>
            <?php
                if (($campoInforme['ambito'] ?? '') !== 'informe') {
                    continue;
                }

                $campoKey = $campoInforme['campo'];
                $campoInterno = (isset($campoInforme['campo_interno']) && $campoInforme['campo_interno'] !== '' && $campoInforme['campo_interno'] !== null)
                    ? $campoInforme['campo_interno']
                    : $campoKey;

                $campoLabel = $campoInforme['etiqueta'];

                $postNamePorInterno = [
                    'medico_solicitante' => 'medico_solicitante',
                    'motivo'             => 'motivo_examen',
                    'recinto'            => 'recinto',
                ];
                $valorPorInterno = [
                    'medico_solicitante' => (string)($fila['medico_solicitante'] ?? ''),
                    'motivo'             => (string)($fila['motivo'] ?? ''),
                    'recinto'            => (string)($fila['recinto'] ?? ''),
                ];

                $inputName = $postNamePorInterno[$campoInterno] ?? $campoInterno;
                $inputId   = $inputName;

                $valor = $valorPorInterno[$campoInterno] ?? '';
                if ($valor === '' && isset($manual_data_certificado[$campoKey]) && !is_array($manual_data_certificado[$campoKey])) {
                    $valor = (string)$manual_data_certificado[$campoKey];
                }

                $visibleInforme = in_array($campoKey, $campos_visibles_actuales ?? [], true);
            ?>
            <div
                class="vm-campo-general mb-3"
                id="wrap_<?= htmlspecialchars($campoKey) ?>"
                data-campo-general="<?= htmlspecialchars($campoKey) ?>"
                data-interno="<?= htmlspecialchars($campoInterno) ?>"
                style="<?= $visibleInforme ? '' : 'display:none;' ?>"
            >
                <label for="<?= htmlspecialchars($inputId) ?>" class="form-label fw-bold"><?= htmlspecialchars($campoLabel) ?></label>
                <?php if ($campoInterno === 'recinto'): ?>
                    <select
                        class="form-select"
                        name="<?= htmlspecialchars($inputName) ?>"
                        id="<?= htmlspecialchars($inputId) ?>"
                    >
                        <option value="">Seleccione recinto...</option>
                        <?php foreach ($clinicas_recinto ?? [] as $nombreClinica): ?>
                            <option value="<?= htmlspecialchars($nombreClinica) ?>" <?= $valor === $nombreClinica ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nombreClinica) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input
                        type="text"
                        class="form-control"
                        name="<?= htmlspecialchars($inputName) ?>"
                        id="<?= htmlspecialchars($inputId) ?>"
                        value="<?= htmlspecialchars($valor) ?>"
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-1 mb-1 align-items-start" id="fila_tipo_e_imagenes_preview">

        <div class="col-md-6 mb-3 d-flex flex-column" id="columna_tipo_plantilla">
            <div class="mb-3">
                <label for="plantilla_informe_id" class="form-label fw-bold">Tipo de Examen</label>
                <select name="plantilla_informe_id" id="plantilla_informe_id" class="form-select" required>
                    <option value="">Seleccione una plantilla</option>
                    <?php foreach ($tipos_estudio as $tipo): ?>
                        <?php if (!empty($tipo['plantillas'])): ?>
                            <optgroup label="<?= htmlspecialchars($tipo['nombre']) ?>">
                                <?php foreach ($tipo['plantillas'] as $plantilla): ?>
                                    <option value="<?= htmlspecialchars($plantilla['id']) ?>"
                                        <?= (isset($fila['tipo_estudio']) && $plantilla['id'] == $fila['tipo_estudio']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plantilla['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="plantillaPreview" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap" style="min-height: 28px;">
                    <label class="form-label fw-bold mb-0">Plantilla Seleccionada</label>

                    <div class="d-flex align-items-center gap-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            id="btnUsarPlantillaContenido"
                            style="display:none;"
                        >
                            <i class="fas fa-arrow-down"></i>
                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            id="btnTogglePlantillaPreview"
                            data-visible="1"
                            title="Ocultar plantilla asociada"
                        >
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="border rounded p-3 bg-light" id="plantillaContenido" style="min-height: 400px; overflow-y: auto;">
                    <em class="text-muted">Selecciona un tipo de examen para ver su plantilla...</em>
                </div>
            </div>

            <div id="plantillaPlaceholder" style="display:block;"></div>
        </div>

        <div class="col-md-6 mb-3 d-flex flex-column" id="columna_imagenes">
            <div class="mb-3">
                <label for="imagenInput" class="form-label fw-bold">Imágenes Asociadas</label>
                <input type="file" id="imagenInput" class="form-control" name="imagenes[]" multiple accept="image/*">
            </div>

            <?php include __DIR__ . '/bloque_imagenes.php'; ?>
        </div>
    </div>
</div>

<script src="certificado/tipo_examen/js/tipo_examen.js?v=10"></script>
<script src="certificado/tipo_examen/js/imagenes.js?v=2"></script>