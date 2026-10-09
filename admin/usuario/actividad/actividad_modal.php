<?php

require_once("../../config.php");
credenciales('usuario', 'listar');

$mysqli = conn();

$usuarioId = (int)($_GET['id'] ?? 0);

if ($usuarioId <= 0) {
    echo '<div class="alert alert-danger mb-0">Usuario inválido.</div>';
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT
        id,
        nombres,
        apellidos,
        email
     FROM usuarios
     WHERE id = ?
       AND deleted_at IS NULL
     LIMIT 1"
);

$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    echo '<div class="alert alert-danger mb-0">Usuario no encontrado.</div>';
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT
        COUNT(*) AS total_sesiones,
        SUM(
            CASE
                WHEN cerrada_en IS NULL
                 AND ultima_actividad >= (NOW() - INTERVAL 2 MINUTE)
                THEN 1
                ELSE 0
            END
        ) AS conexiones_activas,
        MAX(ultima_actividad) AS ultima_actividad
     FROM usuario_sesiones
     WHERE usuario_id = ?"
);

$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conexionesActivas = (int)($actividad['conexiones_activas'] ?? 0);
$totalSesiones = (int)($actividad['total_sesiones'] ?? 0);
$ultimaActividad = $actividad['ultima_actividad'] ?? null;

$stmt = $mysqli->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS hoy,
        SUM(CASE WHEN created_at >= (NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS ultimos_7,
        SUM(CASE WHEN created_at >= (NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS ultimos_30,
        MAX(created_at) AS ultimo_informe
     FROM certificados
     WHERE veterinario_id = ?"
);

$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$informes = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $mysqli->prepare(
    "SELECT
        ip,
        user_agent,
        iniciada_en,
        ultima_actividad,
        cerrada_en
     FROM usuario_sesiones
     WHERE usuario_id = ?
     ORDER BY ultima_actividad DESC
     LIMIT 50"
);

$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$sesiones = $stmt->get_result();

$nombreCompleto = trim(
    (string)($usuario['nombres'] ?? '')
    . ' '
    . (string)($usuario['apellidos'] ?? '')
);

function fechaChile(?string $fecha): string
{
    $fecha = trim((string)$fecha);

    if ($fecha === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable(
            $fecha,
            new DateTimeZone('UTC')
        );

        return $dt
            ->setTimezone(new DateTimeZone('America/Santiago'))
            ->format('d-m-Y H:i:s');
    } catch (Throwable $e) {
        return $fecha;
    }
}
?>

<div class="mb-3">
  <h5 class="mb-1">
    <?= htmlspecialchars($nombreCompleto) ?>
  </h5>
  <div class="text-muted">
    <?= htmlspecialchars((string)($usuario['email'] ?? '')) ?>
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <h6>Estado</h6>

        <?php if ($conexionesActivas > 0): ?>
          <span class="badge bg-success mb-2">En línea</span>
        <?php else: ?>
          <span class="badge bg-secondary mb-2">Desconectado</span>
        <?php endif; ?>

        <div>Conexiones activas: <strong><?= $conexionesActivas ?></strong></div>
        <div>Total de sesiones: <strong><?= $totalSesiones ?></strong></div>
        <div>
          Última actividad:
          <strong>
            <?= $ultimaActividad
                ? htmlspecialchars(fechaChile((string)$ultimaActividad))
                : 'Sin actividad registrada' ?>
          </strong>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <h6>Informes</h6>

        <div class="row">
          <div class="col-md-3 mb-2">
            Total<br>
            <strong><?= (int)($informes['total'] ?? 0) ?></strong>
          </div>

          <div class="col-md-3 mb-2">
            Hoy<br>
            <strong><?= (int)($informes['hoy'] ?? 0) ?></strong>
          </div>

          <div class="col-md-3 mb-2">
            7 días<br>
            <strong><?= (int)($informes['ultimos_7'] ?? 0) ?></strong>
          </div>

          <div class="col-md-3 mb-2">
            30 días<br>
            <strong><?= (int)($informes['ultimos_30'] ?? 0) ?></strong>
          </div>
        </div>

        <div class="mt-2">
          Último informe:
          <strong>
            <?= !empty($informes['ultimo_informe'])
                ? htmlspecialchars(fechaChile((string)$informes['ultimo_informe']))
                : 'Sin informes' ?>
          </strong>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-0">
  <div class="card-header">
    <strong>Conexiones recientes</strong>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-bordered mb-0">
        <thead>
          <tr>
            <th>Estado</th>
            <th>IP</th>
            <th>Inicio</th>
            <th>Última actividad</th>
            <th>Navegador / dispositivo</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($sesion = $sesiones->fetch_assoc()): ?>
            <?php
              $activa =
                  empty($sesion['cerrada_en'])
                  && strtotime((string)$sesion['ultima_actividad']) >= time() - 120;
            ?>
            <tr>
              <td>
                <?php if ($activa): ?>
                  <span class="badge bg-success">Activa</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Finalizada</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)($sesion['ip'] ?? '')) ?></td>
              <td><?= htmlspecialchars(fechaChile((string)$sesion['iniciada_en'])) ?></td>
              <td><?= htmlspecialchars(fechaChile((string)$sesion['ultima_actividad'])) ?></td>
              <td style="max-width:500px; white-space:normal;">
                <?= htmlspecialchars((string)($sesion['user_agent'] ?? '')) ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
