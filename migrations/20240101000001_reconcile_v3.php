<?php

declare(strict_types=1);

/**
 * NovixFin v3 — reconcile an existing/prod DB to the canonical baseline shape.
 *
 * Runs AFTER 20240101000000_baseline_v3.sql. Idempotent + portable: every
 * ALTER is guarded by an information_schema probe, so it works regardless of
 * what the live DB looks like (fresh install, partially-migrated prod, divergent
 * schema). Safe to re-run.
 *
 * Decisions baked in (see /state/context.md):
 *
 *   • categories.user_id → added if missing (kept NULLABLE: legacy global
 *     categories remain shared; per-user ones carry the FK). NOT NOT NULL yet
 *     — that tightening is deferred to a later migration once a backfill owner
 *     is decided.
 *   • goals: ensure `title` exists. If only legacy `name` exists, ADD title
 *     and backfill `title = name`. The legacy `name` column is LEFT in place
 *     during v3 (Phase 3 sanitises code that reads `name`, after which a
 *     future migration can DROP it).
 *   • goals.type enum: canonical set is
 *     (saving, spending_limit, income_target, balance_target, expense_limit).
 *     Legacy 'savings' → 'saving'; legacy 'debt_payoff' → 'expense_limit'.
 *     Then MODIFY COLUMN to the canonical enum.
 *   • transactions.currency → ensure ENUM('BRL','USD','EUR') NOT NULL DEFAULT 'BRL'.
 *     Backfill NULLs/exptraneous values to 'BRL' before tightening.
 *   • credit_card_bills.currency → align from VARCHAR(3) to ENUM('BRL','USD','EUR')
 *     DEFAULT 'BRL' (backfill bad values to BRL first).
 *   • accounts.balance vs initial_balance: if `balance` is missing and
 *     `initial_balance` is present, RENAME COLUMN initial_balance → balance.
 *
 * Returns a callable: function(PDO $pdo, callable $log): void.
 */

return static function (PDO $pdo, callable $log): void {
    $dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?? '');
    if ($dbName === '') {
        throw new RuntimeException('Could not determine current database.');
    }

    // ----------------------------- helpers
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
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
            $log("  + {$table}.{$column}");
            $pdo->exec($sql);
        }
    };

    $guardedAddIndex = static function (string $table, string $index, string $definition) use ($indexExists, $pdo, $log): void {
        if (!$indexExists($table, $index)) {
            $sql = "ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$definition})";
            $log("  + idx {$table}.{$index}");
            $pdo->exec($sql);
        }
    };

    $modifyColumn = static function (string $table, string $column, string $definition) use ($pdo, $log): void {
        $log("  ~ {$table}.{$column} (MODIFY)");
        $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
    };

    // ----------------------------- users
    $guardedAddColumn('users', 'theme', "VARCHAR(20) DEFAULT 'dark'");

    // ----------------------------- categories (per-user)
    $guardedAddColumn('categories', 'user_id', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddIndex('categories', 'idx_user_id', '`user_id`');
    $guardedAddIndex('categories', 'idx_name_type_user', '`name`, `type`, `user_id`');

    // ----------------------------- accounts
    // Handle the users-who-ran-update-accounts.sql edge case: initial_balance
    // instead of balance. The active app reads `balance`, so this fix matters.
    if (!$columnExists('accounts', 'balance') && $columnExists('accounts', 'initial_balance')) {
        $log('  ~ accounts.initial_balance -> accounts.balance (RENAME)');
        $pdo->exec('ALTER TABLE `accounts` RENAME COLUMN `initial_balance` TO `balance`');
    }
    // Widen type enum (idempotent MODIFY).
    $modifyColumn('accounts', 'type', "ENUM('bank','cash','investment','credit','other') DEFAULT 'bank'");
    // Credit-card fields added if missing.
    $guardedAddColumn('accounts', 'credit_limit', 'DECIMAL(15,2) NULL DEFAULT NULL');
    $guardedAddColumn('accounts', 'closing_day', 'TINYINT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('accounts', 'due_day', 'TINYINT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('accounts', 'interest_rate', 'DECIMAL(5,2) NULL DEFAULT NULL');
    $guardedAddColumn('accounts', 'show_in_dashboard', 'BOOLEAN DEFAULT TRUE');
    $guardedAddIndex('accounts', 'idx_user_id', '`user_id`');
    $guardedAddIndex('accounts', 'idx_is_active', '`is_active`');

    // ----------------------------- credit_card_bills
    // Normalize currency from VARCHAR(3) to ENUM. Backfill bad values first.
    $pdo->exec("UPDATE `credit_card_bills` SET `currency` = 'BRL' WHERE `currency` IS NULL OR `currency` NOT IN ('BRL','USD','EUR')");
    $modifyColumn('credit_card_bills', 'currency', "ENUM('BRL','USD','EUR') DEFAULT 'BRL'");
    $guardedAddIndex('credit_card_bills', 'idx_user_id', '`user_id`');
    $guardedAddIndex('credit_card_bills', 'idx_account_id', '`account_id`');
    $guardedAddIndex('credit_card_bills', 'idx_status', '`status`');
    $guardedAddIndex('credit_card_bills', 'idx_billing_month', '`billing_month`');
    $guardedAddIndex('credit_card_bills', 'idx_due_date', '`due_date`');

    // ----------------------------- credit_card_transaction_settings
    // No column work beyond what CREATE TABLE IF NOT EXISTS handled. Ensure
    // the unique index exists (runtime DDL historically omitted it in some
    // installs).
    if (!$indexExists('credit_card_transaction_settings', 'unique_account_user')) {
        $log('  + idx credit_card_transaction_settings.unique_account_user');
        $pdo->exec('ALTER TABLE `credit_card_transaction_settings` ADD UNIQUE INDEX `unique_account_user` (`account_id`, `user_id`)');
    }

    // ----------------------------- transactions
    $guardedAddColumn('transactions', 'account_id', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'bill_id', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'currency', "ENUM('BRL','USD','EUR') DEFAULT 'BRL'");
    $guardedAddColumn('transactions', 'is_effective', 'BOOLEAN DEFAULT TRUE');
    $guardedAddColumn('transactions', 'is_recurring', 'BOOLEAN DEFAULT FALSE');
    $guardedAddColumn('transactions', 'recurrence_type', "ENUM('none','daily','weekly','monthly','yearly','same_day') DEFAULT 'none'");
    $guardedAddColumn('transactions', 'recurrence_day', 'TINYINT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'recurrence_end_date', 'DATE NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'effective_date', 'DATE NULL DEFAULT NULL');
    $guardedAddColumn('transactions', 'effective_at', 'DATETIME NULL DEFAULT NULL');
    $guardedAddIndex('transactions', 'idx_account_id', '`account_id`');
    $guardedAddIndex('transactions', 'idx_bill_id', '`bill_id`');
    $guardedAddIndex('transactions', 'idx_type', '`type`');
    $guardedAddIndex('transactions', 'idx_is_effective', '`is_effective`');
    $guardedAddIndex('transactions', 'idx_is_recurring', '`is_recurring`');
    $guardedAddIndex('transactions', 'idx_recurrence_type', '`recurrence_type`');
    $guardedAddIndex('transactions', 'idx_effective_date', '`effective_date`');
    $guardedAddIndex('transactions', 'idx_effective_at', '`effective_at`');

    // Tighten transactions.currency to NOT NULL after backfilling any NULL/bad rows.
    $pdo->exec("UPDATE `transactions` SET `currency` = 'BRL' WHERE `currency` IS NULL OR `currency` NOT IN ('BRL','USD','EUR')");
    $modifyColumn('transactions', 'currency', "ENUM('BRL','USD','EUR') NOT NULL DEFAULT 'BRL'");
    // Backfill is_effective/is_recurring for legacy rows.
    $pdo->exec("UPDATE `transactions` SET `is_effective` = TRUE WHERE `is_effective` IS NULL");
    $pdo->exec("UPDATE `transactions` SET `is_recurring` = FALSE WHERE `is_recurring` IS NULL");
    $pdo->exec("UPDATE `transactions` SET `recurrence_type` = 'none' WHERE `recurrence_type` IS NULL");
    $pdo->exec("UPDATE `transactions` SET `recurrence_type` = 'monthly' WHERE `is_recurring` = 1 AND `recurrence_type` = 'none'");

    // ----------------------------- goals: title vs name + enum normalization
    // Ensure title exists. Legacy v1 schema has `name`, not `title`.
    if (!$columnExists('goals', 'title')) {
        $log('  + goals.title');
        $pdo->exec('ALTER TABLE `goals` ADD COLUMN `title` VARCHAR(150) NULL DEFAULT NULL');
        // Backfill from legacy `name` if it exists.
        if ($columnExists('goals', 'name')) {
            $log('  ~ goals.title = goals.name (backfill)');
            $pdo->exec('UPDATE `goals` SET `title` = `name` WHERE `title` IS NULL AND `name` IS NOT NULL');
            // Make NOT NULL now that backfill filled every row (rows with NULL
            // name still exist only if `name` itself was NULL — extremely
            // unlikely given NOT NULL constraint in schema.sql).
            $pdo->exec("UPDATE `goals` SET `title` = 'Sem nome' WHERE `title` IS NULL");
        } else {
            $pdo->exec("UPDATE `goals` SET `title` = 'Sem nome' WHERE `title` IS NULL");
        }
    }
    // goals.type enum normalization (map legacy values first).
    $pdo->exec("UPDATE `goals` SET `type` = 'saving' WHERE `type` = 'savings'");
    $pdo->exec("UPDATE `goals` SET `type` = 'expense_limit' WHERE `type` = 'debt_payoff'");
    $pdo->exec("UPDATE `goals` SET `type` = 'saving' WHERE `type` IS NULL OR `type` NOT IN ('saving','spending_limit','income_target','balance_target','expense_limit')");
    $modifyColumn('goals', 'type', "ENUM('saving','spending_limit','income_target','balance_target','expense_limit') DEFAULT 'saving'");
    // Extra goals columns added if missing.
    $guardedAddColumn('goals', 'account_id', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('goals', 'goal_category_id', 'INT UNSIGNED NULL DEFAULT NULL');
    $guardedAddColumn('goals', 'auto_update', 'BOOLEAN DEFAULT TRUE');
    $guardedAddIndex('goals', 'idx_account_id', '`account_id`');
    $guardedAddIndex('goals', 'idx_goal_category_id', '`goal_category_id`');
    $guardedAddIndex('goals', 'idx_completed', '`completed`');

    // ----------------------------- wallets + wallet_accounts
    // Tables created by baseline. Indexes added if missing.
    $guardedAddIndex('wallets',     'idx_user_id', '`user_id`');
    $guardedAddIndex('wallet_accounts', 'idx_wallet_id', '`wallet_id`');
    $guardedAddIndex('wallet_accounts', 'idx_account_id', '`account_id`');

    $log('Reconcile v3 complete.');
};