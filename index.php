<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetMind - Iniciar Sesión</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body, html {
            height: 100%;
            margin: 0;
        }

        body {
            background: url('assets/img/photos/banner5.png') no-repeat center center fixed;
            background-size: cover;
        }

        .full-height {
            height: 100vh;
        }

        .card {
            background: rgba(255, 255, 255, 0.9); /* leve transparencia */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-radius: 15px;
        }

        @media (max-width: 768px) {
            body {
                background: #f8f9fa; /* color neutro en móviles */
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row no-gutters">
            <!-- Imagen lateral -->
            <div class="col-md-7 image-side full-height"></div>
            <!-- Formulario de login -->
            <div class="col-md-5 full-height d-flex align-items-center justify-content-center">
                <div class="card w-75 shadow">
                    <div class="card-body">
                        <div class="text-center mb-2">
                            <img src="assets/img/photos/logo.png" alt="VetMind" class="logo" style="height: 170px; width: 175px;">
                        </div>
                        <!-- <h3 class="text-center mb-2">Informes Veterinarios</h3> -->
                        <form id="loginForm" method="POST">
                            <div class="form-group">
                                <label for="rut">Email</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Contraseña</label>
                                <input type="password" class="form-control" name="pass" id="password" maxlength="50" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
                        </form>
                        <div id="processingMessage" class="text-center mt-4" style="display:none;">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p>Procesando...</p>
                        </div>
                        <p class="text-center mt-2">&copy; VetMind - Todos los derechos reservados</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
    $('#loginForm').submit(function(event) {
        event.preventDefault();

        $('#processingMessage').show();
        $('button[type="submit"]').attr('disabled', true);

        $.ajax({
        url: 'validar.php',
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            console.log(response);
            $('#processingMessage').hide();
            try {
            var data = JSON.parse(response);
            if (data.status === 'success') {
                // 👉 Limpia la última página recordada ANTES de redirigir
                try { localStorage.removeItem('lastPage'); } catch (e) {}
                window.location.href = data.redirect_url; // admin/index.php?inicio=1
            } else {
                Swal.fire('Error', data.message, 'error');
                $('button[type="submit"]').attr('disabled', false);
            }
            } catch (e) {
            Swal.fire('Error', 'Respuesta inesperada del servidor.', 'error');
            $('button[type="submit"]').attr('disabled', false);
            }
        },
        error: function() {
            Swal.fire('Error', 'Hubo un problema con la solicitud.', 'error');
            $('#processingMessage').hide();
            $('button[type="submit"]').attr('disabled', false);
        }
        });
    });
    });
    </script>

    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
