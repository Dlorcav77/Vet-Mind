<?php

function vetmind_env_load_file($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $data = parse_ini_file($path, false, INI_SCANNER_RAW);

    return is_array($data) ? $data : [];
}

function vetmind_env_value($key, $default = '')
{
    static $env = null;

    if ($env === null) {
        $srcRoot = dirname(__DIR__);

        // /srv/docker/vetmind-dev/.env
        // /srv/docker/vetmind/.env
        $envPathOutsideSrc = dirname($srcRoot) . '/.env';

        // Fallback: /src/.env
        $envPathInsideSrc = $srcRoot . '/.env';

        $env = vetmind_env_load_file($envPathOutsideSrc);

        if (empty($env)) {
            $env = vetmind_env_load_file($envPathInsideSrc);
        }
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    $serverValue = getenv($key);

    if ($serverValue !== false && $serverValue !== '') {
        return $serverValue;
    }

    if (isset($env[$key])) {
        return trim((string)$env[$key], "\"'");
    }

    return $default;
}

function vetmind_asset_version()
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $appEnv = strtolower(trim((string)vetmind_env_value('APP_ENV', 'production')));

    if (in_array($appEnv, ['dev', 'development', 'local'], true)) {
        $version = (string)time();
        return $version;
    }

    $version = trim((string)vetmind_env_value('ASSET_VERSION', '1'));

    if ($version === '') {
        $version = '1';
    }

    return $version;
}