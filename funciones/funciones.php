<?php


function validar_length($field, $value, $max_length, $null = false) {
   if (empty($value) && !$null) {
      // Enviar respuesta como JSON para manejar en el lado del cliente
      echo json_encode([
         'status' => 'error',
         'message' => "$field no puede estar vacío."
      ]);
      exit;
   }

   if (strlen($value) > $max_length) {
      $length = strlen($value);
      // Enviar respuesta como JSON para manejar en el lado del cliente
      echo json_encode([
         'status' => 'error',
         'message' => "$field tiene $length caracteres (máximo permitido: $max_length)"
      ]);
      exit;
   }
}

function validar_enum($field, $value, $allowed_values, $null = false) {
   if (empty($value) && !$null) {
      echo json_encode([
         'status' => 'error',
         'message' => "$field no puede estar vacío."
      ]);
      exit;
   }

   if (!in_array($value, $allowed_values, true)) {
      $valores_permitidos = implode(", ", $allowed_values);
      echo json_encode([
         'status' => 'error',
         'message' => "$field debe ser uno de los siguientes valores: $valores_permitidos."
      ]);
      exit;
   }
}



function logg($des){
   $mysqli = conn();
   global $codsede, $usuario_id;

   $nomap = $_SERVER['SCRIPT_NAME'];
   $apli  = explode("/", $nomap);
   $l     = count($apli);
   $nomap = $apli[$l - 1];

   $fhoy = date('Y-m-d');
   $dhoy = date('H:i:s');

   $desR = $mysqli->real_escape_string($des);

   $logc = "INSERT INTO log (descripcion, aplicacion, fecha, hora, id_usuario) 
            VALUES ('$desR', '$nomap', '$fhoy', '$dhoy', '$usuario_id')";

   $logS = $mysqli->query($logc);

}

function contenidoMax($content) {
   $isLong = strlen($content) > 40;
   $shortContent = substr($content, 0, 40) . '...';
   $escapedContent = addslashes($content);

   if ($isLong) {
       return "
           <a href='#' class='text-dark text-decoration-none fw-bold'
              onclick=\"showModal('$escapedContent')\">
              $shortContent
           </a>";
   } else {
       return htmlspecialchars($content);
   }
}


// function getUsuario($usuario_id, $codsede, $mysqli) {
//     $sel = "SELECT nombres, apellidos FROM usuarios WHERE codsede = ? AND id = ? AND deleted_at IS NULL";
//     $stmt = $mysqli->prepare($sel);
//     $stmt->bind_param('si', $codsede, $usuario_id);
//     $stmt->execute();
//     $res = $stmt->get_result();

//     if ($res->num_rows > 0) {
//         $fila = $res->fetch_assoc();
//         return $fila['nombres'] . ' ' . $fila['apellidos'];
//     }
//     return 'Usuario no encontrado';
// }

// function getCategoria($categoria_id, $codsede, $mysqli) {
//    $sel = "SELECT nombre FROM categorias WHERE codsede = ? AND id = ? AND deleted_at IS NULL";
//    $stmt = $mysqli->prepare($sel);
//    $stmt->bind_param('si', $codsede, $categoria_id);
//    $stmt->execute();
//    $res = $stmt->get_result();

//    if ($res->num_rows > 0) {
//        $fila = $res->fetch_assoc();
//        return $fila['nombre'];
//    }
//    return 'Categoría no encontrada';
// }

// function getArea($area_id, $codsede, $mysqli) {
//    $sel = "SELECT nombre FROM areas WHERE codsede = ? AND id = ? AND deleted_at IS NULL";
//    $stmt = $mysqli->prepare($sel);
//    $stmt->bind_param('si', $codsede, $area_id);
//    $stmt->execute();
//    $res = $stmt->get_result();

//    if ($res->num_rows > 0) {
//        $fila = $res->fetch_assoc();
//        return $fila['nombre'];
//    }
//    return 'Área no encontrada';
// }

// function getEmpresa($empresa_id, $codsede, $mysqli) {
//    $sel = "SELECT razon_social FROM empresas WHERE codsede = ? AND id = ? AND deleted_at IS NULL";
//    $stmt = $mysqli->prepare($sel);
//    $stmt->bind_param('si', $codsede, $empresa_id);
//    $stmt->execute();
//    $res = $stmt->get_result();

//    if ($res->num_rows > 0) {
//        $fila = $res->fetch_assoc();
//        return $fila['razon_social'];
//    }
//    return 'Empresa no encontrada';
// }

// function enviarCorreo($asunto, $mensaje, $destinatario) {
//    $headers = "From: soporte@netcomputer.cl\r\n";
//    $headers .= "Reply-To: soporte@netcomputer.cl\r\n";
//    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

//    mail($destinatario, $asunto, nl2br($mensaje), $headers);
// }


?>