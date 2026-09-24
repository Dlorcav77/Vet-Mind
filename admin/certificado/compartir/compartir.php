<?php
require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

function responderCompartir(string $status, string $message, array $extra = [], int $http = 200): void
{
    http_response_code($http);

    echo json_encode(
        array_merge([
            'status' => $status,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($usuarioId <= 0) {
    responderCompartir('error', 'Sesión inválida.', [], 401);
}

validarTokenCsrf();

$accion = trim((string)($_POST['accion'] ?? ''));
$certificadoId = (int)($_POST['certificado_id'] ?? 0);

if ($certificadoId <= 0) {
    responderCompartir('error', 'Informe inválido.', [], 400);
}

/*
 * En esta primera etapa solamente el propietario
 * puede compartir el certificado.
 */
$stmtPropietario = $mysqli->prepare("
    SELECT veterinario_id
    FROM certificados
    WHERE id = ?
    LIMIT 1
");

if (!$stmtPropietario) {
    responderCompartir('error', 'No se pudo validar el informe.', [], 500);
}

$stmtPropietario->bind_param("i", $certificadoId);
$stmtPropietario->execute();

$resPropietario = $stmtPropietario->get_result();
$rowPropietario = $resPropietario->fetch_assoc();
$stmtPropietario->close();

if (!$rowPropietario) {
    responderCompartir('error', 'Informe no encontrado.', [], 404);
}

if ((int)$rowPropietario['veterinario_id'] !== $usuarioId) {
    responderCompartir('error', 'No tienes permiso para compartir este informe.', [], 403);
}

if ($accion === 'buscar_usuario') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderCompartir('error', 'Ingresa un correo válido.', [], 400);
    }

    $stmtUsuario = $mysqli->prepare("
        SELECT id, nombres, apellidos, email
        FROM usuarios
        WHERE id <> ?
          AND estado = 'activo'
          AND deleted_at IS NULL
          AND LOWER(TRIM(email)) = LOWER(?)
        LIMIT 1
    ");

    if (!$stmtUsuario) {
        responderCompartir('error', 'No se pudo realizar la búsqueda.', [], 500);
    }

    $stmtUsuario->bind_param("is", $usuarioId, $email);
    $stmtUsuario->execute();

    $resUsuario = $stmtUsuario->get_result();
    $usuario = $resUsuario->fetch_assoc();
    $stmtUsuario->close();

    if (!$usuario) {
        responderCompartir('success', 'No se encontró una cuenta con ese correo.', [
            'encontrado' => false
        ]);
    }

    $usuarioDestinoId = (int)$usuario['id'];

    $stmtActual = $mysqli->prepare("
        SELECT puede_editar, estado
        FROM certificado_compartidos
        WHERE certificado_id = ?
          AND usuario_id = ?
        LIMIT 1
    ");

    $compartido = false;
    $puedeEditar = false;

    if ($stmtActual) {
        $stmtActual->bind_param("ii", $certificadoId, $usuarioDestinoId);
        $stmtActual->execute();

        $actual = $stmtActual->get_result()->fetch_assoc();
        $stmtActual->close();

        if ($actual && $actual['estado'] === 'activo') {
            $compartido = true;
            $puedeEditar = (int)$actual['puede_editar'] === 1;
        }
    }

    $nombre = trim(
        (string)($usuario['nombres'] ?? '') . ' ' .
        (string)($usuario['apellidos'] ?? '')
    );

    if ($nombre === '') {
        $nombre = (string)$usuario['email'];
    }

    responderCompartir('success', 'Usuario encontrado.', [
        'encontrado' => true,
        'usuario' => [
            'id' => $usuarioDestinoId,
            'nombre' => $nombre,
            'email' => (string)$usuario['email'],
            'compartido' => $compartido,
            'puede_editar' => $puedeEditar
        ]
    ]);
}

if ($accion === 'guardar') {
    $usuarioDestinoId = (int)($_POST['usuario_id'] ?? 0);
    $puedeEditar = !empty($_POST['puede_editar']) ? 1 : 0;

    if ($usuarioDestinoId <= 0 || $usuarioDestinoId === $usuarioId) {
        responderCompartir('error', 'Usuario inválido.', [], 400);
    }

    $stmtUsuario = $mysqli->prepare("
        SELECT id
        FROM usuarios
        WHERE id = ?
          AND estado = 'activo'
          AND deleted_at IS NULL
        LIMIT 1
    ");

    if (!$stmtUsuario) {
        responderCompartir('error', 'No se pudo validar el usuario.', [], 500);
    }

    $stmtUsuario->bind_param("i", $usuarioDestinoId);
    $stmtUsuario->execute();

    $usuarioExiste = $stmtUsuario->get_result()->fetch_assoc();
    $stmtUsuario->close();

    if (!$usuarioExiste) {
        responderCompartir('error', 'La cuenta seleccionada ya no está disponible.', [], 404);
    }

    $stmtCompartir = $mysqli->prepare("
        INSERT INTO certificado_compartidos
        (
            certificado_id,
            usuario_id,
            compartido_por_id,
            puede_ver,
            puede_editar,
            estado,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, 1, ?, 'activo', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            compartido_por_id = VALUES(compartido_por_id),
            puede_ver = 1,
            puede_editar = VALUES(puede_editar),
            estado = 'activo',
            updated_at = NOW()
    ");

    if (!$stmtCompartir) {
        responderCompartir('error', 'No se pudo preparar el acceso compartido.', [], 500);
    }

    $stmtCompartir->bind_param(
        "iiii",
        $certificadoId,
        $usuarioDestinoId,
        $usuarioId,
        $puedeEditar
    );

    if (!$stmtCompartir->execute()) {
        error_log('[certificado_compartir] ' . $stmtCompartir->error);
        $stmtCompartir->close();

        responderCompartir('error', 'No se pudo compartir el informe.', [], 500);
    }

    $stmtCompartir->close();

    responderCompartir('success', 'Informe compartido correctamente.');
}

responderCompartir('error', 'Acción inválida.', [], 400);
