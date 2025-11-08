<?php
declare(strict_types=1);
date_default_timezone_set('America/Santiago');

/**
 * Logger JSON Lines (una línea JSON por evento).
 * - Crea archivos diarios: gpt_app-YYYY-MM-DD.log.jsonl y (opcional) gpt_bodies-*.log.jsonl
 * - Timestamps UTC y local (America/Santiago)
 * - File locking (flock) para evitar race conditions
 * - Limpieza simple de archivos antiguos (probabilística)
 */

if (!defined('LOG_DIR'))              define('LOG_DIR', __DIR__);           // funciones/logs
if (!defined('LOG_INCLUDE_BODIES'))   define('LOG_INCLUDE_BODIES', false);  // true para guardar prompt/output completos
if (!defined('LOG_SAMPLE_PERCENT'))   define('LOG_SAMPLE_PERCENT', 0);      // % de muestreo de cuerpos si LOG_INCLUDE_BODIES=false
if (!defined('LOG_RETENTION_DAYS'))   define('LOG_RETENTION_DAYS', 30);     // días a conservar

function logger_now_meta(): array {
    $utc   = new DateTime('now', new DateTimeZone('UTC'));
    $local = new DateTime('now', new DateTimeZone('America/Santiago'));
    return [
        'ts_utc'   => $utc->format('c'),
        'ts_local' => $local->format('Y-m-d H:i:sP'),
        'hostname' => gethostname() ?: php_uname('n'),
    ];
}

function logger_logfile(string $prefix = 'gpt_app'): string {
    // usa la zona horaria por defecto del proceso (ya seteaste America/Santiago)
    $date = (new DateTime('now'))->format('Y-m-d');
    return rtrim(LOG_DIR, '/')."/{$prefix}-{$date}.log.jsonl";
}

function ensure_log_dir(): void {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0775, true);
    }
}

function write_jsonl(string $path, array $line): void {
    $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '{"event":"encode_error","error":"'.json_last_error_msg().'"}';
    }
    $fh = @fopen($path, 'ab');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $json.PHP_EOL);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
    }
}

function logger_cleanup(string $dir, array $prefixes, int $days): void {
    if ($days <= 0) return;
    $cutoff = time() - $days * 86400;
    foreach (glob(rtrim($dir,'/').'/*.log.jsonl') as $file) {
        $base = basename($file);
        $match = false;
        foreach ($prefixes as $p) {
            if (strpos($base, $p.'-') === 0) { $match = true; break; }
        }
        if (!$match) continue;
        if (@filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}

/**
 * Log “normal”: request/response/error
 * $event: "request" | "response" | "error"
 * $level: "INFO" | "WARN" | "ERROR"
 */
function app_log(string $event, array $data = [], string $level = 'INFO'): void {
    ensure_log_dir();
    $line = array_merge(logger_now_meta(), [
        'event' => $event,
        'level' => $level,
    ], $data);
    write_jsonl(logger_logfile('gpt_app'), $line);

    // limpieza ocasional (1/500 requests)
    if (mt_rand(1,500) === 1) {
        logger_cleanup(LOG_DIR, ['gpt_app','gpt_bodies'], (int)LOG_RETENTION_DAYS);
    }
}

/**
 * Log de cuerpos (prompt/output) sólo si:
 * - LOG_INCLUDE_BODIES = true, o
 * - entra en muestreo (LOG_SAMPLE_PERCENT)
 * $kind: "prompt" | "output" | "raw_response" | etc.
 */
function app_log_body(string $kind, array $data = [], string $level = 'DEBUG'): void {
    $samplePass = (int)LOG_SAMPLE_PERCENT > 0 && (mt_rand(1,100) <= (int)LOG_SAMPLE_PERCENT);
    if (!LOG_INCLUDE_BODIES && !$samplePass) return;

    ensure_log_dir();
    $line = array_merge(logger_now_meta(), [
        'kind'  => $kind,
        'level' => $level,
    ], $data);
    write_jsonl(logger_logfile('gpt_bodies'), $line);
}

/** ID corto para correlación de eventos en un flujo */
function new_request_id(): string {
    return bin2hex(random_bytes(8)); // 16 chars hex
}

/** Hash corto del prompt (no guarda el prompt en claro) */
function sha256_short(string $text): string {
    return substr(hash('sha256', $text), 0, 16);
}

/**
 * Estimación de costo (USD) por tokens, por modelo.
 * Rellena/ajusta precios según tu realidad (valores de referencia).
 * Precios entendidos como USD por 1M tokens.
 */
function gpt_estimate_cost_usd(string $model, int $prompt_tokens, int $completion_tokens): float {
    $pricing = [
        // Ajusta si cambian precios. Si desconocidos, deja 0.
        'gpt-5-nano'   => ['in' => 0.05, 'out' => 0.40], // ref
        'gpt-5-mini'   => ['in' => 0.25, 'out' => 2.00], // ref
        'gpt-5'        => ['in' => 1.25, 'out' => 10.0], // ref
        'gpt-4o'       => ['in' => 2.50, 'out' => 10.0], // ref
        'gpt-4o-mini'  => ['in' => 0.15, 'out' => 0.60], // ref
    ];
    if (!isset($pricing[$model])) return 0.0;
    $in  = $pricing[$model]['in'];
    $out = $pricing[$model]['out'];
    $cost = ($prompt_tokens / 1_000_000) * $in + ($completion_tokens / 1_000_000) * $out;
    return round($cost, 6);
}
