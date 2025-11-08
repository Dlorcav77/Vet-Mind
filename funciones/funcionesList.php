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





// function lisAplicaciones($aplicaciones = array())
// {
//     $mysqli = conn();
//     global $root;
    
//     $categorias = array_map(function($categoria) use ($mysqli) {
//         return "'" . $mysqli->real_escape_string($categoria) . "'";
//     }, $root);
//     $categoriasList = implode(",", $categorias);

//     $sel = "
//         SELECT * 
//         FROM aplicaciones 
//         WHERE deleted_at IS NULL
//         " . (!empty($categorias) ? "AND categoria IN ($categoriasList)" : "") . "
//         ORDER BY
//           FIELD(categoria, 'Administrador', 'Documentos', 'Contratos'), 
//           grupo, id";
//     $res = $mysqli->query($sel);

//     $menu  = "";
//     $currentCategory = "";
//     $group = "";

//     while ($fila = $res->fetch_assoc()) {
//         $id     = $fila['id'];
//         $grupo  = $fila['grupo'];
//         $accion = $fila['accion'];
//         $categoria = $fila['categoria']; 
        
//         if ($categoria != $currentCategory) {
//             if ($currentCategory != "") {
//                 $menu .= "</optgroup>";
//             }
//             $menu .= "<optgroup label='---------------- $categoria ----------------'>";
//             $currentCategory = $categoria;
//         }

//         if ($grupo != $group) {
//             if ($group != "") {
//                 $menu .= "</optgroup>";
//             }
//             $menu .= "<optgroup label='$grupo'>";
//             $group = $grupo;
//         }

//         $selected = in_array($id, $aplicaciones) ? "selected" : "";

//         $menu .= "<option value='$id' $selected>$accion</option>";
//     }

//     if ($group != "") {
//         $menu .= "</optgroup>";
//     }

//     print $menu;
// }











// function lisCategoriaContratos($idCat = null)
// {
//  global $codsede;
//  $mysqli = conn();
//  $sel    = "SELECT * FROM categoriaContratos WHERE codsede='$codsede' AND deleted_at IS NULL ORDER BY id";
//  $res    = $mysqli->query($sel);

//  $menu="";
//  while($fila = $res->fetch_assoc())
//  {
//   $id     = $fila['id'];
//   $nombre = $fila['nombre'];
//   $desc   = $fila['descripcion'];

//   if($idCat==$id){$selected="selected";}else{$selected="";}

//   $menu  = $menu."<option value='$id' $selected>"."$nombre - $desc"."</option>";
//  }
//  print "$menu";
// }


// function lisCategoriaDocumentos($idCat = null)
// {
//  global $codsede;
//  $mysqli = conn();
//  $sel    = "SELECT * FROM categoriaDocumentos WHERE codsede='$codsede' AND deleted_at IS NULL ORDER BY id";
//  $res    = $mysqli->query($sel);

//  $menu="";
//  while($fila = $res->fetch_assoc())
//  {
//   $id     = $fila['id'];
//   $nombre = $fila['nombre'];
//   // $desc   = $fila['descripcion'];

//   if($idCat==$id){$selected="selected";}else{$selected="";}

//   $menu  = $menu."<option value='$id' $selected>"."$nombre"."</option>";
//  }
//  print "$menu";
// }



// function lisTipo($tipo_id = null)
// {
//  global $codsede;
//  $mysqli = conn();
//  $sel    = "SELECT * FROM tipo_noticias WHERE codsede='$codsede' AND deleted_at IS NULL ORDER BY id";
//  $res    = $mysqli->query($sel);

//  $menu="";
//  while($fila = $res->fetch_assoc())
//  {
//   $id     = $fila['id'];
//   $nombre = $fila['nombre'];
//   $icono = $fila['icono'];
//   $color = $fila['color'];
  
//   if($tipo_id==$id){$selected="selected";}else{if($nombre=="Todos" || $nombre=="Todo" || $nombre=="All"){$selected="selected";}else{$selected="";}}
  
//   $menu .= "<option value='$id' data-icon='$icono' data-color='$color' $selected>$nombre</option>";
// }
//  print "$menu";
// }



// function lisArea($area_id = null)
// {
//  global $codsede;
//  $mysqli = conn();
//  $sel    = "SELECT * FROM areas WHERE codsede='$codsede' AND deleted_at IS NULL ORDER BY  id";
//  $res    = $mysqli->query($sel);

//  $menu="";
//  while($fila = $res->fetch_assoc())
//  {
//   $id     = $fila['id'];
//   $nombre = $fila['nombre'];
  
//   if($area_id==$id){$selected="selected";}else{if($nombre=="Todos" || $nombre=="Todo" || $nombre=="All"){$selected="selected";}else{$selected="";}}
  
//   $menu  = $menu."<option value='$id' $selected>"."$nombre"."</option>";
//  }
//  print "$menu";
// }



// function lisEmpresas($empresa_id = null)
// {
//  global $codsede;
//  $mysqli = conn();
//  $sel    = "SELECT * FROM empresas WHERE codsede='$codsede' AND deleted_at IS NULL ORDER BY  id";
//  $res    = $mysqli->query($sel);

//  $menu="";
//  while($fila = $res->fetch_assoc())
//  {
//   $id         = $fila['id'];
//   $rut        = $fila['rut'];
//   $razon_social = $fila['razon_social'];
  
//   if($empresa_id==$id){$selected="selected";}else{$selected="";}
  
//   $menu  = $menu."<option value='$id' $selected>"."$rut - $razon_social"."</option>";
//  }
//  print "$menu";
// }

// function lisClientes($cliente_id = null)
// {
//     global $codsede;
//     $mysqli = conn();
//     $sel    = "SELECT * FROM clientes WHERE deleted_at IS NULL ORDER BY id";
//     $res    = $mysqli->query($sel);

//     $menu = "";
//     while ($fila = $res->fetch_assoc()) {
//         $id      = $fila['id'];
//         $rut     = $fila['rut'];
//         $nombre  = $fila['nombre'];

//         if ($cliente_id == $id) {
//             $selected = "selected";
//         } else {
//             $selected = "";
//         }

//         $menu = $menu . "<option value='$id' $selected>" . "$nombre" . "</option>";
//     }
//     print "$menu";
// }

// function lisTiposServicios($tipo_servicio_id = null)
// {
//     $mysqli = conn();
//     $sel    = "SELECT * FROM tipos_servicios WHERE deleted_at IS NULL ORDER BY id";
//     $res    = $mysqli->query($sel);

//     $menu = "";
//     while ($fila = $res->fetch_assoc()) {
//         $id      = $fila['id'];
//         $nombre  = $fila['nombre'];

//         $selected = ($tipo_servicio_id == $id) ? "selected" : "";

//         $menu .= "<option value='$id' $selected>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</option>";
//     }
//     print $menu;
// }

// function lisDocumentos($documentos_id = null)
// {
//  global $codsede;
//  $mysqli = conn();
//  $sel    = "SELECT * FROM documentos WHERE codsede='$codsede' AND estado = 'activo' AND deleted_at IS NULL ORDER BY fecha_publicacion DESC";
//  $res    = $mysqli->query($sel);

//  $menu="";
//  while($fila = $res->fetch_assoc())
//  {
//   $id            = $fila['id'];
//   $num_documento = $fila['num_documento'];
//   $categoria_id  = $fila['categoria_id'];
//   $version       = $fila['version'];

//   $selC  = "SELECT * FROM categorias WHERE codsede='$codsede' AND id='$categoria_id' AND deleted_at IS NULL ORDER BY id";
//   $resC  = $mysqli->query($selC);
//   $filaC = $resC->fetch_assoc();

//   $nombre            = $filaC['nombre'];
  
//   if($documentos_id==$id){$selected="selected";}else{$selected="";}
  
//   $menu  = $menu."<option value='$id' $selected>"."$num_documento - $version - $nombre"."</option>";
//  }
//  print "$menu";
// }



// function lisPerfilesAccesos($perfiles_seleccionados = [])
// {
//   global $codsede;
//   $mysqli = conn();
//   $sel    = "SELECT * FROM perfiles WHERE codsede='$codsede' AND nombre != 'superAdmin' ORDER BY id";
//   $res    = $mysqli->query($sel);
 
//   if($perfiles_seleccionados){
//     $menu = "<option value='todos' " . (in_array('Todos', $perfiles_seleccionados) ? "selected" : "") . ">Todos</option>";
//   }else{
//     $menu = "<option value='todos' " . (empty($perfiles_seleccionados) ? "selected" : "") . ">Todos</option>";
//   }

//   while($fila = $res->fetch_assoc())
//   {
//     $id          = $fila['id'];
//     $nombre      = $fila['nombre'];
//     $descripcion = $fila['descripcion'];

//     $selected = in_array($nombre, $perfiles_seleccionados) ? "selected" : "";
    
//     $menu  = $menu."<option value='$id' $selected>"."$nombre"."</option>";
//   }
//   print "$menu";
// }
