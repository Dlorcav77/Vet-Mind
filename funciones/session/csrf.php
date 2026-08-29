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

    $tokenActual = tokenCsrf();

    if (
        !is_string($recibido)
        || !hash_equals($tokenActual, $recibido)
    ) {
        http_response_code(403);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode([
            'status'     => 'error',
            'code'       => 'csrf_expired',
            'message'    => 'La sesión del formulario expiró.',
            'csrf_token' => $tokenActual
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }
}