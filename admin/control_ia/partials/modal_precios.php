<?php
//admin/control_ia/partials/modal_precios.php
?>
<div class="modal fade" id="modalPreciosIa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Precios por modelo (USD por 1M tokens)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-end mb-2">
          <button type="button" class="btn btn-sm btn-primary" id="btnNuevoPrecio">
            <i style="width:16px;height:16px;" data-feather="plus"></i> Agregar
          </button>
        </div>

        <div id="preciosLoading" class="text-center py-3" style="display:none">
          <div class="spinner-border text-info" role="status"></div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead>
              <tr>
                <th>Modelo</th>
                <th width="110">In</th>
                <th width="110">Out</th>
                <th width="110">Vigente</th>
                <th width="70">Activo</th>
                <th width="110">Acciones</th>
              </tr>
            </thead>
            <tbody id="preciosTbody"></tbody>
          </table>
        </div>

        <!-- Form inline agregar/editar -->
        <div id="precioForm" class="border rounded p-3 mt-2" style="display:none">
          <input type="hidden" id="precio_id" value="">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control form-control-sm" id="precio_model" placeholder="gpt-5.4">
            </div>
            <div class="col-md-2">
              <label class="form-label">In</label>
              <input type="number" step="0.0001" class="form-control form-control-sm" id="precio_in">
            </div>
            <div class="col-md-2">
              <label class="form-label">Out</label>
              <input type="number" step="0.0001" class="form-control form-control-sm" id="precio_out">
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