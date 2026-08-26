<?php
//admin/control_ia/control_ia.php
###########################################
require_once("../config.php");
credenciales('control_ia', 'listar');
###########################################

global $acceso_aplicaciones;

$puedeEliminar = in_array('eliminar', $acceso_aplicaciones['control_ia'] ?? []);
?>
<link rel="stylesheet" href="control_ia/css/control_ia.css?v=5">
<div id="control_ia" data-page-id="control_ia">
  <div class="control-ia-topbar mb-3">
    <h1 class="h3 control-ia-title"><strong>Control IA</strong></h1>

    <div class="btn-group control-ia-rangos" role="group" id="metricasRango">
      <button type="button" class="btn btn-outline-info" data-rango="hoy">Hoy</button>
      <button type="button" class="btn btn-outline-info" data-rango="semana">Semana</button>
      <button type="button" class="btn btn-outline-info" data-rango="mes">Mes</button>
      <button type="button" class="btn btn-info active" data-rango="todo">Todo</button>
    </div>

    <div></div>
  </div>

  <!-- CARD 1: métricas -->
  <div class="card mb-3">
    <div class="card-body py-3">
      <div class="row g-3">

        <div class="col-lg-5">
          <div class="metric-bubble metric-ia-card">
            <div class="metric-bubble-title">
              <i class="fas fa-robot"></i> IA / Tokens
            </div>

            <div id="m_ia_desglose" class="ia-breakdown"></div>

            <div class="ia-total-box mt-2">
              <div class="ia-total-item ia-total-item-inf">
                <small>Informes</small>
                <strong id="m_informes">-</strong>
              </div>
              <div class="ia-total-item ia-total-item-tok">
                <small>Total tokens IA</small>
                <strong id="m_tokens">-</strong>
              </div>
              <div class="ia-total-item ia-total-item-cost">
                <small>Costo IA</small>
                <strong id="m_costo_ia">-</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="metric-bubble metric-stt-card">
            <div class="metric-bubble-title">
              <i class="fas fa-microphone-alt"></i> Audios / Transcripción
            </div>

            <div id="m_stt_desglose" class="stt-breakdown"></div>

            <div class="stt-total-box mt-2">
              <div class="stt-total-item stt-total-item-aud">
                <small>Audios</small>
                <strong id="m_transcripciones">-</strong>
              </div>
              <div class="stt-total-item stt-total-item-min">
                <small>Minutos totales</small>
                <strong id="m_minutos_trans">-</strong>
              </div>
              <div class="stt-total-item stt-total-item-cost">
                <small>Costo audio</small>
                <strong id="m_costo_trans">-</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-2">
          <div class="metric-bubble metric-total-card">
            <div class="metric-bubble-title">
              <i class="fas fa-calculator"></i> Total
            </div>

            <div class="total-main-box">
              <small>Costo total</small>
              <strong id="m_costo_total">-</strong>
            </div>

            <div class="total-split-box mt-3">
              <div class="total-split-item total-split-ia">
                <div>
                  <small>IA</small>
                  <strong id="m_total_ia">-</strong>
                </div>
                <span id="m_total_ia_pct">-</span>
              </div>

              <div class="total-split-item total-split-audio">
                <div>
                  <small>Audio</small>
                  <strong id="m_total_audio">-</strong>
                </div>
                <span id="m_total_audio_pct">-</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- CARD 2: tabla -->
  <div class="card">
    <div class="card-header">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <button type="button" class="btn btn-outline-secondary" id="btnVerPrecios">
          <i style="width:18px;height:18px;" data-feather="dollar-sign"></i> Precios
        </button>
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="tablaControlIa" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
          <thead id="tablaControlIaHead"></thead>
          <tbody id="tablaControlIaBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/modal_detalle.php'; ?>
<?php require __DIR__ . '/partials/modal_precios.php'; ?>

<script>
  window.CONTROL_IA_PERMS = {
    eliminar: <?= $puedeEliminar ? 'true' : 'false' ?>
  };
</script>
<script src="control_ia/js/control_ia.js?v=14"></script>