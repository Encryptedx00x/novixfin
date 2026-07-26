<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/wallets.php');
}

verifyCsrf($_POST['_token'] ?? '');

$walletId = filter_input(INPUT_POST, 'wallet_id', FILTER_VALIDATE_INT);

if (!$walletId || $walletId <= 0) {
    flash('error', 'Carteira inválida.');
    redirect('/public/wallets.php');
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

try {
    // Verifica se a carteira pertence ao usuário
    $stmt = $pdo->prepare('SELECT id FROM wallets WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $walletId, 'user_id' => $userId]);
    $wallet = $stmt->fetch();

    if (!$wallet) {
        flash('error', 'Carteira não encontrada.');
        redirect('/public/wallets.php');
    }

    // Exclui relacionamentos primeiro
    $stmt = $pdo->prepare('DELETE FROM wallet_accounts WHERE wallet_id = :wallet_id');
    $stmt->execute(['wallet_id' => $walletId]);

    // Exclui a carteira
    $stmt = $pdo->prepare('DELETE FROM wallets WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $walletId, 'user_id' => $userId]);

    flash('success', 'Carteira excluída com sucesso!');
} catch (PDOException $e) {
    error_log('Erro ao excluir carteira: ' . $e->getMessage());
    flash('error', 'Erro ao excluir carteira.');
}

redirect('/public/wallets.php');

