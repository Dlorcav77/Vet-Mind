<?php
// admin/configuracion_informe/tabs/campos.php

/** @var array $campos_configurados */
/** @var array $campos_permitidos */

$layout_tipo_actual_campos = $fila['layout_tipo'] ?? 'clasico';
$es_layout_clinica_campos = in_array($layout_tipo_actual_campos, ['clinica', 'inev'], true);

$filas_campos_configurados = [];

foreach ($campos_configurados as $indexCampo => $campo) {
    $ordenCampo = (int)($campo['orden'] ?? 0);

    if ($es_layout_clinica_campos && $ordenCampo >= 10) {
        $grupoOrden = (int)(floor($ordenCampo / 10) * 10);
        $claveFila = 'grupo-' . $grupoOrden;
    } else {
        $claveFila = 'campo-' . $indexCampo;
    }

    if (!isset($filas_campos_configurados[$claveFila])) {
        $filas_campos_configurados[$claveFila] = [];
    }

    $filas_campos_configurados[$claveFila][] = $campo;
}
?>


<div class="tab-pane fade" id="campos" role="tabpanel">
  <h5 class="fw-bold my-4">
    <i class="fas fa-list me-2"></i> Configuración de Campos
  </h5>

  <p class="text-muted mb-3">
    Agrega, quita o reordena los campos que aparecerán en el bloque de datos del informe.
  </p>

  <div class="campos-toolbar mb-3">
    <div class="campos-toolbar-select">
      <label for="campo-select" class="form-label">Agregar campo</label>
      <select id="campo-select" class="form-select">
        <?php foreach ($campos_permitidos as $id => $etiqueta): ?>
          <?php if (in_array((int)$id, [1, 5], true)) { continue; } ?>
          <option value="<?= $id ?>"><?= htmlspecialchars($etiqueta) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="campos-toolbar-action">
      <label class="form-label d-block">&nbsp;</label>
      <button type="button" id="agregar-campo" class="btn btn-success">
        <i class="fas fa-plus"></i> Agregar Campo
      </button>
    </div>
  </div>

  <div class="row g-3 campos-layout-grid">
    <div class="col-md-6">
      <div class="campos-builder-card h-100">
        <div class="campos-builder-header">
          <div>
            <strong>Campos agregados</strong>
            <small class="text-muted d-block">
              Arrastra las burbujas para cambiar el orden.
            </small>
          </div>
        </div>

        <div id="campos-lista" class="campos-rows-list">
          <?php foreach ($filas_campos_configurados as $filaCampos): ?>
            <div class="campos-chip-row">
              <?php foreach ($filaCampos as $campo): ?>
                <?php
                  $campoId = (int)$campo['campo_id'];
                  $esCampoFijo = in_array($campoId, [1, 5], true);
                  $registroId = (string)$campo['id'];
                  $visible = !empty($campo['visible']);

                  $nameVisible = ctype_digit($registroId)
                      ? 'campos[' . $registroId . '][visible]'
                      : 'campos_fijos[' . $campoId . '][visible]';
                ?>

                <div
                  class="campo-chip <?= $visible ? '' : 'campo-chip-oculto' ?> <?= $esCampoFijo ? 'campo-chip-fijo' : '' ?>"
                  data-id="<?= htmlspecialchars($registroId) ?>"
                  data-campo-id="<?= $campoId ?>"
                  data-fixed="<?= $esCampoFijo ? '1' : '0' ?>"
                  draggable="true"
                  title="Mover campo"
                >
                  <span class="campo-chip-handle">
                    <i class="fas fa-grip-vertical"></i>
                  </span>

                  <span class="campo-chip-label">
                    <?= htmlspecialchars($campo['etiqueta']) ?>
                  </span>

                  <input
                    type="checkbox"
                    class="campo-visible-input d-none"
                    name="<?= htmlspecialchars($nameVisible) ?>"
                    value="1"
                    checked
                    <?= $esCampoFijo ? 'disabled' : '' ?>
                  >

                  <?php if ($esCampoFijo): ?>
                    <button type="button" class="btn btn-sm campo-chip-btn eliminar-campo-chip" disabled title="Campo obligatorio">
                      <i class="fas fa-lock"></i>
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm campo-chip-btn eliminar-campo-chip" title="Eliminar campo">
                      <i class="fas fa-times"></i>
                    </button>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="campos-preview-card h-100">
        <div class="campos-builder-header">
          <div>
            <strong>Vista previa</strong>
            <small class="text-muted d-block">
              Así se verá el bloque de datos según el layout seleccionado.
            </small>
          </div>
        </div>

        <div id="vista-previa-campos-clasico" class="border p-3 rounded vista-previa-campos-layout">
          <table class="table table-bordered mb-0 bg-light">
            <tbody id="vista-previa-campos-clasico-body"></tbody>
          </table>
        </div>

        <div id="vista-previa-campos-clinica" class="border p-3 rounded vista-previa-campos-layout" style="display:none;">
          <div id="vista-previa-campos-clinica-body"></div>
        </div>
      </div>
    </div>
  </div>

  <input type="hidden" name="campos_ids_actuales" id="campos_ids_actuales">
  <input type="hidden" name="campos_orden" id="campos_orden">
</div>