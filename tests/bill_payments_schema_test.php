<?php

declare(strict_types=1);

/**
 * Task B3 — schema de `bill_payments`.
 *
 * Verifica, contra o banco de TESTE (isolado), que:
 *   1. a tabela existe depois do `migrate up`, com colunas e tipos esperados;
 *   2. as FKs e o ENUM de source_type estão como o modelo pede;
 *   3. a migração é IDEMPOTENTE (rodar o up de novo não quebra);
 *   4. o `down` REMOVE a tabela — e o `up` a recria.
 *
 * O ciclo down/up roda contra o banco de teste, nunca o da aplicação.
 */

require_once __DIR__ . '/helpers.php';

if (!test_db_available()) {
    t_skip('sem TEST_DB_DATABASE — teste de schema precisa de banco isolado');
}

$pdo = test_pdo();
$migration = require __DIR__ . '/../migrations/20240101000009_bill_payments_v3.php';

$noop = static function (string $msg): void {};

t_ok('a migração declara up', isset($migration['up']) && is_callable($migration['up']));
t_ok('a migração declara down (reversível)', isset($migration['down']) && is_callable($migration['down']));

/** A tabela existe agora? */
$exists = static function () use ($pdo): bool {
    return $pdo->query("SHOW TABLES LIKE 'bill_payments'")->rowCount() > 0;
};

// Garante o estado inicial: aplicada.
if (!$exists()) {
    $migration['up']($pdo, $noop);
}
t_ok('1. bill_payments existe após o up', $exists());

// -------------------------------------------------------- colunas e tipos
$columns = [];
foreach ($pdo->query('SHOW COLUMNS FROM bill_payments')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $columns[$col['Field']] = $col;
}

foreach (['id', 'bill_id', 'user_id', 'source_type', 'source_account_id', 'amount', 'created_at'] as $needed) {
    t_ok('coluna ' . $needed . ' existe', isset($columns[$needed]));
}

t_ok(
    'amount é DECIMAL(15,2)',
    isset($columns['amount']) && stripos($columns['amount']['Type'], 'decimal(15,2)') !== false
);
t_ok(
    "source_type é ENUM('account','external')",
    isset($columns['source_type'])
        && stripos($columns['source_type']['Type'], "enum('account','external')") !== false
);
t_ok(
    'source_account_id aceita NULL (pagamento externo não tem conta)',
    isset($columns['source_account_id']) && strtoupper((string) $columns['source_account_id']['Null']) === 'YES'
);
t_ok(
    'bill_id é NOT NULL (todo pagamento pertence a uma fatura)',
    isset($columns['bill_id']) && strtoupper((string) $columns['bill_id']['Null']) === 'NO'
);

// ------------------------------------------------------------------- FKs
$fks = $pdo->query(
    "SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
       FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'bill_payments'
        AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchAll(PDO::FETCH_ASSOC);

$refs = [];
foreach ($fks as $fk) {
    $refs[$fk['COLUMN_NAME']] = $fk['REFERENCED_TABLE_NAME'];
}
t_same('FK bill_id → credit_card_bills', $refs['bill_id'] ?? null, 'credit_card_bills');
t_same('FK user_id → users', $refs['user_id'] ?? null, 'users');
t_same('FK source_account_id → accounts', $refs['source_account_id'] ?? null, 'accounts');

// ----------------------------------------------------------- idempotência
$migration['up']($pdo, $noop);
t_ok('3. rodar o up de novo não quebra (idempotente)', $exists());

// ------------------------------------------------------------ down / up
$migration['down']($pdo, $noop);
t_ok('4. down REMOVE a tabela', !$exists());

$migration['up']($pdo, $noop);
t_ok('4. up recria a tabela depois do down', $exists());

// O runner precisa saber lidar com o formato ['up'=>..,'down'=>..].
$runner = file_get_contents(__DIR__ . '/../scripts/migrate.php');
t_ok(
    'o runner aceita migração no formato up/down',
    strpos((string) $runner, "isset(\$runner['up'])") !== false
);
t_ok(
    'o runner expõe o comando down',
    strpos((string) $runner, "case 'down':") !== false
);

t_finish();
