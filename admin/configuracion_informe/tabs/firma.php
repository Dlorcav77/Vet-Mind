<?php
// admin/configuracion_informe/tabs/firma.php

/** @var array $fila */
/** @var array $subtitulos */
?>

<div class="tab-pane fade" id="firma" role="tabpanel">
  <div class="firma-config-grid">
    <div class="firma-card firma-card-imagen">
      <div class="firma-card-header">
        <div>
          <h6 class="firma-card-title mb-0">
            <i class="fas fa-signature me-1"></i> Firma escaneada
          </h6>
          <small class="text-muted">Imagen opcional para mostrar en el informe.</small>
        </div>
      </div>

      <div class="firma-imagen-layout">
        <div class="firma-imagen-controls">
          <div class="firma-switch-box">
            <label class="form-label mb-1" for="mostrar_firma_imagen">Mostrar</label>
            <div class="form-check form-switch mb-0">
              <input
                class="form-check-input"
                type="checkbox"
                name="mostrar_firma_imagen"
                id="mostrar_firma_imagen"
                value="1"
                <?= !empty($fila['mostrar_firma_imagen']) ? 'checked' : '' ?>
              >
              <label class="form-check-label" for="mostrar_firma_imagen">Activar</label>
            </div>
          </div>

          <div class="firma-file-box">
            <label for="firma_imagen" class="form-label mb-1">Nueva imagen</label>
            <input
              type="file"
              class="form-control"
              name="firma_imagen"
              id="firma_imagen"
              accept="image/*"
            >
          </div>
        </div>

        <div class="firma-preview-box">
          <label class="form-label mb-1">Imagen actual</label>

          <div class="firma-preview-frame">
            <?php if (!empty($fila['firma_imagen_url'])): ?>
              <img
                src="../<?= htmlspecialchars($fila['firma_imagen_url']) ?>"
                alt="Firma Actual"
                class="firma-preview-img"
              >
            <?php else: ?>
              <span class="text-muted small">Sin firma</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="firma-card firma-card-datos">
      <div class="firma-card-header">
        <div>
          <h6 class="firma-card-title mb-0">
            <i class="fas fa-user-md me-1"></i> Datos de firma
          </h6>
          <small class="text-muted">Nombre, título, posición y líneas adicionales.</small>
        </div>
      </div>

      <div class="row align-items-end">
        <div class="col-md-5 mb-2">
          <label for="firma_nombre" class="form-label">Nombre en la Firma</label>
          <input
            type="text"
            class="form-control"
            name="firma_nombre"
            id="firma_nombre"
            maxlength="150"
            value="<?= htmlspecialchars($fila['firma_nombre'] ?? '') ?>"
            placeholder="Ej: Dra. Mariana Veliz"
          >
        </div>

        <div class="col-md-4 mb-2">
          <label for="firma_titulo" class="form-label">Título Profesional</label>
          <input
            type="text"
            class="form-control"
            name="firma_titulo"
            id="firma_titulo"
            maxlength="150"
            value="<?= htmlspecialchars($fila['firma_titulo'] ?? '') ?>"
            placeholder="Ej: Médico Veterinario"
          >
        </div>

        <div class="col-md-3 mb-2">
          <label for="firma_align" class="form-label">Posición</label>
          <select name="firma_align" id="firma_align" class="form-select select2">
            <option value="left" data-icon="fas fa-align-left" <?= ($fila['firma_align'] ?? 'center') === 'left' ? 'selected' : '' ?>>
              Izquierda
            </option>
            <option value="center" data-icon="fas fa-align-center" <?= ($fila['firma_align'] ?? 'center') === 'center' ? 'selected' : '' ?>>
              Centro
            </option>
            <option value="right" data-icon="fas fa-align-right" <?= ($fila['firma_align'] ?? 'center') === 'right' ? 'selected' : '' ?>>
              Derecha
            </option>
          </select>
        </div>
      </div>

      <div class="firma-subtitulos-box mt-1">
        <div class="firma-subtitulos-header">
          <label class="form-label mb-0">Subtítulos</label>

          <button type="button" id="agregar-subtitulo" class="btn btn-success btn-sm">
            <i class="fas fa-plus"></i> Agregar línea
          </button>
        </div>

        <div id="firma-subtitulos-container" class="firma-subtitulos-list">
          <?php foreach ($subtitulos as $sub): ?>
            <div class="input-group firma-subtitulo-row">
              <input
                type="text"
                name="firma_subtitulos[]"
                class="form-control"
                value="<?= htmlspecialchars($sub) ?>"
                placeholder="Ej: Diplomada en Imagenología"
              >
              <button type="button" class="btn btn-danger eliminar-subtitulo">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (empty($subtitulos)): ?>
          <small class="text-muted d-block firma-subtitulos-empty">
            Puedes agregar líneas como diplomados, especialidades o registros profesionales.
          </small>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="firma-extra-grid mt-3">
    <div class="firma-card firma-card-footer">
      <div class="firma-card-header firma-footer-header">
        <div>
          <h6 class="firma-card-title mb-0">
            <i class="fas fa-align-center me-1"></i> Pie de página
          </h6>
          <small class="text-muted">Texto opcional al final del informe.</small>
        </div>

        <div class="firma-footer-align-box">
          <label for="footer_align" class="form-label mb-1">Alineación</label>
          <select name="footer_align" id="footer_align" class="form-select select2">
            <option value="left" data-icon="fas fa-align-left" <?= ($fila['footer_align'] ?? 'center') === 'left' ? 'selected' : '' ?>>
              Izquierda
            </option>
            <option value="center" data-icon="fas fa-align-center" <?= ($fila['footer_align'] ?? 'center') === 'center' ? 'selected' : '' ?>>
              Centro
            </option>
            <option value="right" data-icon="fas fa-align-right" <?= ($fila['footer_align'] ?? 'center') === 'right' ? 'selected' : '' ?>>
              Derecha
            </option>
          </select>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12 mb-2">
          <label for="footer_texto" class="form-label">Texto</label>
          <textarea
            class="form-control firma-footer-textarea"
            name="footer_texto"
            id="footer_texto"
            rows="3"
            maxlength="500"
            placeholder="Ej: Este informe debe ser interpretado por un médico veterinario tratante."
          ><?= htmlspecialchars($fila['footer_texto'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="firma-card firma-card-fecha">
      <div class="firma-card-header firma-fecha-header">
        <div>
          <h6 class="firma-card-title mb-0">
            <i class="fas fa-calendar-alt me-1"></i> Fecha del informe
          </h6>
          <small class="text-muted">Formato, lugar y posición de la fecha.</small>
        </div>

        <div class="firma-fecha-switch-box firma-header-control-inline">
          <label class="form-label mb-0" for="mostrar_fecha">Mostrar</label>
          <div class="form-check form-switch mb-0">
            <input
              class="form-check-input"
              type="checkbox"
              name="mostrar_fecha"
              id="mostrar_fecha"
              value="1"
              <?= !empty($fila['mostrar_fecha']) ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="mostrar_fecha">Activar</label>
          </div>
        </div>
      </div>

      <div class="row align-items-end">
        <div class="col-md-5 mb-2">
          <label for="formato_fecha" class="form-label">Formato</label>
          <select name="formato_fecha" id="formato_fecha" class="form-control">
            <option value="{{day}} de {{month}} del {{year}}" <?= ($fila['formato_fecha'] ?? '') === '{{day}} de {{month}} del {{year}}' ? 'selected' : '' ?>>
              dd de MM del aaaa
            </option>
            <option value="{{day}}/{{month}}/{{year}}" <?= ($fila['formato_fecha'] ?? '') === '{{day}}/{{month}}/{{year}}' ? 'selected' : '' ?>>
              dd/MM/aaaa
            </option>
            <option value="{{month}} {{day}}, {{year}}" <?= ($fila['formato_fecha'] ?? '') === '{{month}} {{day}}, {{year}}' ? 'selected' : '' ?>>
              MM dd, aaaa
            </option>
            <option value="{{year}}-{{month}}-{{day}}" <?= ($fila['formato_fecha'] ?? '') === '{{year}}-{{month}}-{{day}}' ? 'selected' : '' ?>>
              aaaa-MM-dd
            </option>
          </select>
        </div>

        <div class="col-md-4 mb-2">
          <label for="lugar_fecha" class="form-label">Lugar</label>
          <input
            type="text"
            name="lugar_fecha"
            id="lugar_fecha"
            class="form-control"
            value="<?= htmlspecialchars($fila['lugar_fecha'] ?? '') ?>"
            placeholder="Ej: Santiago"
          >
        </div>

        <div class="col-md-3 mb-2">
          <label for="fecha_align" class="form-label">Posición</label>
          <select name="fecha_align" id="fecha_align" class="form-select select2">
            <option value="left" data-icon="fas fa-align-left" <?= ($fila['fecha_align'] ?? 'right') === 'left' ? 'selected' : '' ?>>
              Izquierda
            </option>
            <option value="center" data-icon="fas fa-align-center" <?= ($fila['fecha_align'] ?? 'right') === 'center' ? 'selected' : '' ?>>
              Centro
            </option>
            <option value="right" data-icon="fas fa-align-right" <?= ($fila['fecha_align'] ?? 'right') === 'right' ? 'selected' : '' ?>>
              Derecha
            </option>
          </select>
        </div>
      </div>
    
    </div>
  </div>
</div>