<?php
require_once("../../config.php");
$mysqli = conn();
$q = trim($_GET['q'] ?? '');

if (strlen($q) < 3) {
    echo '<p class="text-muted">Ingrese al menos 3 caracteres.</p>';
    exit;
}

$stmt = $mysqli->prepare("
    SELECT  
        p.id, 
        p.nombre, 
        t.nombre_completo,
        p.especie,
        p.raza,
        p.fecha_nacimiento,
        p.sexo
    FROM pacientes p
    JOIN tutores t ON p.tutor_id = t.id
    WHERE (p.nombre LIKE CONCAT('%', ?, '%')
           OR t.nombre_completo LIKE CONCAT('%', ?, '%')
           OR t.rut LIKE CONCAT('%', ?, '%'))
           AND t.veterinario_id = ?
    LIMIT 10
");
$stmt->bind_param("sssi", $q, $q, $q, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<p class="text-muted">Sin resultados.</p>';
    exit;
}

echo '<table class="table table-hover">';
echo '<thead><tr>
                <th>Mascota</th>
                <th>Especie</th>
                <th>Raza</th>
                <th>Tutor</th>
                <th>Acción</th>
            </tr>
        </thead>
    <tbody>';
while ($row = $res->fetch_assoc()) {

    // 🔥 Calcular edad
    $edad = '';
    if (!empty($row['fecha_nacimiento'])) {
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
    }

    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['nombre']) . '</td>';
    echo '<td>' . htmlspecialchars($row['especie']) . '</td>';
    echo '<td>' . htmlspecialchars($row['raza']) . '</td>';
    echo '<td>' . htmlspecialchars($row['nombre_completo']) . '</td>';
    echo '<td><button type="button" class="btn btn-sm btn-success" onclick="seleccionarPaciente('
        . ($row['id'] ?? 0) . ', \'' 
        . addslashes($row['nombre'] ?? '') . '\', \'' 
        . addslashes($row['nombre_completo'] ?? '') . '\', \'' 
        . addslashes($row['especie'] ?? '') . '\', \'' 
        . addslashes($row['raza'] ?? '') . '\', \'' 
        . addslashes($edad ?? '') . '\', \'' 
        . addslashes($row['sexo'] ?? '') . '\')">Seleccionar</button></td>';
    echo '</tr>';
}
echo '</tbody></table>';
?>
