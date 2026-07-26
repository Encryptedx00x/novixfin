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
    $deleteTransactions = isset($_POST['delete_transactions']) && $_POST['delete_transactions'] == '1';
    
    if ($accountId <= 0) {
        flash('error', 'Conta inválida.');
        redirect('accounts.php');
    }
    
    // Verifica se a conta pertence ao usuário
    $stmt = $pdo->prepare('SELECT id FROM accounts WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $accountId, 'user_id' => $userId]);
    if (!$stmt->fetch()) {
        flash('error', 'Conta não encontrada ou não pertence a você.');
        redirect('accounts.php');
    }
    
    if ($deleteTransactions) {
        // Exclui transações da conta
        $stmt = $pdo->prepare('DELETE FROM transactions WHERE account_id = :account_id AND user_id = :user_id');
        $stmt->execute([
            'account_id' => $accountId,
            'user_id' => $userId,
        ]);
    } else {
        // Remove vínculo das transações (define account_id como NULL)
        $stmt = $pdo->prepare('UPDATE transactions SET account_id = NULL WHERE account_id = :account_id AND user_id = :user_id');
        $stmt->execute([
            'account_id' => $accountId,
            'user_id' => $userId,
        ]);
    }
    
    // Exclui a conta
    $stmt = $pdo->prepare('DELETE FROM accounts WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $accountId,
        'user_id' => $userId,
    ]);
    
    flash('success', 'Conta excluída com sucesso!');
    
} catch (Throwable $e) {
    error_log('Delete account error: ' . $e->getMessage());
    flash('error', 'Erro ao excluir conta.');
}

redirect('accounts.php');

