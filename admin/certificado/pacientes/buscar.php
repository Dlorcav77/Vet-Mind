<?php

require_once("../../config.php");

$mysqli = conn();
$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$q = trim($_GET['q'] ?? '');

if ($usuario_id <= 0) {
    echo '<div class="alert alert-danger mb-0">Sesión inválida.</div>';
    exit;
}

if (strlen($q) < 3) {
    echo '<p class="text-muted">Ingrese al menos 3 caracteres.</p>';
    exit;
}

$stmt = $mysqli->prepare("
    SELECT
        p.id,
        p.nombre,
        p.codigo_paciente,
        t.nombre_completo,
        p.especie,
        p.raza,
        p.fecha_nacimiento,
        p.sexo
    FROM pacientes p
    INNER JOIN tutores t ON p.tutor_id = t.id
    WHERE (
            p.nombre LIKE CONCAT('%', ?, '%')
            OR p.codigo_paciente LIKE CONCAT('%', ?, '%')
            OR t.nombre_completo LIKE CONCAT('%', ?, '%')
            OR t.rut LIKE CONCAT('%', ?, '%')
          )
      AND t.veterinario_id = ?
    ORDER BY p.nombre ASC
    LIMIT 10
");

if (!$stmt) {
    echo '<div class="alert alert-danger mb-0">Error preparando búsqueda: '
        . htmlspecialchars($mysqli->error)
        . '</div>';
    exit;
}

$stmt->bind_param(
    "ssssi",
    $q,
    $q,
    $q,
    $q,
    $usuario_id
);

$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<p class="text-muted">Sin resultados.</p>';
    exit;
}

echo '<table class="table table-hover">';
echo '<thead>
        <tr>
            <th>Mascota</th>
            <th>Especie</th>
            <th>Raza</th>
            <th>Tutor</th>
            <th>Acción</th>
        </tr>
      </thead>';
echo '<tbody>';

while ($row = $res->fetch_assoc()) {
    $edad = '';

    if (!empty($row['fecha_nacimiento'])) {
        try {
            $fechaNacimiento = new DateTime($row['fecha_nacimiento']);
            $hoy = new DateTime();
            $diff = $hoy->diff($fechaNacimiento);

            if ($diff->y > 0) {
                $edad = $diff->y . ' años';

                if ($diff->m > 0) {
                    $edad .= ' ' . $diff->m . ' meses';
                }
            } elseif ($diff->m > 0) {
                $edad = $diff->m . ' meses';
            } else {
                $edad = $diff->d . ' días';
            }
        } catch (Throwable $e) {
            $edad = '';
        }
    }

    $nombreMascota = trim((string)($row['nombre'] ?? ''));
    $codigoPaciente = trim((string)($row['codigo_paciente'] ?? ''));

    $mascotaMostrar = $nombreMascota;

    if ($codigoPaciente !== '') {
        $mascotaMostrar .= ' (' . $codigoPaciente . ')';
    }

    echo '<tr>';

    echo '<td>'
        . htmlspecialchars($mascotaMostrar)
        . '</td>';

    echo '<td>'
        . htmlspecialchars($row['especie'] ?? '')
        . '</td>';

    echo '<td>'
        . htmlspecialchars($row['raza'] ?? '')
        . '</td>';

    echo '<td>'
        . htmlspecialchars($row['nombre_completo'] ?? '')
        . '</td>';

    echo '<td>
            <button
                type="button"
                class="btn btn-sm btn-success"
                onclick="seleccionarPaciente('
                    . (int)($row['id'] ?? 0) . ', \''
                    . addslashes($row['nombre'] ?? '') . '\', \''
                    . addslashes($row['nombre_completo'] ?? '') . '\', \''
                    . addslashes($row['especie'] ?? '') . '\', \''
                    . addslashes($row['raza'] ?? '') . '\', \''
                    . addslashes($edad ?? '') . '\', \''
                    . addslashes($row['sexo'] ?? '') . '\', \''
                    . addslashes($row['fecha_nacimiento'] ?? '') . '\', \''
                    . addslashes($row['codigo_paciente'] ?? '') . '\'
                )">
                Seleccionar
            </button>
          </td>';

    echo '</tr>';
}

echo '</tbody>';
echo '</table>';