<?php
// admin/certificado/partials/hidden_fields.php
?>
<input type="hidden" id="plantillaBase" name="plantillaBase" value="">
<input type="hidden" id="configuracion_informe_id_hidden" name="configuracion_informe_id" value="<?= (int)$configuracion_informe_id_actual ?>">
<input type="hidden" name="veterinario_id" value="<?= (int)$usuario_id ?>">
<input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">

<?php if ($action === 'modificar'): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="imagenes_antiguas" id="imagenes_antiguas">
<?php endif; ?>