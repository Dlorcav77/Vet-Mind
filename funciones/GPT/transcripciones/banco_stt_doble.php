<?php
// /funciones/GPT/transcripciones/banco_stt_doble.php
// Banco de pruebas DOBLE MOTOR.
// Sube un audio, elige 2 motores, transcribe con ambos (HTTP a transcribir_audio.php),
// muestra ambas transcripciones, marca discrepancias y permite enviar a la IA (proceso_gpt.php).
// No toca producción: transcribir_audio.php, proceso_gpt.php ni el prompt quedan intactos.
declare(strict_types=1);
@set_time_limit(600);
mb_internal_encoding('UTF-8');

// ===== CONFIG =====
const ENDPOINT_STT = 'https://dev-app.vet-mind.cl/funciones/GPT/transcribir_audio.php';
const ENDPOINT_GPT = 'https://dev-app.vet-mind.cl/funciones/GPT/proceso_gpt.php';
const ENDPOINT_REVISOR = 'https://dev-app.vet-mind.cl/funciones/GPT/proceso_ia/proceso_revisor.php';
const TEST_TOKEN   = 'gondolengua'; // debe coincidir con STT_TEST_TOKEN
const AUDIO_DIR    = __DIR__ . '/banco_audios';

// Motores disponibles. Clave = valor que recibe transcribir_audio.php en $_POST['motor'].
$MOTORES = [
    'assembly'    => 'AssemblyAI viejo (/v2 clásico)',
    'deepgram'    => 'Deepgram Nova-3',
    'assembly_v3' => 'AssemblyAI Universal-3 Pro',
    'grok'        => 'Grok',
    'openai_4o'   => 'GPT-4o',
];

// ---- Listar audios .ogg ----
function listar_audios(): array {
    if (!is_dir(AUDIO_DIR)) return [];
    $files = glob(AUDIO_DIR . '/*.ogg') ?: [];
    sort($files);
    return array_map('basename', $files);
}

// ---- Llamar a un motor por HTTP (sube el .ogg) ----
function probar_motor(string $audioFile, string $motor): array {
    $ruta = AUDIO_DIR . '/' . basename($audioFile);
    if (!is_file($ruta)) {
        return ['ok' => false, 'texto' => '', 'err' => 'Audio no encontrado', 'ms' => 0];
    }
    $post = [
        'audio'      => new CURLFile($ruta, 'audio/ogg', basename($ruta)),
        'test_token' => TEST_TOKEN,
        'motor'      => $motor,
    ];
    $t0 = microtime(true);
    $ch = curl_init(ENDPOINT_STT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 180,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($err !== '') return ['ok' => false, 'texto' => '', 'err' => "cURL: $err", 'ms' => $ms];
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) return ['ok' => false, 'texto' => '', 'err' => "HTTP $http · no-JSON: " . substr((string)$resp, 0, 200), 'ms' => $ms];
    return [
        'ok'    => (($j['status'] ?? '') === 'success'),
        'texto' => (string)($j['texto'] ?? ''),
        'err'   => (string)($j['message'] ?? ''),
        'ms'    => $ms,
    ];
}

// ===== CAPA DE COMPARACIÓN POR CÓDIGO =====
// Une números partidos por espacio tras coma/punto: "0, 4" -> "0,4"
function cmp_pre_limpiar(string $t): string {
    return preg_replace('/(\d)\s*([.,])\s*(\d)/u', '$1$2$3', $t);
}
// Normaliza un token para comparar (minúsculas, sin tildes, sin guiones, coma->punto).
function cmp_norm(string $w): string {
    $w = mb_strtolower($w, 'UTF-8');
    $w = strtr($w, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $w = str_replace('-', '', $w);
    $w = preg_replace('/[^0-9a-z.,]/u', '', $w);
    $w = trim($w, '.,');
    if (preg_match('/\d/', $w)) $w = str_replace(',', '.', $w);
    return $w;
}
function cmp_tokens(string $t): array {
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t === '' ? [] : explode(' ', $t);
}
function cmp_es_numero_norm(string $w): bool {
    return (bool)preg_match('/^\d+(\.\d+)?$/', $w);
}
/**
 * Compara A y B por LCS y devuelve discrepancias: solo_A, solo_B, numero, cambio.
 */
function cmp_comparar(string $textoA, string $textoB): array {
    $textoA = cmp_pre_limpiar($textoA);
    $textoB = cmp_pre_limpiar($textoB);
    $A = cmp_tokens($textoA); $B = cmp_tokens($textoB);
    $na = count($A); $nb = count($B);
    $nA = array_map('cmp_norm', $A); $nB = array_map('cmp_norm', $B);

    $dp = array_fill(0, $na + 1, array_fill(0, $nb + 1, 0));
    for ($i = $na - 1; $i >= 0; $i--) {
        for ($j = $nb - 1; $j >= 0; $j--) {
            if ($nA[$i] !== '' && $nA[$i] === $nB[$j]) $dp[$i][$j] = $dp[$i+1][$j+1] + 1;
            else $dp[$i][$j] = max($dp[$i+1][$j], $dp[$i][$j+1]);
        }
    }
    $i = 0; $j = 0;
    $disc = []; $bufA = []; $bufB = []; $bufNA = []; $bufNB = [];
    $flush = function() use (&$bufA, &$bufB, &$bufNA, &$bufNB, &$disc) {
        if (empty($bufA) && empty($bufB)) return;
        $hayNum = false;
        foreach (array_merge($bufNA, $bufNB) as $w) if (cmp_es_numero_norm($w)) $hayNum = true;
        if (empty($bufB))      $tipo = 'solo_A';
        elseif (empty($bufA))  $tipo = 'solo_B';
        elseif ($hayNum)       $tipo = 'numero';
        else                   $tipo = 'cambio';
        $disc[] = ['tipo'=>$tipo, 'a'=>implode(' ', $bufA), 'b'=>implode(' ', $bufB)];
        $bufA = []; $bufB = []; $bufNA = []; $bufNB = [];
    };
    while ($i < $na && $j < $nb) {
        if ($nA[$i] !== '' && $nA[$i] === $nB[$j]) { $flush(); $i++; $j++; }
        elseif ($dp[$i+1][$j] >= $dp[$i][$j+1]) { $bufA[] = $A[$i]; $bufNA[] = $nA[$i]; $i++; }
        else { $bufB[] = $B[$j]; $bufNB[] = $nB[$j]; $j++; }
    }
    while ($i < $na) { $bufA[] = $A[$i]; $bufNA[] = $nA[$i]; $i++; }
    while ($j < $nb) { $bufB[] = $B[$j]; $bufNB[] = $nB[$j]; $j++; }
    $flush();
    return $disc;
}
function cmp_etiqueta(string $tipo): string {
    return [
        'solo_A' => 'Solo en A',
        'solo_B' => 'Solo en B',
        'numero' => 'Medida distinta',
        'cambio' => 'Palabra distinta',
    ][$tipo] ?? $tipo;
}

// ===== CAPA 3: ENVÍO A IA =====

// Carga la plantilla neutral desde casos.php (misma que usa banco.php). Si no está, cadena vacía.
function cargar_plantilla_neutral(): string {
    $ruta = __DIR__ . '/../banco/casos.php';
    if (!is_file($ruta)) {
        // fallback: intentar ruta alternativa
        $alt = dirname(__DIR__) . '/banco/casos.php';
        if (is_file($alt)) $ruta = $alt; else return '';
    }
    $data = @require($ruta);
    return is_array($data) ? (string)($data['plantilla'] ?? '') : '';
}

// Construye el bloque de discrepancias en texto plano para inyectar en el dictado.
function construir_bloque_discrepancias(array $disc): string {
    if (empty($disc)) return '';
    $lineas = [];
    foreach ($disc as $d) {
        $a = $d['a'] !== '' ? $d['a'] : '(nada)';
        $b = $d['b'] !== '' ? $d['b'] : '(nada)';
        $lineas[] = '- Motor A dice "' . $a . '" / Motor B dice "' . $b . '"';
    }
    return "\n\n=== NOTA: DIFERENCIAS ENTRE 2 TRANSCRIPCIONES DEL MISMO AUDIO (elige la correcta segun contexto clinico; no incluyas esta nota en el informe) ===\n"
         . implode("\n", $lineas);
}

// Bloque de correcciones ya resueltas por el validador, para inyectar en el dictado.
// Le dice a la IA el término correcto; ella lo aplica aunque el texto traiga el equivocado.
function construir_bloque_resueltas(array $resueltas): string {
    if (empty($resueltas)) return '';
    $lineas = [];
    foreach ($resueltas as $r) {
        $lineas[] = '- Usa "' . $r['elegido'] . '" (un motor transcribio mal como "' . $r['descartado'] . '")';
    }
    return "\n\n=== CORRECCIONES YA RESUELTAS (diferencias entre las 2 transcripciones del mismo audio, ya decididas porque un motor transcribio mal; usa SIEMPRE el termino correcto indicado; no incluyas esta nota en el informe) ===\n"
         . implode("\n", $lineas);
}

// Arma el campo 'texto' (dictado) según el modo elegido.
function armar_dictado(string $modo, array $resA, array $resB, string $bloqueDisc, string $cualC): string {
    $tA = $resA['texto'] ?? '';
    $tB = $resB['texto'] ?? '';
    switch ($modo) {
        case 'B': // ambas transcripciones completas
            $base = "=== TRANSCRIPCION MOTOR A ===\n{$tA}\n\n=== TRANSCRIPCION MOTOR B ===\n{$tB}";
            break;
        case 'C': // la que el usuario elija
            $base = ($cualC === 'B') ? $tB : $tA;
            break;
        case 'A': // solo principal (A)
        default:
            $base = $tA;
            break;
    }
    return $base . $bloqueDisc;
}

// Llama a proceso_gpt.php igual que banco.php (mismos campos del POST).
function llamar_proceso_gpt(string $dictado, string $plantilla, string $systemOverride = ''): array {
    $post = [
        'paciente'       => 'banco_doble',
        'especie'        => '',
        'raza'           => '',
        'edad'           => '',
        'sexo'           => '',
        'tipo_estudio'   => 'Eco Abdominal',
        'motivo'         => '',
        'plantilla_base' => $plantilla,
        'texto'          => $dictado,
        'plantilla_id'   => 0,
    ];
    if ($systemOverride !== '') {
        $post['system_override'] = $systemOverride;
        $post['test_token']      = TEST_TOKEN;
    }
    $ch = curl_init(ENDPOINT_GPT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 180,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    if ($err !== '') return ['ok'=>false, 'content'=>'', 'err'=>$err];
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) return ['ok'=>false, 'content'=>'', 'err'=>'JSON inválido del endpoint'];
    return [
        'ok'      => (($j['status'] ?? '') === 'success'),
        'content' => (string)($j['content'] ?? ''),
        'err'     => (string)($j['message'] ?? ''),
    ];
}

// Llama a la IA revisora: compara dictado enviado vs informe generado.
function llamar_revisor(string $dictado, string $informeHtml, string $plantilla = ''): array {
    $post = ['dictado'=>$dictado, 'informe'=>$informeHtml, 'plantilla'=>$plantilla];
    $ch = curl_init(ENDPOINT_REVISOR);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '') return ['ok'=>false,'items'=>[],'err'=>'cURL: '.$err,'usage'=>[],'raw'=>''];
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) {
        return ['ok'=>false,'items'=>[],
            'err'=>'Respuesta no-JSON del revisor (HTTP '.$http.'): '.substr((string)$resp, 0, 600),
            'usage'=>[],'raw'=>(string)$resp];
    }
    return [
        'ok'    => (($j['status'] ?? '')==='success'),
        'items' => is_array($j['items'] ?? null) ? $j['items'] : [],
        'err'   => (string)($j['message'] ?? ''),
        'usage' => is_array($j['usage'] ?? null) ? $j['usage'] : [],
        'raw'   => (string)($j['raw'] ?? ''),
    ];
}

// Render de un panel de informe (reutilizable para produccion y prueba).
function render_informe_html(string $titulo, array $informe): string {
    ob_start(); ?>
    <div class="panel" style="padding:0">
      <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0"><?= e($titulo) ?></h2>
      <?php if ($informe['ok']): ?>
        <div style="padding:16px">
          <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px;background:#fff"><?= $informe['content'] ?></div>
          <details style="margin-top:12px">
            <summary style="cursor:pointer;font-size:13px;color:#64748b">Ver dictado exacto que se envió</summary>
            <pre style="margin-top:8px;background:#f8fafc;padding:12px;border-radius:8px"><?= e($informe['dictado_enviado'] ?? '') ?></pre>
          </details>
        </div>
      <?php else: ?>
        <div style="padding:16px"><div class="err">Error IA: <?= e($informe['err'] ?: 'sin contenido') ?></div></div>
      <?php endif; ?>
    </div>
    <?php return (string)ob_get_clean();
}

// Render del panel del revisor (reutilizable).
function render_revisor_html($revision): string {
    if ($revision === null) return '';
    ob_start(); ?>
    <div class="panel" style="padding:0">
      <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0">
        Revisión IA (gpt-5-mini)
        <?php if (!empty($revision['usage'])): ?>
          <span style="font-weight:400;color:#64748b;font-size:12px">
            · <?= (int)($revision['usage']['ms'] ?? 0) ?> ms
            · $<?= e(number_format((float)($revision['usage']['cost_usd'] ?? 0), 6)) ?>
          </span>
        <?php endif; ?>
      </h2>
      <div style="padding:16px">
        <?php if (!$revision['ok']): ?>
          <div class="err">Error revisor: <?= e($revision['err'] ?: 'sin detalle') ?></div>
        <?php elseif (empty($revision['items'])): ?>
          <div class="nodisc">El revisor no encontró inconsistencias entre el dictado y el informe.</div>
        <?php else: ?>
          <div class="disc">
            <table>
              <tr><th style="width:80px">Sev.</th><th style="width:130px">Tipo</th><th>Zona</th><th>Dictado</th><th>Informe</th><th>Revisar</th></tr>
              <?php foreach ($revision['items'] as $it):
                  $sev = strtolower((string)($it['severidad'] ?? 'media'));
                  $col = $sev==='alta' ? '#fee2e2;color:#991b1b' : ($sev==='media' ? '#fef3c7;color:#92400e' : '#e2e8f0;color:#475569');
              ?>
                <tr>
                  <td><span class="tag" style="background:<?= $col ?>"><?= e($sev) ?></span></td>
                  <td><?= e((string)($it['tipo'] ?? '')) ?></td>
                  <td><?= e((string)($it['zona'] ?? '')) ?></td>
                  <td class="va"><?= e((string)($it['dictado'] ?? '')) ?></td>
                  <td class="vb"><?= e((string)($it['informe'] ?? '')) ?></td>
                  <td><?= e((string)($it['detalle'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php return (string)ob_get_clean();
}

// ===== VALIDADOR POR DICCIONARIO DE ÓRGANOS =====
// Lista: $SECCIONES de banco.php + órganos del word_boost de transcribir_audio.php.
// Se irá ampliando según necesidad.
$ORGANOS_LISTA = [
    'vejiga','riñon','riñones','bazo','higado','vesicula','estomago','pancreas',
    'linfonodulos','adrenal','adrenales','yeyuno','ileon','duodeno','colon',
    'prostata','ciego','peritoneo','ovario','utero','cuerno','cuerpo','testiculos',
];
function org_norm(string $w): string {
    $w = mb_strtolower($w, 'UTF-8');
    $w = strtr($w, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    return preg_replace('/[^a-z0-9]/', '', $w);
}
function org_es_organo(string $tok, array $lista): bool {
    return in_array(org_norm($tok), $lista, true);
}
function org_limpia_borde(string $tok): string {
    return trim($tok, " .,;:");
}
/**
 * Evalúa una discrepancia (a,b). Devuelve:
 *  ['accion'=>'resuelto','elegido'=>X,'descartado'=>Y]  -> uno es órgano, el otro no
 *  ['accion'=>'pasa']                                    -> ambos órganos, ninguno, o multi-palabra
 * Regla de seguridad: solo resuelve si UNO es órgano y el OTRO no. Nunca entre dos órganos.
 */
function org_validar(string $a, string $b, array $lista): array {
    $a1 = org_limpia_borde($a); $b1 = org_limpia_borde($b);
    // Solo palabras sueltas (un token por lado)
    if (strpos($a1, ' ') !== false || strpos($b1, ' ') !== false) return ['accion'=>'pasa'];
    if ($a1 === '' || $b1 === '') return ['accion'=>'pasa']; // un lado vacío (solo_A/solo_B): no toca
    $oa = org_es_organo($a1, $lista); $ob = org_es_organo($b1, $lista);
    if ($oa && !$ob) return ['accion'=>'resuelto','elegido'=>$a1,'descartado'=>$b1];
    if ($ob && !$oa) return ['accion'=>'resuelto','elegido'=>$b1,'descartado'=>$a1];
    return ['accion'=>'pasa']; // ambos o ninguno
}
/**
 * Separa las discrepancias en resueltas (por diccionario) y las que van a la IA.
 */
function org_procesar(array $disc, array $organos, array $conceptos = []): array {
    $resueltas = []; $aIA = [];
    foreach ($disc as $d) {
        $r = org_validar($d['a'], $d['b'], $organos);
        if ($r['accion'] === 'resuelto') {
            $resueltas[] = ['elegido'=>$r['elegido'], 'descartado'=>$r['descartado'], 'origen'=>'organo'];
            continue;
        }
        $c = concepto_validar($d['a'], $d['b'], $conceptos);
        if ($c['accion'] === 'resuelto') {
            $resueltas[] = ['elegido'=>$c['elegido'], 'descartado'=>$c['descartado'], 'origen'=>'concepto'];
            continue;
        }
        $aIA[] = $d;
    }
    return ['resueltas'=>$resueltas, 'a_ia'=>$aIA];
}

// ===== VALIDADOR POR DICCIONARIO DE CONCEPTOS CLÍNICOS =====
// Recupera errores de transcripción (misma palabra mal escrita), NO opuestos clínicos.
// REGLA: nunca incluir solo un miembro de un par opuesto (hipo/hiper, homogeneo/heterogeneo,
// aguzados/redondeados...). Ambos en la lista => se protegen (pasan a la IA). Normalizado:
// minúsculas, sin tildes, sin signos (igual que org_norm).
$CONCEPTOS_LISTA = [
    'ecogenicidad','anecoico','anecoica','anecoicas',
    'hipoecoico','hipoecoica','hipoecoicas','hiperecoico','hiperecoica','hiperecoicas',
    'parenquima','estratificacion','esplenico','felino','aguzados','engrosado','engrosada','engrosadas',
    'mucoso','corticomedular','vasculatura','homogeneo','lobulo','reactivo','distendida',
    'redondeados','conservado','pelvica','grosor',
];

function concepto_es(string $tok, array $lista): bool {
    return in_array(org_norm($tok), $lista, true);
}

// Resuelve solo si la palabra descartada es casi igual al término válido (error de
// transcripción), no un concepto distinto. Umbral = floor(largo/3), mínimo 1.
function concepto_similar(string $valido, string $otro): bool {
    $a = org_norm($valido); $b = org_norm($otro);
    if ($a === '' || $b === '') return false;
    $umbral = max(1, (int)floor(mb_strlen($a) / 3));
    return levenshtein($a, $b) <= $umbral;
}

// Igual que org_validar pero con guarda de similitud. Devuelve resuelto solo cuando UNO es
// concepto válido, el OTRO no, y son casi iguales. Si ambos son conceptos (opuestos),
// ninguno, o no se parecen => pasa a la IA.
function concepto_validar(string $a, string $b, array $conceptos): array {
    $a1 = org_limpia_borde($a); $b1 = org_limpia_borde($b);
    if (strpos($a1, ' ') !== false || strpos($b1, ' ') !== false) return ['accion'=>'pasa'];
    if ($a1 === '' || $b1 === '') return ['accion'=>'pasa'];
    $ca = concepto_es($a1, $conceptos); $cb = concepto_es($b1, $conceptos);
    if ($ca && !$cb && concepto_similar($a1, $b1)) return ['accion'=>'resuelto','elegido'=>$a1,'descartado'=>$b1];
    if ($cb && !$ca && concepto_similar($b1, $a1)) return ['accion'=>'resuelto','elegido'=>$b1,'descartado'=>$a1];
    return ['accion'=>'pasa'];
}


// ---- Ejecución ----
$audios   = listar_audios();
$audioSel = $_GET['audio'] ?? '';
$motorA   = $_GET['motor_a'] ?? 'deepgram';
$motorB   = $_GET['motor_b'] ?? 'assembly_v3';
$resA = null;
$resB = null;

$run = isset($_GET['run']) && $audioSel !== '' && $motorA !== '' && $motorB !== '';
if ($run && in_array($audioSel, $audios, true)) {
    if (isset($MOTORES[$motorA])) $resA = probar_motor($audioSel, $motorA);
    if (isset($MOTORES[$motorB])) $resB = probar_motor($audioSel, $motorB);
}

// Discrepancias (solo si ambas transcripciones salieron OK)
$discrepancias = null;
$discResueltas = [];
$discParaIA    = [];
if ($resA !== null && $resB !== null && $resA['ok'] && $resB['ok']) {
    $discrepancias = cmp_comparar($resA['texto'], $resB['texto']);
    $sep = org_procesar($discrepancias, $ORGANOS_LISTA, $CONCEPTOS_LISTA);
    $discResueltas = $sep['resueltas'];
    $discParaIA    = $sep['a_ia'];
}

// ===== Envío a IA (POST separado, reusa transcripciones via hidden) =====
$informeIA   = null;
$modoIA      = $_POST['modo_ia'] ?? '';
$cualC       = $_POST['cual_c'] ?? 'A';
if (isset($_POST['enviar_ia'])) {
    $txtA = (string)($_POST['txt_a'] ?? '');
    $txtB = (string)($_POST['txt_b'] ?? '');
    $discJson = (string)($_POST['disc_json'] ?? '[]');
    $discIA = json_decode($discJson, true);
    if (!is_array($discIA)) $discIA = [];

    // Reconstruir el contexto para que el bloque de resultados siga visible tras el POST.
    $resA = ['ok'=>true, 'texto'=>$txtA, 'err'=>'', 'ms'=>0];
    $resB = ['ok'=>true, 'texto'=>$txtB, 'err'=>'', 'ms'=>0];
    $discrepancias = $discIA;
    // Re-separar con el validador (las resueltas no van a la IA)
    $sepP = org_procesar($discIA, $ORGANOS_LISTA, $CONCEPTOS_LISTA);
    $discResueltas = $sepP['resueltas'];
    $discParaIA    = $sepP['a_ia'];
    $motorA = $_POST['motor_a'] ?? $motorA;
    $motorB = $_POST['motor_b'] ?? $motorB;
    $audioSel = $_POST['audio'] ?? $audioSel;
    $run = true;

    $bloqueRes  = construir_bloque_resueltas($discResueltas);   // correcciones ya decididas
    $bloqueDisc = construir_bloque_discrepancias($discParaIA);  // solo las NO resueltas
    $dictado    = armar_dictado($modoIA ?: 'A', ['texto'=>$txtA], ['texto'=>$txtB], $bloqueRes . $bloqueDisc, $cualC);
    $plant    = cargar_plantilla_neutral();
    $informeIA = llamar_proceso_gpt($dictado, $plant);
    $informeIA['dictado_enviado'] = $dictado;
    $revision = null;
    if ($informeIA['ok']) {
        $revision = llamar_revisor($dictado, $informeIA['content'], $plant);
    }

    // Comparacion con prompt de prueba (system_override). No toca produccion.
    $systemTest   = trim((string)($_POST['system_test'] ?? ''));
    $comparar     = isset($_POST['comparar']) && $systemTest !== '';
    $informeTest  = null;
    $revisionTest = null;
    if ($comparar) {
        $informeTest = llamar_proceso_gpt($dictado, $plant, $systemTest);
        $informeTest['dictado_enviado'] = $dictado;
        if ($informeTest['ok']) {
            $revisionTest = llamar_revisor($dictado, $informeTest['content'], $plant);
        }
    }
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Banco STT Doble · 2 motores</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f5f7;color:#1f2733}
  .top{background:#0f766e;color:#fff;padding:16px 22px}
  .top h1{margin:0;font-size:18px}
  .wrap{max-width:1200px;margin:0 auto;padding:18px}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:16px}
  .panel h2{margin:0 0 12px;font-size:14px;color:#334155}
  select{width:100%;max-width:480px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
  .dos{display:flex;gap:16px;flex-wrap:wrap}
  .dos > div{flex:1;min-width:240px}
  .btn{display:inline-block;background:#0f766e;color:#fff;border:none;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;cursor:pointer;margin-top:12px}
  .cols{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff}
  .col{padding:14px 16px;border-right:1px solid #eef2f6}
  .col:last-child{border-right:none}
  .col h3{margin:0 0 4px;font-size:13px;color:#0f766e}
  .col .meta{font-size:12px;color:#64748b;margin-bottom:8px}
  pre{white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit;font-size:13px;line-height:1.55}
  .err{background:#fef2f2;color:#991b1b;padding:8px 10px;border-radius:7px;font-size:13px}
  .empty{color:#64748b;font-size:14px}
  label.lbl{display:block;font-size:13px;color:#475569;margin-bottom:4px}
  .disc{margin-top:0}
  .disc table{width:100%;border-collapse:collapse;font-size:13px}
  .disc th{text-align:left;padding:8px 12px;background:#f8fafc;color:#475569;border-bottom:1px solid #e2e8f0;font-weight:600}
  .disc td{padding:8px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top}
  .tag{display:inline-block;font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;white-space:nowrap}
  .tag.solo_A{background:#fef3c7;color:#92400e}
  .tag.solo_B{background:#fef3c7;color:#92400e}
  .tag.numero{background:#fee2e2;color:#991b1b}
  .tag.cambio{background:#e0e7ff;color:#3730a3}
  .va{color:#0f766e;font-weight:600}
  .vb{color:#7c3aed;font-weight:600}
  .nodisc{padding:14px 16px;color:#065f46;background:#ecfdf5;font-size:13px;border-radius:8px}
</style></head><body>
<div class="top"><h1>Banco STT Doble · comparar 2 motores</h1></div>
<div class="wrap">

  <?php if (empty($audios)): ?>
    <div class="panel"><p class="empty">No hay audios en <code><?= e(AUDIO_DIR) ?></code>. Sube tus <code>.ogg</code> ahí.</p></div>
  <?php else: ?>
    <form class="panel" method="get">
      <h2>1 · Elige audio</h2>
      <select name="audio">
        <?php foreach ($audios as $a): ?>
          <option value="<?= e($a) ?>" <?= $a === $audioSel ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>

      <h2 style="margin-top:16px">2 · Elige 2 motores</h2>
      <div class="dos">
        <div>
          <label class="lbl">Motor A (principal)</label>
          <select name="motor_a">
            <?php foreach ($MOTORES as $k => $nombre): ?>
              <option value="<?= e($k) ?>" <?= $k === $motorA ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="lbl">Motor B (segundo)</label>
          <select name="motor_b">
            <?php foreach ($MOTORES as $k => $nombre): ?>
              <option value="<?= e($k) ?>" <?= $k === $motorB ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button class="btn" type="submit" name="run" value="1">▶ Transcribir con ambos</button>
    </form>
  <?php endif; ?>

  <?php if ($run && $resA !== null && $resB !== null): ?>
    <div class="panel" style="padding:0">
      <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0">Audio: <?= e($audioSel) ?></h2>
      <div class="cols">
        <div class="col">
          <h3><?= e($MOTORES[$motorA]) ?> · A</h3>
          <div class="meta"><?= (int)$resA['ms'] ?> ms</div>
          <?php if ($resA['ok']): ?>
            <pre><?= e($resA['texto']) ?></pre>
          <?php else: ?>
            <div class="err"><?= e($resA['err'] ?: 'Error') ?></div>
          <?php endif; ?>
        </div>
        <div class="col">
          <h3><?= e($MOTORES[$motorB]) ?> · B</h3>
          <div class="meta"><?= (int)$resB['ms'] ?> ms</div>
          <?php if ($resB['ok']): ?>
            <pre><?= e($resB['texto']) ?></pre>
          <?php else: ?>
            <div class="err"><?= e($resB['err'] ?: 'Error') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($discrepancias !== null): ?>
      <?php if (!empty($discResueltas)): ?>
        <div class="panel" style="padding:0">
          <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0">
            Resueltas automáticamente por diccionario (órganos + conceptos): <?= count($discResueltas) ?>
          </h2>
          <div class="disc">
            <table>
              <tr><th style="width:110px">Origen</th><th>Se usa</th><th>Se descarta</th></tr>
              <?php foreach ($discResueltas as $r): ?>
                <tr>
                  <td><span class="tag cambio"><?= e($r['origen'] ?? 'organo') ?></span></td>
                  <td class="va"><?= e($r['elegido']) ?></td>
                  <td style="color:#94a3b8;text-decoration:line-through"><?= e($r['descartado']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div class="panel" style="padding:0">
        <h2 style="padding:14px 16px;margin:0;border-bottom:1px solid #e2e8f0">
          Discrepancias para la IA: <?= count($discParaIA) ?>
        </h2>
        <?php if (empty($discParaIA)): ?>
          <div style="padding:16px"><div class="nodisc">No quedan discrepancias para la IA (todas resueltas o sin diferencias).</div></div>
        <?php else: ?>
          <div class="disc">
            <table>
              <tr>
                <th style="width:130px">Tipo</th>
                <th>Motor A (<?= e($MOTORES[$motorA]) ?>)</th>
                <th>Motor B (<?= e($MOTORES[$motorB]) ?>)</th>
              </tr>
              <?php foreach ($discParaIA as $d): ?>
                <tr>
                  <td><span class="tag <?= e($d['tipo']) ?>"><?= e(cmp_etiqueta($d['tipo'])) ?></span></td>
                  <td class="va"><?= $d['a'] !== '' ? e($d['a']) : '<span style="color:#94a3b8">(nada)</span>' ?></td>
                  <td class="vb"><?= $d['b'] !== '' ? e($d['b']) : '<span style="color:#94a3b8">(nada)</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php
        // Para el envío a IA: se manda la lista COMPLETA; el validador se recalcula en el POST.
        $discCompletaIA = $discrepancias ?? [];
        $txtA_val = $resA['texto'] ?? '';
        $txtB_val = $resB['texto'] ?? '';
      ?>
      <div class="panel">
        <h2>Enviar a la IA (proceso_gpt)</h2>
        <form method="post" style="margin:0">
          <input type="hidden" name="txt_a" value="<?= e($txtA_val) ?>">
          <input type="hidden" name="txt_b" value="<?= e($txtB_val) ?>">
          <input type="hidden" name="motor_a" value="<?= e($motorA) ?>">
          <input type="hidden" name="motor_b" value="<?= e($motorB) ?>">
          <input type="hidden" name="audio" value="<?= e($audioSel) ?>">
          <input type="hidden" name="disc_json" value="<?= e(json_encode($discCompletaIA, JSON_UNESCAPED_UNICODE)) ?>">

          <label class="lbl">Modo de dictado a enviar</label>
          <select name="modo_ia" id="modo_ia" onchange="document.getElementById('cualc_box').style.display = this.value==='C' ? 'block':'none'">
            <option value="A">A · Solo motor A (<?= e($MOTORES[$motorA]) ?>) + discrepancias</option>
            <option value="B">B · Ambas transcripciones completas + discrepancias</option>
            <option value="C">C · Elegir una transcripción + discrepancias</option>
          </select>

          <div id="cualc_box" style="display:none;margin-top:10px">
            <label class="lbl">¿Cuál transcripción uso en modo C?</label>
            <select name="cual_c">
              <option value="A">Motor A (<?= e($MOTORES[$motorA]) ?>)</option>
              <option value="B">Motor B (<?= e($MOTORES[$motorB]) ?>)</option>
            </select>
          </div>

          <label class="lbl" style="margin-top:14px">Prompt de prueba (system) — opcional</label>
          <textarea name="system_test" rows="6" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:12px;font-family:monospace" placeholder="Pega aquí el system de prueba. Si lo llenas y marcas comparar, se genera informe PRODUCCIÓN vs PRUEBA."><?= e((string)($_POST['system_test'] ?? '')) ?></textarea>
          <label style="display:block;margin-top:8px;font-size:13px;color:#475569">
            <input type="checkbox" name="comparar" value="1" <?= isset($_POST['comparar']) ? 'checked' : '' ?>> Comparar contra producción
          </label>
          <button class="btn" type="submit" name="enviar_ia" value="1">▶ Generar informe con la IA</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($informeIA !== null): ?>
      <?= render_informe_html('Informe · PRODUCCIÓN · modo '.($modoIA ?: 'A'), $informeIA) ?>
      <?= render_revisor_html($revision ?? null) ?>
    <?php endif; ?>

    <?php if (isset($informeTest) && $informeTest !== null): ?>
      <?= render_informe_html('Informe · PROMPT DE PRUEBA · modo '.($modoIA ?: 'A'), $informeTest) ?>
      <?= render_revisor_html($revisionTest ?? null) ?>
    <?php endif; ?>

  <?php elseif (isset($_GET['run'])): ?>
    <div class="panel"><p class="empty">Elige un audio y 2 motores.</p></div>
  <?php endif; ?>

</div>
</body></html>