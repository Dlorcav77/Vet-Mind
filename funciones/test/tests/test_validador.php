<?php
declare(strict_types=1);
require_once '../../conn/conn.php';
require_once '../../validaciones/validador_morfo.php';

$mysqli = conn();

$html = <<<HTML
<p><strong>rinon derecho</strong>: longitud 33 mm</p>
<p><strong>Riñon izquierdo</strong>: longitud 37 mm</p>
HTML;

// <p><strong>Imagen adrenal izquierda</strong>: polo craneal 0,57 cm</p>
// <p><strong>Imagen adrenal derecha</strong>: polo craneal 0,43 cm</p>

// <p><strong>Imagen vesical</strong> distendida, grosor pared 0,27 cm.</p>
// <p><strong>Próstata</strong> de tamaño 2,03 cm (alto) y 2,05 cm (ancho).</p>

// <p><strong>Estómago (antro)</strong> poco distendido, pared 0,27 cm.</p>
// <p><strong>Duodeno</strong> pared total 0,20 cm.</p>

// <p><strong>Colon</strong> pared total 0,15 cm.</p>
// <p><strong>Imagen colónica</strong> pared total 0,15 cm.</p>

// <p><strong>Yeyuno</strong> pared total 0,28 cm.</p>
// <p><strong>Imagen yeyunal</strong> pared total 2.8 mm.</p>

// <p><strong>Íleon</strong> pared total 0,28 cm.</p>
// <p><strong>Imagen ileal</strong> pared total 2.8 mm.</p>

// <p><strong>Duodeno</strong> pared total 0,20 cm.</p>
// <p><strong>Imagen duodenal</strong> pared total 2.8 mm.</p>

// <p><strong>Imagen esplénica</strong> grosor conservado 1,04 cm.</p>
// <p><strong>Bazo</strong> espesor 8 mm.</p>

// <p><strong>Vesícula biliar</strong> distendida, pared 1.9 mm.</p>
// <p><strong>Imagen vesicular</strong> con pared poco distendida 2.4 mm.</p>

// <p><strong>Páncreas</strong> de aspecto homogéneo, grosor conservado 0,78 cm.</p>

// <p><strong>Uréter proximal derecho</strong>: diámetro 2.4 mm.</p>
// <p><strong>Imagen ureteral</strong> proximal izq., espesor 0,21 cm.</p>
// HTML;




$ctx = [
  'especie' => 'Canino',          // <-- antes pusiste especie_id
  'raza'    => 'Bulldog francés',
  'edad'    => '6 anos 11 meses',
];


$res = validar_informe_html($mysqli, $html, $ctx);

header('Content-Type: text/plain; charset=utf-8');
echo "=== ITEMS ===\n";
print_r($res['items']);

echo "\n\n=== HTML OUT ===\n";
echo $res['html_out'];
