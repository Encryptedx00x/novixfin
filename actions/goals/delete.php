<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('goals.php');
}

verifyCsrf($_POST['_token'] ?? '');

$goalId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$goalId || $goalId <= 0) {
    flash('error', 'Meta inválida.');
    redirect('goals.php');
}

$pdo = Connection::get();

$stmt = $pdo->prepare('DELETE FROM goals WHERE id = :id AND user_id = :user_id');
$stmt->execute([
    'id' => $goalId,
    'user_id' => currentUserId(),
]);

flash('success', 'Meta excluída com sucesso.');
redirect('goals.php');

