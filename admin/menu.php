<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/helpers.php");

$modulos = obtener_modulos_con_listar($perfil_id);

$logo = '';
?>
<body data-theme="default" data-layout="fluid" data-sidebar-position="left" data-sidebar-layout="default">
<div class="wrapper">
  <nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
      <a
        class="sidebar-brand text-center"
        href="/"
        aria-label="VetMind - Ir al inicio"
      >
        <div
          class="vetmind-brand vetmind-brand--sidebar"
          aria-hidden="true"
        >
          <img
            src="/assets/img/branding/logo-vetmind.svg?v=1"
            alt=""
            class="vetmind-brand__icon"
            width="520"
            height="420"
            loading="eager"
            decoding="async"
          >

          <div class="vetmind-brand__name">
            <span
              class="vetmind-brand__part vetmind-brand__vet"
            >VET</span><span
              class="vetmind-brand__part vetmind-brand__mind"
            >M<span class="vetmind-brand__i"><span class="vetmind-brand__i-letter">I</span><span class="vetmind-brand__heart">♥</span></span>ND</span>
          </div>
        </div>
      </a>
      <ul class="sidebar-nav">
        <li class="sidebar-header">Menu</li>
        <li class="sidebar-item <?php echo $menu_id === 'menu-inicio' ? 'active' : ''; ?>" id="menu-inicio">
          <a class="sidebar-link ajax-link" href="inicio/inicio.php" data-appname="inicio.php">
            <i class="fas fa-newspaper align-middle"></i>
            <span class="align-middle">Inicio</span>
          </a>
        </li>
        <?php
        foreach ($modulos as $seccion => $items): ?>
          <li class="sidebar-header"><?php echo $seccion; ?></li>
        
          <?php foreach ($items as $modulo): ?>
            <li class="sidebar-item" id="menu-<?php echo $modulo['modulo']; ?>">
              <a class="sidebar-link ajax-link" href="<?php echo $modulo['modulo'] . '/' . $modulo['archivo_base']; ?>" data-appname="<?php echo $modulo['archivo_base']; ?>">
                <i class="<?php echo $modulo['icono']; ?> align-middle"></i>
                <span class="align-middle"><?php echo $modulo['nombre']; ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>
