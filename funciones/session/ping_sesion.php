<?php
// funciones/session/ping_sesion.php
declare(strict_types=1);

// 1) Arrancar sesión sin pasar por ini_session (evitar fin_session con JS)
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Reutilizamos misma política de cookie si quieres (opcional)
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path'     => '/',
        'domain'   => '.' . ($_SERVER['HTTP_HOST'] ?? ''),
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2) No cachear la respuesta
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

// 3) Reglas de expiración coherentes con fin_session()
$INACTIVO = 2400; // = 12 días y algo, mismo valor que usas en fin_session()

/**
 * Devuelve:
 *  - ['ok'] si la sesión es válida y vigente
 *  - ['expired'] si venció por inactividad
 *  - ['no_session'] si no existe sesión
 */
function estado_sesion(int $inactivo): array {
    // ¿Existe usuario logueado?
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    if (!$usuarioId) {
        return ['no_session' => true];
    }

    // ¿Hay marca de tiempo y está vigente?
    $last = $_SESSION['time'] ?? null;
    if (!$last) {
        // si no hay marca, la creamos ahora y consideramos ok
        $_SESSION['time'] = time();
        return ['ok' => true];
    }

    $vida = time() - (int)$last;
    if ($vida > $inactivo) {
        return ['expired' => true];
    }

    // Refrescamos la marca como hace fin_session()
    $_SESSION['time'] = time();
    return ['ok' => true];
}

$status = estado_sesion($INACTIVO);

// 4) (Opcional) Verificar conectividad a BD solo si la sesión está ok
//     Así puedes diferenciar “tu sesión está bien” vs “la BD está caída”.
//     Si quieres, descomenta y ajusta:
$withDbCheck = true; // <-- pon false si no quieres chequear BD aquí
$dbOk = null;
$dbErr = null;

if (isset($status['ok']) && $status['ok'] === true && $withDbCheck) {
    // Evitar arrastrar fin_session(); incluye solo la conexión cruda
    require_once __DIR__ . '/../conn/conn.php';
    try {
        $mysqli = conn();
        // ping básico
        if (method_exists($mysqli, 'ping')) {
            $dbOk = $mysqli->ping();
        } else {
            $dbOk = (bool)$mysqli;
        }
    } catch (Throwable $e) {
        $dbOk = false;
        $dbErr = $e->getMessage();
    }
}

// 5) Responder según casos
if (isset($status['ok']) && $status['ok'] === true) {
    http_response_code(200);
    echo json_encode([
        'status'   => 'ok',
        'user_id'  => $_SESSION['usuario_id'] ?? null,
        'db_ok'    => $dbOk,            // null si no chequeas BD
        'db_error' => $dbErr            // null si no hay error o no chequeas
    ]);
    exit;
}

if (isset($status['expired']) && $status['expired'] === true) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'expired',
        'message' => 'sesion_expirada'
    ]);
    exit;
}

http_response_code(401);
echo json_encode([
    'status'  => 'no_session',
    'message' => 'sesion_inexistente'
]);
exit;
