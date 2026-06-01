<?php
// admin/configuracion_informe/tabs/general.php

/** @var array $fila */
?>

<div class="tab-pane fade show active" id="general" role="tabpanel">
  <div class="row mt-4 align-items-end">
    <div class="col-md-4 mb-2">
      <label for="titulo_informe" class="form-label">Título</label>
      <input
        type="text"
        class="form-control"
        name="titulo_informe"
        id="titulo_informe"
        maxlength="150"
        value="<?= htmlspecialchars($fila['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>"
        placeholder="Ej: INFORME ECOGRÁFICO"
      >
    </div>

    <div class="col-md-4 mb-2">
      <label for="subtitulo" class="form-label">Subtítulo</label>
      <input
        type="text"
        class="form-control"
        name="subtitulo"
        id="subtitulo"
        maxlength="150"
        value="<?= htmlspecialchars($fila['subtitulo'] ?? '') ?>"
        placeholder="Ej: DESCRIPCIÓN ECOGRÁFICA"
      >
    </div>

    <div class="col-md-2 mb-2">
      <label for="subtitulo_align" class="form-label">Alineación Subtítulo</label>
      <select name="subtitulo_align" id="subtitulo_align" class="form-select select2">
        <option value="left" data-icon="fas fa-align-left" <?= ($fila['subtitulo_align'] ?? 'center') === 'left' ? 'selected' : '' ?>>
          Izquierda
        </option>
        <option value="center" data-icon="fas fa-align-center" <?= ($fila['subtitulo_align'] ?? 'center') === 'center' ? 'selected' : '' ?>>
          Centro
        </option>
        <option value="right" data-icon="fas fa-align-right" <?= ($fila['subtitulo_align'] ?? 'center') === 'right' ? 'selected' : '' ?>>
          Derecha
        </option>
      </select>
    </div>

    <div class="col-md-2 mb-2">
      <label for="imagenes_por_fila" class="form-label">Cantidad de Imágenes</label>
      <select name="imagenes_por_fila" id="imagenes_por_fila" class="form-control">
        <option value="1" <?= ($fila['imagenes_por_fila'] ?? '2') == '1' ? 'selected' : '' ?>>
          1 imagen por fila
        </option>
        <option value="2" <?= ($fila['imagenes_por_fila'] ?? '2') == '2' ? 'selected' : '' ?>>
          2 imágenes por fila
        </option>
        <option value="3" <?= ($fila['imagenes_por_fila'] ?? '2') == '3' ? 'selected' : '' ?>>
          3 imágenes por fila
        </option>
        <option value="4" <?= ($fila['imagenes_por_fila'] ?? '2') == '4' ? 'selected' : '' ?>>
          4 imágenes por fila
        </option>
      </select>
    </div>
  </div>

  <hr class="mt-4" style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">

  <h5 class="fw-bold my-4">
    <i class="fas fa-paint-brush me-2"></i> Diseño visual de la plantilla
  </h5>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="border rounded p-3 h-100">
        <h6 class="fw-bold mb-3">
          <i class="fas fa-image me-1"></i> Logo
        </h6>

        <div class="row align-items-start">
          <div class="col-md-7">
            <div class="mb-2">
              <label for="logo_position" class="form-label mb-1">Posición</label>
              <select id="logo_position" name="logo_position" class="form-select select2">
                <option value="left" <?= ($fila['logo_position'] ?? 'center') === 'left' ? 'selected' : '' ?> data-icon="fas fa-align-left">
                  Izquierda
                </option>
                <option value="center" <?= ($fila['logo_position'] ?? 'center') === 'center' ? 'selected' : '' ?> data-icon="fas fa-align-center">
                  Centro
                </option>
                <option value="right" <?= ($fila['logo_position'] ?? 'center') === 'right' ? 'selected' : '' ?> data-icon="fas fa-align-right">
                  Derecha
                </option>
              </select>
            </div>

            <div class="mb-2">
              <label for="logo_size" class="form-label mb-1">Tamaño</label>
              <select name="logo_size" id="logo_size" class="form-select">
                <option value="small" <?= ($fila['logo_size'] ?? 'medium') === 'small' ? 'selected' : '' ?>>
                  Chico
                </option>
                <option value="medium" <?= ($fila['logo_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>
                  Mediano
                </option>
                <option value="large" <?= ($fila['logo_size'] ?? 'medium') === 'large' ? 'selected' : '' ?>>
                  Grande
                </option>
              </select>
            </div>

            <div class="mb-0">
              <label for="logo" class="form-label mb-1">Nuevo</label>
              <input type="file" class="form-control" name="logo" id="logo" accept="image/*">
            </div>
          </div>

          <div class="col-md-5">
            <label class="form-label mb-1">Imagen actual</label>
            <div class="border rounded bg-white d-flex align-items-center justify-content-center" style="height:150px;">
              <?php if (!empty($fila['logo_url'])): ?>
                <img
                  src="../<?= htmlspecialchars($fila['logo_url']) ?>"
                  alt="Logo Actual"
                  style="max-height:120px; max-width:100%; object-fit:contain;"
                >
              <?php else: ?>
                <span class="text-muted small">Sin logo</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="border rounded p-3 h-100">
        <h6 class="fw-bold mb-3">
          <i class="fas fa-tint me-1"></i> Marca de Agua
        </h6>

        <div class="row align-items-start">
          <div class="col-md-7">
            <div class="mb-2">
              <label class="form-label mb-1" for="mostrar_marca_agua">Mostrar</label>
              <div class="form-check form-switch">
                <input
                  class="form-check-input"
                  type="checkbox"
                  name="mostrar_marca_agua"
                  id="mostrar_marca_agua"
                  value="1"
                  <?= !empty($fila['mostrar_marca_agua']) ? 'checked' : '' ?>
                >
                <label class="form-check-label" for="mostrar_marca_agua">Activar</label>
              </div>
            </div>

            <div class="mb-2">
              <label for="marca_agua_size" class="form-label mb-1">Tamaño</label>
              <select name="marca_agua_size" id="marca_agua_size" class="form-select">
                <option value="small" <?= ($fila['marca_agua_size'] ?? 'medium') === 'small' ? 'selected' : '' ?>>
                  Chico
                </option>
                <option value="medium" <?= ($fila['marca_agua_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>
                  Mediano
                </option>
                <option value="large" <?= ($fila['marca_agua_size'] ?? 'medium') === 'large' ? 'selected' : '' ?>>
                  Grande
                </option>
              </select>
            </div>

            <div class="mb-0">
              <label for="marca_agua" class="form-label mb-1">Nuevo</label>
              <input type="file" class="form-control" name="marca_agua" id="marca_agua" accept="image/*">
            </div>
          </div>

          <div class="col-md-5">
            <label class="form-label mb-1">Imagen actual</label>
            <div class="border rounded bg-white d-flex align-items-center justify-content-center" style="height:150px;">
              <?php if (!empty($fila['marca_agua_url'])): ?>
                <img
                  src="../<?= htmlspecialchars($fila['marca_agua_url']) ?>"
                  alt="Marca de Agua Actual"
                  style="max-height:120px; max-width:100%; object-fit:contain; opacity:.55;"
                >
              <?php else: ?>
                <span class="text-muted small">Sin marca</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>