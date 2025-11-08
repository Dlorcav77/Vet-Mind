<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/helpers.php");

$modulos = obtener_modulos_con_listar($perfil_id);

$logo = '';
?>
<style>
  /* Contenedor del logo */
.brand-card{
  --radius: 18px;
  --pad: 12px;

  width: 130px;                 /* ajusta si quieres */
  height: 130px;
  padding: var(--pad);
  margin: -10px auto -30px;
  border-radius: var(--radius);
  background: linear-gradient(160deg, #0b2b3a 0%, #0f3f56 100%);
  border: 1px solid rgba(255,255,255,.06);
  box-shadow: 0 8px 24px rgba(0,0,0,.25);
}

/* La imagen siempre “contenida”, sin estirarse */
.brand-logo{
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  image-rendering: auto;        /* evita pixelado raro */
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.20)); /* sombra sutil del ícono */
}

/* Tema claro/oscuro automático */
@media (prefers-color-scheme: light){
  .brand-card{
    background: linear-gradient(160deg, #eaf6f3 0%, #dff1ee 100%);
    border-color: rgba(0,0,0,.06);
    box-shadow: 0 8px 20px rgba(34, 97, 84, .12);
  }
}

</style>
<body data-theme="default" data-layout="fluid" data-sidebar-position="left" data-sidebar-layout="default">
<div class="wrapper">
  <nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
      <a class="sidebar-brand text-center" href="/">
        <div class="brand-card">
          <picture>
            <!-- Si tienes SVG, úsalo primero -->
            <source srcset="/assets/img/photos/logo3.1.png" type="image/svg+xml">
            <!-- PNG normal + @2x para pantallas retina -->
            <img 
              src="/assets/img/logo/vetmind.png"
              srcset="/assets/img/logo/vetmind.png 1x, /assets/img/logo/vetmind@2x.png 2x"
              alt="VetMind"
              class="brand-logo"
              loading="eager"
              decoding="async">
          </picture>
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
