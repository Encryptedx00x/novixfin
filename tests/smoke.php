<?php

declare(strict_types=1);

/**
 * NovixFin v3 — minimal cold smoke test. No Composer / PHPUnit.
 *
 * Run on the server (CLI only):
 *   php tests/smoke.php
 *
 * The test loads just enough of v3's autoload surface to verify:
 *   1. includes/env.php loads and the env() helper returns defaults for
 *      missing keys (the new env loader contract introduced in Phase 1).
 *   2. The two pure-function smoke assertions on the InsightService class
 *      file load and classify (a string-returning method exists).
 *
 * Anything that requires a DB connection / migration state belongs in
 * end-to-end tests run by a future Composer + PHPUnit cycle.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$failures = 0;

$assert = static function (string $label, bool $ok) use (&$failures): void {
    if ($ok) {
        echo "  ✓ {$label}\n";
    } else {
        echo "  ✗ {$label}\n";
        $failures++;
    }
};

echo "Phase 1 — env loader\n";
require_once $root . '/includes/env.php';

// Reset the static loaded-flag so this test sees a clean state.
// (load_env uses a static $loaded — we can't reach it directly, so we just
// re-run load_env with a path that doesn't exist; it should be a no-op and
// env() should fall back to real getenv + $_ENV + $_SERVER + default.)
load_env('/tmp/__nope_nonexistent_env_file__novixfin_smoke');

$assert('env(NON_EXISTENT_KEY) === default', env('NON_EXISTENT_KEY', 'fallback-default') === 'fallback-default');
$assert('env() helper returns null default when no default given', env('__totally_unset_key__') === null);

// Test that env() resolves a real environment variable.
putenv('NOVIXFIN_SMOKE_TEST=value-from-system-env');
$assert('env() exposes putenv() values', env('NOVIXFIN_SMOKE_TEST') === 'value-from-system-env');
putenv('NOVIXFIN_SMOKE_TEST'); // clean up

echo "Phase 5 — recurring service loads\n";
// We can't actually exercise RecurringService without a live DB, but we can
// at least confirm the class loads through the autoloader AND exposes the
// expected static contract.
spl_autoload_register(static function (string $class) use ($root): void {
    if (strpos($class, 'Novix\\') === 0) {
        $file = $root . '/src/' . str_replace(['Novix\\', '\\'], ['', '/'], $class) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
$reflectionClass = class_exists('\Novix\Services\InsightService') ? new ReflectionClass('\Novix\Services\InsightService') : null;
$assert('InsightService class loads through the autoloader', $reflectionClass !== null);
if ($reflectionClass) {
    $hasForUser = $reflectionClass->hasMethod('forUser');
    $assert('InsightService::forUser() method exists', $hasForUser);
    if ($hasForUser) {
        $method = $reflectionClass->getMethod('forUser');
        $returnType = $method->getReturnType();
        $returnTypeName = $returnType instanceof ReflectionNamedType ? $returnType->getName() : '';
        $assert('InsightService::forUser() return type is array', $returnTypeName === 'array');
    }
}

$reflectionClass = class_exists('\Novix\Services\RecurringService') ? new ReflectionClass('\Novix\Services\RecurringService') : null;
$assert('RecurringService class loads through the autoloader', $reflectionClass !== null);
if ($reflectionClass) {
    $assert('RecurringService::generateDue() method exists', $reflectionClass->hasMethod('generateDue'));
}

$reflectionClass = class_exists('\Novix\Services\GamificationService') ? new ReflectionClass('\Novix\Services\GamificationService') : null;
$assert('GamificationService class loads through the autoloader', $reflectionClass !== null);
if ($reflectionClass) {
    $assert('GamificationService::award() method exists', $reflectionClass->hasMethod('award'));
}

echo "Phase 3 — config file structure\n";
$configPath = $root . '/config/config.php';
$assert('config/config.php exists', is_file($configPath));
if (is_file($configPath)) {
    $contents = file_get_contents($configPath);
    $assert('config/config.php does NOT contain the leaked Production password', strpos($contents, 'Novix200505') === false);
    $assert('config/config.php reads env (DB_HOST)', strpos($contents, 'env(\'DB_HOST\'') !== false);
}

echo "Validation W2 — server-side throttle\n";
// Load throttle helpers explicitly; this smoke test intentionally avoids the
// full bootstrap path.
require_once $root . '/includes/throttle.php';

$assert('throttle_identifier() is callable', is_callable('throttle_identifier'));
$assert('throttle_identifier_for_register() is callable', is_callable('throttle_identifier_for_register'));
$assert('is_throttled() is callable', is_callable('is_throttled'));
$assert('record_failed_attempt() is callable', is_callable('record_failed_attempt'));
$assert('clear_failed_attempts() is callable', is_callable('clear_failed_attempts'));

$hasThrottleFunctions = is_callable('throttle_identifier')
    && is_callable('throttle_identifier_for_register')
    && is_callable('is_throttled')
    && is_callable('record_failed_attempt')
    && is_callable('clear_failed_attempts');

if ($hasThrottleFunctions) {
    // Identifier determinism + 64-hex-char shape.
    $idA = throttle_identifier('alice@example.com', '203.0.113.10');
    $idB = throttle_identifier('ALICE@example.com  ', '203.0.113.10');
    $assert('throttle_identifier is case/whitespace-insensitive (normalised email)', $idA === $idB);
    $assert('throttle_identifier is sha256 hex (64 chars)', strlen($idA) === 64 && ctype_xdigit($idA));
    $assert('throttle_identifier keys in IP (different IP => different id)',
        throttle_identifier('alice@example.com', '198.51.100.20') !== $idA);
    $regId = throttle_identifier_for_register('203.0.113.10');
    $assert('register identifier is distinct from login identifier', $regId !== $idA);
    $assert('throttle_identifier_for_register is sha256 hex (64 chars)',
        strlen($regId) === 64 && ctype_xdigit($regId));
}

echo "Done\n";
echo "Failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
