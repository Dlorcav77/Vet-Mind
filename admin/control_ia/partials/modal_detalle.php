<?php
//admin/control_ia/partials/modal_detalle.php
?>
<style>
  .dpill {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 12px; border-radius:999px;
    background:#fff; border:1px solid #e2e8f0;
    font-size:13px; line-height:1;
  }
  .dpill i { font-size:12px; opacity:.8; }
  .dpill-k { color:#94a3b8; font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
  .dpill-v { font-weight:700; color:#1e293b; }
  .dpill-provider { border-color:#bae6fd; background:#f0f9ff; }
  .dpill-provider i { color:#0284c7; }
  .dpill-model    { border-color:#ddd6fe; background:#f5f3ff; }
  .dpill-model i  { color:#7c3aed; }
  .dpill-plantilla{ border-color:#fde68a; background:#fffbeb; }
  .dpill-plantilla i { color:#d97706; }
  .dpill-tokens   { border-color:#a7f3d0; background:#ecfdf5; }
  .dpill-tokens i { color:#059669; }
  .dpill-cost     { border-color:#fecaca; background:#fef2f2; }
  .dpill-cost i   { color:#dc2626; }
  .dpill-rid i    { color:#64748b; }
  .dpill-date i   { color:#64748b; }

  .dcard { border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
  .dcard-head {
    padding:8px 14px; font-size:12px; font-weight:700;
    text-transform:uppercase; letter-spacing:.03em;
    display:flex; align-items:center; gap:8px;
  }
  .dcard-head i { font-size:13px; }
  .dcard-body { margin:0; padding:14px; font-size:13px; line-height:1.5; }
  .dcard-trans .dcard-head { background:#f0f9ff; color:#075985; }
  .dcard-trans .dcard-body { white-space:pre-wrap; background:#fff; font-family:inherit; color:#334155; }
  .dcard-res .dcard-head { background:#ecfdf5; color:#166534; }
  .dcard-res .dcard-body { background:#fff; }
</style>
<div class="modal fade" id="modalDetalleIa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle operación IA</h5>
        <div class="ms-auto d-flex align-items-center gap-2">
          <select id="detalleSelector" class="form-select form-select-sm" style="width:auto">
            <option value="informe">Informe</option>
            <option value="revision">Revisión</option>
          </select>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
      </div>
      <div class="modal-body">
        <div id="detalleLoading" class="text-center py-4" style="display:none">
          <div class="spinner-border text-info" role="status"></div>
        </div>

        <div id="detalleContenido" style="display:none">
          <div class="card border-0 shadow-sm mb-3" style="background:#f8fafc">
            <div class="card-body py-3">
              <div class="d-flex flex-wrap gap-2 justify-content-center">
                <span class="dpill dpill-provider">
                  <i class="fas fa-microchip"></i>
                  <span class="dpill-k">Proveedor</span><span id="d_provider" class="dpill-v">-</span>
                </span>
                <span class="dpill dpill-model">
                  <i class="fas fa-robot"></i>
                  <span class="dpill-k">Modelo</span><span id="d_model" class="dpill-v">-</span>
                </span>
                <span class="dpill dpill-plantilla">
                  <i class="fas fa-file-alt"></i>
                  <span class="dpill-k">Plantilla</span><span id="d_plantilla" class="dpill-v">-</span>
                </span>
                <span class="dpill dpill-tokens">
                  <i class="fas fa-coins"></i>
                  <span class="dpill-k">Tokens</span><span id="d_tokens" class="dpill-v">-</span>
                </span>
                <span class="dpill dpill-cost">
                  <i class="fas fa-dollar-sign"></i>
                  <span class="dpill-k">Costo USD</span><span id="d_cost" class="dpill-v">-</span>
                </span>
              </div>
              <hr class="my-3">
              <div class="d-flex flex-wrap gap-2 justify-content-center">
                <span class="dpill dpill-rid">
                  <i class="fas fa-hashtag"></i>
                  <span class="dpill-k">RID</span><span id="d_rid" class="dpill-v" style="font-family:monospace">-</span>
                </span>
                <span class="dpill dpill-date">
                  <i class="fas fa-clock"></i>
                  <span class="dpill-k">Fecha IA</span><span id="d_datetime" class="dpill-v">-</span>
                </span>
                <span class="dpill dpill-date">
                  <i class="fas fa-calendar-check"></i>
                  <span class="dpill-k">Creado</span><span id="d_created" class="dpill-v">-</span>
                </span>
              </div>
            </div>
          </div>

          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_detalles" type="button">Detalles</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_final" type="button">Resultado</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_input" type="button">Input</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_prompt" type="button">Prompt</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_system" type="button">System</button></li>
          </ul>
          <div class="tab-content border border-top-0 p-3" style="max-height:45vh;overflow:auto">
            <div class="tab-pane fade show active" id="tab_detalles">
              <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-info" id="btnCopiarDetalles">
                  <i class="fas fa-copy"></i> Copiar todo
                </button>
              </div>

              <div class="dcard dcard-trans mb-3">
                <div class="dcard-head">
                  <i class="fas fa-microphone-alt"></i> Transcripción (dictado enviado)
                </div>
                <pre id="d_det_transcripcion" class="dcard-body"></pre>
              </div>

              <div class="dcard dcard-res">
                <div class="dcard-head">
                  <i class="fas fa-file-medical"></i> Resultado (informe generado)
                </div>
                <div id="d_det_resultado" class="dcard-body"></div>
              </div>
            </div>

            <div class="tab-pane fade" id="tab_final">
              <div id="d_final_render"></div>
              <hr>
              <small class="text-muted">HTML crudo:</small>
              <pre id="d_final_raw" style="white-space:pre-wrap;font-size:11px;background:#f8fafc;padding:8px;border-radius:6px"></pre>
            </div>
            <div class="tab-pane fade" id="tab_input">
              <pre id="d_input" style="white-space:pre-wrap;font-size:11px;background:#f8fafc;padding:8px;border-radius:6px"></pre>
            </div>
            <div class="tab-pane fade" id="tab_prompt">
              <pre id="d_prompt" style="white-space:pre-wrap;font-size:11px;background:#f8fafc;padding:8px;border-radius:6px"></pre>
            </div>
            <div class="tab-pane fade" id="tab_system">
              <pre id="d_system" style="white-space:pre-wrap;font-size:11px;background:#f8fafc;padding:8px;border-radius:6px"></pre>
            </div>
          </div>
        </div>

        <div id="detalleVacio" class="text-center text-muted py-4" style="display:none">
          No hay datos para esta operación.
        </div>
      </div>
    </div>
  </div>
</div>