<?php

declare(strict_types=1);

/**
 * Add installment metadata for recurring transactions.
 *
 * Parent transaction = installment 1/N.
 * Generated children = installments 2/N through N/N.
 *
 * This migration is additive and idempotent. It deliberately avoids
 * constraints so existing production rows remain untouched apart from the
 * deterministic child -> parent backfill from recurring_generated.
 */

return static function (PDO $pdo, callable $log): void {
    $dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?? '');
    if ($dbName === '') {
        throw new RuntimeException('Could not determine current database.');
    }

    $columnExists = static function (string $table, string $column) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = :db AND table_name = :t AND column_name = :c LIMIT 1'
        );
        $stmt->execute([':db' => $dbName, ':t' => $table, ':c' => $column]);
        return (bool) $stmt->fetchColumn();
    };

    $indexExists = static function (string $table, string $index) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = :db AND table_name = :t AND index_name = :i LIMIT 1'
        );
        $stmt->execute([':db' => $dbName, ':t' => $table, ':i' => $index]);
        return (bool) $stmt->fetchColumn();
    };

    $guardedAddColumn = static function (string $table, string $column, string $definition) use ($columnExists, $pdo, $log): void {
        if (!$columnExists($table, $column)) {
            $log("  + {$table}.{$column}");
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };

    $guardedAddIndex = static function (string $table, string $index, string $definition) use ($indexExists, $pdo, $log): void {
        if (!$indexExists($table, $index)) {
            $log("  + idx {$table}.{$index}");
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$definition})");
        }
    };

    $guardedAddColumn('transactions', 'parent_transaction_id', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'installment_index', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'recurrence_total_occurrences', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('recurring_generated', 'installment_index', 'INT UNSIGNED NULL DEFAULT NULL');

    $guardedAddIndex('transactions', 'idx_transactions_parent_transaction_id', '`parent_transaction_id`');
    $guardedAddIndex('transactions', 'idx_transactions_installment_parent', '`parent_transaction_id`, `installment_index`');
    $guardedAddIndex('transactions', 'idx_transactions_recurrence_total', '`recurrence_total_occurrences`');
    $guardedAddIndex('recurring_generated', 'idx_recurring_generated_installment', '`installment_index`');

    if ($columnExists('transactions', 'parent_transaction_id')) {
        $log('  ~ transactions.parent_transaction_id backfill from recurring_generated');
        $pdo->exec(
            'UPDATE `transactions` t
             INNER JOIN `recurring_generated` rg ON rg.`child_transaction_id` = t.`id`
             SET t.`parent_transaction_id` = rg.`parent_transaction_id`
             WHERE t.`parent_transaction_id` IS NULL'
        );
    }

    $log('Recurrence installments migration complete.');
};
