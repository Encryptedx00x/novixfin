<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('accounts.php');
}

try {
    $token = $_POST['_token'] ?? '';
    verifyCsrf($token);
    
    $pdo = Connection::get();
    $userId = currentUserId() ?? 0;
    $accountId = (int) ($_POST['account_id'] ?? 0);
    
    if ($accountId <= 0) {
        flash('error', 'Conta inválida.');
        redirect('accounts.php');
    }
    
    // Alterna o status is_active
    $stmt = $pdo->prepare('UPDATE accounts SET is_active = NOT is_active WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $accountId,
        'user_id' => $userId,
    ]);
    
    flash('success', 'Status da conta atualizado!');
    
} catch (Throwable $e) {
    error_log('Toggle account error: ' . $e->getMessage());
    flash('error', 'Erro ao atualizar status da conta.');
}

redirect('accounts.php');

