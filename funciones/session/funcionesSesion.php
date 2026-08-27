<?php

require_once __DIR__ . '/csrf.php';

function configurarErroresAplicacion(
    bool $respuestaJson = false
): void {
    $entorno = strtolower(
        trim(
            (string)(
                getenv('APP_ENV')
                ?: ($_ENV['APP_ENV'] ?? '')
                ?: ($_SERVER['APP_ENV'] ?? '')
                ?: 'production'
            )
        )
    );

    $esDesarrollo = in_array(
        $entorno,
        ['development', 'dev', 'local'],
        true
    );

    error_reporting(E_ALL);

    ini_set(
        'log_errors',
        '1'
    );

    /*
     * En respuestas JSON nunca mostramos errores PHP,
     * porque un warning rompería la respuesta JSON.
     */
    $mostrarErrores =
        $esDesarrollo
        && !$respuestaJson;

    ini_set(
        'display_errors',
        $mostrarErrores ? '1' : '0'
    );

    ini_set(
        'display_startup_errors',
        $mostrarErrores ? '1' : '0'
    );
}

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $protocoloProxy = strtolower(
        $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
    );

    $conexionSegura =
        $protocoloProxy === 'https'
        || (
            !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
        );

    /*
     * Nombre propio para evitar conflictos con cookies
     * PHPSESSID antiguas o de otras aplicaciones.
     */
    session_name('vetmind_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $conexionSegura,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function sesionAutenticada(): bool
{
    return !empty($_SESSION['usuario_id'])
        && !empty($_SESSION['perfil_id']);
}

function solicitudAjaxSesion(): bool
{
    return strtolower(
        $_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''
    ) === 'xmlhttprequest';
}


function cerrarSesionActual(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $parametros = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $parametros['path'],
            'domain'   => $parametros['domain'],
            'secure'   => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => 'Lax'
        ]);
    }

    session_destroy();
}


function exigirAutenticacion(
    string $redirectUrl = '../index.php'
): void
{
    $inactivo = 2400; // 40 minutos

    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $perfilId  = $_SESSION['perfil_id'] ?? null;

    $ultimoUso = (int) (
        $_SESSION['ultimo_uso']
        ?? 0
    );

    $sesionValida =
        !empty($usuarioId)
        && !empty($perfilId);

    $sesionExpirada =
        $ultimoUso > 0
        && (time() - $ultimoUso) > $inactivo;

    if (!$sesionValida || $sesionExpirada) {

        cerrarSesionActual();

        if (solicitudAjaxSesion()) {

            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');

            echo json_encode([
                'status'       => 'expired',
                'message'      => 'La sesión expiró.',
                'redirect_url' => $redirectUrl
            ]);

            exit;
        }

        header(
            'Location: '
            . $redirectUrl
        );

        exit;
    }

    $_SESSION['ultimo_uso'] = time();
}

function credenciales($modulo, $accion = 'listar')
{
    $accesos = $_SESSION['acceso_aplicaciones'] ?? [];

    $excepciones = [
        'inicio',
        'perfil',
        'password',
        'lisTickets'
    ];

    if (in_array($modulo, $excepciones, true)) {
        return;
    }

    if (
        !isset($accesos[$modulo])
        || !in_array($accion, $accesos[$modulo], true)
    ) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
        Swal.fire({
            icon: 'error',
            title: 'Acceso denegado',
            text: 'No tienes permiso para acceder a esta sección.',
            confirmButtonText: 'Volver',
            allowOutsideClick: false
        }).then(() => {
            window.history.back();
        });
        </script>";

        exit;
    }
}


function acceso_aplicaciones($perfil_id)
{
    $mysqli = conn();

    $accesos = [];

    $sql = "SELECT
                ma.modulo,
                mp.accion
            FROM perfiles_permisos pp
            INNER JOIN modulo_permisos mp
                ON mp.id = pp.permiso_id
            INNER JOIN modulos_aplicaciones ma
                ON ma.id = mp.modulo_id
            WHERE pp.perfil_id = ?";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param("i", $perfil_id);

    $stmt->execute();

    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {

        $modulo = $row['modulo'];
        $accion = $row['accion'];

        if (!isset($accesos[$modulo])) {
            $accesos[$modulo] = [];
        }

        $accesos[$modulo][] = $accion;
    }

    $stmt->close();

    return $accesos;
}