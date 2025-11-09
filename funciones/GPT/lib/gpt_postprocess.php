<?php
declare(strict_types=1);

/**
 * funciones para arreglar lo que viene de GPT:
 * - quitar conclusión si no iba
 * - renumerar flags
 * - recrear Observaciones del Asistente
 * - meter CSS
 */

function gpt_postprocess_html(string $html, bool $incluir_conclusion, array $ctxPaciente = []): string
{
    // 1) quitar conclusión si no venía en dictado
    if (!$incluir_conclusion) {
        $html = preg_replace('#<p>\s*<strong>\s*CONCLUSI(Ó|O)N:\s*</strong>\s*<br>\s*.*?</p>#is', '', $html);
    }

    // 2) renumerar flags
    $seq = 0;
    $html = preg_replace_callback(
        '#<sup\b[^>]*class=[\'"]flag[\'"][^>]*>(.*?)</sup>#is',
        function ($m) use (&$seq) {
            $seq++;
            $tipo = 'valor_sospechoso';
            if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $m[0], $mt)) {
                $tipo = $mt[1];
            }
            return "<sup class='flag' data-flag=\"{$seq}\" data-tipo=\"{$tipo}\">({$seq})</sup>";
        },
        $html
    );

    // 3) generar/mezclar Observaciones
    $lexicos = ['termino_confuso' => ['abusados']]; // puedes seguir agregando acá
    if (stripos($html, 'class="flag"') !== false || stripos($html, "class='flag'") !== false) {
        $html = gpt_build_observaciones_asistente($html, $ctxPaciente, $lexicos);
    }

    // 4) inyectar CSS
    if (preg_match('/class=["\']flag["\']/i', $html)) {
        $flagCss = <<<HTML
<style>
.flag{font-weight:600;}
.flag[data-tipo="valor_sospechoso"]{color:#E67E22;}
.flag[data-tipo="incongruencia"]{color:#C0392B;}
.flag[data-tipo="termino_confuso"]{color:#8E44AD;}
.flag[data-tipo="falta_unidad"]{color:#D35400;}
</style>
HTML;
        if (!preg_match('#<style[^>]*>\s*\.flag#is', $html)) {
            $html = $flagCss . $html;
        }
    }

    return $html;
}

function gpt_parse_observaciones_ia(string $html): array {
    $result = ['incongruencia' => [], 'termino_confuso' => [], 'falta_unidad' => [], 'valor_sospechoso' => []];

    if (preg_match('#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>\s*<br>\s*(.*?)\s*</p>#is', $html, $m)) {
        $block = $m[1];
        $lines = preg_split('#<br>\s*#i', $block);
        foreach ($lines as $line) {
            $plain = trim(strip_tags($line));
            if ($plain === '') continue;

            $tipo = null;
            foreach (array_keys($result) as $t) {
                if (preg_match('/\b' . preg_quote($t, '/') . '\b/i', $plain)) {
                    $tipo = strtolower($t);
                    break;
                }
            }
            if (!$tipo) continue;
            $plain = preg_replace('/^\(\d+\)\s*/', '', $plain);
            $result[$tipo][] = $plain;
        }
    }
    return $result;
}

function gpt_build_observaciones_asistente(string $html, array $ctx = [], array $lex = []): string
{
    $obsIAByType = gpt_parse_observaciones_ia($html);

    // quitar bloque previo
    $html = preg_replace('#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>.*?</p>#is', '', $html);

    // sin flags -> nada
    if (stripos($html, 'class="flag"') === false && stripos($html, "class='flag'") === false) {
        return $html;
    }

    $hasCtx = (
        trim((string)($ctx['especie'] ?? '')) !== '' ||
        trim((string)($ctx['raza'] ?? ''))    !== '' ||
        trim((string)($ctx['edad'] ?? ''))    !== ''
    );

    if (!preg_match_all('#<sup\b[^>]*class=[\'"]flag[\'"][^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    $len   = strlen($html);
    $items = [];

    foreach ($m[0] as $match) {
        $supTag = $match[0];
        $pos    = (int)$match[1];

        $n = null; $tipo = 'valor_sospechoso';
        if (preg_match('/data-flag=["\'](\d+)["\']/i', $supTag, $mn)) {
            $n = (int)$mn[1];
        }
        if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $supTag, $mt)) {
            $tipo = strtolower($mt[1]);
        }
        if (!$n) continue;

        $pStart = strrpos(substr($html, 0, $pos), '<p');
        if ($pStart === false) { $pStart = max(0, $pos - 300); }
        $pEnd = strpos($html, '</p>', $pos);
        if ($pEnd === false) { $pEnd = min($len, $pos + 300); }
        $para = substr($html, $pStart, $pEnd - $pStart);

        $organo = '';
        $beforeFlag = substr($para, 0, $pos - $pStart);
        if (preg_match_all('#<strong>([^<]+)</strong>#i', $beforeFlag, $ms, PREG_OFFSET_CAPTURE)) {
            $last = trim(end($ms[1])[0]);
            if (preg_match('/^(derecha|izquierda)$/i', $last) && count($ms[1]) >= 2) {
                $prev = trim($ms[1][count($ms[1]) - 2][0]);
                $organo = $prev . ' ' . $last;
            } else {
                $organo = $last;
            }
        }

        $winStart = max(0, ($pos - $pStart) - 120);
        $flagRel  = ($pos - $pStart) - $winStart;
        $win      = substr($para, $winStart, 240);

        $kw = '';
        if (preg_match('/(aumentad\w*|engros\w*|disminuid\w*|relaci(?:ón|&oacute;n)|abusados|↑|↓)/iu', $win, $mk)) {
            $kw = $mk[1];
        }

        $numu = '';
        if (preg_match_all('/\d+(?:[.,]\d+)?\s*(?:cm|mm|mt)/iu', $win, $mmu, PREG_OFFSET_CAPTURE)) {
            $best = null; $bestDist = 1e9;
            foreach ($mmu[0] as $cand) {
                $start = $cand[1];
                $dist  = abs($start - $flagRel);
                if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
            }
            if ($best !== null) { $numu = $best; }
        }
        if ($numu === '' && $tipo === 'falta_unidad') {
            if (preg_match_all('/(?>\d+(?:[.,]\d+)?)(?!\s*(?:cm|mm|mt))/iu', $win, $mnu, PREG_OFFSET_CAPTURE)) {
                $best = null; $bestDist = 1e9;
                foreach ($mnu[0] as $cand) {
                    $start = $cand[1];
                    $dist  = abs($start - $flagRel);
                    if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
                }
                if ($best !== null) { $numu = $best; }
            }
        }

        $items[] = ['n'=>$n,'tipo'=>$tipo,'organo'=>$organo,'kw'=>$kw,'numu'=>$numu];
    }

    $lines = [];
    $lexConf = array_map('mb_strtolower', $lex['termino_confuso'] ?? []);

    $iaQueue = [
        'incongruencia'   => $obsIAByType['incongruencia']   ?? [],
        'termino_confuso' => $obsIAByType['termino_confuso'] ?? [],
    ];
    $shiftIA = function(string $tipo) use (&$iaQueue) {
        if (empty($iaQueue[$tipo])) return null;
        return array_shift($iaQueue[$tipo]);
    };

    usort($items, fn($a,$b)=>$a['n']<=>$b['n']);

    foreach ($items as $it) {
        $n    = $it['n'];
        $tipo = $it['tipo'];
        $ctxo = $it['organo'] ? ($it['organo'] . ': ') : '';
        $kw   = $it['kw'];
        $numu = $it['numu'];

        switch ($tipo) {
            case 'falta_unidad':
                $ntxt = $numu ? " «{$numu}»" : "";
                $lines[] = "($n) falta_unidad → {$ctxo}medida sin unidad (cm/mm/mt){$ntxt}. Agrega la unidad correspondiente.";
                break;

            case 'valor_sospechoso':
                if ($numu) {
                    $lines[] = "($n) valor_sospechoso → {$ctxo}magnitud/indicador inusual ({$numu}). Revisar.";
                } else {
                    $lines[] = "($n) valor_sospechoso → {$ctxo}hallazgo marcado. Revisar en contexto clínico.";
                }
                break;

            case 'termino_confuso':
                if (in_array(mb_strtolower($kw), $lexConf, true)) {
                    $lines[] = "($n) termino_confuso → {$ctxo}término potencialmente confuso «{$kw}». Confirma significado.";
                } else {
                    $ia = $shiftIA('termino_confuso');
                    if ($ia) {
                        $lines[] = "($n) termino_confuso → {$ctxo}" . preg_replace('/^[a-z_]+\s*→\s*/i','',$ia);
                    } else {
                        $kwtxt = $kw ? "«{$kw}»" : "término ambiguo";
                        $lines[] = "($n) termino_confuso → {$ctxo}{$kwtxt}. Confirma significado exacto.";
                    }
                }
                break;

            case 'incongruencia':
                if (!$hasCtx) break;
                $ia = $shiftIA('incongruencia');
                if ($ia) {
                    $lines[] = "($n) incongruencia → {$ctxo}" . preg_replace('/^[a-z_]+\s*→\s*/i','',$ia);
                } else {
                    $kwtxt = $kw ? "«{$kw}»" : "hallazgos discordantes";
                    $add   = $numu ? " ({$numu})" : "";
                    $lines[] = "($n) incongruencia → {$ctxo}coexisten descriptores potencialmente discordantes {$kwtxt}{$add}. Revisar.";
                }
                break;

            default:
                $lines[] = "($n) {$tipo} → {$ctxo}revisar hallazgo" . ($numu ? " ({$numu})" : "") . ".";
                break;
        }
    }

    if (!empty($lines)) {
        $obs = "<p><strong>Observaciones del Asistente:</strong><br>\n" .
               implode("<br>\n", $lines) .
               "<br>\n</p>";
        $html .= $obs;
    }

    return $html;
}
