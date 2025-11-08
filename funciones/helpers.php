<?php
function tiene_acceso($modulo, $accion = null) {
  if (!isset($_SESSION['acceso_aplicaciones'][$modulo])) return false;
  if ($accion === null) return true;
  return in_array($accion, $_SESSION['acceso_aplicaciones'][$modulo]);
}

function obtener_modulos_con_listar($perfil_id) {
  $mysqli = conn();

  $sql = "
    SELECT 
        m.seccion,
        m.modulo,
        m.nombre,
        m.icono,
        m.archivo_base
    FROM modulos_aplicaciones m
    INNER JOIN modulo_permisos p ON m.id = p.modulo_id
    INNER JOIN perfiles_permisos pp ON p.id = pp.permiso_id
    WHERE pp.perfil_id = ?
    AND p.accion = 'listar'
    ORDER BY m.orden, m.seccion ASC
  ";

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("i", $perfil_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $modulos = [];

  while ($fila = $res->fetch_assoc()) {
      $seccion = $fila['seccion'];
      if (!isset($modulos[$seccion])) {
          $modulos[$seccion] = [];
      }

      $modulos[$seccion][] = [
          'modulo'       => $fila['modulo'],
          'nombre'       => $fila['nombre'],
          'icono'        => $fila['icono'],
          'archivo_base' => $fila['archivo_base']
      ];
  }

  $stmt->close();
  return $modulos;
}


?>