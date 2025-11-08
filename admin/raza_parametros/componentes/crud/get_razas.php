<?php
require_once("../../../../funciones/conn/conn.php");
$mysqli = conn();

$id = intval($_GET['especie_id']);
$res = $mysqli->query("SELECT * FROM razas WHERE especie_id = $id ORDER BY nombre ASC");

if ($res->num_rows === 0) {
  echo "<tr><td colspan='4'>No hay razas registradas para esta especie.</td></tr>";
  exit;
}

while ($row = $res->fetch_assoc()) {
  echo "<tr>
          <td>".htmlspecialchars($row['nombre'])."</td>
          <td>".htmlspecialchars($row['tamano'] ?? '-')."</td>
          <td>".($row['activo'] ? 'Activo' : 'Inactivo')."</td>
          <td>
            <button class='btn btn-sm btn-info btn-editar' 
                    data-id='{$row['id']}'
                    data-nombre='".htmlspecialchars($row['nombre'], ENT_QUOTES)."'
                    data-tamano='".htmlspecialchars($row['tamano'], ENT_QUOTES)."'>
              Editar
            </button>
            <button class='btn btn-sm btn-danger btn-eliminar' 
                    data-id='{$row['id']}'>
              Eliminar
            </button>
          </td>
        </tr>";

}
