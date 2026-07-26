<?php

declare(strict_types=1);

$authConfig = require __DIR__ . '/../config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($authConfig['session_name']);

    // Detecta HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                 (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => $authConfig['session_cookie_lifetime'],
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => $authConfig['session_cookie_httponly'],
        'samesite' => $authConfig['session_cookie_samesite'],
    ]);

    session_start();
}

