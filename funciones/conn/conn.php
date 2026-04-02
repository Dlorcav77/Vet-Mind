<?php

function conn()
{
    ##################### CONEXION #######################################

    $host = getenv('DB_HOST') ?: 'db';
    $user = getenv('DB_USERNAME') ?: '';
    $pass = getenv('DB_PASSWORD') ?: '';
    $name = getenv('DB_DATABASE') ?: '';

    $mysqli = new mysqli($host, $user, $pass, $name);

    if ($mysqli->connect_error) {
        die('Error de Conexión (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
    }

    // Configurar la conexión para usar UTF-8
    $mysqli->set_charset("utf8");

    return $mysqli;
}
?>