<?php
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams["lifetime"],
    'path' => '/',
    'domain' => '.' . $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/conn/conn.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/funciones/session/funcionesSesion.php");


$usuario_id = $_SESSION['usuario_id'] ?? null;
$codsede    = $_SESSION['codsede'] ?? null;
$categorias = $_SESSION['categorias'] ?? [];
$perfil_id   = $_SESSION['perfil_id'] ?? null;
$root       = $_SESSION['root'] ?? [];

fin_session($codsede); 

$acceso_aplicaciones = acceso_aplicaciones($perfil_id);
$_SESSION['acceso_aplicaciones'] = $acceso_aplicaciones;

