<?php
// /funciones/GPT/probar_proceso_gpt.php

// URL del endpoint real
// $endpoint = 'http://localhost/funciones/GPT/proceso_gpt.php';
// si lo tienes en el dominio:
$endpoint = 'https://app.vet-mind.cl/funciones/GPT/proceso_gpt.php';




$plantilla_base = <<<HTML
<strong>VEJIGA URINARIA:</strong>
Imagen vesical depletada/con poco contenido anecoico. Grosor de la pared acorde al grado de distensión.
<strong>RIÑÓN IZQUIERDO:</strong>
Tamaño renal levemente aumentado (4,2 cm). Forma ovalada. Bordes corticales irregulares. Ecogenicidad cortical aumentada. Relación córtico-medular aumentada, con límite córtico-medular difuso. Presencia de anillo medular. Imagen pélvica con al menos 3 urolitos, el menor de 0,25 cm y el mayor de 0,76 cm aproximadamente. Uréter izquierdo conservado.
<strong>RIÑÓN DERECHO:</strong>
Tamaño dentro de límites (3,89 cm). Forma ovalada. Bordes corticales irregulares. Ecogenicidad cortical aumentada. Relación córtico-medular aumentada, con límite córtico-medular difuso. Imagen pélvica con al menos 3 urolitos, el menor de 0,3 cm y el mayor de 0,7 cm aproximadamente. Uréter derecho conservado.
<strong>BAZO:</strong>
Imagen esplénica de tamaño normal. Parénquima isoecogénico y homogéneo. Contornos regulares con bordes aguzados.
<strong>ESTÓMAGO:</strong>
Imagen gástrica semidistendida por contenido alimenticio homogéneo y gas leve. Grosor de la pared aumentado (0,35 cm).
<strong>INTESTINO DELGADO:</strong>
Duodeno con contenido mucoso, motilidad conservada y grosor de pared aumentado (0,27 cm). Yeyuno con grosor de pared conservado (0,22 cm), patrón mucoso y motilidad conservada.
<strong>COLON:</strong>
Pared conservada. Contenido sólido moderado.
<strong>HÍGADO:</strong>
Tamaño conservado. Bordes y contornos redondeados. Parénquima de ecogenicidad hiperecoica con ecotextura granular fina. Parénquima homogéneo. Vasculatura principal conservada.
<strong>VESÍCULA BILIAR:</strong>
Distendida, pared conservada, con contenido anecoico homogéneo.
<strong>PÁNCREAS:</strong>
Imagen pancreática de aspecto homogéneo, ecogenicidad hipoecoica, grosor conservado.
<strong>ADRENALES:</strong>
Imágenes adrenales no evaluables al momento del examen por dolor abdominal.
<strong>LINFONODOS:</strong>
Linfonnódulos conservados.
<strong>PERITONEO / MESENTERIO:</strong>
Peritoneo y mesenterio conservados. No se observa derrame peritoneal. No se observan masas en cavidad abdominal al momento del examen.

HTML;


$contenido = "
Imagen vesical distendida con contenido anecoico, grosor parece un grado de distensión. Imagen renal izquierda 
de tamaño conservado en 3,79 centímetros, forma ovalada, ecogenicidad cortical conservada. Con un ángulo de visión 
de 150 grados. Puedes capturar lo que pasa antes y después de tu moto con gran claridad. Relación córtico medular 
conservada, límite córtico medular definido e imagen pélvica normal. Imagen renal derecha de tamaño conservado 
en 4,3 centímetros, forma ovalada, bordes corticales regulares, ecogenicidad cortical conservada, relación córtico
medular conservada, límite córtico medular definido e imagen pélvica normal. Imagen esplénica de tamaño conservado, 
parénquima isoecogénico homogéneo, contornos regulares con bordes redondeados. Todo esto viene con la garantía de 
un año y la empresa que diseña y desarrolla esta cámara está lista para ayudarme a resolver cualquier problema y 
grosor cuerpo esplénico aumentado en 1,53 cm. Imagen gástrica distendida por presencia de contenido alimenticio
homogéneo, gas leve y de pared, grosor conservado en 0,29 cm. Imagen duodenal con contenido mucoso, motilidad 
conservada, grosor de pared aumentada en 0,41 cm. Imagen yeyunal con contenido mucoso, motilidad conservada, grosor
de pared aumentado en 0,35 cm. Soy Ryan F. y esta es la nueva insta imagen colónica de pared aumentada en 0,17 cm, 
con contenido semisólido y abundante gas. Imagen hepática de tamaño conservado, bordes y contornos abusados. 
También permite una estabilización del estado de flujo sin igual, ya que existen parénquima, ecogenicidad 
conservada con ecotextura granular gruesa, parénquima homogéneo, vasculatura principal conservada. Vesícula 
biliar distendida de pared conservada con contenido anecoico homogéneo. Imagen pancreática de tamaño homogéneo,
ecogenicidad conservada, grosor conservado. Próstata de tamaño levemente aumentado (3,1 cm) con ecogenicidad homogénea
y límites bien definidos, compatible con hiperplasia prostática leve para la edad. Imágenes adrenales no evaluables 
al momento del examen por dolor abdominal. Linfonódulos yeyunales reactivos. Peritoneo mesenterio conservado. 
No se observa derrame peritoneal. No se observan masas en la cavidad abdominal al momento del examen.
Conclusión: cambios inflamatorios leves en intestino delgado (duodeno y yeyuno) y aumento de grosor del cuerpo esplénico; 
correlacionar clínicamente y con antecedentes del paciente.
";


$postData = [
    'paciente'       => 'Firulais',
    'especie'        => 'Canino',
    'raza'           => 'Mestizo',
    'edad'           => '5 años',
    'sexo'           => 'Macho',
    'tipo_estudio'   => 'Ecografía abdominal',
    'motivo'         => 'Control postoperatorio',
    'plantilla_base' => $plantilla_base,
    'texto'          => $contenido,
    'plantilla_id'   => 0,
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

$response = curl_exec($ch);
$err      = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');

if ($err) {
    echo json_encode(['status' => 'error', 'message' => $err]);
    exit;
}

echo $response;
