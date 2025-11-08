<?php
require_once("../conn/conn.php");
$mysqli = conn();

$fecha_limite = date('Y-m-d H:i:s', strtotime('-1 month')); // Ajusta según el período de retención (1 semana o 1 mes)

// Obtener los certificados más antiguos (por ejemplo, más antiguos de un mes) que no han sido modificados recientemente
$stmt = $mysqli->prepare("
    SELECT id, imagenes_json 
    FROM certificados 
    WHERE created_at < ? AND imagenes_json IS NOT NULL
");
$stmt->bind_param("s", $fecha_limite);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $imagenes_json = $row['imagenes_json'];
    $imagenes = json_decode($imagenes_json, true); // Decodificamos el JSON para obtener las imágenes

    // Si el JSON no es válido o no hay imágenes, seguimos con la siguiente iteración
    if (empty($imagenes)) {
        continue;
    }

    // Verificar que la imagen no esté asociada a otro informe reciente
    foreach ($imagenes as $imagen) {
        // Verificar si la imagen está siendo utilizada en informes recientes
        $verificar_stmt = $mysqli->prepare("SELECT COUNT(*) FROM certificados WHERE imagenes_json LIKE ?");
        $imagen_search = "%" . $imagen . "%"; // Buscamos la imagen en el JSON de otros certificados
        $verificar_stmt->bind_param("s", $imagen_search);
        $verificar_stmt->execute();
        $verificar_res = $verificar_stmt->get_result();
        $count = $verificar_res->fetch_row()[0];

        // Si la imagen no se usa en otros informes y es mayor al límite de tiempo, la eliminamos
        if ($count === 0) {
            $imagen_ruta = "../../uploads/certificados/informes/" . $imagen;

            // Eliminar la imagen del servidor
            if (file_exists($imagen_ruta)) {
                unlink($imagen_ruta);
            }

            // Podrías también eliminarla del campo JSON si ya no se usa, pero no es estrictamente necesario
            // Actualizamos el JSON eliminando la imagen
            $nuevo_json = array_filter($imagenes, fn($img) => $img !== $imagen);
            $nuevo_json = json_encode(array_values($nuevo_json));

            $update_stmt = $mysqli->prepare("UPDATE certificados SET imagenes_json = ? WHERE id = ?");
            $update_stmt->bind_param("si", $nuevo_json, $row['id']);
            $update_stmt->execute();
        }
    }
}

echo "Proceso de eliminación completado.";
?>
