<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/themes.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

verifyCsrf($_POST['_token'] ?? '');

// Entrada slice: validate against the canonical 15-slug whitelist
// (includes/themes.php); anything else falls back to 'ametista'.
$theme = novix_valid_theme($_POST['theme'] ?? NOVIX_THEME_DEFAULT);

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

$stmt = $pdo->prepare('UPDATE users SET theme = :theme WHERE id = :id');
$stmt->execute([
    'theme' => $theme,
    'id' => $userId,
]);

$_SESSION['user']['theme'] = $theme;

flash('success', 'Tema atualizado com sucesso.');
redirect('/public/profile.php');

