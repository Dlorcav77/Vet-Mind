<?php
function lisAplicaciones($permisos = array())
{
    $mysqli = conn();

    $sel = "
        SELECT 
            mp.id AS permiso_id,
            mp.accion,
            ma.nombre AS modulo
        FROM modulo_permisos mp
        INNER JOIN modulos_aplicaciones ma ON mp.modulo_id = ma.id
        ORDER BY ma.nombre, mp.accion";

    $res = $mysqli->query($sel);

    $menu = "";
    $currentModulo = "";

    while ($fila = $res->fetch_assoc()) {
        $permiso_id = $fila['permiso_id'];
        $accion     = $fila['accion'];
        $modulo     = $fila['modulo'];

        // Agrupar por módulo (como si fuera un "grupo")
        if ($modulo != $currentModulo) {
            if ($currentModulo != "") {
                $menu .= "</optgroup>";
            }
            $menu .= "<optgroup label='$modulo'>";
            $currentModulo = $modulo;
        }

        $selected = in_array($permiso_id, $permisos) ? "selected" : "";
        $menu .= "<option value='$permiso_id' $selected>$accion</option>";
    }

    if ($currentModulo != "") {
        $menu .= "</optgroup>";
    }

    echo $menu;
}

function lisUsuarios($usuario_id = null)
{
  global $codsede;
  $mysqli = conn();
  $sel    = "SELECT * FROM usuarios WHERE deleted_at IS NULL ORDER BY  id";
  $res    = $mysqli->query($sel);

  while ($fila = $res->fetch_assoc()) {
      $id = $fila['id'];
      $rut = $fila['rut'];
      $nombres = $fila['nombres'];
      $apellidos = $fila['apellidos'];
      $email = $fila['email'];

      $selected = ($usuario_id == $id) ? "selected" : "";

      echo "<option value='$id' $selected>$nombres $apellidos - $email</option>";
  }
}



function lisPerfiles($perfiles_id = null)
{
    global $codsede, $root;
    
    $mysqli = conn();


    $sel = "
        SELECT * 
        FROM perfiles 
        WHERE deleted_at IS NULL 
        ORDER BY id";
    $res = $mysqli->query($sel);

    $menu = "";
    while ($fila = $res->fetch_assoc()) {
        $id          = $fila['id'];
        $nombre      = $fila['nombre'];
        $descripcion = $fila['descripcion'];

        $selected = ($perfiles_id == $id) ? "selected" : "";

        $menu .= "<option value='$id' $selected>$nombre - $descripcion</option>";
    }
    print $menu;
}



function lisRazas(?string $especieNombre = null, ?string $razaSeleccionadaNombre = null, ?int $razaSeleccionadaId = null)
{
    $mysqli = conn();

    $especie_id = null;
    if (!empty($especieNombre)) {
        $sqlEsp = "SELECT id FROM especies WHERE LOWER(nombre)=LOWER(?) LIMIT 1";
        if ($st = $mysqli->prepare($sqlEsp)) {
            $st->bind_param('s', $especieNombre);
            $st->execute();
            $rs = $st->get_result();
            if ($row = $rs->fetch_assoc()) $especie_id = (int)$row['id'];
            $st->close();
        }
    }

    $sql = "SELECT r.id, r.nombre, e.nombre AS especie
              FROM razas r
              JOIN especies e ON e.id = r.especie_id
             WHERE r.activo = 1";
    if ($especie_id) {
        $sql .= " AND r.especie_id = " . (int)$especie_id;
    }

    // 👇 Orden: Canino (0), Felino (1), resto (2); dentro, alfabético
    $sql .= " ORDER BY 
                CASE 
                  WHEN e.nombre = 'Canino' THEN 0
                  WHEN e.nombre = 'Felino' THEN 1
                  ELSE 2
                END,
                e.nombre ASC,
                r.nombre ASC";

    $res = $mysqli->query($sql);

    echo "<option value=''>Seleccione raza...</option>";

    $especieActual = null;
    $razaSelNorm = mb_strtolower((string)$razaSeleccionadaNombre, 'UTF-8');

    while ($fila = $res->fetch_assoc()) {
        $id      = (int)$fila['id'];
        $nombre  = (string)$fila['nombre'];
        $especie = (string)$fila['especie'];

        if ($especieActual !== $especie) {
            if ($especieActual !== null) echo "</optgroup>";
            echo "<optgroup label='" . htmlspecialchars($especie, ENT_QUOTES, 'UTF-8') . "'>";
            $especieActual = $especie;
        }

        $selected = '';
        if ($razaSeleccionadaId !== null && $razaSeleccionadaId === $id) {
            $selected = 'selected';
        } elseif ($razaSeleccionadaId === null && $razaSeleccionadaNombre !== null &&
                  mb_strtolower($nombre, 'UTF-8') === $razaSelNorm) {
            $selected = 'selected';
        }

        echo "<option value='" . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . "' $selected>"
           . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')
           . "</option>";
    }
    if ($especieActual !== null) echo "</optgroup>";
}

function lisEspecies(?string $especieSeleccionadaNombre = null, ?int $especieSeleccionadaId = null)
{
    $mysqli = conn();

    $sql = "
        SELECT id, nombre
        FROM especies
        ORDER BY
            CASE
                WHEN nombre = 'Canino' THEN 0
                WHEN nombre = 'Felino' THEN 1
                ELSE 2
            END,
            nombre ASC
    ";

    $res = $mysqli->query($sql);

    echo "<option value=''>Seleccione especie...</option>";

    if (!$res) {
        return;
    }

    $especieSelNorm = mb_strtolower(
        trim((string)$especieSeleccionadaNombre),
        'UTF-8'
    );

    while ($fila = $res->fetch_assoc()) {
        $id     = (int)$fila['id'];
        $nombre = trim((string)$fila['nombre']);

        $selected = '';

        if (
            $especieSeleccionadaId !== null &&
            $especieSeleccionadaId === $id
        ) {
            $selected = ' selected';
        } elseif (
            $especieSeleccionadaId === null &&
            $especieSeleccionadaNombre !== null &&
            mb_strtolower($nombre, 'UTF-8') === $especieSelNorm
        ) {
            $selected = ' selected';
        }

        echo "<option value='" . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . "'" . $selected . ">"
           . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')
           . "</option>";
    }
}