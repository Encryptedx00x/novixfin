<?php

declare(strict_types=1);

// Centralized error handling + .env loader.
// Loaded FIRST so `APP_ENV` is available to every later include (session,
// helpers, repositories) and so production never leaks errors to the response.
require_once __DIR__ . '/errors.php';

// Define timezone brasileiro (env-driven, defaults to America/Sao_Paulo).
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

$rootPath = dirname(__DIR__);

// Autoloader simples para classes Novix
spl_autoload_register(function ($class) use ($rootPath) {
    if (strpos($class, 'Novix\\') === 0) {
        $class = str_replace('Novix\\', '', $class);
        $file = $rootPath . '/src/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Inicia sessão
require_once __DIR__ . '/session.php';

// Carrega helpers
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/currency.php';
// v3 W2 fix: server-side login throttle (no external deps; migrated via
// /migrations/20240101000004_add_login_attempts_v3.sql). Loaded after
// helpers because bootstrap.php is the canonical auth-include root —
// public/login.php + public/register.php depend on these functions.
require_once __DIR__ . '/throttle.php';

// Retorna configuração
return require __DIR__ . '/../config/config.php';