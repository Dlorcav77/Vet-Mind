<?php
function fin_session($codsede)
{
  $inactivo = 2400;
  $link = "../index.php";

  if (isset($_SESSION['time'])) {
    $session_life = time() - $_SESSION['time'];

    //  print "<br>tiempo $session_life -- $inactivo <br>";
    if ($session_life > $inactivo) {
      echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
           document.addEventListener('DOMContentLoaded', function () {
              Swal.fire({
                icon: 'error',
                title: 'Sesión Expirada',
                confirmButtonText: 'Aceptar',
                allowOutsideClick: false, 
                allowEscapeKey: false,     
                allowEnterKey: false,      
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
              }).then(() => {
                  top.location.href = '$link';
              });
            });
        </script>
        ";
      exit;
    }
  }
  $_SESSION['time'] = time();

  if (isset($_SESSION['usuario_id'])) {
    $cliente = $_SESSION['usuario_id'];
  } else {
    echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
         document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: 'error',
                title: 'Acceso denegado',
                text: 'sin sesion iniciada.',
                confirmButtonText: 'Aceptar',
                allowOutsideClick: false,  
                allowEscapeKey: false,
                allowEnterKey: false, 
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    top.location.href = '$link';
                }
            });
          });
        </script>";
        if (!isset($_SESSION['usuario_id'])) {
            echo "
            <script>
                window.location.href = '$link';
            </script>";
            exit;
        }        
    exit;
  }
  ##################################################################################################
}

function credenciales($modulo, $accion = 'listar') {
    $accesos = $_SESSION['acceso_aplicaciones'] ?? [];
// print_r($accesos);
    // Módulos públicos opcionales, si los tienes
    $excepciones = ['inicio', 'perfil', 'password', 'lisTickets'];

    if (in_array($modulo, $excepciones)) {
        return; // acceso libre
    }

    if (!isset($accesos[$modulo]) || !in_array($accion, $accesos[$modulo])) {
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
            INNER JOIN modulo_permisos mp ON mp.id = pp.permiso_id
            INNER JOIN modulos_aplicaciones ma ON ma.id = mp.modulo_id
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

    return $accesos;
}
