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

function copiarArchivoClonSeguro(
    string $rutaRelativa,
    string $directorioRelativo,
    string $prefijoNuevo
): ?string {
    $rutaRelativa = trim($rutaRelativa);

    if ($rutaRelativa === '') {
        return null;
    }

    $rutaRelativa = str_replace('\\', '/', $rutaRelativa);
    $rutaRelativa = ltrim($rutaRelativa, '/');

    $raiz = realpath(__DIR__ . '/../../../');

    if ($raiz === false) {
        throw new RuntimeException(
            'No se pudo resolver la raíz de archivos.'
        );
    }

    $directorioFisico =
        $raiz . '/' . trim($directorioRelativo, '/');

    if (!is_dir($directorioFisico)) {
        throw new RuntimeException(
            'No existe el directorio de destino.'
        );
    }

    $directorioReal = realpath($directorioFisico);

    if ($directorioReal === false) {
        throw new RuntimeException(
            'No se pudo resolver el directorio de destino.'
        );
    }

    $origen = realpath(
        $raiz . '/' . $rutaRelativa
    );

    if (
        $origen === false ||
        !is_file($origen) ||
        strpos(
            $origen,
            $directorioReal . DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        throw new RuntimeException(
            'Archivo original inválido para clonación.'
        );
    }

    $extension = strtolower(
        pathinfo($origen, PATHINFO_EXTENSION)
    );

    if ($extension === '') {
        throw new RuntimeException(
            'El archivo original no tiene extensión válida.'
        );
    }

    $nombreNuevo =
        $prefijoNuevo .
        bin2hex(random_bytes(12)) .
        '.' .
        $extension;

    $destino =
        $directorioReal .
        DIRECTORY_SEPARATOR .
        $nombreNuevo;

    if (!copy($origen, $destino)) {
        throw new RuntimeException(
            'No se pudo copiar un archivo del informe.'
        );
    }

    @chmod($destino, 0644);

    return
        trim($directorioRelativo, '/') .
        '/' .
        $nombreNuevo;
}

function copiarImagenesClonSeguro(
    ?string $imagenesJson,
    int $usuarioDestinoId,
    array &$archivosCreados
): ?string {
    if ($imagenesJson === null || trim($imagenesJson) === '') {
        return null;
    }

    $imagenes = json_decode($imagenesJson, true);

    if (!is_array($imagenes)) {
        return null;
    }

    $nuevas = [];

    foreach ($imagenes as $imagen) {
        $imagen = trim((string)$imagen);

        if ($imagen === '') {
            continue;
        }

        $nueva = copiarArchivoClonSeguro(
            $imagen,
            'uploads/certificados/img',
            'clon_' . $usuarioDestinoId . '_'
        );

        if ($nueva !== null) {
            $nuevas[] = $nueva;
            $archivosCreados[] = $nueva;
        }
    }

    return json_encode(
        $nuevas,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
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

$esPropietarioInforme =
    (int)$rowPropietario['veterinario_id'] === $usuarioId;

if (
    in_array(
        $accion,
        [
            'buscar_usuario',
            'guardar',
            'listar_compartidos',
            'listar_favoritos',
            'revocar'
        ],
        true
    ) &&
    !$esPropietarioInforme
) {
    responderCompartir(
        'error',
        'No tienes permiso para compartir este informe.',
        [],
        403
    );
}

if ($accion === 'listar_compartidos') {
    $stmtLista = $mysqli->prepare("
        SELECT
            cc.usuario_id,
            cc.tipo_solicitud,
            cc.incluir_comentarios,
            cc.puede_editar,
            cc.estado,
            cc.created_at,
            cc.updated_at,
            u.nombres,
            u.apellidos,
            u.email
        FROM certificado_compartidos cc
        INNER JOIN usuarios u
            ON u.id = cc.usuario_id
        WHERE cc.certificado_id = ?
          AND cc.estado IN ('pendiente', 'activo')
        ORDER BY
            CASE
                WHEN cc.estado = 'pendiente' THEN 0
                ELSE 1
            END,
            cc.updated_at DESC,
            cc.id DESC
    ");

    if (!$stmtLista) {
        responderCompartir(
            'error',
            'No se pudieron consultar los usuarios agregados.',
            [],
            500
        );
    }

    $stmtLista->bind_param('i', $certificadoId);
    $stmtLista->execute();

    $resLista = $stmtLista->get_result();
    $usuarios = [];

    while ($row = $resLista->fetch_assoc()) {
        $nombre = trim(
            (string)($row['nombres'] ?? '') . ' ' .
            (string)($row['apellidos'] ?? '')
        );

        if ($nombre === '') {
            $nombre = (string)$row['email'];
        }

        $usuarios[] = [
            'id' => (int)$row['usuario_id'],
            'nombre' => $nombre,
            'email' => (string)$row['email'],
            'estado' => (string)$row['estado'],
            'tipo_solicitud' =>
                (string)$row['tipo_solicitud'],
            'incluir_comentarios' =>
                (int)$row['incluir_comentarios'] === 1,
            'puede_editar' =>
                (int)$row['puede_editar'] === 1
        ];
    }

    $stmtLista->close();

    responderCompartir(
        'success',
        'Usuarios cargados.',
        ['usuarios' => $usuarios]
    );
}

if ($accion === 'listar_favoritos') {
    $stmtFavoritos = $mysqli->prepare("
        SELECT
            u.id,
            u.nombres,
            u.apellidos,
            u.email
        FROM usuario_favoritos_compartir uf
        INNER JOIN usuarios u
            ON u.id = uf.favorito_usuario_id
        WHERE uf.usuario_id = ?
        ORDER BY
            u.nombres ASC,
            u.apellidos ASC,
            u.email ASC
    ");

    if (!$stmtFavoritos) {
        responderCompartir(
            'error',
            'No se pudieron consultar los favoritos.',
            [],
            500
        );
    }

    $stmtFavoritos->bind_param(
        'i',
        $usuarioId
    );

    $stmtFavoritos->execute();

    $resFavoritos =
        $stmtFavoritos->get_result();

    $favoritos = [];

    while ($row = $resFavoritos->fetch_assoc()) {
        $nombre = trim(
            (string)($row['nombres'] ?? '') . ' ' .
            (string)($row['apellidos'] ?? '')
        );

        if ($nombre === '') {
            $nombre = (string)$row['email'];
        }

        $favoritos[] = [
            'id' => (int)$row['id'],
            'nombre' => $nombre,
            'email' => (string)$row['email']
        ];
    }

    $stmtFavoritos->close();

    responderCompartir(
        'success',
        'Favoritos cargados.',
        ['usuarios' => $favoritos]
    );
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
    $estadoActual = null;

    if ($stmtActual) {
        $stmtActual->bind_param("ii", $certificadoId, $usuarioDestinoId);
        $stmtActual->execute();

        $actual = $stmtActual->get_result()->fetch_assoc();
        $stmtActual->close();

        if ($actual) {
            $estadoActual = (string)$actual['estado'];
            $puedeEditar =
                (int)$actual['puede_editar'] === 1;

            if ($estadoActual === 'activo') {
                $compartido = true;
            }
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
            'estado' => $estadoActual,
            'puede_editar' => $puedeEditar
        ]
    ]);
}

if ($accion === 'guardar') {
    $usuarioDestinoId = (int)($_POST['usuario_id'] ?? 0);
    $puedeEditar = !empty($_POST['puede_editar']) ? 1 : 0;

    $tipoSolicitud =
        trim((string)($_POST['tipo_solicitud'] ?? 'compartir'));

    $incluirComentarios =
        !empty($_POST['incluir_comentarios']) ? 1 : 0;

    $marcarFavorito =
        !empty($_POST['favorito']) ? 1 : 0;

    if (!in_array($tipoSolicitud, ['compartir', 'clonar'], true)) {
        responderCompartir(
            'error',
            'Tipo de solicitud inválido.',
            [],
            400
        );
    }

    if ($tipoSolicitud === 'clonar') {
        $puedeEditar = 0;
    }

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
            tipo_solicitud,
            puede_ver,
            puede_editar,
            incluir_comentarios,
            estado,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, 1, ?, ?, 'pendiente', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            compartido_por_id = VALUES(compartido_por_id),
            tipo_solicitud = VALUES(tipo_solicitud),
            puede_ver = 1,
            puede_editar = VALUES(puede_editar),
            incluir_comentarios = VALUES(incluir_comentarios),
            estado = CASE
                WHEN estado = 'activo'
                     AND tipo_solicitud = 'compartir'
                     AND VALUES(tipo_solicitud) = 'compartir'
                    THEN 'activo'
                ELSE 'pendiente'
            END,
            updated_at = NOW()
    ");

    if (!$stmtCompartir) {
        responderCompartir('error', 'No se pudo preparar el acceso compartido.', [], 500);
    }

    $stmtCompartir->bind_param(
        "iiisii",
        $certificadoId,
        $usuarioDestinoId,
        $usuarioId,
        $tipoSolicitud,
        $puedeEditar,
        $incluirComentarios
    );

    if (!$stmtCompartir->execute()) {
        error_log('[certificado_compartir] ' . $stmtCompartir->error);
        $stmtCompartir->close();

        responderCompartir('error', 'No se pudo compartir el informe.', [], 500);
    }

    $stmtCompartir->close();

    if ($marcarFavorito === 1) {
        $stmtFavorito = $mysqli->prepare("
            INSERT INTO usuario_favoritos_compartir
            (
                usuario_id,
                favorito_usuario_id,
                created_at
            )
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                created_at = created_at
        ");

        if ($stmtFavorito) {
            $stmtFavorito->bind_param(
                'ii',
                $usuarioId,
                $usuarioDestinoId
            );

            $stmtFavorito->execute();
            $stmtFavorito->close();
        }
    } else {
        $stmtFavorito = $mysqli->prepare("
            DELETE FROM usuario_favoritos_compartir
            WHERE usuario_id = ?
              AND favorito_usuario_id = ?
        ");

        if ($stmtFavorito) {
            $stmtFavorito->bind_param(
                'ii',
                $usuarioId,
                $usuarioDestinoId
            );

            $stmtFavorito->execute();
            $stmtFavorito->close();
        }
    }

    responderCompartir(
        'success',
        $tipoSolicitud === 'clonar'
            ? 'Solicitud de clonación enviada correctamente.'
            : 'Solicitud de acceso enviada correctamente.'
    );
}

if ($accion === 'revocar') {
    $usuarioDestinoId = (int)($_POST['usuario_id'] ?? 0);

    if ($usuarioDestinoId <= 0) {
        responderCompartir(
            'error',
            'Usuario inválido.',
            [],
            400
        );
    }

    $stmtRevocar = $mysqli->prepare("
        UPDATE certificado_compartidos
        SET estado = 'revocado',
            updated_at = NOW()
        WHERE certificado_id = ?
          AND usuario_id = ?
          AND estado IN ('pendiente', 'activo')
    ");

    if (!$stmtRevocar) {
        responderCompartir(
            'error',
            'No se pudo preparar el retiro del acceso.',
            [],
            500
        );
    }

    $stmtRevocar->bind_param(
        'ii',
        $certificadoId,
        $usuarioDestinoId
    );

    if (!$stmtRevocar->execute()) {
        error_log(
            '[certificado_compartir][revocar] ' .
            $stmtRevocar->error
        );

        $stmtRevocar->close();

        responderCompartir(
            'error',
            'No se pudo retirar el acceso.',
            [],
            500
        );
    }

    $afectadas = $stmtRevocar->affected_rows;
    $stmtRevocar->close();

    if ($afectadas === 0) {
        responderCompartir(
            'error',
            'El acceso ya no está activo o pendiente.',
            [],
            409
        );
    }

    responderCompartir(
        'success',
        'Acceso retirado correctamente.'
    );
}

if ($accion === 'aceptar' || $accion === 'rechazar') {
    $stmtSolicitud = $mysqli->prepare("
        SELECT
            cc.id,
            cc.tipo_solicitud,
            cc.incluir_comentarios,
            cc.estado,

            c.id AS certificado_origen_id,
            c.veterinario_id AS propietario_origen_id,
            c.paciente_id,
            c.manual_data,
            c.tipo_estudio,
            c.configuracion_informe_id,
            c.medico_solicitante,
            c.recinto,
            c.motivo,
            c.fecha_examen,
            c.archivo_pdf,
            c.imagenes_json,
            c.contenido_html,
            c.tipo_ingreso,

            p.nombre AS paciente_nombre,
            p.codigo_paciente,
            p.n_chip,
            p.especie,
            p.sexo,
            p.raza,
            p.fecha_nacimiento,

            t.nombre_completo AS tutor_nombre,
            t.rut AS tutor_rut,
            t.telefono AS tutor_telefono,
            t.email AS tutor_email,
            t.direccion AS tutor_direccion

        FROM certificado_compartidos cc

        INNER JOIN certificados c
            ON c.id = cc.certificado_id

        LEFT JOIN pacientes p
            ON p.id = c.paciente_id

        LEFT JOIN tutores t
            ON t.id = p.tutor_id

        WHERE cc.certificado_id = ?
          AND cc.usuario_id = ?
          AND cc.estado = 'pendiente'

        LIMIT 1
    ");

    if (!$stmtSolicitud) {
        responderCompartir(
            'error',
            'No se pudo consultar la solicitud.',
            [],
            500
        );
    }

    $stmtSolicitud->bind_param(
        'ii',
        $certificadoId,
        $usuarioId
    );

    $stmtSolicitud->execute();

    $solicitud =
        $stmtSolicitud
            ->get_result()
            ->fetch_assoc();

    $stmtSolicitud->close();

    if (!$solicitud) {
        responderCompartir(
            'error',
            'La solicitud ya no está pendiente o no te pertenece.',
            [],
            409
        );
    }

    if ($accion === 'rechazar') {
        $stmtRechazar = $mysqli->prepare("
            UPDATE certificado_compartidos
            SET estado = 'rechazado',
                updated_at = NOW()
            WHERE id = ?
              AND usuario_id = ?
              AND estado = 'pendiente'
        ");

        if (!$stmtRechazar) {
            responderCompartir(
                'error',
                'No se pudo preparar el rechazo.',
                [],
                500
            );
        }

        $solicitudId = (int)$solicitud['id'];

        $stmtRechazar->bind_param(
            'ii',
            $solicitudId,
            $usuarioId
        );

        $stmtRechazar->execute();
        $stmtRechazar->close();

        responderCompartir(
            'success',
            'Solicitud rechazada.'
        );
    }

    /*
     * Compartir normal:
     * aceptar solamente activa el acceso.
     */
    if ($solicitud['tipo_solicitud'] === 'compartir') {
        $stmtAceptar = $mysqli->prepare("
            UPDATE certificado_compartidos
            SET estado = 'activo',
                updated_at = NOW()
            WHERE id = ?
              AND usuario_id = ?
              AND estado = 'pendiente'
        ");

        if (!$stmtAceptar) {
            responderCompartir(
                'error',
                'No se pudo preparar la aceptación.',
                [],
                500
            );
        }

        $solicitudId = (int)$solicitud['id'];

        $stmtAceptar->bind_param(
            'ii',
            $solicitudId,
            $usuarioId
        );

        $stmtAceptar->execute();
        $stmtAceptar->close();

        responderCompartir(
            'success',
            'Informe compartido aceptado.'
        );
    }

    /*
     * Clonación:
     * crea tutor, paciente, archivos, certificado y notas
     * independientes para el receptor.
     */
    if ($solicitud['tipo_solicitud'] !== 'clonar') {
        responderCompartir(
            'error',
            'Tipo de solicitud inválido.',
            [],
            400
        );
    }

    $archivosCreados = [];

    try {
        $mysqli->begin_transaction();

        $nuevoTutorId = null;
        $nuevoPacienteId = null;

        /*
         * Si el informe original está asociado a un paciente
         * real, clonamos primero tutor y paciente.
         */
        if (
            !empty($solicitud['paciente_id']) &&
            !empty($solicitud['paciente_nombre'])
        ) {
            $stmtTutor = $mysqli->prepare("
                INSERT INTO tutores
                (
                    veterinario_id,
                    nombre_completo,
                    rut,
                    telefono,
                    email,
                    direccion,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmtTutor) {
                throw new RuntimeException(
                    'No se pudo preparar la copia del tutor.'
                );
            }

            $tutorNombre =
                (string)($solicitud['tutor_nombre'] ?? '');

            if (trim($tutorNombre) === '') {
                $tutorNombre = 'Sin propietario';
            }

            $tutorRut =
                $solicitud['tutor_rut'] !== null
                    ? (string)$solicitud['tutor_rut']
                    : null;

            $tutorTelefono =
                $solicitud['tutor_telefono'] !== null
                    ? (string)$solicitud['tutor_telefono']
                    : null;

            $tutorEmail =
                $solicitud['tutor_email'] !== null
                    ? (string)$solicitud['tutor_email']
                    : null;

            $tutorDireccion =
                $solicitud['tutor_direccion'] !== null
                    ? (string)$solicitud['tutor_direccion']
                    : null;

            $stmtTutor->bind_param(
                'isssss',
                $usuarioId,
                $tutorNombre,
                $tutorRut,
                $tutorTelefono,
                $tutorEmail,
                $tutorDireccion
            );

            if (!$stmtTutor->execute()) {
                throw new RuntimeException(
                    'No se pudo copiar el tutor.'
                );
            }

            $nuevoTutorId =
                (int)$stmtTutor->insert_id;

            $stmtTutor->close();

            if ($nuevoTutorId <= 0) {
                throw new RuntimeException(
                    'No se obtuvo el tutor clonado.'
                );
            }

            $stmtPaciente = $mysqli->prepare("
                INSERT INTO pacientes
                (
                    veterinario_id,
                    tutor_id,
                    nombre,
                    codigo_paciente,
                    n_chip,
                    especie,
                    sexo,
                    raza,
                    fecha_nacimiento,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    NOW(), NOW()
                )
            ");

            if (!$stmtPaciente) {
                throw new RuntimeException(
                    'No se pudo preparar la copia del paciente.'
                );
            }

            $pacienteNombre =
                (string)$solicitud['paciente_nombre'];

            $codigoPaciente =
                $solicitud['codigo_paciente'] !== null
                    ? (string)$solicitud['codigo_paciente']
                    : null;

            $nChip =
                $solicitud['n_chip'] !== null
                    ? (string)$solicitud['n_chip']
                    : null;

            $especie =
                $solicitud['especie'] !== null
                    ? (string)$solicitud['especie']
                    : null;

            $sexo =
                $solicitud['sexo'] !== null
                    ? (string)$solicitud['sexo']
                    : null;

            $raza =
                $solicitud['raza'] !== null
                    ? (string)$solicitud['raza']
                    : null;

            $fechaNacimiento =
                $solicitud['fecha_nacimiento'] !== null
                    ? (string)$solicitud['fecha_nacimiento']
                    : null;

            $stmtPaciente->bind_param(
                'iisssssss',
                $usuarioId,
                $nuevoTutorId,
                $pacienteNombre,
                $codigoPaciente,
                $nChip,
                $especie,
                $sexo,
                $raza,
                $fechaNacimiento
            );

            if (!$stmtPaciente->execute()) {
                throw new RuntimeException(
                    'No se pudo copiar el paciente.'
                );
            }

            $nuevoPacienteId =
                (int)$stmtPaciente->insert_id;

            $stmtPaciente->close();

            if ($nuevoPacienteId <= 0) {
                throw new RuntimeException(
                    'No se obtuvo el paciente clonado.'
                );
            }
        }

        /*
         * Copia física independiente del PDF.
         */
        $nuevoPdf = null;

        if (!empty($solicitud['archivo_pdf'])) {
            $nuevoPdf = copiarArchivoClonSeguro(
                (string)$solicitud['archivo_pdf'],
                'uploads/certificados/informes',
                'cert_' . $usuarioId . '_'
            );

            if ($nuevoPdf !== null) {
                $archivosCreados[] = $nuevoPdf;
            }
        }

        /*
         * Copia física independiente de imágenes.
         */
        $nuevasImagenesJson =
            copiarImagenesClonSeguro(
                $solicitud['imagenes_json'] !== null
                    ? (string)$solicitud['imagenes_json']
                    : null,
                $usuarioId,
                $archivosCreados
            );

        $stmtCertificado = $mysqli->prepare("
            INSERT INTO certificados
            (
                veterinario_id,
                paciente_id,
                manual_data,
                tipo_estudio,
                configuracion_informe_id,
                medico_solicitante,
                recinto,
                motivo,
                fecha_examen,
                archivo_pdf,
                imagenes_json,
                contenido_html,
                tipo_ingreso,
                es_destacado,
                destacado_titulo,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                0, NULL, NOW(), NOW()
            )
        ");

        if (!$stmtCertificado) {
            throw new RuntimeException(
                'No se pudo preparar el certificado clonado.'
            );
        }

        $manualData =
            $solicitud['manual_data'] !== null
                ? (string)$solicitud['manual_data']
                : null;

        /*
         * Si clonamos un paciente real, no necesitamos
         * duplicar sus datos en manual_data.
         */
        if ($nuevoPacienteId !== null) {
            $manualData = null;
        }

        $tipoEstudio =
            $solicitud['tipo_estudio'] !== null
                ? (string)$solicitud['tipo_estudio']
                : null;

        $configuracionId =
            $solicitud['configuracion_informe_id'] !== null
                ? (int)$solicitud['configuracion_informe_id']
                : null;

        $medicoSolicitante =
            $solicitud['medico_solicitante'] !== null
                ? (string)$solicitud['medico_solicitante']
                : null;

        $recinto =
            $solicitud['recinto'] !== null
                ? (string)$solicitud['recinto']
                : null;

        $motivo =
            (string)($solicitud['motivo'] ?? '');

        $fechaExamen =
            (string)$solicitud['fecha_examen'];

        $contenidoHtml =
            $solicitud['contenido_html'] !== null
                ? (string)$solicitud['contenido_html']
                : null;

        $tipoIngreso =
            (string)($solicitud['tipo_ingreso'] ?? 'sistema');

        $stmtCertificado->bind_param(
            'iississssssss',
            $usuarioId,
            $nuevoPacienteId,
            $manualData,
            $tipoEstudio,
            $configuracionId,
            $medicoSolicitante,
            $recinto,
            $motivo,
            $fechaExamen,
            $nuevoPdf,
            $nuevasImagenesJson,
            $contenidoHtml,
            $tipoIngreso
        );

        if (!$stmtCertificado->execute()) {
            throw new RuntimeException(
                'No se pudo crear el certificado clonado.'
            );
        }

        $nuevoCertificadoId =
            (int)$stmtCertificado->insert_id;

        $stmtCertificado->close();

        if ($nuevoCertificadoId <= 0) {
            throw new RuntimeException(
                'No se obtuvo el certificado clonado.'
            );
        }

        /*
         * Las notas se copian desde el propietario original
         * hacia el usuario que recibe la clonación.
         */
        if (
            (int)$solicitud['incluir_comentarios'] === 1
        ) {
            $stmtNotas = $mysqli->prepare("
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
                SELECT
                    ?,
                    ?,
                    organo_clave,
                    organo_nombre,
                    nota,
                    NOW(),
                    NOW()
                FROM certificado_notas
                WHERE certificado_id = ?
                  AND usuario_id = ?
            ");

            if (!$stmtNotas) {
                throw new RuntimeException(
                    'No se pudieron preparar los comentarios.'
                );
            }

            $certificadoOrigenId =
                (int)$solicitud['certificado_origen_id'];

            $propietarioOrigenId =
                (int)$solicitud['propietario_origen_id'];

            $stmtNotas->bind_param(
                'iiii',
                $nuevoCertificadoId,
                $usuarioId,
                $certificadoOrigenId,
                $propietarioOrigenId
            );

            if (!$stmtNotas->execute()) {
                throw new RuntimeException(
                    'No se pudieron copiar los comentarios.'
                );
            }

            $stmtNotas->close();
        }

        $stmtFinal = $mysqli->prepare("
            UPDATE certificado_compartidos
            SET estado = 'clonado',
                updated_at = NOW()
            WHERE id = ?
              AND usuario_id = ?
              AND estado = 'pendiente'
              AND tipo_solicitud = 'clonar'
        ");

        if (!$stmtFinal) {
            throw new RuntimeException(
                'No se pudo finalizar la clonación.'
            );
        }

        $solicitudId = (int)$solicitud['id'];

        $stmtFinal->bind_param(
            'ii',
            $solicitudId,
            $usuarioId
        );

        if (!$stmtFinal->execute()) {
            throw new RuntimeException(
                'No se pudo marcar la clonación como completada.'
            );
        }

        $stmtFinal->close();

        $mysqli->commit();

        responderCompartir(
            'success',
            'Informe clonado correctamente.',
            [
                'clonado' => true,
                'certificado_id' => $nuevoCertificadoId
            ]
        );

    } catch (Throwable $e) {
        $mysqli->rollback();

        $raiz =
            realpath(__DIR__ . '/../../../');

        if ($raiz !== false) {
            foreach ($archivosCreados as $rutaCreada) {
                $rutaCreada = ltrim(
                    str_replace('\\\\', '/', $rutaCreada),
                    '/'
                );

                $fisica =
                    $raiz . '/' . $rutaCreada;

                if (is_file($fisica)) {
                    @unlink($fisica);
                }
            }
        }

        error_log(
            '[certificado_compartir][clonar] ' .
            $e->getMessage()
        );

        responderCompartir(
            'error',
            'No se pudo clonar el informe.',
            [],
            500
        );
    }
}

responderCompartir('error', 'Acción inválida.', [], 400);
