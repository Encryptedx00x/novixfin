-- Adiciona suporte a cartão de crédito ao NovixFin
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===========================
-- Adiciona tipo 'credit' nas contas
-- ===========================
ALTER TABLE `accounts` 
MODIFY COLUMN `type` ENUM('bank', 'cash', 'investment', 'credit', 'other') DEFAULT 'bank';

-- Adiciona campos específicos para cartão de crédito na tabela accounts
ALTER TABLE `accounts`
ADD COLUMN `credit_limit` DECIMAL(15, 2) NULL DEFAULT NULL AFTER `balance`,
ADD COLUMN `closing_day` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Dia do fechamento da fatura (1-31)' AFTER `credit_limit`,
ADD COLUMN `due_day` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Dia do vencimento da fatura (1-31)' AFTER `closing_day`,
ADD COLUMN `interest_rate` DECIMAL(5, 2) NULL DEFAULT NULL COMMENT 'Taxa de juros mensal (%)' AFTER `due_day`;

-- ===========================
-- Tabela: credit_card_bills (Faturas de cartão de crédito)
-- ===========================
CREATE TABLE IF NOT EXISTS `credit_card_bills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `billing_month` VARCHAR(7) NOT NULL COMMENT 'Formato: YYYY-MM',
  `closing_date` DATE NOT NULL COMMENT 'Data de fechamento da fatura',
  `due_date` DATE NOT NULL COMMENT 'Data de vencimento da fatura',
  `total_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Valor total da fatura (despesas)',
  `paid_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Valor já pago',
  `interest_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Juros calculados',
  `fine_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Multa por atraso',
  `status` ENUM('open', 'paid', 'overdue', 'cancelled') DEFAULT 'open',
  `paid_at` DATETIME NULL DEFAULT NULL COMMENT 'Data de pagamento',
  `currency` VARCHAR(3) DEFAULT 'BRL',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_bill` (`account_id`, `billing_month`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_account_id` (`account_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_billing_month` (`billing_month`),
  INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- Adiciona coluna bill_id em transactions para vincular transações às faturas
-- ===========================
ALTER TABLE `transactions`
ADD COLUMN `bill_id` INT UNSIGNED NULL DEFAULT NULL AFTER `account_id`,
ADD FOREIGN KEY (`bill_id`) REFERENCES `credit_card_bills`(`id`) ON DELETE SET NULL,
ADD INDEX `idx_bill_id` (`bill_id`);

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================
-- Notas:
-- - Cartões de crédito devem ter tipo 'credit'
-- - Cada fatura representa um mês de fechamento (billing_month)
-- - Transações de despesa podem ser vinculadas a uma fatura específica
-- - Juros são calculados quando a fatura está vencida
-- ===========================

