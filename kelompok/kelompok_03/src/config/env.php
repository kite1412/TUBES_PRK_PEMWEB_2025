<?php
/**
 * Simple .env loader for the `src` folder.
 *
 * Usage:
 *   require_once __DIR__ . '/env.php';
 *   // env values are available via getenv('KEY'), $_ENV['KEY'], or $_SERVER['KEY']
 *
 */

function load_env_file(string $path = null): bool {
    if ($path === null) {
        // Prefer the `.env` placed in `src/` (one level up from this config folder)
        $candidate = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env';
        $candidate = realpath($candidate) ?: $candidate;
        if (is_readable($candidate)) {
            $path = $candidate;
        } else {
            // fallback to `.env` inside `src/config/` itself
            $path = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        }
    }

    if (!is_readable($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '' || $line[0] === '#') continue;

        // support `export KEY=VALUE`
        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // strip surrounding quotes (single or double)
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value)-1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // unescape common escapes in double-quoted values
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\r', "\r", $value);
        $value = str_replace('\\t', "\t", $value);

        // set environment
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    return true;
}

function env(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false) {
        if (array_key_exists($key, $_ENV)) return $_ENV[$key];
        if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];
        return $default;
    }
    return $v;
}

// Auto-load `.env` using the preference logic (prefer `src/.env`).
@load_env_file();
