<?php
require_once("../../../../funciones/conn/conn.php");
$mysqli = conn();

$especie_id = isset($_GET['especie_id']) ? (int)$_GET['especie_id'] : 0;

$sql = "SELECT 
          op.id,
          op.organo,
          e.nombre AS especie,
          e.id     AS especie_id,
          COALESCE(op.tamano, '')          AS tamano,
          COALESCE(op.etapa,  '')          AS etapa,
          op.tamano_min,
          op.tamano_max,
          COALESCE(op.tamano_min_critico, '') AS tamano_min_error,
          COALESCE(op.tamano_max_critico, '') AS tamano_max_error,
          op.unidad
        FROM organos_parametros op
        JOIN especies e ON e.id = op.especie_id";

if ($especie_id > 0) {
  $sql .= " WHERE op.especie_id = ?";
}

$sql .= " ORDER BY e.nombre ASC, op.organo ASC";

if ($especie_id > 0) {
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('i', $especie_id);
} else {
  $stmt = $mysqli->prepare($sql);
}

$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
  $btns = '
    <button class="btn btn-sm btn-outline-primary btn-editar-organo"
      data-id="'.$r['id'].'"
      data-organo="'.htmlspecialchars($r['organo']).'"
      data-especie="'.htmlspecialchars($r['especie']).'"
      data-especieid="'.$r['especie_id'].'"
      data-tamano="'.htmlspecialchars($r['tamano']).'"
      data-etapa="'.htmlspecialchars($r['etapa']).'"
      data-min="'.$r['tamano_min'].'"
      data-max="'.$r['tamano_max'].'"
      data-minerror="'.htmlspecialchars($r['tamano_min_error']).'"
      data-maxerror="'.htmlspecialchars($r['tamano_max_error']).'"
      data-unidad="'.htmlspecialchars($r['unidad']).'"
    ><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
    <button class="btn btn-sm btn-outline-danger btn-eliminar-organo" data-id="'.$r['id'].'"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
  ';

  echo "<tr>
          <td>".htmlspecialchars($r['organo'])."</td>
          <td>".htmlspecialchars($r['especie'])."</td>
          <td>".($r['tamano'] !== '' ? htmlspecialchars($r['tamano']) : '-')."</td>
          <td>".($r['etapa']  !== '' ? htmlspecialchars($r['etapa'])  : '-')."</td>
          <td>".htmlspecialchars($r['tamano_min'])."
          - ".htmlspecialchars($r['tamano_max'])."</td>
          <td>".($r['tamano_min_error'] !== '' ? htmlspecialchars($r['tamano_min_error']) : '-')."
          - ".($r['tamano_max_error'] !== '' ? htmlspecialchars($r['tamano_max_error']) : '-')."</td>
          <td>".htmlspecialchars($r['unidad'])."</td>
          <td>".$btns."</td>
        </tr>";
}
