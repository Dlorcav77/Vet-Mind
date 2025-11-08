<?php
require ("../funciones/session/ini_session.php");

include 'header.php';
include 'menu.php';

$mysqli = conn();

$sel  ="select nombres from usuarios where id='$usuario_id'";
$res  = $mysqli->query($sel);
$row  = $res->fetch_assoc();

$nombres = $row['nombres'];

$nombre = explode(' ', $nombres);
$pNombre = $nombre[0];


// $selL ="select logo from logo where codsede='$codsede'";
// $resL = $mysqli->query($selL);
// $rowL = $resL->fetch_assoc();
// $logo = $rowL['logo'];
$logo = '';


$link = "../index.php";

$forzarInicio = isset($_GET['inicio']) && $_GET['inicio'] === '1';
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
                <a class="dropdown-item ajax-link" href="infoUsuarios/perfil.php" data-appname="perfil.php">
                    <i class="fas fa-user me-2"></i> Ver Perfil
                </a>
                <a class="dropdown-item ajax-link" href="infoUsuarios/password.php" data-appname="password.php">
                    <i class="fa-solid fa-lock me-2"></i> Cambio Contraseña
                </a>
                <a class="dropdown-item ajax-link position-relative" href="tickets/lisTickets.php" data-appname="lisTickets.php">
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
    const FORCE_HOME = <?= $forzarInicio ? 'true' : 'false' ?>;

    if (typeof FORCE_HOME !== 'undefined' && FORCE_HOME) {
        try { localStorage.removeItem('lastPage'); } catch (e) {}
        // Opcional: limpiar el query param ?inicio=1 de la URL actual
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('inicio');
            const newQ = url.searchParams.toString();
            window.history.replaceState({}, '', url.pathname + (newQ ? '?' + newQ : ''));
        }
    }

    var lastPage = (typeof FORCE_HOME !== 'undefined' && FORCE_HOME) ? null : localStorage.getItem('lastPage');

    if (lastPage) {
    $('#content').load(lastPage, function() {
        const pageId = $('#content').find('[data-page-id]').attr('data-page-id');
        updateMenuState(pageId);
    });
    } else {
    $('#content').load('inicio/inicio.php', function() {
        const pageId = $('#content').find('[data-page-id]').attr('data-page-id');
        updateMenuState(pageId);
    });
    }


    $(document).on('click', '.sidebar-link, .ajax-link', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');

        $.ajax({
            url: url,
            method: 'GET',
            success: function(data) {
                $('#content').html(data);

                localStorage.setItem('lastPage', url);

                var pageId = $('#content').find('[data-page-id]').attr('data-page-id');
                updateMenuState(pageId);

                history.pushState({ url: url }, null, window.location.pathname);
            },
            error: function() {
                alert('Error al cargar el contenido.');
            }
        });
    });


    window.onpopstate = function(event) {
        if (event.state && event.state.url) {
            var url = event.state.url;

            $.ajax({
                url: url,
                method: 'GET',
                success: function(data) {
                    $('#content').html(data);

                    localStorage.setItem('lastPage', url);

                    var pageId = $('#content').find('[data-page-id]').attr('data-page-id');
                    updateMenuState(pageId);
                },
                error: function() {
                    alert('Error al cargar el contenido.');
                }
            });
        }
    };

    function updateMenuState(pageId) {
        $('.sidebar-item').removeClass('active');
        if (pageId) {
            $('#menu-' + pageId).addClass('active');
        }
    }



    function updateMenuStateByUrl(url) {
        $.ajax({
            url: url,
            method: 'GET',
            success: function(data) {
                $('#content').html(data);

                var pageId = $('#content').find('[data-page-id]').attr('data-page-id');

                updateMenuState(pageId);
            },
            error: function() {
                alert('Error al cargar el contenido.');
            }
        });
    }
    












// === CONFIGURACIÓN DEL WATCHDOG DE SESIÓN ===
const PING_URL = '../funciones/session/ping_sesion.php'; // ajusta si fuera necesario
const PING_INTERVAL_MS = 1 * 60 * 1000; // cada 2 minutos (ajustable)
let pingTimer = null;
let sesionBloqueada = false; // evita mostrar el modal más de una vez

// Crea un overlay para bloquear la UI cuando expire
function crearOverlayBloqueo() {
    if ($('#overlay-bloqueo-sesion').length) return;
    const overlay = `
      <div id="overlay-bloqueo-sesion" 
           style="position:fixed; inset:0; background:rgba(255,255,255,0.6); 
                  z-index: 1040; display:none;">
      </div>`;
    $('body').append(overlay);
}

function bloquearUI() {
  if (sesionBloqueada) return;
  sesionBloqueada = true;
  crearOverlayBloqueo();
  $('#overlay-bloqueo-sesion').fadeIn(120);

  // Solo lo tuyo:
  $('main, #content, .sidebar, nav').find(':input, a, button')
    .prop('disabled', true)
    .addClass('disabled');
}

function desbloquearUI() {
  sesionBloqueada = false;
  $('#overlay-bloqueo-sesion').fadeOut(100);
  $('main, #content, .sidebar, nav').find(':input, a, button')
    .prop('disabled', false)
    .removeClass('disabled');
}

// Llamado de ping
function checkSesion() {
    // Si ya estamos bloqueados, no sigas spameando
    if (sesionBloqueada) return;

    $.ajax({
        url: PING_URL,
        method: 'GET',
        cache: false,
        timeout: 15000, // 15s por si hay red lenta
        success: function(resp) {
            // resp.status === 'ok' esperado
            // Opcional: si quieres distinguir caída de BD
            // if (resp && resp.db_ok === false) { ... }
        },
        statusCode: {
            401: function(xhr) {
                // Sesión expirada o inexistente
                bloquearUI();
                Swal.fire({
                    icon: 'warning',
                    title: 'Sesión expirada',
                    text: 'Tu sesión terminó por inactividad. Debes volver a iniciar sesión.',
                    confirmButtonText: 'Ir a login',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false
                }).then(() => {
                    // Redirige al login (misma lógica que usas en fin_session)
                    window.top.location.href = '../index.php';
                });
            }
        },
        error: function() {
            // Si falla por red/timeout, no bloqueamos de inmediato.
            // Podrías contar fallos consecutivos y, si exceden N, avisar.
            // Ej: mostrar un toast suave "Problemas de red, reintentando..."
        }
    });
}

// Arranque del watchdog
function iniciarWatchdogSesion() {
    // Llamado inmediato al cargar
    checkSesion();
    // Repetición cada N minutos
    if (pingTimer) clearInterval(pingTimer);
    pingTimer = setInterval(checkSesion, PING_INTERVAL_MS);
}

// Iniciar al cargar la app
iniciarWatchdogSesion();


// === INTERCEPTOR AJAX GLOBAL ===
// Reutiliza la misma bandera y helpers del Paso 2:
function mostrarModalSesionExpirada() {
    if (sesionBloqueada) return;
    bloquearUI();
    Swal.fire({
        icon: 'warning',
        title: 'Sesión expirada',
        text: 'Tu sesión terminó por inactividad. Debes volver a iniciar sesión.',
        confirmButtonText: 'Ir a login',
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false
    }).then(() => {
        window.top.location.href = '../index.php';
    });
}

// Manejo centralizado de códigos
$.ajaxSetup({
    statusCode: {
        401: function() {
            // No autorizado / sesión expirada
            mostrarModalSesionExpirada();
        },
        419: function() {
            // (Opcional) Token inválido/expirado (más común en Laravel)
            mostrarModalSesionExpirada();
        }
    }
});

// Por si algún endpoint responde con otro status pero error semántico
$(document).ajaxError(function(event, jqxhr, settings, thrownError) {
    // Red segura: algunos servidores devuelven 200 pero incluyen un JSON "expired"
    try {
        const ct = jqxhr.getResponseHeader && jqxhr.getResponseHeader('Content-Type');
        if (ct && ct.indexOf('application/json') >= 0 && jqxhr.responseText) {
            const data = JSON.parse(jqxhr.responseText);
            if (data && (data.status === 'expired' || data.status === 'no_session')) {
                mostrarModalSesionExpirada();
            }
        }
    } catch (e) { /* noop */ }
});

});

</script>
<?php include 'footer.php';?> 
</body>
</html>