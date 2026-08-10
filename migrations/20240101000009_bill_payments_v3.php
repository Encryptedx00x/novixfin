<?php

declare(strict_types=1);

/**
 * NovixFin — Task B3: `bill_payments`, o registro de ORIGEM do pagamento de fatura.
 *
 * POR QUE EXISTE: hoje o pagamento de uma fatura não guarda de onde o dinheiro
 * saiu. `revert-payment.php` precisa redescobrir as transações auxiliares por
 * PREFIXO DE DESCRIÇÃO + data — o que quebra se o cartão for renomeado entre o
 * pagamento e a reversão (ver reports/auditoria_cartoes_faturas.md). Com esta
 * tabela, cada parcela do pagamento vira uma linha com vínculo explícito, e a
 * reversão passa a ser por id.
 *
 * MODELO:
 *   - source_type 'account'  → saiu de uma conta do usuário (debita `source_account_id`)
 *   - source_type 'external' → pago por fora do app (não debita conta nenhuma)
 *   Um pagamento pode ter VÁRIAS linhas (multi-origem): 400 da conta X + 600 da Y.
 *
 * IDEMPOTENTE: CREATE TABLE IF NOT EXISTS e checagem antes de cada índice/FK.
 * REVERSÍVEL: `down` remove a tabela. Como ela é NOVA e nenhuma outra tabela
 * depende dela, o down não destrói dado pré-existente — só o histórico de
 * origem gravado depois desta migração.
 *
 * MariaDB 11.8: sem sintaxe MySQL-only.
 */

return [
    'up' => function (PDO $pdo, callable $log): void {
        $log('  ~ CREATE TABLE IF NOT EXISTS bill_payments');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS `bill_payments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `bill_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `source_type` ENUM("account","external") NOT NULL DEFAULT "account",
                `source_account_id` INT UNSIGNED NULL DEFAULT NULL,
                `transaction_id` INT UNSIGNED NULL DEFAULT NULL,
                `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `paid_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_bill_payments_bill` (`bill_id`),
                INDEX `idx_bill_payments_user` (`user_id`),
                INDEX `idx_bill_payments_source_account` (`source_account_id`),
                INDEX `idx_bill_payments_transaction` (`transaction_id`),
                CONSTRAINT `fk_bill_payments_bill` FOREIGN KEY (`bill_id`)
                    REFERENCES `credit_card_bills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_bill_payments_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_bill_payments_account` FOREIGN KEY (`source_account_id`)
                    REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_bill_payments_transaction` FOREIGN KEY (`transaction_id`)
                    REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $log('  ~ bill_payments pronta');
    },

    'down' => function (PDO $pdo, callable $log): void {
        // Só a tabela nova sai. Nada mais no schema depende dela, então não há
        // efeito colateral em transactions/credit_card_bills/accounts.
        $log('  ~ DROP TABLE IF EXISTS bill_payments');
        $pdo->exec('DROP TABLE IF EXISTS `bill_payments`');
    },
];
