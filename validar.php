<?php

require_once __DIR__ . '/funciones/conn/conn.php';
require_once __DIR__ . '/funciones/session/funcionesSesion.php';
require_once __DIR__ . '/funciones/session/csrf.php';

iniciarSesionSegura();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {

    http_response_code(405);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status'  => 'error',
        'message' => 'Método no permitido.'
    ]);

    exit;
}

validarTokenCsrf();

$ahora = time();

$intentos = $_SESSION['login_intentos'] ?? [
    'cantidad' => 0,
    'desde'    => $ahora
];

// Después de 5 minutos se reinicia el contador
if ($ahora - (int)$intentos['desde'] > 300) {

    $intentos = [
        'cantidad' => 0,
        'desde'    => $ahora
    ];

    $_SESSION['login_intentos'] = $intentos;
}

// Máximo 8 intentos dentro de la ventana de 5 minutos
if ((int)$intentos['cantidad'] >= 5) {

    echo json_encode([
        'status'  => 'error',
        'message' => 'Demasiados intentos. Espera unos minutos antes de volver a intentar.'
    ]);

    exit;
}

$mysqli = conn();

$email   = trim($_POST['email'] ?? '');
$passIng = $_POST['pass'] ?? '';


// Verificar si el usuario existe por email
$sel = "SELECT * 
        FROM usuarios 
        WHERE email = ? 
          AND deleted_at IS NULL";

$stmt = $mysqli->prepare($sel);
$stmt->bind_param("s", $email);
$stmt->execute();

$res = $stmt->get_result();

if ($res->num_rows == 0) {

    $intentos['cantidad'] = (int)$intentos['cantidad'] + 1;

    $_SESSION['login_intentos'] = $intentos;

    usleep(350000);

    echo json_encode([
        'status'  => 'error',
        'message' => 'El correo ingresado no está registrado.'
    ]);

    exit;
}

$fila = $res->fetch_assoc();

$id      = (int)$fila['id'];
$estado  = $fila['estado'];
$passSis = $fila['password'];


// Verificar estado del usuario
if ($estado !== 'activo') {

    echo json_encode([
        'status'  => 'error',
        'message' => 'El usuario no está activo.'
    ]);

    exit;
}


// Verificar perfil activo
$fecha_actual = date('Y-m-d');

$selP = "SELECT *
         FROM usuarios_perfil
         WHERE usuario_id = ?
           AND estado = 'activo'
           AND fecha_inicio <= ?
           AND (fecha_termino IS NULL OR fecha_termino >= ?)
           AND deleted_at IS NULL
         LIMIT 1";

$stmtP = $mysqli->prepare($selP);

$stmtP->bind_param(
    "iss",
    $id,
    $fecha_actual,
    $fecha_actual
);

$stmtP->execute();

$resP = $stmtP->get_result();

if ($resP->num_rows == 0) {

    echo json_encode([
        'status'  => 'error',
        'message' => 'El usuario no tiene un perfil activo.'
    ]);

    exit;
}

$perfil = $resP->fetch_assoc();

$perfil_id = (int)$perfil['perfiles_id'];


if (!password_verify($passIng, $passSis)) {

    $intentos['cantidad'] = (int)$intentos['cantidad'] + 1;

    $_SESSION['login_intentos'] = $intentos;

    usleep(350000);

    echo json_encode([
        'status'  => 'error',
        'message' => 'Contraseña incorrecta.'
    ]);

    exit;
}

$_SESSION['login_intentos'] = [
    'cantidad' => 0,
    'desde'    => $ahora
];

// Generar un nuevo ID de sesión después de autenticar
session_regenerate_id(true);


// Asignar variables de sesión
$_SESSION['usuario_id']     = $id;
$_SESSION['usuario_email']  = $email;
$_SESSION['perfil_id']      = $perfil_id;
$_SESSION['ultimo_uso']     = time();


// Registrar login en log
$descripcion_movimiento =
    "Autenticación exitosa para usuario ID: $id";

$aplicacion = "validar.php";
$hora_actual = date('H:i:s');

$stmt_log = $mysqli->prepare(
    "INSERT INTO log (
        id_usuario,
        descripcion,
        aplicacion,
        fecha,
        hora
    ) VALUES (?, ?, ?, ?, ?)"
);

$stmt_log->bind_param(
    "issss",
    $id,
    $descripcion_movimiento,
    $aplicacion,
    $fecha_actual,
    $hora_actual
);

$stmt_log->execute();


echo json_encode([
    'status'       => 'success',
    'redirect_url' => 'admin/index.php'
]);

exit;