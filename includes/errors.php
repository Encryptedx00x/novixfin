<?php

declare(strict_types=1);

/**
 * Centralized error reporting.
 *
 * Production default: never leak errors to the response.
 * Local dev: flip with `APP_ENV=local` in .env (loaded via includes/env.php).
 */

require_once __DIR__ . '/env.php';

$appEnv = env('APP_ENV', 'production');

if ($appEnv === 'production' || $appEnv === 'prod' || $appEnv === null || $appEnv === '') {
    // Production: never leak errors to the response.
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('display_startup_errors', '0');
} else {
    // Local development: full visibility.
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('display_startup_errors', '1');
}