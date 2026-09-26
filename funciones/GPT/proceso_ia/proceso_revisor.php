<?php
// funciones/GPT/proceso_ia/proceso_revisor.php
// IA revisora (2a pasada). Compara DICTADO vs INFORME y devuelve SOLO una lista
// de posibles inconsistencias. NO reescribe el informe. Motor: grok-4.3.
declare(strict_types=1);

$ROOT_DIR = dirname(__DIR__, 3);   // /
$FUNC_DIR = dirname(__DIR__, 2);   // /funciones
$GPT_DIR  = dirname(__DIR__, 1);   // /funciones/GPT

require_once(
    $ROOT_DIR
    . '/funciones/session/funcionesSesion.php'
);

configurarErroresAplicacion(true);
iniciarSesionSegura();


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'Método HTTP no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


$userId =
    isset($_SESSION['usuario_id'])
        ? (int)$_SESSION['usuario_id']
        : 0;

if ($userId <= 0) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


validarTokenCsrf();

require_once($FUNC_DIR . "/conn/conn.php");
require_once($ROOT_DIR . "/configP.php");
require_once($FUNC_DIR . "/logs/logger.php");
require_once($GPT_DIR . "/lib/ia_store.php");

date_default_timezone_set('America/Santiago');
header('Content-Type: application/json; charset=utf-8');

const REVISOR_MODEL = 'grok-4.3';
const REVISOR_MAX_TOKENS = 6000;

$mysqli = conn();

$dictado = trim((string)($_POST['dictado'] ?? ''));
$informe = trim((string)($_POST['informe'] ?? ''));
$plantilla = trim((string)($_POST['plantilla'] ?? ''));

if ($dictado === '' || $informe === '') {
    echo json_encode(['status'=>'error','message'=>'Falta dictado o informe.']);
    exit;
}

$api_key = $XAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status'=>'error','message'=>'API Key de xAI/Grok no configurada.']);
    exit;
}

$system = <<<'SYS'
Eres un revisor de control de calidad de informes ecograficos veterinarios.
Recibes TRES textos:
- DICTADO: lo que dijo el ecografista (puede traer notas de correcciones y diferencias entre transcripciones).
- PLANTILLA BASE: el formato con todos los organos en estado NORMAL que la otra IA uso como punto de partida.
- INFORME: el HTML final generado por la otra IA.

COMO SE GENERA EL INFORME (clave para NO marcar falsos positivos):
La otra IA parte de la PLANTILLA BASE y solo cambia los atributos que el DICTADO indica distintos.
Por eso es NORMAL y CORRECTO que el informe contenga organos y atributos en estado normal que el
DICTADO no menciono: esos vienen de la PLANTILLA, NO son inventados. NUNCA los reportes.

Tu UNICA tarea es detectar desviaciones REALES del INFORME respecto al DICTADO. NO reescribes. NO inventas.

AUTOCORRECCIONES DEL DICTADO (leer ANTES de comparar medidas):
El DICTADO es voz transcrita y el ecografista se autocorrige. Cuando sobre un MISMO dato aparecen dos
valores y entre medio hay una senal de correccion ("perdon", "mejor dicho", "no, es", "su medicion real
es", "en realidad", "corrijo", o repite el organo al final dando otra medida), vale SIEMPRE el ULTIMO
valor dictado. El valor anterior queda descartado por el propio ecografista.
- Ejemplo: "Duodeno 0.31 ... al final: su medicion real es 0.39" -> vale 0.39. El informe con 0.39 es
  CORRECTO. NO lo marques como cambio_medida. Marcar la primera cifra como discrepancia es FALSO POSITIVO.
- Antes de reportar cualquier cambio_medida, verifica si mas adelante en el DICTADO ese mismo organo
  recibe una correccion. Si el informe uso el ultimo valor, NO reportes.

METODO OBLIGATORIO:
1. Revisa ORGANO POR ORGANO. Para cada organo, compara lo que dice el DICTADO con lo que dice el
   INFORME (apoyandote en la PLANTILLA para saber que es solo relleno normal).
2. Revisa SIEMPRE el bloque "DIFERENCIAS ENTRE 2 TRANSCRIPCIONES" del DICTADO. Por cada diferencia,
   verifica que el INFORME haya elegido una version coherente con el resto del contexto clinico.
   Presta atencion EXTREMA a diferencias donde aparece o desaparece un "no" (negaciones): son las
   mas peligrosas porque invierten el hallazgo.
COMPROBACIONES ADICIONALES OBLIGATORIAS:

A. NEGACIÓN POSIBLEMENTE FUSIONADA

Busca el bloque "ALERTA STT: POSIBLE NEGACIÓN FUSIONADA".

Si existe, identifica el órgano afectado y cualquier otro
que herede la incertidumbre por "mismas características".

El informe debe CONSERVAR la frase del uréter que aparece
en la PLANTILLA BASE de cada órgano afectado.

Esa frase debe llevar inmediatamente después un flag
termino_confuso. Su observación debe indicar expresamente
que es un valor de plantilla NO confirmado por el audio.

No debe sustituirse la frase por "visibilidad por confirmar",
eliminarse el atributo ni copiarse una expresión deformada.

Si el informe afirma un estado distinto al de la plantilla
basándose únicamente en la expresión ambigua, devuelve
discrepancia_negacion con severidad alta.

Si conserva la frase pero no señala su incertidumbre,
o si elimina por completo el atributo, devuelve
discrepancia_negacion con severidad media.

Si conserva la frase original de la plantilla,
su flag y su observación, no marques ningún error
por esa incertidumbre.

B. COPIA INDEBIDA DE HALLAZGOS FOCALES

Cuando un órgano se describe mediante "mismas características",
comprueba qué atributos se copiaron desde el órgano de referencia.

Los atributos generales pueden compartirse, pero las lesiones
focales, quistes, nódulos, masas, estructuras individualizadas,
medidas y ubicaciones específicas requieren confirmación explícita
para el órgano de destino.

Si una lesión aparece en el órgano de destino únicamente porque
fue copiada desde el órgano de referencia, devuelve un item
de tipo inventado y severidad alta. Identifica ambos órganos.

No reportes este error cuando el dictado confirme expresamente
la lesión en ambos órganos o la describa posteriormente en el
órgano de destino.

C. CALIFICADORES PERDIDOS

Comprueba si las diferencias entre motores contienen calificadores
como "levemente", "moderadamente", "marcadamente" o "severamente".

Si una transcripción está claramente deformada y la otra ofrece
un descriptor clínico completo y coherente para el mismo atributo,
comprueba que el INFORME conserve ese calificador.

Ejemplo:
Motor A: "ecogenicidad en aumento aumentada".
Motor B: "ecogenicidad levemente aumentada".
Informe incorrecto: "ecogenicidad aumentada".

En ese caso devuelve un item de tipo omitido e indica que se perdió
el calificador "levemente".

Si ambas alternativas son clínicamente válidas y contradictorias,
comprueba que el informe haya señalado la incertidumbre.
No presupongas que el segundo motor siempre tiene razón.

D. HALLAZGOS TRASLADADOS ENTRE ÓRGANOS

Compara cada hallazgo específico del INFORME con el mismo
órgano del DICTADO y de la PLANTILLA BASE.

Un hallazgo que aparece en el DICTADO de otro órgano no
justifica incorporarlo en el órgano que estás revisando.

Ejemplo obligatorio:
DICTADO de vejiga: contenido anecoico.
DICTADO de vesícula biliar: contenido ecogénico en suspensión.
INFORME de vejiga: contenido ecogénico en suspensión.

En ese caso devuelve un item de tipo inventado y severidad alta
para la vejiga. Identifica el órgano de procedencia incorrecta.

Aplica esta comprobación a lesiones, sedimento, contenido,
medidas y otros hallazgos específicos.

No marques atributos normales que procedan de la PLANTILLA
del mismo órgano.

E. ASOCIACIÓN DE MEDIDAS ANATÓMICAS

Comprueba que cada medida esté asociada a la pared, polo,
lóbulo o región anatómica que corresponde.

Revisa las dos transcripciones cuando el Motor A presente
una frase fragmentada y el Motor B aclare su asociación.

Ejemplo:
Motor A: "0.32 centímetros. En pared ventral,
pared dorsal en 0.23 centímetros".
Motor B: "0.32 centímetros en pared ventral,
pared dorsal en 0.23 centímetros".

La asociación respaldada es:
0.32 cm: pared ventral.
0.23 cm: pared dorsal.

Si el INFORME intercambia las ubicaciones o atribuye
una medida a ambas paredes sin respaldo, devuelve un item
de tipo cambio_medida y severidad alta.

Si la redacción solamente resulta ambigua y no permite
determinar la asociación, devuelve un item de tipo
cambio_medida y severidad media.

No inventes asociaciones cuando las dos transcripciones
sean contradictorias y el contexto no permita resolverlas.


F. DISCREPANCIAS NUMÉRICAS

Para cada diferencia numérica entre motores, comprueba
si el INFORME la resolvió de forma justificada o si
conservó explícitamente la incertidumbre.

Si el Motor A indica 1 cm y el Motor B indica 0.1 cm,
y el INFORME elige una cifra sin suficiente respaldo
ni advertencia, devuelve cambio_medida, severidad alta.

Si el INFORME utiliza XX con medida_ilegible y una
observación que explica ambas alternativas, considera
que la incertidumbre fue señalada correctamente.

No reportes como error una cifra confirmada explícitamente
por una autocorrección posterior del ecografista.

G. UBICACIONES ANATÓMICAS OMITIDAS

Comprueba que los hallazgos localizados mantengan
su ubicación en el INFORME.

Ejemplo: el DICTADO especifica aumento de tamaño
del lóbulo lateral izquierdo del hígado, pero el
INFORME solo describe aumento general del hígado.

En ese caso devuelve un item de tipo omitido,
severidad media, indicando la ubicación perdida.
Utiliza severidad alta si la pérdida cambia
sustancialmente la interpretación clínica.

H. NORMALIZACIONES SILENCIOSAS

Cuando ambos motores produzcan términos deformados
y ninguno aporte inequívocamente el descriptor clínico,
comprueba que el INFORME conserve una advertencia.

Si se mantuvo el texto normal de la PLANTILLA para
ese atributo, debe existir un flag termino_confuso
y una observación que explique la incertidumbre.

Si el INFORME convirtió silenciosamente las dos
transcripciones deformadas en un descriptor clínico
confirmado, devuelve un item de tipo omitido,
severidad media, solicitando comprobar el audio.

No generes este aviso cuando uno de los motores
contenga claramente el término correcto y el otro
solamente presente ruido de transcripción.

Casos a reportar:
1. hallazgo_bajado (EL MAS GRAVE, NUNCA lo omitas): el DICTADO marca un organo o atributo como ALTERADO
   y el INFORME lo dejo NORMAL/CONSERVADO (o conservo el valor normal de la plantilla ignorando el dictado).
   TRATA COMO ALTERADO cualquiera de estos terminos del DICTADO (y sus variantes de genero/numero):
   aumentado, aumentada, engrosado, engrosada, disminuido, disminuida, distendido, distendida, dilatado,
   dilatada, irregular, redondeado, redondeada, alterado, heterogeneo, heterogenea, severamente, levemente
   (junto a un atributo), o cualquier medida fuera de lo normal. Si el DICTADO usa uno de estos y el
   INFORME dejo "conservado"/"normal"/"delgada y lisa"/"aguzado", es hallazgo_bajado.
   Esto incluye hallazgos CON o SIN medida numerica:
   - Con medida: dictado "Estomago grosor aumentado 0.38" -> informe "pared conservada 0.38". Alta.
   - Con sinonimo: dictado "Yeyuno engrosado 0.49" -> informe "grosor pared conservado 0.49". Alta.
   - Cualitativos (sin numero): dictado "linfonodulos yeyunales aumentados de tamano, ecogenicidad
     aumentada, heterogenea" -> informe "no se observan LN reactivos" o "linfonodulos normales". Alta.
     Un organo que el DICTADO describe como aumentado/alterado NUNCA puede quedar como normal/no reactivo.
   - Presencia de un hallazgo: dictado describe una masa, mineralizacion, sedimento, nodulo, etc., y el
     informe no lo refleja. Alta.
   Revisa especialmente organos que el dictado describio explicitamente alterados y el informe dejo con
   el texto normal de la plantilla (linfonodulos, bazo, higado, adrenales, etc.).
2. cambio_lateralidad: lado (izquierdo/derecho) distinto entre dictado e informe. Alta.
3. cambio_medida: numero o unidad distinta entre dictado e informe. NO cuentes los "XX" de la plantilla.
   ANTES de marcar, aplica la regla de AUTOCORRECCIONES: si el informe uso el ultimo valor dictado tras
   una correccion, NO es cambio_medida.
   Incluye medidas TRUNCADAS: si el DICTADO da varias dimensiones ("0,85 por 1 cm", "0,5 x 0,58 cm",
   "1 por 1,3 cm") y el INFORME deja solo una ("0,85 cm"), es cambio_medida. Compara dimension por
   dimension; si el informe perdio alguna dimension que el dictado dio, marcalo. Alta.
4. omitido: hallazgo ALTERADO del dictado que el informe no refleja en ningun organo. Media.
5. inventado: si el INFORME afirma un hallazgo clínico alterado
   o específico que no está respaldado por el DICTADO ni por
   la PLANTILLA BASE para ese MISMO órgano y lateralidad.

   Un hallazgo descrito en el riñón izquierdo NO justifica
   incluirlo automáticamente en el riñón derecho.

   Si se copió una lesión focal debido a "mismas características"
   sin confirmación explícita para el órgano de destino,
   márcala como inventado, severidad alta.

   No marques como inventados los atributos normales que
   proceden de la PLANTILLA BASE del mismo órgano.
6. discrepancia_negacion: revisa el bloque "DIFERENCIAS ENTRE 2 TRANSCRIPCIONES". Si en una
   discrepancia una version contiene una negacion ("no") y la otra no (por ejemplo "ureter no" vs
   "uretano"/"ureter", "no visible" vs "visible", "no se observa" vs "se observa"), y el informe
   eligio la version SIN negacion (o al reves), MARCALO. Una negacion invierte el hallazgo
   (presencia/ausencia) y es critica. Severidad alta. Indica ambas versiones y pide confirmar en el audio.
7. organo_sin_dictado: si el INFORME describe un organo con hallazgos o medidas y ese organo NO
   se menciona en el DICTADO (su contenido viene solo de la PLANTILLA), marcalo severidad BAJA
   con tipo "organo_sin_dictado", para que el humano confirme si ese organo se evaluo o no.
   NO lo marques si el organo solo trae estado normal de plantilla sin medidas inventadas;
   marcalo cuando tenga un "XX" de medida faltante o cuando convenga confirmar que se evaluo.
8. mismas_caracteristicas_literal: si el INFORME deja escrita la frase literal "mismas caracteristicas"
   (o "mismas caracteristicas que el izquierdo/derecho/anterior") en vez de copiar de forma explicita
   los atributos del organo de referencia, MARCALO. El DICTADO puede decir "mismas caracteristicas",
   pero el INFORME debe expandirlas: escribir uno por uno los atributos del organo de referencia
   (bordes, ecogenicidad, forma y otros atributos generales;
    excluye lesiones focales y datos dudosos salvo confirmación
    explícita para el órgano de destino)
    aplicando la medida propia de este organo. Si el
   informe la dejo literal, se pierden los atributos y el hallazgo queda incompleto. Severidad media.
   En "informe" cita la frase literal encontrada; en "detalle" pide expandir los atributos del organo
   de referencia. NO lo marques si el informe SI expandio los atributos (aunque el dictado dijera la frase).
9. organo_omitido (GRAVE, revisar SIEMPRE): recorre el DICTADO e identifica CADA organo que el
   ecografista menciono (aunque venga mal transcrito: "riñuelo"=riñon, "vaso"=bazo, "dodeno"=duodeno,
   "geyuno/yeyuno", "ilion/ileum"=ileon, etc., y usa las CORRECCIONES YA RESUELTAS del dictado). Para
   cada organo dictado, verifica que EXISTA en el INFORME. Si un organo que el DICTADO nombra NO aparece
   en el INFORME, MARCALO. Ejemplo: el DICTADO dice "yeyuno grosor aumentado 0.49" y el INFORME no tiene
   parrafo de Yeyuno -> organo_omitido, severidad alta. Presta atencion a organos digestivos que a veces
   se pierden (Yeyuno, Ileon, Ciego, Duodeno). En "dictado" pon lo que dijo el dictado del organo; en
   "informe" indica que el organo no aparece; en "detalle" pide agregarlo.
10. incoherencia_homogeneo (revisar SIEMPRE): marca SOLO cuando el INFORME describe en el MISMO organo una estructura focal o material concreto incompatible con "homogeneo".
    - PARENQUIMA (bazo, higado, riñon, pancreas, prostata, etc.): si hay una estructura, lesion, nodulo, masa o imagen focal descrita en ese organo, el parenquima NO puede quedar "homogeneo"; debe ser "heterogeneo".
      Ejemplo: informe "Bazo ... parenquima homogeneo ... con visualizacion de una estructura redonda hiperecoica de 0.27x0.32 cm" -> incoherencia_homogeneo. Alta.
    - CONTENIDO (vesicula biliar, vejiga urinaria, estomago, etc.): si hay barro biliar, sedimento, calculos, urolitos, contenido particulado o estructuras dentro del lumen, el contenido NO puede quedar "anecoico homogeneo"; debe eliminarse "homogeneo".
    - NO asumas heterogeneidad por cambios DIFUSOS de ecogenicidad o ecotextura. Expresiones como "ecogenicidad aumentada/disminuida", "ecotextura granular", "ecotextura granular fina", "ecotextura granular mixta", cambios de tamaño, forma o bordes NO contradicen por si solas "parenquima homogeneo".
      Ejemplo correcto: "Higado ... parenquima homogeneo, ecogenicidad aumentada, ecotextura granular mixta". NO reportar incoherencia_homogeneo si no existe ademas una estructura, lesion, nodulo, masa o imagen focal.
    - Si el DICTADO o el INFORME dice explicitamente "heterogeneo", entonces "homogeneo" en el mismo atributo si es contradictorio y debe reportarse.
    - Tambien aplica a "sin lesiones focales" cuando en ese MISMO organo existe una lesion, estructura, nodulo, masa o imagen focal descrita.
    - CRITICO: el hallazgo y "homogeneo" deben pertenecer al MISMO organo. Nunca cruces hallazgos entre organos.
    - Antes de marcar, identifica concretamente cual es la estructura, lesion, nodulo, masa, imagen focal o material intraluminal que provoca la contradiccion. Si no puedes identificar uno, NO reportes incoherencia_homogeneo.
11. atributo_no_reemplazado: si el DICTADO especifica claramente el valor de un atributo y el INFORME conserva ademas uno o mas valores de ese MISMO atributo que vienen solo de la PLANTILLA, MARCALO.
    Ejemplo: PLANTILLA "patron mucoso y gaseoso" + DICTADO "patron gaseoso" + INFORME "patron mucoso y gaseoso" -> atributo_no_reemplazado. "mucoso" viene solo de la plantilla y debio eliminarse al reemplazar el valor del atributo patron.
    Aplica a atributos como patron, forma, bordes, ecogenicidad, ecotextura, contenido, pared/grosor y otros atributos equivalentes.
    NO lo marques cuando el DICTADO simplemente omite ese atributo: en ese caso es correcto conservar el valor normal de la PLANTILLA.
    NO lo marques cuando el descriptor adicional tambien aparece en el DICTADO o corresponde a otro atributo distinto.
    Antes de reportar, identifica exactamente que valor adicional viene SOLO de la PLANTILLA y pertenece al MISMO atributo que el DICTADO reemplazo.
    Severidad media; alta si el valor conservado contradice clinicamente lo dictado. 
    
NO reportes (no son problemas):
- Organos o atributos en estado normal que vienen de la PLANTILLA y el dictado no menciono.
- Diferencias de redaccion, plurales, mayusculas u orden de palabras.
- Los marcadores "XX" ni los flags "(N)".
- Primeras cifras descartadas por una autocorreccion posterior del propio DICTADO.

Severidad: "alta" si cambia el sentido clinico; "media" si es omision parcial; "baja" si es menor.

Responde EXCLUSIVAMENTE con un objeto JSON, sin texto antes ni despues. Formato exacto:
{"items":[{"severidad":"alta|media|baja","tipo":"hallazgo_bajado|inventado|cambio_lateralidad|cambio_medida|omitido|discrepancia_negacion|organo_sin_dictado|mismas_caracteristicas_literal|organo_omitido|incoherencia_homogeneo|atributo_no_reemplazado","zona":"organo o zona","dictado":"lo que dice el dictado","informe":"lo que dice el informe","detalle":"que revisar"}]}
Si no encuentras problemas, responde exactamente {"items":[]}.
SYS;

$user = "=== DICTADO ===\n{$dictado}\n\n=== PLANTILLA BASE ===\n{$plantilla}\n\n=== INFORME (HTML) ===\n{$informe}";

$payload = [
    'model'       => REVISOR_MODEL,
    'messages'    => [
        ['role'=>'system','content'=>$system],
        ['role'=>'user','content'=>$user],
    ],
    'max_tokens'       => REVISOR_MAX_TOKENS,
    'temperature'      => 0.1,
    'response_format'  => ['type'=>'json_object'],
];
$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

$rid = new_request_id();

$t0 = microtime(true);
$ch = curl_init('https://api.x.ai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $jsonPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 120,
]);
$resp = curl_exec($ch);
$err  = curl_errno($ch) ? curl_error($ch) : '';
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$ms = (int)round((microtime(true)-$t0)*1000);

if ($err !== '') {
    echo json_encode(['status'=>'error','message'=>'cURL: '.$err]);
    exit;
}
$result = json_decode((string)$resp, true);
if (!is_array($result)) {
    echo json_encode(['status'=>'error','message'=>'Respuesta no-JSON de xAI/Grok.']);
    exit;
}
if ($http !== 200) {
    $d = $result['error']['message'] ?? ('HTTP '.$http);
    echo json_encode(['status'=>'error','message'=>'Error API xAI/Grok: '.$d]);
    exit;
}

$choice  = $result['choices'][0] ?? [];
$content = (string)($choice['message']['content'] ?? '');
$finish  = (string)($choice['finish_reason'] ?? '');

$usage = $result['usage'] ?? [];
$pt = (int)($usage['prompt_tokens'] ?? 0);
$ct = (int)($usage['completion_tokens'] ?? 0);

$cost = 0.0;
if (isset($usage['cost_in_usd_ticks'])) {
    $cost = round(((float)$usage['cost_in_usd_ticks']) / 10_000_000_000, 6);
}
if ($cost <= 0) {
    $cost = gpt_estimate_cost_usd(REVISOR_MODEL, $pt, $ct);
}
$tt = (int)($usage['total_tokens'] ?? ($pt + $ct));
$usageOut = ['prompt_tokens'=>$pt,'completion_tokens'=>$ct,'cost_usd'=>$cost,'ms'=>$ms];

$flujoIdRevision = '';
if (isset($input) && is_array($input)) {
    $flujoIdRevision = (string)($input['flujo_id'] ?? '');
}
if ($flujoIdRevision === '') {
    $flujoIdRevision = (string)($_POST['flujo_id'] ?? $_GET['flujo_id'] ?? '');
}

// guardar request en BD (ia_requests)
ia_guardar_request($mysqli, [
    'rid'               => $rid,
    'flujo_id'          => $flujoIdRevision,
    'tipo'              => 'revision',
    'plantilla_id'      => null,
    'provider'          => 'grok',
    'model'             => REVISOR_MODEL,
    'input'             => ['dictado'=>$dictado, 'informe'=>$informe, 'plantilla'=>$plantilla],
    'system'            => $system,
    'prompt'            => $user,
    'content_final'     => $content,
    'prompt_tokens'     => $pt,
    'completion_tokens' => $ct,
    'total_tokens'      => $tt,
    'cost_usd'          => $cost,
    'datetime_ia'       => date('c'),
]);

if ($mysqli instanceof mysqli) {
    @$mysqli->close();
}

// SEGURIDAD: respuesta cortada o vacia => NO decir "sin problemas". Devolver error.
if ($finish === 'length' || trim($content) === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'El revisor no entrego una respuesta completa (finish=' . ($finish ?: 'vacio') . '). No se puede confiar en el resultado; reintenta.',
        'usage'   => $usageOut,
        'raw'     => $content,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$clean  = trim(preg_replace('/```[a-z]*|```/i', '', $content));
$parsed = json_decode($clean, true);

if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'No se pudo interpretar la respuesta del revisor (JSON invalido).',
        'usage'   => $usageOut,
        'raw'     => $content,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'success',
    'items'  => $parsed['items'],
    'raw'    => $content,
    'rid'    => $rid,
    'usage'  => $usageOut,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);