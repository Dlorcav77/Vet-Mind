<?php

require_once __DIR__ . '/funciones/session/funcionesSesion.php';

iniciarSesionSegura();

cerrarSesionActual();

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
header('Pragma: no-cache');
header('Expires: 0');

header('Location: index.php', true, 303);
exit;