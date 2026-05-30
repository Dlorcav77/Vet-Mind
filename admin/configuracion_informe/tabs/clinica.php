<?php
// admin/configuracion_informe/tabs/clinica.php

/** @var array $clinica_config */
?>

<div class="tab-pane fade" id="layout-config" role="tabpanel">
  <div class="layout-config-bloque" data-layout-config="clinica">
    <h5 class="fw-bold my-4">
      <i class="fas fa-file-medical-alt me-2"></i> Datos institucionales Clínica
    </h5>

    <p class="text-muted">
      Estos datos se mostrarán en el encabezado del layout Clínica.
    </p>

    <div class="row mb-3">
      <div class="col-md-12 mb-2">
        <label for="clinica_institucion_nombre" class="form-label">Nombre de la institución</label>
        <input
          type="text"
          class="form-control"
          name="layout_config[clinica][institucion_nombre]"
          id="clinica_institucion_nombre"
          maxlength="150"
          value="<?= htmlspecialchars($clinica_config['institucion_nombre'] ?? '') ?>"
          placeholder="Ej: Instituto Neurológico Veterinario"
        >
      </div>

      <div class="col-md-12 mb-2">
        <label for="clinica_direccion" class="form-label">Dirección</label>
        <input
          type="text"
          class="form-control"
          name="layout_config[clinica][direccion]"
          id="clinica_direccion"
          maxlength="200"
          value="<?= htmlspecialchars($clinica_config['direccion'] ?? '') ?>"
          placeholder="Ej: Pepe Vila #25, La Reina, Santiago, Chile"
        >
      </div>

      <div class="col-md-6 mb-2">
        <label for="clinica_telefonos" class="form-label">Teléfonos</label>
        <input
          type="text"
          class="form-control"
          name="layout_config[clinica][telefonos]"
          id="clinica_telefonos"
          maxlength="150"
          value="<?= htmlspecialchars($clinica_config['telefonos'] ?? '') ?>"
          placeholder="Ej: 22 356 39 89 - 22 356 39 90"
        >
      </div>

      <div class="col-md-6 mb-2">
        <label for="clinica_correo" class="form-label">Correo</label>
        <input
          type="email"
          class="form-control"
          name="layout_config[clinica][correo]"
          id="clinica_correo"
          maxlength="150"
          value="<?= htmlspecialchars($clinica_config['correo'] ?? '') ?>"
          placeholder="Ej: contacto@institutoneurologico.cl"
        >
      </div>

      <div class="col-md-6 mb-2">
        <label for="clinica_web" class="form-label">Web</label>
        <input
          type="text"
          class="form-control"
          name="layout_config[clinica][web]"
          id="clinica_web"
          maxlength="150"
          value="<?= htmlspecialchars($clinica_config['web'] ?? '') ?>"
          placeholder="Ej: clinica.cl"
        >
      </div>
    </div>
  </div>
</div>