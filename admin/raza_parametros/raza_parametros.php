<?php
require_once("../config.php");

$mysqli = conn();
?>
<div id="raza_parametros" data-page-id="raza_parametros">
  <h1 class="h3 mb-3"><strong>Parámetros del sistema</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">




          <!-- Pestañas -->
          <ul class="nav nav-tabs" id="tabs_parametros" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-especies-tab" data-bs-toggle="tab" data-bs-target="#tab-especies" type="button" role="tab">Razas y Especies</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-organos-tab" data-bs-toggle="tab" data-bs-target="#tab-organos" type="button" role="tab">Órganos</button>
            </li>
          </ul>

          <!-- Contenido -->
          <div class="tab-content mt-4" id="tabs_parametrosContent">
            <div class="tab-pane fade show active" id="tab-especies" role="tabpanel">
              <?php include("componentes/especies_razas.php"); ?>
            </div>
            <div class="tab-pane fade" id="tab-organos" role="tabpanel">
              <?php include("componentes/razas_parametros.php"); ?>
            </div>
          </div>




        </div>
      </div>
    </div>
  </div>
</div>

