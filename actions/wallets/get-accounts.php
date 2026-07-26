<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireAuth();

use Novix\Database\Connection;

header('Content-Type: application/json');

$walletId = filter_input(INPUT_GET, 'wallet_id', FILTER_VALIDATE_INT);

if (!$walletId || $walletId <= 0) {
    echo json_encode(['account_ids' => []]);
    exit;
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

try {
    // Verifica se a carteira pertence ao usuário
    $stmt = $pdo->prepare('SELECT id FROM wallets WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $walletId, 'user_id' => $userId]);
    $wallet = $stmt->fetch();

    if (!$wallet) {
        echo json_encode(['account_ids' => []]);
        exit;
    }

    // Busca IDs das contas da carteira
    $stmt = $pdo->prepare('
        SELECT account_id 
        FROM wallet_accounts 
        WHERE wallet_id = :wallet_id
    ');
    $stmt->execute(['wallet_id' => $walletId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['account_ids' => array_map('intval', $accounts)]);
} catch (PDOException $e) {
    // Tabelas não existem, retorna vazio
    error_log('Erro ao buscar contas da carteira: ' . $e->getMessage());
    echo json_encode(['account_ids' => []]);
}

