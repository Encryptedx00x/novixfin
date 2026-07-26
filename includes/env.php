<?php

declare(strict_types=1);

/**
 * Tiny dependency-free .env loader for NovixFin v3.
 *
 * Phase 1: replaces the hardcoded production credentials that lived in
 * `config/config.php`. No Composer / no vlucas/phpdotenv dependency (works
 * on shared hosting with no external packages).
 *
 * Usage:
 *   env('DB_HOST', '127.0.0.1')   // returns value from .env (or system env),
 *                                 // falling back to the default if unset.
 *
 * Loading is lazy + idempotent: the first call to env() (or load_env())
 * parses the project-root .env file once. Subsequent calls are no-ops.
 *
 * Each parsed value is set into putenv(), $_ENV, and $_SERVER so it is
 * visible both to `getenv()` (used by `includes/errors.php`) and to
 * modern superglobals.
 */

/**
 * Load the .env file at $path (defaults to project root).
 * Safe to call multiple times — parses only once.
 */
function load_env(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if ($path === null) {
        // Project root = two levels up from this file: includes/ -> project/.
        $path = dirname(__DIR__) . '/.env';
    }

    if (!is_file($path)) {
        return; // no .env present; rely on real environment variables only
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Skip lines without `=` (malformed) — do not explode into ambiguous state.
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        // Strip a trailing inline comment introduced by ` #` (with preceding
        // whitespace) only when the value is unquoted.
        if (
            $value !== ''
            && $value[0] !== '"'
            && $value[0] !== "'"
            && strpos($value, ' #') !== false
        ) {
            $value = trim(substr($value, 0, strpos($value, ' #')));
        }

        // Strip matching surrounding quotes.
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, $len - 2);
            }
        }

        // Export to all three mechanisms so both getenv() and $_ENV/$_SERVER callers see it.
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/**
 * Return an environment value with a default.
 *
 * Resolution order: getenv() (which includes values we putenv'd), then $_ENV,
 * then $_SERVER. Empty strings are treated as "unset" (return the default),
 * which matches user intuition when an `.env` key is left blank.
 */
function env(string $key, $default = null)
{
    load_env();

    $value = getenv($key);
    if ($value === false || $value === '') {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            $value = $_ENV[$key];
        } elseif (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            $value = $_SERVER[$key];
        } else {
            return $default;
        }
    }

    // Normalise common boolean literals to actual booleans.
    if (is_string($value)) {
        $lower = strtolower($value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null' || $lower === '(null)' ) return $default;
        if ($lower === 'empty' || $lower === "(empty)") return $default;
    }

    return $value;
}
