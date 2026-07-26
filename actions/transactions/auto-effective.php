<?php

declare(strict_types=1);

/**
 * Script para efetivar automaticamente transações não efetivadas
 * quando chegar a data de efetivação.
 * 
 * Este script deve ser executado diariamente via cron job:
 * 0 0 * * * /usr/bin/php /caminho/para/actions/transactions/auto-effective.php
 *
 * Phase 0 hardening: when invoked through the web server (non-CLI SAPI)
 * the script refuses to run with HTTP 403, so it cannot be triggered by
 * anonymous users hitting /actions/transactions/auto-effective.php over HTTP.
 */

require_once __DIR__ . '/../../includes/errors.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden: cron-only script.' . PHP_EOL;
    exit;
}

require_once __DIR__ . '/../../includes/bootstrap.php';
// v3 validation fix: goal recalc helper (auto-effective flips is_effective
// from 0→1 which should trigger goal progress updates; we recalc per user at
// the end of the batch).
require_once __DIR__ . '/../../includes/goals.php';

use DateTimeImmutable;
use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;
use Novix\Repositories\CreditCardBillRepository;

$pdo = Connection::get();
$transactionRepository = new TransactionRepository($pdo);
$billRepository = new CreditCardBillRepository($pdo);

$today = new DateTimeImmutable();

// Busca transações não efetivadas com data de efetivação igual ou anterior a hoje
$stmt = $pdo->prepare('
    SELECT t.id, t.user_id, t.account_id, t.type, t.amount, t.is_recurring, t.bill_id
    FROM transactions t
    WHERE t.is_effective = 0
    AND t.effective_date IS NOT NULL
    AND t.effective_date <= :today
');

$stmt->execute(['today' => $today->format('Y-m-d')]);
$transactions = $stmt->fetchAll();

$count = 0;
$now = new DateTimeImmutable();
$touchedUsers = []; // v3 validation fix: track affected users for goal recalc.

foreach ($transactions as $transaction) {
    try {
        // Busca a data de efetivação da transação
        $stmt = $pdo->prepare('SELECT effective_date, transaction_date FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $transaction['id']]);
        $transData = $stmt->fetch();
        
        // Usa a data de efetivação se existir, senão usa a data atual
        $effectiveDate = $transData['effective_date'] ?? $transData['transaction_date'] ?? $today->format('Y-m-d');
        $effectiveDateTime = new DateTimeImmutable($effectiveDate . ' ' . $now->format('H:i:s'));
        
        // Efetiva a transação usando a data de efetivação como data/hora de efetivação
        $stmt = $pdo->prepare('
            UPDATE transactions 
            SET is_effective = 1,
                effective_at = :effective_at
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $transaction['id'],
            'effective_at' => $effectiveDateTime->format('Y-m-d H:i:s'),
        ]);
        
        // Atualiza o saldo da conta
        $multiplier = $transaction['type'] === 'income' ? 1 : -1;
        $stmt = $pdo->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :account_id');
        $stmt->execute([
            'amount' => (float) $transaction['amount'] * $multiplier,
            'account_id' => $transaction['account_id'],
        ]);
        
        // Se for cartão de crédito, atualiza o total da fatura
        if ($transaction['bill_id']) {
            $billRepository->updateBillTotal((int) $transaction['bill_id']);
        }
        
        $count++;
        $touchedUsers[(int) $transaction['user_id']] = true;
        
    } catch (Exception $e) {
        error_log('Erro ao efetivar transação ' . $transaction['id'] . ': ' . $e->getMessage());
    }
}

// v3 validation fix: recalc every touched user's auto_update goals. When a
// scheduled-but-pending transaction flips to effective via this cron, the
// balance has been updated but goal progress hasn't — recalc clears that.
foreach (array_keys($touchedUsers) as $touchedUserId) {
    try {
        recalculateAllGoals((int) $touchedUserId);
    } catch (Throwable $goalErr) {
        error_log('auto-effective: recalculateAllGoals failed for user ' . $touchedUserId . ': ' . $goalErr->getMessage());
    }
}

if (php_sapi_name() === 'cli') {
    echo "Transações efetivadas: {$count}\n";
}

