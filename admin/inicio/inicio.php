<?php
require_once("../config.php");

$mysqli = conn();
?>
<div id="inicio" data-page-id="inicio">
  <div class="container py-4" style="background: linear-gradient(135deg, <?= $color_primario ?? '#3498db' ?>31 0%, #fff 90%); min-height: 100vh;">

    <?php include("componentes/inicio_accesos.php"); ?>

    <div id="bloque-estadisticas">
      <?php include("componentes/inicio_estadisticas.php"); ?>
    </div>

    <div id="bloque-grafico">
      <?php include("componentes/inicio_grafico.php"); ?>
    </div>

  </div>
</div>
