<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): void
{
    if (empty($_SESSION['csrf_token'])) {
        http_response_code(419);
        throw new RuntimeException('Token CSRF não encontrado na sessão.');
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        throw new RuntimeException('Token CSRF inválido.');
    }
}

