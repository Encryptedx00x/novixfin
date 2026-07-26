<?php

declare(strict_types=1);

/**
 * Example application configuration template.
 *
 * Copy this file to `config/config.php` and either:
 *   (a) fill the .env file at the project root (preferred), or
 *   (b) override the default values inside config.php directly.
 *
 * NEVER commit real credentials. The active `config/config.php` is gitignored.
 */

require_once __DIR__ . '/../includes/env.php';

return [
    'db' => [
        'host' => (string) env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'database' => (string) env('DB_DATABASE', 'novix_fin'),
        'username' => (string) env('DB_USERNAME', 'root'),
        'password' => (string) env('DB_PASSWORD', ''),
        'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    ],
    'app' => [
        'name' => (string) env('APP_NAME', 'Novix Fin'),
        'url' => (string) env('APP_URL', 'http://localhost'),
        'timezone' => (string) env('APP_TIMEZONE', 'America/Sao_Paulo'),
        'env' => (string) env('APP_ENV', 'production'),
    ],
];