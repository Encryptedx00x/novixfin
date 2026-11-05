<?php

declare(strict_types=1);

// Compilation warnings happen before bootstrap can disable display_errors.
// php -l can exit zero even when it emits such a warning.
require_once __DIR__ . '/helpers.php';

foreach (glob(dirname(__DIR__) . '/public/*.php') ?: [] as $file) {
    $command = escapeshellarg(PHP_BINARY)
        . ' -d display_errors=1 -d error_reporting=-1 -l '
        . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $status = 0;
    exec($command, $output, $status);
    $diagnostics = implode("\n", $output);
    t_ok(basename($file) . ' compiles without diagnostics',
        $status === 0 && !preg_match('/warning|deprecated|parse error|fatal error/i', $diagnostics));
}

t_finish();
