<?php

function conn()
{
    ##################### CONEXION #######################################

    $host = 'db';
    $user =  'vetmind_dev_user';
    $pass = '*t8@%6Q3TG4--Dalv89--rMhW87moo';
    $name = 'vetmind_dev_db';

    $mysqli = new mysqli($host, $user, $pass, $name);

    // Configurar la conexión para usar UTF-8
    $mysqli->set_charset("utf8");

    if ($mysqli->connect_error) {
        die('Error de Conexión (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
    }

    return $mysqli;
}

?>
