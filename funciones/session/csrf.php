<?php

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}


function validarTokenCsrf(?string $token = null): void
{
    $recibido =
        $token
        ?? ($_POST['csrf_token'] ?? null)
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (
        !is_string($recibido)
        || !hash_equals(tokenCsrf(), $recibido)
    ) {
        http_response_code(419);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'status'  => 'error',
            'message' => 'La sesión del formulario expiró. Recarga la página e intenta nuevamente.'
        ]);

        exit;
    }
}