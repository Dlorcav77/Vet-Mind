<?php

require_once __DIR__ . '/funciones/session/funcionesSesion.php';

iniciarSesionSegura();

cerrarSesionActual();

header('Location: index.php');
exit;