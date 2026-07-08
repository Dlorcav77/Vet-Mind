<?php
// admin/certificado/partials/hidden_fields.php

/**
 * @var string $action
 * @var int    $configuracion_informe_id_actual
 */
?>
<input type="hidden" id="plantillaBase" name="plantillaBase" value="">
<input type="hidden" name="rid_ia" id="rid_ia" value="<?= htmlspecialchars($fila['rid_ia'] ?? '') ?>">
<input type="hidden" name="rid_revision" id="rid_revision" value="<?= htmlspecialchars($fila['rid_revision'] ?? '') ?>">
<input type="hidden" name="configuracion_informe_id" id="configuracion_informe_id_hidden" value="<?= (int)$configuracion_informe_id_actual ?>">
<input type="hidden" name="veterinario_id" value="<?= (int)$usuario_id ?>">
<input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
<input type="hidden" name="borrador_id" id="borrador_id" value="<?= (int)($borrador_id ?? 0) ?>">
<input type="hidden" name="borrador_scope_key" id="borrador_scope_key" value="<?= htmlspecialchars($borrador_scope_key ?? '') ?>">

<?php if ($action === 'modificar'): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="imagenes_antiguas" id="imagenes_antiguas">
<?php endif; ?>