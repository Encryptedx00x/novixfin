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
    
    // Alterna a exibição na dashboard
    $stmt = $pdo->prepare('UPDATE accounts SET show_in_dashboard = NOT show_in_dashboard WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $accountId,
        'user_id' => $userId,
    ]);
    
    if ($stmt->rowCount() > 0) {
        // Verifica o novo estado
        $checkStmt = $pdo->prepare('SELECT show_in_dashboard FROM accounts WHERE id = :id AND user_id = :user_id');
        $checkStmt->execute(['id' => $accountId, 'user_id' => $userId]);
        $account = $checkStmt->fetch();
        $isShowing = $account['show_in_dashboard'] ?? false;
        flash('success', $isShowing ? 'Conta fixada na dashboard!' : 'Conta removida da dashboard.');
    } else {
        flash('error', 'Erro ao atualizar exibição.');
    }
    
} catch (Throwable $e) {
    error_log('Toggle dashboard error: ' . $e->getMessage());
    flash('error', 'Erro ao atualizar exibição.');
}

redirect('accounts.php');

