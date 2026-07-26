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
$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$icon = trim((string) ($_POST['icon'] ?? '💼'));
$color = trim((string) ($_POST['color'] ?? ''));
$accountIds = $_POST['account_ids'] ?? [];

if (empty($name)) {
    flash('error', 'O nome da carteira é obrigatório.');
    redirect('/public/wallets.php');
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

// v3: tables `wallets` and `wallet_accounts` are created by the canonical
// migration `migrations/20240101000000_baseline_v3.sql` (Phase 2). The
// runtime CREATE-TABLE-IF-NOT-EXIST DDL that lived here is removed so the
// canonical schema (with FKs to users/accounts) is the only definition.
// Deploy requirement: run `php scripts/migrate.php up` once before using
// the app — see docs/migration-order.md.

if ($walletId > 0) {
    // Atualiza carteira existente
    $stmt = $pdo->prepare('
        UPDATE wallets 
        SET name = :name, description = :description, icon = :icon, color = :color
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([
        'id' => $walletId,
        'user_id' => $userId,
        'name' => $name,
        'description' => $description,
        'icon' => $icon,
        'color' => $color,
    ]);
    
    // Verifica se a carteira pertence ao usuário
    $stmt = $pdo->prepare('SELECT id FROM wallets WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $walletId, 'user_id' => $userId]);
    if (!$stmt->fetch()) {
        flash('error', 'Carteira não encontrada.');
        redirect('/public/wallets.php');
    }
} else {
    // Cria nova carteira
    $stmt = $pdo->prepare('
        INSERT INTO wallets (user_id, name, description, icon, color)
        VALUES (:user_id, :name, :description, :icon, :color)
    ');
    $stmt->execute([
        'user_id' => $userId,
        'name' => $name,
        'description' => $description,
        'icon' => $icon,
        'color' => $color,
    ]);
    $walletId = (int) $pdo->lastInsertId();
}

// Remove todas as contas atuais da carteira
$stmt = $pdo->prepare('DELETE FROM wallet_accounts WHERE wallet_id = :wallet_id');
$stmt->execute(['wallet_id' => $walletId]);

// Adiciona as contas selecionadas
if (!empty($accountIds) && is_array($accountIds)) {
    $stmt = $pdo->prepare('
        INSERT INTO wallet_accounts (wallet_id, account_id)
        SELECT :wallet_id, a.id
        FROM accounts a
        WHERE a.id = :account_id AND a.user_id = :user_id AND a.is_active = 1
    ');
    
    foreach ($accountIds as $accountId) {
        $accountId = filter_var($accountId, FILTER_VALIDATE_INT);
        if ($accountId > 0) {
            $stmt->execute([
                'wallet_id' => $walletId,
                'account_id' => $accountId,
                'user_id' => $userId,
            ]);
        }
    }
}

flash('success', $walletId > 0 ? 'Carteira atualizada com sucesso!' : 'Carteira criada com sucesso!');
redirect('/public/wallets.php');

