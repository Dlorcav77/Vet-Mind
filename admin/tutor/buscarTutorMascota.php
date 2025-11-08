<?php
require_once("../config.php");
$mysqli = conn();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo "<p class='text-danger'>Debe ingresar al menos 3 caracteres.</p>";
    exit;
}

// Buscar tutores o mascotas que coincidan
$query = "SELECT t.id as tutor_id, t.nombre_completo as tutor_nombre, t.rut, 
                p.id as paciente_id, p.nombre as mascota_nombre, p.especie, p.n_chip
          FROM tutores t
          LEFT JOIN pacientes p ON t.id = p.tutor_id
          WHERE(p.veterinario_id = ?) 
            AND (t.rut LIKE CONCAT('%', ?, '%')
            OR t.nombre_completo LIKE CONCAT('%', ?, '%')
            OR p.nombre LIKE CONCAT('%', ?, '%')
            OR p.n_chip LIKE CONCAT('%', ?, '%'))
          ORDER BY t.nombre_completo, p.nombre
          LIMIT 20
        ";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("issss", $usuario_id, $q, $q, $q, $q);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<p class='text-warning'>No se encontraron resultados.</p>";
    exit;
}

echo "<table class='table table-sm table-hover'>";
echo "<thead><tr>
        <th>Tutor</th>
        <th>RUT</th>
        <th>Mascota</th>
        <th>Chip</th>
        <th>especie</th>
        <th>Acciones</th>
      </tr></thead><tbody>";


while ($row = $res->fetch_assoc()) {
    echo "<tr>
            <td>".htmlspecialchars($row['tutor_nombre'])."</td>
            <td>".htmlspecialchars($row['rut'])."</td>
            <td>".htmlspecialchars($row['mascota_nombre'] ?? '-')."</td>
            <td>".resaltar(htmlspecialchars($row['n_chip'] ?? '-'), $q)."</td>
            <td>".htmlspecialchars($row['especie'] ?? '-')."</td>
            <td>
              <button class=\"btn btn-sm btn-primary\" onclick=\"abrirMascotasDesdeBusqueda(".$row['tutor_id'].")\">
                  Ver Mascotas
              </button>
            </td>
          </tr>";
}


echo "</tbody></table>";

function resaltar($texto, $busqueda) {
    return preg_replace("/(".preg_quote($busqueda, '/').")/i", '<mark>$1</mark>', $texto);
}

?>
<script>
// 👉 Abre el modal de Mascotas DESPUÉS de cerrar el buscador
function abrirMascotasDesdeBusqueda(tutorId) {
    $('#modalBuscar').one('hidden.bs.modal', function () {
        verPacientes(tutorId); // Cuando se cierre, abrir el de mascotas
    }).modal('hide'); // 🔥 Primero cerrar el buscador
}

// ✅ Permitir múltiples modales apilados correctamente
$(document).on('show.bs.modal', '.modal', function () {
    var zIndex = 1050 + (10 * $('.modal:visible').length);
    $(this).css('z-index', zIndex);
    setTimeout(function() {
        $('.modal-backdrop').not('.modal-stack')
            .css('z-index', zIndex - 1)
            .addClass('modal-stack');
    }, 0);
});

// 🧹 Limpieza cuando no hay más modales abiertos
$(document).on('hidden.bs.modal', function () {
    if ($('.modal.show').length === 0) {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    }
});
</script>
