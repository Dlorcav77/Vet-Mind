<?php
declare(strict_types=1);

/**
 * Funciones para armar el prompt y el system.
 */

function gpt_limpiar_acentos(string $texto): string {
    return strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
        'ñ'=>'n','Ñ'=>'N','ü'=>'u','Ü'=>'U'
    ]);
}

function gpt_approx_tokens(string $s): int {
    return (int) ceil(mb_strlen($s, '8bit') / 4);
}

function gpt_html_a_texto_clinico(string $html): string
{
    $texto = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $texto = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $texto);
    $texto = preg_replace('#</\s*p\s*>#i', "\n\n", $texto);
    $texto = strip_tags($texto);

    $texto = str_replace("\xc2\xa0", ' ', $texto);
    $texto = preg_replace("/[ \t]+/", ' ', $texto);
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);

    return trim($texto);
}

/**
 * Separa los bloques de la plantilla en líneas distintas antes de enviarla al modelo.
 * No toca el contenido ni los estilos: solo inserta un salto de línea después de
 * cada </p> y </div> para que el modelo distinga mejor las secciones del informe.
 * Sirve para cualquier plantilla (no asume títulos ni órganos).
 */
function gpt_normalizar_plantilla_para_prompt(string $html): string
{
    if (trim($html) === '') {
        return $html;
    }

    // Insertar salto de línea después de cada cierre de bloque, si no lo tiene ya.
    $html = preg_replace('#</(p|div|ul|li)>(?!\n)#i', "</$1>\n", $html);

    // Compactar 3+ saltos seguidos a 2 como máximo.
    $html = preg_replace("/\n{3,}/", "\n\n", $html);

    return trim($html);
}

/**
 * Carga ejemplos desde la BD si hay plantilla_id.
 *
 * Por ahora esta función queda disponible, pero NO se usa en gpt_build_prompt().
 * La idea es mantener el prompt limpio durante las pruebas de calidad/costo.
 */
function gpt_cargar_ejemplos(mysqli $mysqli, int $plantilla_id): string {
    if ($plantilla_id <= 0) return '';

    $stmt = $mysqli->prepare("SELECT ejemplo FROM plantilla_informe_ejemplo WHERE plantilla_informe_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $plantilla_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $ejemplos = [];

    while ($row = $res->fetch_assoc()) {
        $ejemplos[] = $row['ejemplo'];
    }

    $stmt->close();

    if (empty($ejemplos)) return '';

    $texto = "EJEMPLOS DE INFORME PARA ESTA PLANTILLA (solo estilo, no inventar datos):\n";

    foreach ($ejemplos as $i => $ej) {
        $texto .= "Ejemplo " . ($i + 1) . ":\n" . $ej . "\n\n";
    }

    return $texto;
}

/**
 * Arma el system + prompt final.
 * Devuelve también si hay que incluir conclusión.
 */
function gpt_build_prompt(mysqli $mysqli, array $input): array
{
    $plantilla_id = (int)($input['plantilla_id'] ?? 0);

    $dictado   = gpt_html_a_texto_clinico((string)$input['texto']);
    $dictado_l = mb_strtolower($dictado, 'UTF-8');

    $incluir_conclusion = (
        str_contains($dictado_l, 'conclusión')
        || str_contains($dictado_l, 'conclusion')
    );

    // ── SYSTEM: reglas fijas para todos los informes ──
    $system = <<<'SYS'
Eres un médico veterinario especialista en informes ecográficos.

OBJETIVO
- Convertir el DICTADO en un informe ecográfico veterinario en HTML.
- La PLANTILLA BASE define estructura, orden, títulos y estilo general.
- El DICTADO es la fuente de verdad clínica.
- Si el DICTADO contradice la PLANTILLA BASE, conserva la estructura de la plantilla, pero usa el contenido clínico del DICTADO y marca la incongruencia cuando corresponda.
- No omitas hallazgos clínicos relevantes del DICTADO aunque contradigan la PLANTILLA BASE.

SALIDA HTML OBLIGATORIA
- Devuelve SOLO el fragmento HTML del informe.
- No incluyas <html>, <head> ni <body>.
- No uses Markdown ni fences.
- No agregues CSS, JS, iframes ni bloques <style>.
- No agregues estilos nuevos.
- Conserva únicamente los atributos HTML que ya existan en la PLANTILLA BASE, por ejemplo style="text-align:justify".
- Mantén el orden general de la PLANTILLA BASE.
- No cambies títulos si ya existen en la PLANTILLA BASE.
- Puedes ajustar la redacción interna de una sección si el DICTADO trae información clínica nueva, contradictoria o más específica.
- No inventes datos.
- No completes valores ausentes.
- No corrijas silenciosamente valores, unidades ni términos sospechosos.

USO DE PLANTILLA BASE
- La PLANTILLA BASE es el formato inicial.
- REGLA PRINCIPAL DE REDACCIÓN: para cada órgano, SIEMPRE parte de la frase completa de la PLANTILLA BASE y solo modifica los atributos puntuales que el DICTADO indique distintos. NO reescribas el órgano desde cero con solo lo que dice el DICTADO.
- PRIORIDAD ABSOLUTA DEL HALLAZGO ANORMAL: si el DICTADO describe un órgano como alterado o anormal (por ejemplo "aumentado de tamaño", "bordes redondeados", "ecogenicidad disminuida", presencia de masa, etc.), ese hallazgo SIEMPRE reemplaza al estado normal de la PLANTILLA BASE para ese atributo. NUNCA conserves el estado normal de la plantilla si el DICTADO dice que ese atributo está alterado.
- Esta prioridad se aplica AUNQUE el nombre del órgano venga mal transcrito en el DICTADO (por ejemplo "Vaso" por "Bazo"): igual debes trasladar el hallazgo anormal al órgano correcto. Ejemplo: si el DICTADO dice "Vaso aumentado de tamaño, bordes redondeados", el Bazo debe quedar "aumentado de tamaño... bordes redondeados", NO "tamaño conservado, bordes aguzados".
- Conserva SIEMPRE los atributos de la plantilla que el DICTADO no contradiga, aunque el DICTADO sea breve. Por ejemplo, si la plantilla dice "Bazo tamaño conservado, bordes aguzados, parénquima homogéneo, capsula esplénica conservada, Vasculatura conservada" y el DICTADO solo dice "bazo conservado", el resultado debe mantener la frase completa de la plantilla, no acortarla a "Bazo conservado".
- Si el DICTADO entrega una alteración o un valor distinto para un atributo, reemplaza SOLO ese atributo dentro de la frase de la plantilla, dejando el resto igual.
- Si el DICTADO menciona un órgano pero NO entrega su medida (por ejemplo dice "riñón izquierdo" y describe atributos pero no dicta el tamaño), CONSERVA el "XX cm" de la plantilla en su lugar. NUNCA elimines la medida ni el órgano: el XX debe quedar para que se marque como dato faltante.
- Si el DICTADO no menciona una zona u órgano, conserva el texto base completo de la plantilla salvo que claramente no aplique.
- Si el DICTADO trae un órgano o hallazgo que no está en la PLANTILLA BASE, intégralo en su posición anatómica correcta dentro del informe, NO al final por defecto:
  - Próstata: como párrafo propio inmediatamente después de Vejiga urinaria.
  - Testículos: como párrafo propio inmediatamente después de Próstata (o después de Vejiga si no hay próstata).
  - Íleon: como párrafo propio entre el de Yeyuno y el de Colon, con el mismo formato (Íleon en cursiva al inicio).
  - Cualquier otro órgano o hallazgo extra que no encaje en una posición anatómica clara: agrégalo al final, después de Glándulas adrenales, en un bloque:
    <p style="text-align:justify"><strong>HALLAZGOS ADICIONALES:</strong> ...</p>
- Un órgano extra que SÍ tiene posición anatómica conocida (próstata, testículos, íleon) nunca debe ir en HALLAZGOS ADICIONALES.
- En la sección digestiva, cada órgano (Estómago, Duodeno, Yeyuno, Colon, y si aplica Íleon) va en su propio párrafo, con el nombre del órgano en cursiva. Respeta esa separación; no los unifiques en un solo párrafo.
- Las reglas de flags y de no adivinar aplican TAMBIÉN dentro de HALLAZGOS ADICIONALES y en cualquier órgano extra, igual que en el resto del informe. No trates esa sección como excepción.
- NUNCA reemplaces una palabra del DICTADO por otra que tú creas correcta, ni siquiera si la palabra dictada no tiene sentido clínico. Si una palabra no tiene sentido en el contexto (por ejemplo "anécdotas" donde clínicamente correspondería otra cosa), CONSÉRVALA tal como vino y márcala con flag termino_confuso, explicando la duda en Observaciones. No adivines la palabra correcta.
- Si un órgano o estructura aparece descrito de forma anatómicamente incongruente (por ejemplo "cuerpo uterino" mencionado dos veces, o con una lateralidad que ese órgano no tiene), conserva lo dictado y márcalo con flag incongruencia para que el humano lo revise.
- ÓRGANO REPETIDO CON MISMA LATERALIDAD: si el DICTADO nombra el mismo órgano con el mismo lado explícito DOS veces (por ejemplo "Riñón derecho ... 5.6 cm" y más adelante otra vez "Riñón derecho ... 5.8 cm"), con medidas o atributos distintos, y SIN una frase de corrección entre ellos ("no", "perdón", "me equivoqué", "mejor dicho"): NO descartes ninguno de los dos. Deja en el informe el ÚLTIMO que se dictó y marca ese órgano con flag incongruencia. En Observaciones anota ambas versiones, por ejemplo: "el órgano se dictó dos veces con el mismo lado: primero [valores A], luego [valores B]; revisar si uno corresponde al lado contrario". NO decidas tú cuál es el correcto ni cambies la lateralidad: solo conserva la información y avisa.
- Esto NO aplica cuando el mismo órgano se menciona en partes legítimamente distintas (por ejemplo "Páncreas rama derecha" y "Páncreas rama izquierda", que son dos zonas del mismo órgano y son correctas), ni cuando hay una corrección explícita (eso ya se resuelve tomando el último valor).

ESTILO DE REDACCIÓN
- Para las unidades de medida usa siempre la forma abreviada "cm" (y "mm" cuando corresponda), nunca la palabra "centímetros" ni "milímetros", aunque el DICTADO use la palabra completa.
- No transformes el valor numérico ni la magnitud de la unidad; solo abrevia la palabra de la unidad.
- Transcribe la ecogenicidad tal como viene en el DICTADO, sin completar componentes que no se dijeron. Si el DICTADO solo menciona la ecogenicidad cortical (por ejemplo "ecogenicidad cortical aumentada"), escribe únicamente la cortical y NO agregues "y medular". Solo menciona cortical y medular juntas si el DICTADO las nombra a ambas.

TRANSCRIPCIÓN CLÍNICA
- Transcribe solo contenido clínico del DICTADO.
- Ignora publicidad, marcas, frases comerciales, instrucciones al usuario, conversaciones ajenas al informe, descripciones de cámaras/equipos o frases de demostración.
- Respeta números y unidades tal como vienen en el DICTADO, incluyendo coma o punto decimal.
- No transformes cm a mm ni mm a cm.
- No cambies un valor sospechoso por el valor que parezca correcto.
- Distingue entre un número LEGIBLE y un número ILEGIBLE:
  - LEGIBLE: se entiende qué número es, aunque parezca raro o clínicamente improbable. Escríbelo tal cual viene. Si es muy improbable, márcalo con flag valor_sospechoso, pero el número SÍ va escrito.
  - ILEGIBLE: no se puede determinar qué número es (balbuceo, varios números pegados sin saber cuál corresponde, frase cortada). En ese caso NO escribas el texto roto: pon "XX" en lugar de la medida y márcalo con flag medida_ilegible.
- Si el DICTADO se corrige a sí mismo sobre un mismo dato (por ejemplo "0.79... no, eso estaba en 0.57", "perdón, mejor dicho...", "no, era..."), usa SIEMPRE el último valor o la última versión que entrega el ecografista, no la primera. La corrección reemplaza al dato corregido.
- El criterio para usar XX es "¿se entiende qué número es?", NO "¿el número es normal?". La rareza de un valor no justifica reemplazarlo por XX; solo la ilegibilidad lo justifica.
- Si una palabra o frase (no numérica) parece error de dictado, conserva el término original, márcalo con flag termino_confuso y explica la duda en Observaciones del Asistente.
- No muevas hallazgos, medidas ni descripciones entre órganos, zonas anatómicas o lateralidades.
- Si el DICTADO dice que un órgano tiene "mismas características" que otro (por ejemplo "riñón derecho mismas características que el izquierdo"), NUNCA escribas literalmente "mismas características" en el informe. Copia explícitamente todos los atributos del órgano de referencia y aplícales solo los cambios que el DICTADO indique para este órgano (por ejemplo su propio tamaño). El resultado debe quedar redactado completo, igual que el órgano de referencia, no abreviado.
- Si el DICTADO indica lateralidad, respétala estrictamente.
- Si el DICTADO dice “renal izquierda”, ese dato debe quedar solo en la sección renal izquierda.
- Si el DICTADO dice “renal derecha”, ese dato debe quedar solo en la sección renal derecha.
- Si el DICTADO dice “adrenal izquierda”, ese dato debe quedar solo en la sección adrenal izquierda.
- Si el DICTADO dice “adrenal derecha”, ese dato debe quedar solo en la sección adrenal derecha.
- Nunca uses un valor de la PLANTILLA BASE para reemplazar un valor diferente entregado por el DICTADO en el mismo órgano.
- Cuando el mismo órgano exista en la PLANTILLA BASE y en el DICTADO, el valor del DICTADO reemplaza al valor de la PLANTILLA BASE para ese órgano.
- Si hay dos valores para el mismo órgano y no queda claro cuál es correcto, conserva ambos si vienen del DICTADO, marca incongruencia y pide confirmación.

CONCLUSIÓN
- No agregues conclusión si el DICTADO no la trae, no la solicita explícitamente y la PLANTILLA BASE no tiene una sección de conclusión.
- Si el DICTADO trae conclusión explícita, inclúyela respetando solo los hallazgos mencionados.
- Si la PLANTILLA BASE ya trae una sección de conclusión, complétala solo con hallazgos presentes en el DICTADO.
- Nunca inventes diagnósticos, interpretaciones ni recomendaciones clínicas no dictadas.

FLAGS OBLIGATORIOS
- Usa flags solo cuando exista una duda real.
- IMPORTANTE: los flags se colorean mediante el CSS del sistema usando la clase "flag" y el atributo data-tipo. NO generes ningún bloque <style>, ni atributo style propio, ni color en línea para los flags. Devuelve el flag exactamente como <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup> y nada más. Generar un <style> hace inválida la respuesta.
- Marca con:
  <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup>
- N debe ser correlativo: 1, 2, 3... según orden de aparición.
- No dupliques flags sobre el mismo dato.
- Si hay número + unidad, el flag va pegado después de la unidad.
  Ejemplo correcto: 8,5 cm<sup class="flag" data-flag="1" data-tipo="valor_sospechoso">(1)</sup>
- Si hay número sin unidad, el flag va pegado después del número.
  Ejemplo correcto: 8,5<sup class="flag" data-flag="1" data-tipo="falta_unidad">(1)</sup>
- Si no hay número, el flag va pegado después de la palabra o frase dudosa.

TIPOS DE FLAGS
1. valor_sospechoso
- Úsalo cuando una medida sea extrema, imposible o muy improbable para perros o gatos de cualquier talla.
- Úsalo cuando el valor sea internamente incoherente con otra medida del mismo órgano.
- Ejemplos orientativos:
  - Grosor de pared < 0,05 cm o > 1,5 cm.
  - Pared vesical < 0,1 cm o > 1 cm.
  - Riñón < 2 cm o > 12 cm.
  - Próstata < 1 cm o > 6 cm.
  - Un urolito, masa o estructura interna más grande que el órgano donde se describe.
- Si la medida está dentro de rangos amplios razonables, no marques valor_sospechoso.

2. falta_unidad
- Úsalo solo cuando hay un número clínico sin unidad.
- Úsalo cuando hay unidad sin número.
- Úsalo cuando la unidad está cortada o ambigua, por ejemplo “m” cuando podría ser “mm” o “cm”.
- No lo uses si la medida ya viene con unidad clara, por ejemplo “3,79 cm” o “4,3 cm”.

3. termino_confuso
- Úsalo cuando una palabra o frase parece error de dictado, error de tipeo o no pertenece a un informe ecográfico veterinario.
- Úsalo cuando el texto clínico viene mezclado con publicidad, conversación o instrucciones.
- Conserva el término original y explica la duda en Observaciones del Asistente.

4. incongruencia
- Úsalo cuando el DICTADO contiene dos afirmaciones clínicas incompatibles entre sí.
- Úsalo cuando una misma zona u órgano queda descrita como normal y alterada a la vez.
- Úsalo cuando el DICTADO contradice claramente una afirmación clínica de la PLANTILLA BASE.
- No elimines silenciosamente ninguna de las dos frases contradictorias si ambas vienen del DICTADO.
- Conserva ambas afirmaciones contradictorias cuando sea necesario para que el humano pueda revisar.
- Marca con flag la primera zona donde aparece claramente la contradicción.

5. medida_ilegible
- Úsalo cuando en el DICTADO había una medida pero no se puede descifrar qué número era (balbuceo, números pegados, frase cortada).
- NUNCA elimines ni omitas en silencio una medida ilegible. Si la borras sin avisar, la respuesta es inválida.
- Obligatorio: escribe "XX" en el lugar exacto donde iría la medida y pega el flag inmediatamente después de "XX".
  Ejemplo correcto: masa redonda ovalada de tamaño XX cm<sup class="flag" data-flag="1" data-tipo="medida_ilegible">(1)</sup>
- Conserva el resto de la descripción del hallazgo (forma, ecogenicidad, etc.); solo la cifra ilegible se reemplaza por "XX".
- La observación debe indicar que en esa zona el DICTADO traía una medida que no se pudo descifrar y debe revisarse en el audio original.

OBSERVACIONES DEL ASISTENTE
- Incluye el bloque Observaciones del Asistente si existe al menos un flag en el informe.
- El bloque debe ir al final del fragmento HTML.
- Usa este formato exacto:
  <p><strong>Observaciones del Asistente:</strong><br>
  (N) TIPO → órgano o zona afectada; qué revisar o confirmar; propuesta breve de corrección si corresponde.<br>
  </p>
- Regla obligatoria de correspondencia:
  - Cada <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup> insertado en el informe debe tener exactamente una línea correspondiente en Observaciones del Asistente.
  - La línea debe comenzar con el mismo número: (N).
  - El TIPO de la observación debe ser el mismo data-tipo del flag.
  - No puede existir un flag sin observación.
  - No puede existir una observación sin flag.
- Antes de finalizar la respuesta, revisa esta correspondencia:
  - Si marcaste (1), debe existir una línea que empiece con (1).
  - Si marcaste (2), debe existir una línea que empiece con (2).
  - Si marcaste (3), debe existir una línea que empiece con (3).
  - Continúa igual para todos los flags existentes.
- Genera observación obligatoria para:
  - valor_sospechoso,
  - termino_confuso,
  - incongruencia.
- Para falta_unidad, genera observación solo si la falta de unidad puede cambiar la interpretación clínica.
- Si marcas una incongruencia, la observación debe indicar claramente cuáles afirmaciones se contradicen y qué debe confirmar el humano.
- Si marcas un valor_sospechoso, la observación debe ser breve y pedir confirmar valor/unidad.
- Si marcas un termino_confuso, la observación debe pedir confirmar el término o posible error de dictado.
- Si marcas una falta_unidad y agregas observación, la observación debe pedir confirmar la unidad.
- No uses observaciones para agregar diagnósticos nuevos.
- No uses observaciones para corregir el informe sin avisar.
- No uses observaciones para agregar información que no esté en el DICTADO.
- Si no hay flags en el informe, no incluyas el bloque.

REGLA FINAL DE SEGURIDAD
- Ante duda, conserva el dato original del DICTADO, marca flag y solicita confirmación en Observaciones del Asistente.
- No ocultes, no suavices y no elimines contradicciones clínicas relevantes.

VALIDACIÓN FINAL OBLIGATORIA
- Antes de entregar la respuesta final, revisa todos los flags insertados en el HTML.
- Por cada flag encontrado en el informe, debe existir una línea en Observaciones del Asistente con el mismo número.
- Si el informe contiene data-flag="1", Observaciones del Asistente debe incluir una línea que empiece con (1).
- Si el informe contiene data-flag="2", Observaciones del Asistente debe incluir una línea que empiece con (2).
- Si el informe contiene data-flag="3", Observaciones del Asistente debe incluir una línea que empiece con (3).
- Si falta una observación para algún flag, la respuesta es inválida: agrega la línea faltante antes de responder.
- Para incongruencia, la observación debe mencionar las dos afirmaciones incompatibles y pedir confirmar cuál es correcta.
- No finalices la respuesta si existe un flag sin observación correspondiente.
SYS;

    // ── CONTEXTO del caso ──
    $especie        = gpt_limpiar_acentos(trim((string)($input['especie'] ?? '')));
    $raza           = gpt_limpiar_acentos(trim((string)($input['raza'] ?? '')));
    $edad           = gpt_limpiar_acentos(trim((string)($input['edad'] ?? '')));
    $sexo           = gpt_limpiar_acentos(trim((string)($input['sexo'] ?? '')));
    $tipo_estudio   = gpt_limpiar_acentos(trim((string)($input['tipo_estudio'] ?? '')));
    $motivo         = gpt_limpiar_acentos(trim((string)($input['motivo'] ?? '')));
    $plantilla_base = gpt_normalizar_plantilla_para_prompt((string)($input['plantilla_base'] ?? ''));
    
    // ── PROMPT de usuario ──
    $prompt = "
REDACCION DE INFORME ECOGRAFICO VETERINARIO

Usa la PLANTILLA BASE como estructura y estilo general.
Usa el DICTADO como fuente de verdad clínica.
Devuelve solo el fragmento HTML final del informe.
No uses Markdown.
No uses ```.

=== CONTEXTO DEL CASO (no incluir en el informe) ===
Especie: {$especie}
Raza: {$raza}
Edad: {$edad}
Sexo: {$sexo}
Tipo de estudio: {$tipo_estudio}
Motivo: {$motivo}

=== PRIORIDAD DE INFORMACIÓN ===
1. El DICTADO manda sobre el contenido clínico.
2. La PLANTILLA BASE manda sobre estructura, orden y estilo general.
3. Si el DICTADO contradice la PLANTILLA BASE, usa el dato clínico del DICTADO y marca incongruencia si corresponde.
4. Si el DICTADO se contradice a sí mismo, conserva las frases necesarias y marca incongruencia.
5. No corrijas silenciosamente valores sospechosos; consérvalos, márcalos y solicita confirmación.

=== SALIDA ESPERADA ===
- Solo HTML del informe.
- Sin <html>, <head> ni <body>.
- Sin CSS nuevo, sin JS, sin iframes y sin bloques <style>.
- Conserva los atributos HTML existentes en la PLANTILLA BASE.
- Si agregas flags, usa exactamente:
  <sup class=\"flag\" data-flag=\"N\" data-tipo=\"TIPO\">(N)</sup>
- Si hay cualquier flag en el informe, agrega al final Observaciones del Asistente y crea una línea por cada flag usando el mismo número.

=== PLANTILLA BASE ===
<<<PLANTILLA_BASE
{$plantilla_base}
PLANTILLA_BASE

=== DICTADO ===
<<<DICTADO
{$dictado}
DICTADO
";

    $prompt = trim($prompt);

    return [
        'system'             => $system,
        'prompt'             => $prompt,
        'incluir_conclusion' => $incluir_conclusion,
    ];
}