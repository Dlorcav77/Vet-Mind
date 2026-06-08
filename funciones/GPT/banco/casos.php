<?php
// /funciones/GPT/banco/casos.php
declare(strict_types=1);

// Plantilla neutral compartida por todos los casos (Eco Abdominal).
const PLANTILLA_NEUTRAL = <<<'HTML'
<p></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Vejiga urinaria</strong> semi distendida por contenido anecoico, homogéneo, pared conservada de hasta XX cm. bordes regulares.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Riñón izquierdo</strong> posición normal, forma ovalada, tamaño conservado de XX cm, bordes regulares, límite cortico medular definido, relación cortico medular conservada y ecogenicidad cortical conservada, Vasculatura normal. Pelvis conservada. No se visualiza uréter.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Riñón derecho</strong> posición normal, forma ovalada, tamaño conservado de XX cm, bordes regulares, límite cortico medular definido, relación cortico medular conservada y ecogenicidad cortical conservada, Vasculatura normal. Pelvis conservada. No se visualiza uréter.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Bazo</strong> tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Hígado</strong> se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad hipoecoica respecto al bazo, granular grueso. Sin lesiones focales. Vasculatura conservada.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Vesícula biliar</strong> de tamaño normal, contenido anecoico homogéneo. Pared delgada y lisa.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Gastro entero</strong> <em>Estómago</em> con patrón alimenticio, pared conservada de XX cm, estratificación conservada.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><em>Duodeno</em> en patrón mucoso, pared conservada de XX cm. estratificación conservada.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><em>Yeyuno</em> en patrón mucoso, pared conservada de XX cm. estratificación conservada.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><em>Colon</em> con contenido fecal sólido, pared conservada de XX cm. estratificación conservada.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Páncreas</strong> de tamaño y parénquima conservado en su rama derecha.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Linfonódulos</strong> no se observan LN reactivos.</span></p>
<p style="text-align: justify;"><span style="color: windowtext;"><strong>Glándulas adrenales</strong> AI de tamaño conservado de XX cm en su polo caudal, forma y ecogenicidad conservada. AD de tamaño conservado de XX cm en su polo caudal, forma y ecogenicidad conservada.</span></p>
<div data-vm-page-break="1" class="vm-page-break" style="break-after: page;"><span class="vm-page-break-label">[Salto de página del informe]</span></div>
<p><span style="color: rgb(237, 125, 49); font-size: 20pt;"><strong>IMPRESIÓN DIAGNÓSTICA</strong></span></p>
<p style="text-align: justify;"><span style="color: black; font-size: 14pt;"><strong>&nbsp;</strong></span></p>
<ul><li><p style="text-align: justify;">&nbsp;</p></li></ul>
<p style="text-align: justify;"><span style="color: rgb(237, 125, 49); font-size: 20pt;"><strong>SUGERENCIAS</strong></span></p>
<ul><li><p style="text-align: justify;">&nbsp;</p></li></ul>
<p style="text-align: justify;"><br><br></p>
HTML;

// Datos de paciente: se dejan vacíos igual que en producción (en el snapshot real
// solo venía tipo_estudio). No inventamos especie/raza/sexo.
function caso_base(): array {
    return [
        'especie'      => '',
        'raza'         => '',
        'edad'         => '',
        'sexo'         => '',
        'motivo'       => '',
        'tipo_estudio' => 'Eco Abdominal INEV',
    ];
}

$CASOS = [];

$CASOS['sol'] = caso_base() + [
    'paciente' => 'sol',
    'dictado'  => <<<'TXT'
Paciente sol vejiga distendida con contenido anecoico homogéneo, grosor conservado en 0.21 centímetros, colon levemente engrosado en 0.22 centímetros, con contenido semisólido, estratificación conservada. Riñones izquierdo tamaño conservado en 4.1 centímetros, bordes irregulares, ecogenicidad aumentada, relación disminuida, límite difuso, pelvis normal, ureter no visible, bazo conservado con bordes redondeados, Hígado conservado, bordes aguzados, ecogenicidad, tamaño conservado, vesícula biliar también conservada con leve sedimento biliar, estómago engrosado en 0,44 centímetros, Con patrón alimenticio moderado, estratificación conservada, perdón, estratificación con capa muscular engrosada. Y en curvatura menor, el grosor está en 0.7 centímetros, dode no engrosado en 0,47 centímetros, estratificación conservada con patrón mucoso, páncreas conservado. Riñón derecho mismas características que el izquierdo, tamaño en 3.8, adrenal izquierda de tamaño aumentado en. En 0.68 centímetros de forma conservada, ecogenicidad conservada y la derecha forma conservada aumentada en 0.74 cm y ecogenicidad aumentada. Yeyuno grosor conservado en 0,4 centímetros, estratificación conservada, patrón mucoso. Y eso sería.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria distendida por contenido anecoico, homogéneo, pared conservada de hasta 0.21 cm. bordes regulares.
Riñón izquierdo posición normal, forma ovalada, tamaño conservado de 4.1 cm, bordes irregulares, límite cortico medular difuso, relación cortico medular disminuida y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño conservado de 3.8 cm, bordes irregulares, límite cortico medular difuso, relación cortico medular disminuida y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño conservado, bordes redondeados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad hipoecoica respecto al bazo, granular grueso. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar de tamaño normal, contenido anecoico con leve cantidad de barro biliar. Pared delgada y lisa.
Gastro entero:
Estómago con patrón alimenticio moderado, pared engrosada de 0.44 cm, estratificación conservada con capa muscular engrosada. En curvatura menor el grosor es de 0.7 cm.
Duodeno en patrón mucoso, estratificación conservada, pared engrosada de 0.47 cm.
Yeyuno en patrón mucoso, pared conservada de 0.4 cm. estratificación conservada.
Colon con contenido fecal semi sólido, pared levemente engrosada de 0.22 cm. estratificación conservada
Páncreas de tamaño y parénquima conservado en su rama derecha.
Linfonódulos no se observan LN reactivos.
Glándulas adrenales:
AI de tamaño aumentada de 0.68 cm en su polo caudal, forma y ecogenicidad conservada.
AD de tamaño aumentada de 0.74 cm en su polo caudal, forma normal y ecogenicidad aumentada.
TXT,
    'notas' => 'Linfonódulos no se dictan: debe quedar el normal de plantilla. Riñón derecho "mismas características": la ayudante expande todos los atributos del izquierdo.',
];

$CASOS['niut'] = caso_base() + [
    'paciente' => 'niut',
    'dictado'  => <<<'TXT'
Vejiga de poca distensión, contenido anecoico homogéneo, pared conservada 0.19 centímetros. Colon con contenido sólido, pared conservada en 0.1 centímetros, estratificación conservada. Riñón izquierdo de forma redondeada, tamaño conservado en 3.7 centímetros, ecogenicidad aumentada, relación córtico medular levemente disminuido, límite córtico medular difuso, imagen pélvica normal, vasculatura normal, ureter no visible. Riñón derecho de forma ovalada, tamaño conservado en 3.6 centímetros, ecogenicidad aumentada, relación córtico medular difuso, imagen pélvica normal, vasculatura normal y ureter no visible. Estómago con contenido alimenticio, pared conservada en 0.18 centímetros, estratificación conservada. Hígado, tamaño, forma, ecogenicidad conservada. Vesícula biliar distendida con contenido anecoico homogéneo, pared conservada. Duodeno con contenido alimenticio, grosor levemente aumentado en 0.26 centímetros, estratificación conservada. Páncreas de forma, tamaño, ecogenicidad conservada. Yeyuno también con contenido alimenticio y DAS Leve, grosor en 0.18cm que está conservado, estratificación conservada. Ilión, grosor conservado en 0.24 centímetros, también con patrón alimenticio y estratificación conservada.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria semi distendida por contenido anecoico, homogéneo, pared conservada de hasta 0.19 cm. bordes regulares.
Riñón izquierdo posición normal, forma redondeada, tamaño conservado de 3.7 cm, bordes regulares, límite cortico medular difuso, relación cortico medular levemente disminuido y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño conservado de 3.6 cm, bordes regulares, límite cortico medular difuso, relación cortico medular conservado y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad hipoecoico respecto al bazo, granular grueso. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar de tamaño normal, distendida, contenido anecoico homogéneo. Pared delgada y lisa.
Gastro entero: Estómago con patrón alimenticio, pared conservada de 0.18 cm, estratificación conservada. Duodeno en patrón alimenticio, estratificación conservada, pared levemente aumentada de 0.26 cm. Yeyuno en patrón alimenticio y gaseoso leve, pared conservada de 0.18 cm. estratificación conservada, íleon en patrón alimenticio, pared conservada de 0.24 cm. estratificación conservada Colon con contenido fecal sólido, pared conservada de 0.1 cm.
Páncreas de tamaño y parénquima conservado en su rama derecha.
Linfonódulos no se observan LN reactivos.
Glándulas adrenales: AI de tamaño conservado de xx cm en su polo caudal, forma y ecogenicidad conservada. AD de tamaño conservado de xx cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'NO EXIGIBLE a la IA (amarillo): la ayudante corrigió "relación cortico medular conservado" en RD (el audio venía confuso) y dejó adrenales con "xx cm" porque no se dictaron.',
];

$CASOS['lissie'] = caso_base() + [
    'paciente' => 'lissie',
    'dictado'  => <<<'TXT'
Vejiga poco Distendida, grosor en 0,25 centímetros, contenido anecoico homogéneo. Colon engrosado en 0.33 centímetros, contenidO semisólido, estratificación conservada. Riñón. Tamaño conservado en 4 centímetros, forma ovalada, bordes levemente irregulares, ecogenicidad levemente aumentada, relación conservada, límite difuso. Pelvis normal, uréter no visible. Vaso conservado. Adrenal izquierda conservada en 0.47 centímetros. Estómago. Grosor aumentado en 0,35 con contenido mucoso y gaseoso leve, estratificación conservada. Hígado. Tamaño conservado, ecogenicidad aumentada, granular fino. Vesícula biliar conservada. Riñón derecho Mismas características, tamaños 4.3 centímetros, duodeno aumentado en 0,47 centímetros, patrón mucoso, estratificación conservada. Páncreas conservado. Yeyuno. Grosor conservado en 0,29 con patrón mucoso, estratificación conservada. Adrenal derecha conservada en 0,48 centímetros. Y se me olvidó que los bordes del hígado están abusados.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria poco distendida por contenido anecoico, homogéneo, pared conservada de hasta 0.25 cm. bordes regulares.
Riñón izquierdo posición normal, forma ovalada, tamaño conservado de 4.0 cm, bordes levemente irregulares, límite cortico medular difuso, relación cortico medular conservada y ecogenicidad cortical y medular levemente aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño conservado de 4.3cm bordes levemente irregulares, límite cortico medular difuso, relación cortico medular conservada y ecogenicidad cortical y medular levemente aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad aumentada respecto al bazo, granular fino. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar de tamaño normal, contenido anecoico homogéneo. Pared delgada y lisa.
Gastro entero:
Estómago con patrón mucoso y gaseoso leve, pared aumentada de 0.35cm, estratificación conservada.
Duodeno en patrón mucoso, pared aumentada de 0.47 cm, estratificación conservada.
Yeyuno en patrón mucoso, pared conservada de 0.29 cm, estratificación conservada.
Colon con contenido fecal semi sólido, pared engrosada de 0.33 cm, estratificación conservada.
Páncreas de tamaño y parénquima conservado en su rama derecha.
Linfonódulos no se observan LN reactivos.
Glándulas adrenales:
AI de tamaño conservado de 0.47 cm en su polo caudal, forma y ecogenicidad conservada.
AD de tamaño conservado de 0.48 cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'Se espera flag termino_confuso en "abusados" (posible "abultados"). Adrenales SÍ dictadas (0.47 / 0.48), son exigibles. "Riñón único" del dictado lo asigna al izquierdo.',
];

$CASOS['tony'] = caso_base() + [
    'paciente' => 'tony',
    'dictado'  => <<<'TXT'
Paciente Toni Vejiga poco distendida en 0.23 centímetros, próstata aumentada de tamaño en 2.2 x 2.1 centímetros, forma redonda, bordes irregulares, ecogenicidad aumentada. Heterogénea, con Presencia de lesiones redondas anecoicas de 0.58 por 0.52. Colon descendente, Porción distal sin contenido, porción proximal con contenido líquido leve, grosor aumentado 0.26 centímetros, estratificación conservada. Riñón izquierdo tamaño normal en 4.1 centímetros, bordes levemente irregulares, ecogenicidad aumentada, relación córtico medular aumentada, límite mal definido, pelvis normal, ureter no visible. Adrenal izquierda. Tamaño, forma, ecogenicidad conservada en 0,42 centímetros. Bazo conservado, estómago. Levemente engrosado en 0,35 cm, patrón mucoso y gas moderado, estratificación conservada, Páncreas conservado. Hígado Tamaño ecogenicidad. Conservada con bordes aguzados, vesícula biliar conservada. Riñón derecho tamaño conservado en 3.6 centímetros, bordes irregulares, ecogenicidad aumentada, relación corticomedular aumentada, límite mal definido, pelvis normal, ureter no visible. Duodeno grosor aumentado 0,46 cm, patrón mucoso Estratificación con predominio de la capa muscular. Páncreas conservado. Adrenal derecha. Tamaño conservado 0,48 cm, forma y lo demás conservado. Yeyuno. Grosor conservado 0,27 centímetros, estratificación con predominio de la capa muscular. Testículos Tamaños conservados, ecogenicidad conservada con. Presencia de mediastino y lesiones.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria poco distendida por contenido anecoico, homogéneo, pared conservada de hasta 0.23 cm. bordes regulares.
Próstata aumentada de tamaño en 2.2cm x 2.1cm, forma redonda, bordes irregulares, ecogenicidad aumentada, heterogénea, con presencia de lesiones redondas anecoicas de tamaño 0.58cm x 0.52cm.
Testículos de tamaño y ecogenicidad conservada, presencia de mediastino, sin lesiones.
Riñón izquierdo posición normal, forma ovalada, tamaño conservado de 4.1 cm, bordes levemente irregulares, límite cortico medular mal definido, relación cortico medular aumentada y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño conservado de 3.6 cm, bordes irregulares, límite cortico medular mal definido, relación cortico medular aumentada y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad hipoecoica respecto al bazo, granular grueso. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar de tamaño normal, contenido anecoico homogéneo. Pared delgada y lisa.
Gastro entero:
Estómago con patrón mucoso y gas moderado, pared levemente engrosada de 0.35 cm, estratificación conservada.
Duodeno en patrón mucoso, pared aumentada de 0.46 cm. estratificación conservada con predominio de la capa muscular.
Yeyuno en patrón mucoso, pared conservada de 0.27 cm. estratificación conservada con predominio de la capa muscular.
Colon descendente porción distal sin contenido, porción proximal con contenido líquido leve, pared aumentada de 0.26 cm. estratificación conservada
Páncreas de tamaño y parénquima conservado en su rama derecha.
Linfonódulos no se observan LN reactivos.
Glándulas adrenales:
AI de tamaño conservado de 0.42 cm en su polo caudal, forma y ecogenicidad conservada.
AD de tamaño conservado de 0.48 cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'Macho: próstata y testículos van en el informe (después de Vejiga), no en HALLAZGOS ADICIONALES. Adrenales dictadas (0.42 / 0.48). El dictado dice "presencia de mediastino y lesiones"; la ayudante interpretó "sin lesiones" (revisar audio original).',
];

$CASOS['pancho'] = caso_base() + [
    'paciente' => 'pancho',
    'dictado'  => <<<'TXT'
Paciente pancho vejiga distendida, grosor aumentado en 0.36cm, con presencia de sedimento urinario leve, próstata disminuida de tamaño, bordes irregulares, tamaño redondo, hipoecoico, heterogéneo. Colon levemente engrosado en 0,2 cm, contenido semisólido con gas, estratificación conservada. Hígado aumentado de tamaño severamente el lóbulo izquierdo. Por presencia de masa redonda ovalada de bordes irregulares, ecogenicidad aumentada, heterogénea, con presencia de cavitaciones redondas anecoicas de tamaño. 6, no tengo otro de tamaño de 7.5 x 7.6, Estómago engrosado de 0.48 centímetros, con aumento de la capa muscular, patrón mucoso. Riñón izquierdo tamaño conservado en 3.7 centímetros, forma ovalada, bordes levemente irregulares, ecogenicidad aumentada, relación aumentada, límite difuso, pelvis normal, ureter no visible, adrenal izquierda aumentada de tamaño en Polo caudal en 0.79 centímetros, ecogenicidad aumentada. Heterogéneo. Riñón derecho mismas características en 3.7 centímetros, duodeno engrosado en 0,39 centímetros centímetros, patrón mucoso estratificación conservada. Páncreas Rama derecha conservada y rama izquierda aumentada en su ecogenicidad. Yeyuno engrosado en 0,41 cm, patrón mucoso estratificación conservada, adrenal derecha aumentada de tamaño 0,8 cm, ecogenicidad conservada.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria distendida por contenido anecoico y con presencia de sedimento urinario leve, pared aumentada de hasta 0.36 cm. bordes regulares.
Próstata disminuida de tamaño, bordes irregulares, forma redondeada, hipoecoico, heterogéneo.
Riñón izquierdo posición normal, forma ovalada, tamaño conservado de 3.7 cm, bordes levemente irregulares, límite cortico medular difuso, relación cortico medular aumentada y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño conservado de 3.7 cm, bordes levemente irregulares, límite cortico medular difuso, relación cortico medular aumentada y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño severamente aumentado, lóbulo lateral izquierdo redondeado, parénquima homogéneo, ecogenicidad hipoecoica respecto al bazo, granular grueso. Vasculatura conservada. Presencia de masa redonda ovalada, de bordes irregulares, ecogenicidad aumentada, heterogénea, con presencia de cavitaciones, redondas, anecoicas de tamaño de 7.5cm x 7.2 cm la mas grande.   FALTO VASCULARIZACION de la masa
Vesícula biliar de tamaño normal, contenido anecoico con leve cantidad de barro biliar. Pared delgada y lisa.
Gastro entero:
Estómago con patrón mucoso, pared engrosada de 0.48 cm, estratificación conservada con aumento de la capa muscular.
Duodeno en patrón mucoso, estratificación conservada, pared engrosada de 0.39 cm.
Yeyuno en patrón mucoso, pared engrosada de 0.41 cm. estratificación conservada,
Colon con contenido fecal semi sólido y gas, pared levemente engrosada de 0.2 cm.
Páncreas de tamaño y parénquima conservado en su rama derecha. Aumento de ecogenicidad en rama izquierda
Linfonódulos no se observan LN reactivos.
Glándulas adrenales: AI de tamaño aumentado de 0.79 cm en su polo caudal, ecogenicidad aumentada, heterogénea. AD de tamaño aumentado de 0.8 cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'NO EXIGIBLE (amarillo): "FALTO VASCULARIZACION de la masa" lo agregó la ayudante. Medida de la masa viene confusa en el audio ("6... 7.5 x 7.6"); ayudante puso 7.5 x 7.2. Macho: próstata después de Vejiga.',
];

$CASOS['kaiser'] = caso_base() + [
    'paciente' => 'kaiser',
    'dictado'  => <<<'TXT'
Hay dos Kaiser, por eso te puse Kaiser 1 y el otro va a ser Kaiser 2. Kaiser 1. Vejiga poco distendida, pared engrosada en 0,45 centímetros, bordes irregulares, contenido en ecoico homogéneo. Próstata tamaño conservado en 2 por 2.5, redondo ovalado, bordes irreguares, ecogenicidad aumentada. Parenchyma heterogéneo. Colon levemente engrosado en 0.17 centímetros, contenido semisólido, estratificación conservada, vaso conservado. Riñón izquierdo, tamaño normal en 4.7 centímetros, bordes levemente irregulares, ecogenicidad levemente aumentada, relación conservada, límite difuso. Pelvis conservada. Hígado tamaño ecogenicidad conservada, bordes aguzados. Vesícula biliar conservada con contenido homogéneo. Estómago engrosado en 0.64 centímetros, con contenido semi. Perdón, contenido líquido leve, estratificación conservada. Páncreas rama izquierda, ecogenicidad aumentada, bordes irregulares, heterogéneo. Yiyuno Tamaño conservado en 0,32 cm. Patrón mucoso Estratificación conservada. Adrenal izquierda, tamaño, ecogenicidad conservada 0.37 centímetros, duodeno engrosado en 0,44 centímetros, patrón mucoso estratificación conservada y páncreas rama derecha conservada. Riñón derecho, tamaño conservado en 5.5 centímetros, ecogenicidad aumentada, relación aumentada, límite difuso. Pelvis normal. Ureter no visible, adrenal derecha conservado en 0.46 centímetros, Eso.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria poco distendida por contenido anecoico, homogéneo, pared engrosada de hasta 0.45 cm. bordes irregulares.
Próstata de tamaño conservado de 2.0cm x 2.5cm, redondeada y ovalada, bordes irregulares, ecogenicidad aumentada, parénquima heterogéneo.
Riñón izquierdo posición normal, forma ovalada, tamaño conservado de 4.7 cm, bordes levemente irregulares, límite cortico medular difuso, relación cortico medular conservada y ecogenicidad cortical y medular levemente aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño conservado de 5.5 cm, bordes regulares, límite cortico medular difuso, relación cortico medular aumentada y ecogenicidad cortical y medular aumentada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad hipoecoica respecto al bazo, granular grueso. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar de tamaño normal, contenido anecoico homogéneo. Pared delgada y lisa.
Gastro entero:
Estómago con patrón líquido leve, pared engrosada de 0.64 cm, estratificación conservada.
Duodeno en patrón mucoso, estratificación conservada, pared engrosada de 0.44 cm.
Yeyuno en patrón mucoso, pared conservada de 0.32 cm. estratificación conservada,
Colon con contenido fecal semi sólido, pared levemente engrosada de 0.17 cm.
Páncreas de tamaño y parénquima conservado en su rama derecha. Ecogenicidad aumentada en rama izquierda, bordes irregulares, heterogéneo.
Linfonódulos no se observan LN reactivos.
Glándulas adrenales: AI de tamaño conservado de 0.37 cm en su polo caudal, forma y ecogenicidad conservada. AD de tamaño conservado de 0.46 cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'El dictado parte con charla ("Hay dos Kaiser..."): debe ignorarse, no entra al informe. Macho: próstata después de Vejiga. Adrenales dictadas (0.37 / 0.46).',
];

$CASOS['lago'] = caso_base() + [
    'paciente' => 'lago',
    'dictado'  => <<<'TXT'
Vejiga distendida con contenido anecoico, grosor conservado 0.2 centímetros. Colon con contenido semisólido, grosor aumentado 0.23 centímetros, estratificación conservada. Riñón izquierdo tamaño normal en 5.5 centímetros, ecogenicidad cortical aumentada, relación córtico medular conservada, límite córtico medular difuso, pelvis normal. Ureter no visible. Riñón derecho Mismas características, tamaño en 6 cm. Hígado de tamaño conservado, ecogenicidad conservada. Vesícula biliar También conservada, conservada con contenido homogéneo. Bazo. Ecogenicidad conservada, bordes redondeados, aumento del grosor del cuerpo en 1.78 cm. Estómago contraído con contenido mucoso, gas leve, grosor conservado. En 0,23 centímetros, estratificación conservada. Duodeno con contenido mucoso, grosor aumentado en 0.54 centímetros, estratifiCaCiÓn conservada. Yeyuno con contenido mucoso, Grosor aumentado 0,45 centímetros, EstratifiCaCiÓn Conservada. Páncreas Tamaño aumentado, ecogenicidad conservada. Y lo demás conservador.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria distendida por contenido anecoico, homogéneo, pared conservada de hasta 0.12 cm. bordes regulares.
Próstata de tamaño de 0.79cm x 1.11cm de aspecto conservado
Riñón izquierdo posición normal, forma ovalada, tamaño aumentado de 5.5 cm, bordes regulares, límite cortico medular definido, relación cortico medular aumentada y ecogenicidad cortical levemente aumentada y medular conservada, Vasculatura normal. Pelvis conservada, uréter no visible
Riñón derecho posición normal, forma ovalada, tamaño aumentado de 5.6 cm, bordes regulares, límite cortico medular definido, relación cortico medular aumentada y ecogenicidad cortical levemente aumentada y medular conservada, Vasculatura normal. Pelvis conservada, uréter no visible
Bazo tamaño aumentado hacia cola esplénica, bordes redondeados, parénquima homogéneo y ecogenicidad aumentada, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño conservado, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad hipoecoico respecto al bazo, granular grueso. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar de tamaño normal, contenido anecoico homogéneo. Pared delgada y lisa.
Gastro entero: Estómago contraído con patrón mucosos y gaseoso moderado, pared aumentada de 0.49 cm, estratificación conservada. Duodeno en patrón mucoso y gas leve, estratificación conservada, pared conservada de 0.35 cm. Yeyuno en patrón mucoso, pared conservada de 0.26 cm. estratificación conservada, Colon con contenido fecal semi sólido y gas leve, pared conservada de 0.16 cm, se observa una lesión focal en colon ascendente de forma redondeada de tamaño 1.3cm x 1.7cm, bordes irregulares, cavitario y contenido anecoico.
Páncreas de tamaño y parénquima conservado en su rama derecha.
Linfonódulos yeyunales aumentados de tamaño
Glándulas adrenales: AI de tamaño conservado de xx cm en su polo caudal, forma y ecogenicidad conservada. AD de tamaño conservado de xx cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'CASO RUIDOSO: transcripción mala (vejiga 0.2 vs 0.12, RD 6 vs 5.6). La ayudante agregó próstata, lesión en colon ascendente y linfonódulos aumentados que NO están en el audio. Útil solo como referencia visual, NO para puntaje duro.',
];

$CASOS['test_multiflag'] = caso_base() + [
    'paciente' => 'test_multiflag',
    'dictado'  => <<<'TXT'
Vejiga distendida, contenido anecoico homogéneo, pared conservada en 0.2 cm. Riñón izquierdo tamaño conservado en 18 centímetros, forma ovalada, ecogenicidad conservada, relación conservada, límite definido, pelvis normal, ureter no visible. Riñón derecho tamaño conservado en 4 centímetros. Bazo conservado. Estómago con contenido alimenticio, pared conservada en 0.18. Hígado conservado, bordes aguzados. Vesícula biliar con un urolito de 9 centímetros en su interior. Páncreas conservado. Yeyuno con grosor conservado en 0.3 cm, patrón mucoso, estratificación conservada.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (sintética, no clínica):
- Riñón izquierdo 18 cm: número legible pero clínicamente imposible -> escribir "18 cm" + flag valor_sospechoso.
- Estómago "0.18" sin unidad: número sin cm -> flag falta_unidad.
- Vesícula biliar urolito 9 cm: estructura interna más grande que el órgano -> flag valor_sospechoso.
- Deben quedar 3 flags correlativos (1,2,3) y 3 líneas en Observaciones, una por cada uno.
- Linfonódulos y adrenales no dictados -> quedan normales/XX de plantilla.
TXT,
    'notas' => 'SINTÉTICO. Estresa multi-flag + renumeración + correspondencia flag↔obs. Espera 3 flags: valor_sospechoso (riñón 18cm), falta_unidad (estómago 0.18), valor_sospechoso (urolito 9cm).',
];

$CASOS['test_ilegible'] = caso_base() + [
    'paciente' => 'test_ilegible',
    'dictado'  => <<<'TXT'
Vejiga distendida, pared conservada en 0.2 cm. Riñón izquierdo tamaño conservado en 4 centímetros. Riñón derecho mismas características que el izquierdo. Bazo conservado. Estómago con masa redonda de bordes irregulares, ecogenicidad aumentada, de tamaño eh no sé tres coma o cuatro y siete por dos punto cinco creo. Hígado conservado. Vesícula biliar conservada. Páncreas conservado. Yeyuno conservado en 0.3 cm, patrón mucoso.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (sintética, no clínica):
- Estómago: descripción de la masa (forma, bordes, ecogenicidad) SE CONSERVA, pero el tamaño venía balbuceado/ilegible -> escribir "XX cm" + flag medida_ilegible, NO incrustar el balbuceo ni borrar la medida en silencio.
- Riñón derecho "mismas características": debe expandirse completo (posición, forma, etc.), no quedar literal.
- 1 flag (medida_ilegible) con su línea en Observaciones.
TXT,
    'notas' => 'SINTÉTICO. Estresa medida_ilegible (XX+flag, no borrar) y "mismas características" expandido. La masa va en estómago, no en HALLAZGOS ADICIONALES.',
];

$CASOS['test_ruido_macho'] = caso_base() + [
    'paciente' => 'test_ruido_macho',
    'dictado'  => <<<'TXT'
Ya, partimos. Esto te lo mando por WhatsApp después. Aprovecha la oferta del 20 por ciento en el ecógrafo nuevo. Vejiga poco distendida, pared conservada en 0.2 cm. Próstata aumentada de tamaño en 3.5 por 3.1 centímetros, bordes irregulares, ecogenicidad aumentada. Testículos de tamaño conservado, ecogenicidad conservada. Riñón izquierdo tamaño conservado en 4 centímetros, ecogenicidad aumentada. Riñón derecho tamaño conservado en 3.9 centímetros. Bazo conservado. Estómago conservado en 0.2 cm. Hígado conservado. Vesícula biliar conservada. Páncreas conservado. Íleon engrosado en 0.4 cm, patrón mucoso. Yeyuno conservado en 0.3 cm. Adrenales no evaluables al momento del examen por dolor abdominal. Y listo, eso sería, corta.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (sintética, no clínica):
- La charla inicial ("Ya, partimos", WhatsApp, oferta del ecógrafo) y el cierre ("eso sería, corta") NO entran al informe.
- Próstata y testículos van después de Vejiga (no en HALLAZGOS ADICIONALES).
- Íleon va dentro de Gastro entero, entre Yeyuno y Colon.
- Adrenales: se respeta "no evaluables por dolor abdominal" tal cual, NO se pone el normal con XX.
- Riñón izquierdo: solo "ecogenicidad aumentada" (cortical), sin inventar "y medular".
- Sin flags esperables -> sin bloque de Observaciones.
TXT,
    'notas' => 'SINTÉTICO. Estresa: ignorar ruido/publicidad, próstata+testículos posicionados, íleon en gastro entero, adrenales "no evaluables" respetadas, ecogenicidad sin inventar medular. NO debe haber flags.',
];


$CASOS['botones'] = caso_base() + [
    'paciente' => 'botones',
    'dictado'  => <<<'TXT'
Paciente. Botones Vejiga distendida con contenido anecoico homogéneo, pared regular, grosor conservado en 0.13 centímetros. Próstata levemente aumentada, de tamaño en 1.7 x 2.4 centímetros, forma redonda bilobulada, bordes levemente irregulares, ecogenicidad aumentada. Paréntema Heterogéneo. Colon Con contenido semisólido, gas Grosor levemente aumentado 0,17 cm. Estratificación conservada. Riñón Izquierdo Tamaño, forma ovalada, bordes levemente irregulares, ecogenicidad aumentada, relación córtico medular aumentada, límite córtico medular difuso. Imagen pélvica normal. Ureter No visible, vasculatura disminuida. Adrenal izquierda de forma, tamaño y ecogenicidad conservada en 0.35 centímetros. Vaso forma conservada, ecogenicidad conservada con. Bordes aguzados. Hígado Tamaño conservado, bordes aguzados, ecogenicidad conservada. Ecotextura granular gruesa. Vesícula biliar distendida con contenido anecoico homogéneo, pared conservada y lisa. Estómago contraído sin contenido alimenticio, Patrón mucoso y gas moderado, grosor aumentado en 0,38 centímetros, estratificación conservada. Riñón Derecho Forma ovalada, bordes levemente irregulares, ecogenicidad Cortical aumentada, relación córtico medular levemente aumentada, límite córtico medular difuso. Imagen pélvica normal. Ureter no visible, tamaño en 3.9 centímetros. Duodeno con patrón mucoso, grosor en 0.37 centímetros. Conservado, estratificación conservada. Yeyuno patrón mucoso grosor en 0.3 centímetros. ¿Conservado coma estratificación conservada? Páncreas De tamaño, ecogenicidad y grosor conservado. Adrenal derecha no evaluable al momento del examen.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (caso real de la polola, sin respuesta de ayudante para comparar):
- Riñón izquierdo: el dictado nombra "Tamaño" pero NO da el número. Debe expandir la frase completa de plantilla Y dejar "XX cm" + flag valor_faltante (NO borrar la medida). Respetar "vasculatura disminuida" dictada (la plantilla dice normal).
- Próstata: macho, va después de Vejiga, con sus medidas.
- Parchado: órganos genéricos/normales del dictado deben salir con la frase completa de plantilla, no recortada.
- "Vaso forma conservada" del dictado = "Bazo" mal transcrito; va en sección Bazo.
- Adrenal derecha "no evaluable": respetar la nota tal cual, no poner XX.
- Riñón derecho: dictó "ecogenicidad cortical aumentada" -> solo cortical, no inventar medular.
- Gastro separado en párrafos (Estómago/Duodeno/Yeyuno/Colon).
TXT,
    'notas' => 'CASO REAL (polola), plantilla nueva. Estresa: parchado sobre plantilla, XX marcado cuando se nombra órgano sin medida, dictado gana sobre plantilla (vasculatura disminuida), adrenal "no evaluable" respetada.',
];

$CASOS['kitty'] = caso_base() + [
    'paciente' => 'kitty',
    'dictado'  => <<<'TXT'
Paciente Kitty. Vejiga distendida con contenido anecoico homogéneo, grosor conservado en 0.15 centímetros. Colon con contenido fecal sólido, Grosor conservado en 0.16 centímetros. Estratificación conservada. Riñón izquierdo. Tamaño conservado en 3.1 centímetros. Forma redonda ovalada, bordes levemente irregulares, ecogenicidad cortical aumentada. Relación córtico medular levemente aumentada. Límite córtico medular difuso. Imagen pélvica normal. Ureter no visible. Adrenal izquierda De tamaño, forma y ecogenicidad conservada 0,41 cm. Vaso de tamaño conservado, ecogenicidad conservada, bordes aguzados. Estómago contraído con discreto contenido líquido, grosor conservado en 0.23 centímetros. Estratificación conservada. Hígado De tamaño normal, ecogenicidad conservada. Ecotextura granular gruesa, bordes aguzados. Vesícula biliar distendida con contenido anecoico con presencia de barro biliar moderado. Riñón derecho. Tamaño conservado 3.5 centímetros. Forma ovalada redonda, bordes levemente irregulares, ecogenicidad cortical aumentada. Relación córtico medular levemente aumentada. Límite córtico medular medular difuso. Imagen pélvica normal. Ureterno Visible Adrenal derecha. Tamaño, forma y ecogenicidad conservada en 0,47 centímetros. Ilión con patrón mucoso levemente engrosado en 0.36 centímetros. Estratificación conservada. Duodeno. Grosor conservado 0.22cm. 4 mucoso. Estratificación Conservada. Páncreas De tamaño, forma y ecogenicidad conservada. Yeyuno con patrón mucoso, grosor conservado en 0,23 centímetros. Estratificación conservada.
TXT,
    'ayudante' => <<<'TXT'
Vejiga urinaria distendida por contenido anecoico, homogéneo, pared conservada de hasta 0.15 cm. bordes regulares.
Riñón izquierdo posición normal, forma redonda ovalada, tamaño conservado de 3.1 cm, bordes levemente irregulares, límite cortico medular difuso, relación cortico medular levemente aumentada y ecogenicidad cortical aumentada, Vasculatura normal. Pelvis conservada. No se visualiza uréter.
Riñón derecho posición normal, forma ovalada redonda, tamaño conservado de 3.5 cm, bordes levemente irregulares, límite cortico medular difuso, relación cortico medular levemente aumentada y ecogenicidad cortical aumentada, Vasculatura normal. Pelvis conservada. No se visualiza uréter.
Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada.
Hígado se observa de tamaño normal, lóbulo lateral izquierdo aguzado, parénquima homogéneo, ecogenicidad conservada, granular grueso. Sin lesiones focales. Vasculatura conservada.
Vesícula biliar distendida, contenido anecoico homogéneo con presencia de barro biliar moderado. Pared delgada y lisa.
Gastro entero Estómago contraído con discreto contenido líquido, pared conservada de 0.23 cm, estratificación conservada.
Duodeno en patrón mucoso, pared conservada de 0.22 cm. estratificación conservada.
Yeyuno en patrón mucoso, pared conservada de 0.23 cm. estratificación conservada.
Íleon en patrón mucoso levemente engrosado, pared conservada de 0.36 cm. estratificación conservada.
Colon con contenido fecal sólido, pared conservada de 0.16 cm. estratificación conservada.
Páncreas de tamaño, forma y ecogenicidad conservada en su rama derecha.
Linfonódulos no se observan LN reactivos.
Glándulas adrenales AI de tamaño conservado de 0.41 cm en su polo caudal, forma y ecogenicidad conservada. AD de tamaño conservado de 0.47 cm en su polo caudal, forma y ecogenicidad conservada.
TXT,
    'notas' => 'CASO REAL (polola), plantilla nueva. Respuesta esperada RECONSTRUIDA por Claude (no es la de la ayudante real). Estresa: íleon desordenado en el dictado debe quedar entre Yeyuno y Colon; parchado sobre plantilla; "Vaso"="Bazo"; sin flags (dictado completo). PUNTOS A VALIDAR: ecotextura "gruesa" vs "grueso"; si el hígado normal debe incluir "lóbulo lateral izquierdo aguzado" cuando no se dicta.',
];

$CASOS['sultan'] = caso_base() + [
    'paciente' => 'sultan',
    'dictado'  => <<<'TXT'
Paciente Sultán Vejiga distendida con contenido anecoico, grosor conservado en 0.16 centímetros. Próstata disminuida de tamaño en 2.9 x 3.9 centímetros, forma redonda, bordes irregulares, ecogenicidad disminuida. Colo Con contenido semisólido, grosor conservado en 0.18 centímetros, estratificación conservada. Riñón izquierdo tamaño normal en 7.6 centímetros, forma ovalada, bordes levemente irregulares, ecogenicidad cortical levemente aumentada. Límite córtico medular levemente aumentado, Relación cortico medular levemente aumentada, límite córtico medular difuso. Imagen pélvica normal, Pelvis conservada. Ureter no visible. Adrenal izquierda de forma, tamaño y ecogenicidad conservada en 0.79 centímetros. No, eso no estaba en 0.57 centímetros. Vaso aumentado de tamaño, longitud, forma conservada, ecogenicidad conservada, bordes levemente redondeados. Vesícula biliar distendida con contenido anecoico homogéneo, pared conservada y lisa. Hígado Tamaño conservado, ecogenicidad levemente aumentada. Ecotextura granulable gruesa, bordes abusados. Estómago distendido con contenido Gaseoso, grosor conservado 0.24 centímetros, estratificación conservada. Riñón derecho forma ovalada, bordes levemente irregulares, tamaño conservado en 8 centímetros, ecogenicidad cortical aumentada, relación córtico medular levemente aumentada, límite córtico medular de BU difuso. Imagen pélvica normal. Ureter no visible. Duodeno engrosado en 0.55 centímetros, con patrón mucoso, estratificación conservada. Yeyuno con patrón mucoso, grosor conservado en 0,44 centímetros, no engrosado en 0.44 centímetros, estratificación conservadA. Adrenal derecha, forma, tamaño, ecogenicidad conservada en 0.62 centímetros. Páncreas de tamaño, ecogenicidad y grosor conservador.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (caso real de la polola, RECONSTRUIDA por Claude, no es la de la ayudante real):
- Bazo: el dictado dice "Vaso aumentado de tamaño... bordes levemente redondeados" (anormal, y "Vaso"="Bazo"). El bazo DEBE quedar aumentado de tamaño con bordes redondeados, NUNCA el normal de plantilla.
- Adrenal izquierda: el dictado se autocorrige "0.79... no, eso estaba en 0.57". Debe quedar 0.57 cm (el último valor), NO 0.79.
- Hígado "bordes abusados": debe marcarse con flag termino_confuso (posible "aguzados").
- Riñón izquierdo: límite duplicado en dictado ("levemente aumentado... difuso"); resolver a "difuso".
- Próstata anormal (disminuida, ecogenicidad disminuida) respetada; macho, después de Vejiga.
- Riñones 7.6 y 8 cm: legibles aunque grandes, se escriben tal cual (sin XX).
- Gastro separado; Páncreas con coletilla de rama derecha de plantilla.
TXT,
    'notas' => 'CASO REAL (polola). Respuesta esperada RECONSTRUIDA por Claude. Estresa: autocorrección del dictado (0.79->0.57), hallazgo anormal del dictado gana sobre plantilla (bazo aumentado pese a "Vaso" mal transcrito), flag termino_confuso en "abusados". Caso clave para validar los cambios A y B del prompt.',
];

$CASOS['runa'] = caso_base() + [
    'paciente' => 'runa',
    'dictado'  => <<<'TXT'
Paciente runa vejiga distendida, pared grosor aumentada 0.43 centímetros, levemente irregular, el contenido es anecoico. Cuerpo uterino engrosado en 1.5 por 2.4 centímetros, paredes irregulares engrosadas también con presencia de pequeñas lesiones hipoecoicas distribuidos en el parénchima. Colon Grosor conservado en 0,14 cm, con contenido semisólido, estratificación conservada. Vaso aumentado en longitud, grosor, ecogenicidad conservada. Riñón izquierdo Forma ovalada, bordes irregulares, ecogenicidad aumentada, Relación córtico medular aumentada, límite difuso, pelvis normal, ureter no visible y el tamaño es de 7.5 centímetros. Ovario izquierdo tamaño normal en 0.88 centímetros por 1.1 centímetros, ecogenicidad conservada. Cuerpo uterino izquierdo engrosado con paredes irregulares, con lesiones. Anécdotas en 0.73 centímetros de grosor, Estómago levemente engrosado 0.41 centímetros, estratificación conservada con visualización de al menos dos estructuras, una de 1.5 centímetros y otra de 1.7, es hiperecoica, ovalada con bordes irregulares que generan sombra acústica. El hígado bordes aguzados, ecogenicidad conservada. Ecotextura granular mixta. Vesícula biliar conservada. Riñón derecho Mismas características en tamaño en 7.3 centímetros. Duodeno levemente aumentado en 0,45 centímetros. Páncreas conservado. El duodeno tiene patrón alimenticio, estratificación conservada. Relleno con patrón mucoso, grosor conservado en 0,28 cm, estratificación conservada. Páncreas conservadores.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (caso real de la polola, RECONSTRUIDA por Claude, no es la de la ayudante real):
- Vejiga: pared aumentada 0.43 cm, levemente irregular (anormal del dictado, debe ganar a la plantilla).
- Bazo: "Vaso aumentado en longitud, grosor" -> bazo aumentado (anormal gana; "Vaso"="Bazo").
- Riñones 7.5 / 7.3 cm: legibles, se escriben tal cual. Riñón derecho "mismas características": expandir atributos del izquierdo.
- Estómago: conservar las dos estructuras hiperecoicas con sombra acústica (hallazgo importante).
- Yeyuno: el dictado dice "Relleno... 0,28 cm" ("Relleno"=Yeyuno mal transcrito). Idealmente toma 0.28; si no lo reconoce, dejar XX (NO adivinar).
- Adrenales: NO dictadas -> XX + flag valor_faltante en ambas.
- Reproductivo (hembra) en HALLAZGOS ADICIONALES: útero, ovario.
- "Anécdotas": término sin sentido (era "anecoicas"). CONSERVAR tal cual + flag termino_confuso. NUNCA adivinar/reemplazar.
- "Cuerpo uterino" aparece dos veces (uno con lateralidad): idealmente marcar incongruencia (no siempre lo detecta).
- Correspondencia flag<->observación debe quedar alineada (este caso tiene varios flags, valida el arreglo del cruce).
TXT,
    'notas' => 'CASO REAL (polola), HEMBRA, dictado muy ruidoso. Respuesta esperada RECONSTRUIDA por Claude. Caso clave para validar: flags en HALLAZGOS ADICIONALES, no adivinar términos ("Anécdotas"), y correspondencia flag<->observación con múltiples flags (arreglo del cruce). PENDIENTE conocido: cuerpo uterino duplicado no siempre se marca incongruencia; "Relleno"=Yeyuno no siempre se reconoce.',
];

$CASOS['fer'] = caso_base() + [
    'paciente' => 'fer',
    'dictado'  => <<<'TXT'
Paciente FER Vejigas semidistendidas, distendida, con contenido anecoico homogéneo, grosor conservado en 0.17 centímetros. Próstata disminuida, de tamaño de 0.8, de 0.88 a 1.74 centímetros, forma redonda, bordes levemente irregulares, ecogenicidad hipoecoica. Colon. De grosor aumentado en 0,22 centímetros, estratificación conservada, con contenido fecal semisólido. Vaso de forma tamaño conservado, con bordes redondeados. Riñón derecho, tamaño con observado en 5.6 centímetros, forma ovalada, bordes levemente irregulares, ecogenicidad cortical aumentada, relación córtico medular aumentada, límite córtico medular difuso. Imagen pélvica normal, Pelvis conservada. Ureter no visible. Adrenal izquierda de forma conservada, tamaño aumentado en 0.7 centímetros, ecogenicidad conservada. Estómago contraído, grosor aumentado en 0.49 centímetros, patrón mucoso estratificación conservada, con predominio de la capa muscular. Hígado de tamaño conservado, ecogenicidad aumentada, heterogéneo, bordes redondeados, Vesícula biliar distendida, con contenido anecoico, con presencia de barro biliar moderado. Riñón derecho, tamaño conservado en 5.8 centímetros, forma ovalada, bordes levemente irregulares, ecogenicidad cortical aumentada, relación córtico medular levemente aumentada, límite córtico medular difuso. Imagen pélvica normal. Ureter no visible, adrenal derecha, tamaño aumentado en 0.78 centímetros, forma ovalada, ecogenicidad aumentada. Yeyuno grosor aumentado en 0,55 centímetros, con patrón mucoso, estratificación conservada. Duodeno grosor aumentado en 00.58 centímetros, patrón mucoso estratificación conservada.- Páncreas rama izquierda. Aumentado en su ecogenicidad, grosor levemente aumentado, Parénchima heterogéneo por presencia de lesiones redondas con bordes difusos, hipoecoicas, con señal Doppler positivo. Lesiones de 0.4 por 0.44 centímetros, otra lesión de 0.24 por 4 por 4, 0.48 de 0.38 por 0.43 centímetros.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (caso real de la polola, RECONSTRUIDA por Claude, no es la de la ayudante real):
- RIÑÓN DUPLICADO (clave de este caso): el dictado dice "Riñón derecho" DOS veces con medidas distintas (5.6 y 5.8) sin corrección. Debe dejar el último (5.8) arriba y marcar flag incongruencia, anotando ambos valores en Observaciones (revisar si el de 5.6 era el izquierdo). NO descartar uno en silencio. NO inventar el izquierdo como normal.
- Próstata: macho, después de Vejiga; autocorrección "0.8, de 0.88 a 1.74" -> tomar el rango final.
- Bazo: "Vaso ... bordes redondeados" -> anormal del dictado respetado.
- Páncreas: rama derecha conservada (plantilla) + rama izquierda con lesiones (dictado). "rama derecha/izquierda" NO es duplicado, NO marcar incongruencia ahí.
- Lesión del páncreas "0.24 por 4 por 4": ilegible -> XX + flag medida_ilegible. Las otras lesiones (0.4x0.44 y 0.38x0.43) legibles, se escriben.
- Adrenales aumentadas (0.7 / 0.78) respetadas.
TXT,
    'notas' => 'CASO REAL (polola), MACHO. Respuesta esperada RECONSTRUIDA por Claude. Caso clave para validar la regla de ÓRGANO DUPLICADO (riñón derecho x2) Y que NO genere falso positivo en "páncreas rama derecha/izquierda". También valida medida_ilegible en lesiones del páncreas. PENDIENTE conocido: la detección de órgano duplicado es la regla más nueva y de mayor riesgo de falsos positivos.',
];

$CASOS['emma'] = caso_base() + [
    'paciente' => 'emma',
    'dictado'  => <<<'TXT'
Paciente Hema felino Vejiga muy poco distendida, grosor levemente aumentada en 0.3 centímetros, bordes irregulares. Colon engrosado en 0.21 centímetros, con contenido fecal líquido moderado, estratificación conservada. Riñón izquierdo. Tamaño conservado en 4.5 centímetros, forma ovalada, bordes levemente irregulares, ecogenicidad levemente aumentada, relación córtico medular disminuida, límite córtico medular difuso. Imagen pélvica normal. Ureterma visible. Vaso de tamaño conservado, ecogenicidad mixta, con presencia de al menos dos lesiones de 0.6 por 0.66 cm y la otra de 0.77 x 0.98 cm, además con lesiones puntiformes hiperecoicas distribuidos en el hilo esplénico. Estómago contraído con patrón mucoso, estratificación conservada, grosor conservado en solo 28 centímetros. Yeyuno grosor aumentado en 0,36 centímetros, estratificación con patrón líquido. Estratificación con predominio de la capa muscular y submucosa, ambas en 0,09 centímetros. Páncreas aumentado de tamaño en 1.28 centímetros, ecogenicidad aumentada. Heterogéneo paréntema con presencia de lesiones redondas hipoecoicas de 0,47 por 0,52, otra lesión de 1 centímetro por 1.28 centímetros. Duodeno engrosado en 0.47 centímetros, con patrón líquido, estratificación conservada. Riñón derecho Forma ovalada, bordes levemente irregulares, tamaño conservado en 4.1 centímetros, ecogenicidad cortical aumentada, relación córtico medular disminuida, límite córtico medular difuso. Imagen pélvica normal. Ureter no visible. Hígado Tamaño conservado. Parénchima aumentado en su ecogenicidad, Patrón mixto granular mixto, perdón, patrón granular mixto. Vesícula biliar distendida con contenido anecoico homogéneo, Linfonódulos yeyunales y pancrático duodenal de aspecto reactivo y León engrosado en 0.34 centímetros, con patrón líquido, estratificación conservada. Ciego aumentado de tamaño 0,68 x 0,92 centímetros, forma alargada, ecogenicidad aumentada. Peritoneo de aspecto reactivo generalizado.
TXT,
    'ayudante' => <<<'TXT'
SALIDA ESPERADA (caso real de la polola, RECONSTRUIDA por Claude, no es la de la ayudante real):
- Estómago "28 cm": transcripción mala (el audio decía 0.28). El sistema NO debe adivinar: escribir "28 cm" + flag valor_sospechoso (imposible en felino). El vet corrige a mano.
- Bazo: "Vaso ... ecogenicidad mixta, con lesiones" -> anormal del dictado respetado ("Vaso"=Bazo). Lesiones del bazo conservadas.
- Íleon: el dictado lo nombra "León" mal transcrito y tardío (después de linfonódulos). Debe reconocerse como Íleon y ubicarse entre Yeyuno y Colon.
- Páncreas aumentado con lesiones; linfonódulos reactivos (anormales del dictado ganan a plantilla).
- Ciego: no tiene posición fija en plantilla -> HALLAZGOS ADICIONALES. Peritoneo reactivo también.
- Adrenales NO dictadas -> XX + flag valor_faltante en ambas.
- Hígado: autocorrección "patrón mixto granular mixto, perdón, patrón granular mixto" -> tomar "patrón granular mixto".
TXT,
    'notas' => 'CASO REAL (polola), FELINO, dictado complejo. Respuesta esperada RECONSTRUIDA por Claude. Estresa: valor_sospechoso en "28 cm" (mala transcripción de 0.28, NO adivinar), íleon desordenado y mal transcrito ("León"), ciego en HALLAZGOS ADICIONALES, autocorrección del hígado, lesiones múltiples en bazo y páncreas. NO confundir: el "28" se conserva tal cual con flag, no se corrige a 0.28.',
];

return ['plantilla' => PLANTILLA_NEUTRAL, 'casos' => $CASOS];