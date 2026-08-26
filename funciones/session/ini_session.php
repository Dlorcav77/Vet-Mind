<?php

require_once(
    $_SERVER['DOCUMENT_ROOT']
    . "/funciones/conn/conn.php"
);

require_once(
    $_SERVER['DOCUMENT_ROOT']
    . "/funciones/session/funcionesSesion.php"
);

configurarErroresAplicacion();

iniciarSesionSegura();

exigirAutenticacion();

$usuario_id = $_SESSION['usuario_id'];
$codsede = $_SESSION['codsede'] ?? null;
$categorias = $_SESSION['categorias'] ?? [];
$perfil_id = $_SESSION['perfil_id'];
$root = $_SESSION['root'] ?? [];


$acceso_aplicaciones = acceso_aplicaciones(
    (int)$perfil_id
);

$_SESSION['acceso_aplicaciones'] =
    $acceso_aplicaciones;