<?php
require_once("../../config.php");

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

function responderNota(string $status, string $message, array $extra = [], int $http = 200): void
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

if (!$mysqli) {
    responderNota('error', 'No se pudo conectar a la base de datos.', [], 500);
}

if ($usuarioId <= 0) {
    responderNota('error', 'Sesión inválida.', [], 401);
}

$accion = trim((string)($_REQUEST['accion'] ?? 'listar'));
$certificadoId = (int)($_REQUEST['certificado_id'] ?? 0);

if ($certificadoId <= 0) {
    responderNota('error', 'Informe inválido.', [], 400);
}

$stmtAcceso = $mysqli->prepare("
    SELECT c.id
    FROM certificados c
    LEFT JOIN certificado_compartidos cc
        ON cc.certificado_id = c.id
        AND cc.usuario_id = ?
        AND cc.estado = 'activo'
        AND cc.puede_ver = 1
    WHERE c.id = ?
      AND (
          c.veterinario_id = ?
          OR cc.id IS NOT NULL
      )
    LIMIT 1
");

if (!$stmtAcceso) {
    responderNota('error', 'No se pudo validar el acceso.', [], 500);
}

$stmtAcceso->bind_param(
    "iii",
    $usuarioId,
    $certificadoId,
    $usuarioId
);

$stmtAcceso->execute();
$tieneAcceso = $stmtAcceso->get_result()->fetch_assoc();
$stmtAcceso->close();

if (!$tieneAcceso) {
    responderNota('error', 'No tienes acceso a este informe.', [], 403);
}

if ($accion === 'listar') {
    $stmt = $mysqli->prepare("
        SELECT
            n.usuario_id,
            n.organo_clave,
            n.organo_nombre,
            n.nota,
            n.updated_at,
            u.nombres,
            u.apellidos,
            u.email
        FROM certificado_notas n
        INNER JOIN usuarios u
            ON u.id = n.usuario_id
        WHERE n.certificado_id = ?
        ORDER BY n.organo_clave, n.updated_at, n.id
    ");

    if (!$stmt) {
        responderNota('error', 'No se pudieron consultar los comentarios.', [], 500);
    }

    $stmt->bind_param("i", $certificadoId);
    $stmt->execute();

    $res = $stmt->get_result();
    $notas = [];

    while ($row = $res->fetch_assoc()) {
        $autor = trim(
            (string)($row['nombres'] ?? '') . ' ' .
            (string)($row['apellidos'] ?? '')
        );

        if ($autor === '') {
            $autor = (string)($row['email'] ?? '');
        }

        $notas[] = [
            'usuario_id' => (int)$row['usuario_id'],
            'organo_clave' => (string)$row['organo_clave'],
            'organo_nombre' => (string)$row['organo_nombre'],
            'nota' => (string)$row['nota'],
            'autor' => $autor,
            'es_mia' => (int)$row['usuario_id'] === $usuarioId,
            'updated_at' => (string)$row['updated_at']
        ];
    }

    $stmt->close();

    responderNota('success', 'Comentarios cargados.', [
        'notas' => $notas
    ]);
}

if ($accion === 'guardar') {
    validarTokenCsrf();

    $organoClave = mb_substr(
        trim((string)($_POST['organo_clave'] ?? '')),
        0,
        191,
        'UTF-8'
    );

    $organoNombre = mb_substr(
        trim((string)($_POST['organo_nombre'] ?? '')),
        0,
        255,
        'UTF-8'
    );

    $nota = trim((string)($_POST['nota'] ?? ''));

    if ($organoClave === '') {
        responderNota('error', 'Sección inválida.', [], 400);
    }

    if ($organoNombre === '') {
        $organoNombre = $organoClave;
    }

    if ($nota === '') {
        $stmt = $mysqli->prepare("
            DELETE FROM certificado_notas
            WHERE certificado_id = ?
              AND usuario_id = ?
              AND organo_clave = ?
        ");

        if (!$stmt) {
            responderNota('error', 'No se pudo preparar la eliminación.', [], 500);
        }

        $stmt->bind_param(
            "iis",
            $certificadoId,
            $usuarioId,
            $organoClave
        );

        if (!$stmt->execute()) {
            $stmt->close();
            responderNota('error', 'No se pudo eliminar el comentario.', [], 500);
        }

        $stmt->close();

        responderNota('success', 'Comentario eliminado.');
    }

    $stmt = $mysqli->prepare("
        INSERT INTO certificado_notas
            (
                certificado_id,
                usuario_id,
                organo_clave,
                organo_nombre,
                nota,
                created_at,
                updated_at
            )
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            organo_nombre = VALUES(organo_nombre),
            nota = VALUES(nota),
            updated_at = NOW()
    ");

    if (!$stmt) {
        responderNota('error', 'No se pudo preparar el comentario.', [], 500);
    }

    $stmt->bind_param(
        "iisss",
        $certificadoId,
        $usuarioId,
        $organoClave,
        $organoNombre,
        $nota
    );

    if (!$stmt->execute()) {
        $stmt->close();
        responderNota('error', 'No se pudo guardar el comentario.', [], 500);
    }

    $stmt->close();

    responderNota('success', 'Comentario guardado.');
}

responderNota('error', 'Acción inválida.', [], 400);
