<?php

declare(strict_types=1);

/**
 * Task 0.1 — prova que o harness funciona: banco de teste ISOLADO, transação
 * revertida ao fim e factories mínimas. Se não houver banco de teste
 * configurado, marca SKIP (nunca cai no banco da aplicação).
 */

require_once __DIR__ . '/helpers.php';

if (!test_db_available()) {
    t_skip('sem TEST_DB_DATABASE — helpers de banco não exercitados');
}

$pdo = test_pdo();

// O banco de teste tem o schema das migrações do repo?
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['users', 'accounts', 'categories', 'transactions', 'credit_card_bills'] as $table) {
    t_ok("schema tem a tabela {$table}", in_array($table, $tables, true));
}

$createdUserId = null;

test_with_rollback(static function (PDO $pdo) use (&$createdUserId): void {
    $userId = make_user($pdo);
    $createdUserId = $userId;
    t_ok('make_user devolve id', $userId > 0);

    $accountId = make_account($pdo, $userId, 'Conta Teste', 'bank', 100.00);
    t_money('make_account grava o saldo inicial', account_balance($pdo, $accountId), 100.00);

    $cardId = make_card($pdo, $userId, 'Cartão Teste', 5, 15, 10000.00);
    $card = $pdo->query('SELECT type, closing_day, due_day FROM accounts WHERE id = ' . (int) $cardId)->fetch();
    t_same('make_card cria conta do tipo credit', $card['type'], 'credit');
    t_same('make_card grava closing_day', (int) $card['closing_day'], 5);

    $categoryId = make_category($pdo, $userId, 'expense');
    t_ok('make_category devolve id', $categoryId > 0);

    $billId = make_bill($pdo, $cardId, $userId, '2026-01', '2026-01-05', '2026-01-15');
    t_ok('make_bill devolve id', $billId > 0);

    $txId = make_transaction($pdo, $userId, $accountId, $categoryId, 'expense', 40.00, '2026-01-03', true, null);
    t_ok('make_transaction devolve id', $txId > 0);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM transactions WHERE user_id = ' . (int) $userId)->fetchColumn();
    t_same('transação visível DENTRO da transação de banco', $count, 1);
});

// O rollback apagou tudo? É o contrato que impede resíduo no banco de teste.
$leftover = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE id = ' . (int) $createdUserId)->fetchColumn();
t_same('rollback removeu o usuário criado (sem resíduo)', $leftover, 0);

t_finish();
