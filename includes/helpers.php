<?php

declare(strict_types=1);

function redirect(string $path): void
{
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header('Location: ' . $path);
        exit;
    }
    
    // Remove prefixo 'public/' se existir
    $path = preg_replace('#^public/#', '', $path);
    
    // Lista de arquivos que estão em public/ (v3 — inclui wallets.php e credit-cards.php)
    $publicFiles = ['dashboard.php', 'login.php', 'register.php', 'logout.php', 'profile.php', 'goals.php', 'history.php', 'index.php', 'accounts.php', 'categories.php', 'currency-settings.php', 'wallets.php', 'credit-cards.php'];
    
    // Se for um arquivo público e não tiver o prefixo, adiciona
    $filename = basename($path);
    if (in_array($filename, $publicFiles, true) && strpos($path, 'public/') === false) {
        $path = 'public/' . ltrim($path, '/');
    }
    
    header('Location: /' . ltrim($path, '/'));
    exit;
}

function isAuthenticated(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function currentUserId(): ?int
{
    return $_SESSION['user']['id'] ?? null;
}

function requireAuth(): void
{
    if (!isAuthenticated()) {
        flash('error', 'Faça login para continuar.');
        redirect('login.php');
    }
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message === null) {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }
        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    
    $_SESSION['flash'][$key] = $message;
    return null;
}

