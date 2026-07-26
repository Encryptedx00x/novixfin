<?php

declare(strict_types=1);

/**
 * Auth/session configuration.
 *
 * Phase 1: session name + cookie options are env-driven with the previous
 * hardcoded values kept as defaults so existing sessions continue to work.
 */

require_once __DIR__ . '/../includes/env.php';

$sameSite = (string) env('SESSION_COOKIE_SAMESITE', 'Lax');
// Allow only the two SameSite values the original code used.
if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
    $sameSite = 'Lax';
}

return [
    'session_name' => (string) env('SESSION_NAME', 'novix_fin_session'),
    'session_cookie_lifetime' => (int) env('SESSION_COOKIE_LIFETIME', '604800'), // 7 dias
    'session_cookie_httponly' => true,
    'session_cookie_samesite' => $sameSite,
    'password_algo' => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
    'password_options' => defined('PASSWORD_ARGON2ID') ? [
        'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
        'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
    ] : ['cost' => 12],
];