<?php
// admin/certificado/partials/hidden_fields.php

/**
 * @var string $action
 * @var int    $configuracion_informe_id_actual
 */

$notasOrganosIniciales = isset($notas_organos) && is_array($notas_organos)
    ? $notas_organos
    : [];

if (
    isset($borrador_payload['notas_organos']) &&
    is_array($borrador_payload['notas_organos'])
) {
    $notasOrganosIniciales = $borrador_payload['notas_organos'];
}

$notasOrganosInicialesJson = json_encode(
    $notasOrganosIniciales,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($notasOrganosInicialesJson === false) {
    $notasOrganosInicialesJson = '{}';
}
?>
<input type="hidden" id="plantillaBase" name="plantillaBase" value="">
<input type="hidden" name="rid_ia" id="rid_ia" value="<?= htmlspecialchars($fila['rid_ia'] ?? '') ?>">
<input type="hidden" name="rid_revision" id="rid_revision" value="<?= htmlspecialchars($fila['rid_revision'] ?? '') ?>">
<input type="hidden" name="configuracion_informe_id" id="configuracion_informe_id_hidden" value="<?= (int)$configuracion_informe_id_actual ?>">
<input type="hidden" name="veterinario_id" value="<?= (int)$usuario_id ?>">
<input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
<input type="hidden" name="borrador_id" id="borrador_id" value="<?= (int)($borrador_id ?? 0) ?>">
<input type="hidden" name="borrador_scope_key" id="borrador_scope_key" value="<?= htmlspecialchars($borrador_scope_key ?? '') ?>">
<input type="hidden" name="notas_organos" id="notas_organos" value="<?= htmlspecialchars($notasOrganosInicialesJson, ENT_QUOTES, 'UTF-8') ?>">

<?php if ($action === 'modificar'): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="imagenes_antiguas" id="imagenes_antiguas">
<?php endif; ?>