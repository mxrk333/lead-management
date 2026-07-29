<?php

declare(strict_types=1);

/**
 * Minimal .env loader. This project has no Composer/vendor autoload, so
 * phpdotenv isn't available — this fills getenv()/$_ENV from .env at the
 * project root without adding a dependency. Real environment variables
 * (e.g. ones set by the host) always win: a key already present via
 * getenv() is left untouched.
 */
if (!function_exists('load_env')) {
    function load_env(?string $path = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = $path ?? dirname(__DIR__) . '/.env';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (strlen($value) >= 2) {
                $isQuoted = ($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'"));
                if ($isQuoted) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

load_env();
