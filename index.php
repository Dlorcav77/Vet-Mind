<?php

require_once __DIR__ . '/funciones/session/funcionesSesion.php';
require_once __DIR__ . '/funciones/session/csrf.php';

iniciarSesionSegura();

/*
 * El formulario de login contiene un token CSRF ligado
 * a la sesión actual. Nunca debe reutilizarse desde caché.
 */
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
header('Pragma: no-cache');
header('Expires: 0');

if (sesionAutenticada()) {
    header('Location: admin/index.php');
    exit;
}

$csrf = tokenCsrf();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="icon"
        type="image/svg+xml"
        href="assets/img/branding/logo-vetmind.svg"
    >

    <title>VetMind - Iniciar Sesión</title>

    <link
        href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="assets/css/branding.css?v=4"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --vetmind-dark: #184c53;
            --vetmind-primary: #39777a;
            --vetmind-primary-hover: #2b6267;
            --vetmind-light: #8fbab4;
            --vetmind-background: #f4f7f6;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            background: var(--vetmind-background);
            font-family:
                "Segoe UI",
                Arial,
                sans-serif;
        }

        .login-layout {
            display: grid;
            grid-template-columns:
                minmax(0, 7fr)
                minmax(360px, 3fr);
            min-height: 100vh;
            min-height: 100dvh;
        }

        .decorative-side {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
            background:
                radial-gradient(
                    ellipse at 50% 50%,
                    rgba(248, 251, 249, 0.92) 0%,
                    rgba(232, 242, 238, 0.78) 24%,
                    rgba(194, 217, 211, 0.34) 52%,
                    transparent 72%
                ),
                radial-gradient(
                    circle at 2% 4%,
                    rgba(255, 255, 255, 0.52) 0%,
                    transparent 40%
                ),
                radial-gradient(
                    circle at 100% 100%,
                    rgba(24, 76, 83, 0.30) 0%,
                    transparent 48%
                ),
                linear-gradient(
                    135deg,
                    #d0e1da 0%,
                    #90b3ae 50%,
                    #397477 100%
                );
            isolation: isolate;
        }

        /*
         * Líneas decorativas creadas con CSS:
         * se mantienen nítidas sin importar el tamaño de la pantalla.
         */
        .decorative-side::before,
        .decorative-side::after {
            position: absolute;
            z-index: 0;
            width: min(70vw, 900px);
            aspect-ratio: 1;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 42% 58% 54% 46%;
            content: "";
            pointer-events: none;
        }

        .decorative-side::before {
            top: -58%;
            left: -48%;
            box-shadow:
                0 0 0 32px rgba(255, 255, 255, 0.09),
                0 0 0 68px rgba(255, 255, 255, 0.06),
                0 0 0 108px rgba(255, 255, 255, 0.04);
            transform: rotate(-18deg);
        }

        .decorative-side::after {
            right: -50%;
            bottom: -62%;
            border-radius: 58% 42% 47% 53%;
            box-shadow:
                0 0 0 34px rgba(255, 255, 255, 0.09),
                0 0 0 72px rgba(255, 255, 255, 0.06),
                0 0 0 114px rgba(255, 255, 255, 0.04);
            transform: rotate(22deg);
        }

        .decorative-content {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: 48px;
            text-align: center;
        }

        .login-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh;
            padding: 24px;
            background:
                radial-gradient(
                    circle at 20% 15%,
                    rgba(143, 186, 180, 0.12),
                    transparent 36%
                ),
                var(--vetmind-background);
        }

        .login-card {
            width: min(100%, 390px);
            overflow: hidden;
            border: 1px solid rgba(24, 76, 83, 0.10);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow:
                0 18px 50px
                rgba(20, 59, 64, 0.20);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .login-card .card-body {
            padding: 32px 36px 28px;
        }

        .login-title {
            margin: 0 0 24px;
            color: #294f54;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
        }

        .form-group label {
            margin-bottom: 6px;
            color: #294f54;
            font-size: 15px;
            font-weight: 600;
        }

        .form-control {
            height: 46px;
            border: 1px solid #ccd9d7;
            border-radius: 9px;
            color: #294f54;
            background-color: #ffffff;
        }

        .form-control:focus {
            border-color: var(--vetmind-primary);
            box-shadow:
                0 0 0 0.2rem
                rgba(57, 119, 122, 0.16);
        }

        .btn-vetmind {
            min-height: 46px;
            border-color: var(--vetmind-primary);
            border-radius: 9px;
            color: #ffffff;
            background-color: var(--vetmind-primary);
            font-weight: 600;
            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .btn-vetmind:hover,
        .btn-vetmind:focus {
            border-color: var(--vetmind-primary-hover);
            color: #ffffff;
            background-color: var(--vetmind-primary-hover);
        }

        .btn-vetmind:active {
            transform: translateY(1px);
        }

        .btn-vetmind:disabled {
            cursor: not-allowed;
            opacity: 0.70;
        }

        #processingMessage {
            color: #587276;
        }

        #processingMessage .spinner-border {
            color: var(--vetmind-primary);
        }

        .login-footer {
            margin: 22px 0 0;
            color: #708286;
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            body {
                background: var(--vetmind-background);
            }

            .login-layout {
                display: block;
            }

            .decorative-side {
                display: none;
            }

            .decorative-side::before,
            .decorative-side::after {
                display: none;
            }

            .login-panel {
                align-items: center;
                min-height: 100vh;
                min-height: 100dvh;
                padding: 18px;
                background: transparent;
            }

            .login-card {
                width: 100%;
                max-width: 430px;
                border-radius: 16px;
            }

            .login-card .card-body {
                padding: 28px 24px 24px;
            }



        }

        @media (max-height: 650px) and (min-width: 768px) {
            .login-panel {
                padding-top: 18px;
                padding-bottom: 18px;
            }

            .login-card .card-body {
                padding-top: 22px;
                padding-bottom: 20px;
            }

            .decorative-content {
                padding: 28px;
            }




        }
    </style>
</head>

<body>
    <main class="login-layout">
        <section
            class="decorative-side"
            aria-label="VetMind"
        >
            <div class="decorative-content">
                <div
                    class="vetmind-brand vetmind-brand--banner"
                    role="img"
                    aria-label="VetMind"
                >
                    <img
                        src="assets/img/branding/logo-vetmind.svg"
                        alt=""
                        class="vetmind-brand__icon"
                        width="520"
                        height="420"
                        aria-hidden="true"
                    >

                    <div
                        class="vetmind-brand__name"
                        aria-hidden="true"
                    >
                        <span
                            class="vetmind-brand__part vetmind-brand__vet"
                        >VET</span>

                        <span
                            class="vetmind-brand__part vetmind-brand__mind"
                        >M<span class="vetmind-brand__i"><span class="vetmind-brand__i-letter">I</span><span class="vetmind-brand__heart">♥</span></span>ND</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="login-panel">
            <div class="card login-card">
                <div class="card-body">
                    <div
                        class="vetmind-brand vetmind-brand--card"
                        role="img"
                        aria-label="VetMind"
                    >


                        <div
                            class="vetmind-brand__name"
                            aria-hidden="true"
                        >
                            <span
                                class="vetmind-brand__part vetmind-brand__vet"
                            >VET</span>

                            <span
                                class="vetmind-brand__part vetmind-brand__mind"
                            >M<span class="vetmind-brand__i"><span class="vetmind-brand__i-letter">I</span><span class="vetmind-brand__heart">♥</span></span>ND</span>
                        </div>
                    </div>

                    <h1 class="login-title">
                        Iniciar sesión
                    </h1>

                    <form
                        id="loginForm"
                        method="POST"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
                        >
                        <div class="form-group">
                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                name="email"
                                id="email"
                                autocomplete="email"
                                autocapitalize="none"
                                spellcheck="false"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">
                                Contraseña
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                name="pass"
                                id="password"
                                maxlength="50"
                                autocomplete="current-password"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            class="btn btn-vetmind btn-block"
                        >
                            Iniciar Sesión
                        </button>
                    </form>

                    <div
                        id="processingMessage"
                        class="text-center mt-4"
                        role="status"
                        aria-live="polite"
                        style="display: none;"
                    >
                        <div
                            class="spinner-border"
                            aria-hidden="true"
                        ></div>

                        <p class="mt-2 mb-0">
                            Procesando...
                        </p>
                    </div>

                    <p class="login-footer">
                        &copy; <?php echo date('Y'); ?>
                        VetMind - Todos los derechos reservados
                    </p>
                </div>
            </div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function () {
            var $loginForm = $('#loginForm');
            var $submitButton = $loginForm.find('button[type="submit"]');
            var $processingMessage = $('#processingMessage');

            function obtenerMensajeError(xhr, fallback) {
                if (
                    xhr &&
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    return xhr.responseJSON.message;
                }

                if (xhr && xhr.responseText) {
                    try {
                        var data = JSON.parse(xhr.responseText);

                        if (data && data.message) {
                            return data.message;
                        }
                    } catch (error) {
                        // Respuesta no JSON.
                    }
                }

                return fallback;
            }

            function enviarLogin(reintentoCsrf) {
                $processingMessage.show();
                $submitButton.prop('disabled', true);

                $.ajax({
                    url: 'validar.php',
                    type: 'POST',
                    data: $loginForm.serialize(),
                    dataType: 'json',

                    success: function (data) {
                        $processingMessage.hide();

                        if (data && data.status === 'success') {
                            window.location.href = data.redirect_url;
                            return;
                        }

                        Swal.fire(
                            'Error',
                            (data && data.message)
                                ? data.message
                                : 'No fue posible iniciar sesión.',
                            'error'
                        );

                        $submitButton.prop('disabled', false);
                    },

                    error: function (xhr) {
                        var respuesta =
                            xhr && xhr.responseJSON
                                ? xhr.responseJSON
                                : null;

                        /*
                        * La página quedó abierta con un CSRF perteneciente
                        * a una sesión que ya expiró.
                        *
                        * El servidor inició una sesión nueva y devolvió
                        * su nuevo CSRF. Lo actualizamos y reintentamos
                        * exactamente una vez.
                        */
                        if (
                            !reintentoCsrf &&
                            respuesta &&
                            respuesta.code === 'csrf_expired' &&
                            respuesta.csrf_token
                        ) {
                            $loginForm
                                .find('input[name="csrf_token"]')
                                .val(respuesta.csrf_token);

                            enviarLogin(true);
                            return;
                        }

                        $processingMessage.hide();

                        Swal.fire(
                            'Error',
                            obtenerMensajeError(
                                xhr,
                                'Hubo un problema con la solicitud.'
                            ),
                            'error'
                        );

                        $submitButton.prop('disabled', false);
                    }
                });
            }

            $loginForm.on('submit', function (event) {
                event.preventDefault();
                enviarLogin(false);
            });
        });
    </script>

    <script
        src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"
    ></script>
</body>
</html>