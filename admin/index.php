<?php
require ("../funciones/session/ini_session.php");

include 'header.php';
include 'menu.php';

require_once __DIR__ . '/rutas.php';

global $usuario_id;

$mysqli = conn();

$sel  ="select nombres from usuarios where id='$usuario_id'";
$res  = $mysqli->query($sel);
$row  = $res->fetch_assoc();

$nombres = $row['nombres'];

$nombre = explode(' ', $nombres);
$pNombre = $nombre[0];

$logo = '';

$link = "../logout.php";

$rutaSolicitada = $_GET['p'] ?? '';

$rutaInicial = resolver_ruta_panel(
    $rutaSolicitada,
    'inicio/inicio.php'
);

?>
<style>
    .badge-notification-avatar {
        position: absolute;
        top: 5px;
        right: 65px;
        color: white;
        font-size: 0.8rem;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        display: none;
    }
    .badge-notification {
        position: absolute;
        top: 5px;
        right: 145px;
        color: white;
        font-size: 0.8rem;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        display: none;
    }
</style>
<div class="main">
  <nav class="navbar navbar-expand navbar-light navbar-bg">
    <a class="sidebar-toggle js-sidebar-toggle">
        <i class="hamburger align-self-center"></i>
    </a>
    <div class="navbar-collapse collapse">
    <a>
        <span class="text-dark">  <?php print"&nbsp; &nbsp; &nbsp; &nbsp;";?></span>
    </a>
    <ul class="navbar-nav navbar-align">
        <li class="nav-item dropdown">
            <a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown">
                <i class="align-middle" data-feather="settings"></i>
            </a>
            <a class="nav-link dropdown-toggle d-none d-sm-inline-block position-relative" href="#" data-bs-toggle="dropdown">
                <img src="../assets/img/avatars/user-net.jpg" class="avatar img-fluid rounded me-1" alt="Usuario Sistema" />
                <span class="text-dark">
                    <?php echo $pNombre; ?>
                </span>
                <span class="badge-notification-avatar"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-end">
                <a
                    class="dropdown-item ajax-link"
                    href="index.php?p=<?= rawurlencode('infoUsuarios/perfil.php') ?>"
                >
                    <i class="fas fa-user me-2"></i> Ver Perfil
                </a>
                <a
                    class="dropdown-item ajax-link"
                    href="index.php?p=<?= rawurlencode('infoUsuarios/password.php') ?>"
                >
                    <i class="fa-solid fa-lock me-2"></i> Cambio Contraseña
                </a>
                <a
                    class="dropdown-item ajax-link position-relative"
                    href="index.php?p=<?= rawurlencode('tickets/lisTickets.php') ?>"
                >
                    <i class="fas fa-tags me-2"></i> Tickets
                    <span class="badge-notification"></span>
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?php echo $link; ?>">
                    <i class="align-middle me-1" data-feather="power"></i> Cerrar Sesi&oacute;n
                </a>
            </div>
        </li>
    </ul>
    </div>
  </nav>

<div class="main">
  <main class="content">
    <div id="content">
    </div>
  </main>
</div>


<script>
$(document).ready(function() {

    const RUTA_INICIAL = <?= json_encode(
        $rutaInicial,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>;


    function updateMenuState(pageId) {

        $('.sidebar-item').removeClass('active');

        if (pageId) {
            $('#menu-' + pageId).addClass('active');
        }
    }


    /*
     * Convierte un href de la aplicación en una ruta
     * relativa dentro de /admin.
     *
     * Soporta:
     *
     * tutor/tutores.php
     *
     * certificado/certificados.php?action=modificar&id=10
     *
     * index.php?p=tutor/tutores.php
     */
    function obtenerRutaPanel(href) {

        if (!href || href === '#') {
            return null;
        }

        try {

            const url = new URL(
                href,
                window.location.href
            );


            /*
             * No permitimos navegar por AJAX hacia
             * otro dominio.
             */
            if (url.origin !== window.location.origin) {
                return null;
            }


            /*
             * Enlace del nuevo formato:
             *
             * index.php?p=tutor/tutores.php
             */
            const rutaParametro =
                url.searchParams.get('p');

            if (rutaParametro) {
                return rutaParametro;
            }


            /*
             * Enlaces actuales:
             *
             * tutor/tutores.php
             * certificado/certificados.php?action=...
             */
            const adminBase =
                new URL('./', window.location.href).pathname;

            if (!url.pathname.startsWith(adminBase)) {
                return null;
            }

            const path =
                url.pathname.substring(adminBase.length);

            if (!path) {
                return null;
            }


            /*
             * Conservamos los parámetros propios
             * de la pantalla.
             */
            return path + url.search;

        } catch (error) {

            return null;
        }
    }


    /*
     * Actualiza la URL visible sin recargar.
     *
     * URLSearchParams codificará automáticamente:
     *
     * /  -> %2F
     * ?  -> %3F
     * &  -> %26
     *
     * Eso es correcto.
     */
    function actualizarUrlPanel(ruta, reemplazar = false) {

        const url = new URL(
            'index.php',
            window.location.href
        );

        url.searchParams.set('p', ruta);

        const nuevaUrl =
            url.pathname + url.search;

        if (reemplazar) {

            history.replaceState(
                { ruta: ruta },
                '',
                nuevaUrl
            );

            return;
        }

        history.pushState(
            { ruta: ruta },
            '',
            nuevaUrl
        );
    }


    /*
     * Carga una pantalla dentro de #content.
     */
    function cargarPagina(
        ruta,
        actualizarHistorial = false
    ) {

        $('#content').css(
            'visibility',
            'hidden'
        );

        $.ajax({
            url: ruta,
            method: 'GET',

            success: function(data) {

                $('#content').html(data);

                const pagina =
                    $('#content')
                        .find('[data-page-id]')
                        .first();

                const pageId =
                    pagina.attr('data-page-id');

                updateMenuState(pageId);


                /*
                 * Solo guardamos la URL si la respuesta
                 * corresponde a una pantalla de VetMind.
                 *
                 * Los módulos actuales ya utilizan
                 * data-page-id.
                 */
                if (
                    actualizarHistorial &&
                    pagina.length > 0
                ) {
                    actualizarUrlPanel(ruta);
                }


                setTimeout(function() {

                    $('#content').css(
                        'visibility',
                        'visible'
                    );

                }, 250);
            },

            error: function(xhr) {

                $('#content').css(
                    'visibility',
                    'visible'
                );

                /*
                 * El controlador global se encarga
                 * de una sesión expirada.
                 */
                if (xhr.status === 401) {
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No fue posible cargar el contenido.'
                });
            }
        });
    }


    /*
     * Primera carga.
     *
     * Puede venir de:
     *
     * index.php
     *
     * o:
     *
     * index.php?p=tutor/tutores.php
     */
    cargarPagina(
        RUTA_INICIAL,
        false
    );


    /*
     * Toda pantalla navegable actual utiliza ajax-link.
     */
    $(document).on(
        'click',
        'a.ajax-link',
        function(event) {

            const href =
                $(this).attr('href');

            const ruta =
                obtenerRutaPanel(href);

            if (!ruta) {
                return;
            }

            event.preventDefault();

            cargarPagina(
                ruta,
                true
            );
        }
    );


    /*
     * Botones atrás / adelante.
     */
    window.addEventListener(
        'popstate',
        function() {

            const url =
                new URL(window.location.href);

            const ruta =
                url.searchParams.get('p')
                || 'inicio/inicio.php';

            cargarPagina(
                ruta,
                false
            );
        }
    );


    // === CONTROL GLOBAL DE SESIÓN EXPIRADA ===

    let sesionBloqueada = false;


    function mostrarModalSesionExpirada() {

        if (sesionBloqueada) {
            return;
        }

        sesionBloqueada = true;

        Swal.fire({
            icon: 'warning',
            title: 'Sesión expirada',
            text: 'Tu sesión terminó por inactividad. Debes volver a iniciar sesión.',
            confirmButtonText: 'Ir a login',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false
        }).then(() => {

            window.top.location.href =
                '../index.php';
        });
    }


    $.ajaxSetup({
        statusCode: {

            401: function() {
                mostrarModalSesionExpirada();
            }

        }
    });


    $(document).ajaxError(
        function(event, jqxhr) {

            try {

                const contentType =
                    jqxhr.getResponseHeader &&
                    jqxhr.getResponseHeader(
                        'Content-Type'
                    );

                if (
                    contentType &&
                    contentType.indexOf(
                        'application/json'
                    ) >= 0 &&
                    jqxhr.responseText
                ) {

                    const data =
                        JSON.parse(
                            jqxhr.responseText
                        );

                    if (
                        data &&
                        (
                            data.status === 'expired' ||
                            data.status === 'no_session'
                        )
                    ) {
                        mostrarModalSesionExpirada();
                    }
                }

            } catch (e) {
                // No hacer nada.
            }
        }
    );

});
</script>

<?php include 'footer.php';?> 
</body>
</html>