<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Phase 1: all sensitive values come from environment (project-root .env or
 * real system env). NO production credentials live in this file anymore.
 *
 * Copy config/config.example.php over this file in a fresh deployment, then
 * fill values via .env (preferred) or edit this file with local overrides.
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