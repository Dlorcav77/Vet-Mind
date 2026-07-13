<?php
//admin/control_ia/partials/modal_precios.php
?>
<div class="modal fade" id="modalPreciosIa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Precios IA / Transcripción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div class="btn-group btn-group-sm" role="group" id="preciosTipo">
            <button type="button" class="btn btn-info active" data-tipo="ia">IA / Tokens</button>
            <button type="button" class="btn btn-outline-info" data-tipo="stt">Transcripción / Minuto</button>
          </div>

          <button type="button" class="btn btn-sm btn-primary" id="btnNuevoPrecio">
            <i style="width:16px;height:16px;" data-feather="plus"></i> Agregar
          </button>
        </div>

        <div id="preciosLoading" class="text-center py-3" style="display:none">
          <div class="spinner-border text-info" role="status"></div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead id="preciosThead"></thead>
            <tbody id="preciosTbody"></tbody>
          </table>
        </div>

        <div id="precioForm" class="border rounded p-3 mt-2" style="display:none">
          <input type="hidden" id="precio_id" value="">

          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label" id="precio_model_label">Modelo</label>
              <input type="text" class="form-control form-control-sm" id="precio_model" placeholder="gpt-5.4">
            </div>

            <div class="col-md-2 precio-ia-field">
              <label class="form-label">In</label>
              <input type="number" step="0.0001" class="form-control form-control-sm" id="precio_in">
            </div>

            <div class="col-md-2 precio-ia-field">
              <label class="form-label">Out</label>
              <input type="number" step="0.0001" class="form-control form-control-sm" id="precio_out">
            </div>

            <div class="col-md-2 precio-stt-field" style="display:none">
              <label class="form-label">Precio min.</label>
              <input type="number" step="0.000001" class="form-control form-control-sm" id="precio_min">
            </div>

            <div class="col-md-2">
              <label class="form-label">Vigente desde</label>
              <input type="date" class="form-control form-control-sm" id="precio_vigente">
            </div>

            <div class="col-md-2">
              <label class="form-label">Activo</label>
              <select class="form-select form-select-sm" id="precio_activo">
                <option value="1">Sí</option>
                <option value="0">No</option>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-secondary" id="btnCancelarPrecio">Cancelar</button>
            <button type="button" class="btn btn-sm btn-primary" id="btnGuardarPrecio">Guardar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>