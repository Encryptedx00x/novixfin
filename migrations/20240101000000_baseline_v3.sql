-- NovixFin v3 — baseline schema.
--
-- Applies idempotently on a fresh install (CREATE TABLE IF NOT EXISTS).
-- On an existing evolving prod DB, this migration is a no-op for tables
-- that already exist — the canonical column shapes are reconciled by
-- the PHP migration 20240101000001_reconcile_v3.php which runs afterwards
-- and only ALTERs missing/different columns.
--
-- Engine: MySQL 8.0+ / MariaDB 10.6+.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===========================
-- users
-- ===========================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) NULL,
  `level` VARCHAR(50) DEFAULT 'Iniciante',
  `points` INT DEFAULT 0,
  `badges` JSON NULL,
  `theme` VARCHAR(20) DEFAULT 'dark',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- categories  (per-user since v2, global ones carry user_id NULL)
-- ===========================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `icon` VARCHAR(50) NULL,
  `color` VARCHAR(20) NULL,
  `user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_type` (`type`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_name_type_user` (`name`, `type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- accounts
-- ===========================
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('bank','cash','investment','credit','other') DEFAULT 'bank',
  `icon` VARCHAR(50) DEFAULT '🏦',
  `color` VARCHAR(20) DEFAULT '#6366f1',
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `is_active` BOOLEAN DEFAULT TRUE,
  `show_in_dashboard` BOOLEAN DEFAULT TRUE,
  `credit_limit` DECIMAL(15,2) NULL DEFAULT NULL,
  `closing_day` TINYINT UNSIGNED NULL DEFAULT NULL,
  `due_day` TINYINT UNSIGNED NULL DEFAULT NULL,
  `interest_rate` DECIMAL(5,2) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- credit_card_bills
-- ===========================
CREATE TABLE IF NOT EXISTS `credit_card_bills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `billing_month` VARCHAR(7) NOT NULL,
  `closing_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
  `interest_amount` DECIMAL(15,2) DEFAULT 0.00,
  `fine_amount` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('open','paid','overdue','cancelled') DEFAULT 'open',
  `paid_at` DATETIME NULL DEFAULT NULL,
  `currency` ENUM('BRL','USD','EUR') DEFAULT 'BRL',
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
-- credit_card_transaction_settings
-- ===========================
CREATE TABLE IF NOT EXISTS `credit_card_transaction_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `register_bill_as_expense` BOOLEAN DEFAULT TRUE,
  `register_limit_as_income` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_account_user` (`account_id`, `user_id`),
  INDEX `idx_user_id` (`user_id`),
  CONSTRAINT `fk_ccts_account` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ccts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- transactions
-- ===========================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NULL DEFAULT NULL,
  `bill_id` INT UNSIGNED NULL DEFAULT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `currency` ENUM('BRL','USD','EUR') NOT NULL DEFAULT 'BRL',
  `description` VARCHAR(255) NULL,
  `transaction_date` DATE NOT NULL,
  `is_effective` BOOLEAN DEFAULT TRUE,
  `is_recurring` BOOLEAN DEFAULT FALSE,
  `recurrence_type` ENUM('none','daily','weekly','monthly','yearly','same_day') DEFAULT 'none',
  `recurrence_day` TINYINT UNSIGNED NULL DEFAULT NULL,
  `recurrence_end_date` DATE NULL DEFAULT NULL,
  `effective_date` DATE NULL DEFAULT NULL,
  `effective_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`bill_id`) REFERENCES `credit_card_bills`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_account_id` (`account_id`),
  INDEX `idx_bill_id` (`bill_id`),
  INDEX `idx_transaction_date` (`transaction_date`),
  INDEX `idx_type` (`type`),
  INDEX `idx_is_effective` (`is_effective`),
  INDEX `idx_is_recurring` (`is_recurring`),
  INDEX `idx_recurrence_type` (`recurrence_type`),
  INDEX `idx_effective_date` (`effective_date`),
  INDEX `idx_effective_at` (`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- goals  (title is canonical, legacy `name` is reconciled by PHP migration)
-- ===========================
CREATE TABLE IF NOT EXISTS `goals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NULL DEFAULT NULL,
  `goal_category_id` INT UNSIGNED NULL DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `type` ENUM('saving','spending_limit','income_target','balance_target','expense_limit') DEFAULT 'saving',
  `target_amount` DECIMAL(15, 2) NOT NULL,
  `current_amount` DECIMAL(15, 2) DEFAULT 0.00,
  `deadline` DATE NULL,
  `description` TEXT NULL,
  `completed` BOOLEAN DEFAULT FALSE,
  `auto_update` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_account_id` (`account_id`),
  INDEX `idx_goal_category_id` (`goal_category_id`),
  INDEX `idx_completed` (`completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- user_currency_settings
-- ===========================
CREATE TABLE IF NOT EXISTS `user_currency_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `primary_currency` ENUM('BRL','USD','EUR') DEFAULT 'BRL',
  `enabled_currencies` JSON DEFAULT '["BRL","USD","EUR"]',
  `currency_order` JSON DEFAULT '["BRL","USD","EUR"]',
  `convert_all_to_one` BOOLEAN DEFAULT FALSE,
  `conversion_target_currency` ENUM('BRL','USD','EUR') NULL,
  `conversion_rate_brl_to_usd` DECIMAL(10,4) DEFAULT 0.18,
  `conversion_rate_brl_to_eur` DECIMAL(10,4) DEFAULT 0.18,
  `conversion_rate_usd_to_brl` DECIMAL(10,4) DEFAULT 5.61,
  `conversion_rate_usd_to_eur` DECIMAL(10,4) DEFAULT 0.85,
  `conversion_rate_eur_to_brl` DECIMAL(10,4) DEFAULT 5.61,
  `conversion_rate_eur_to_usd` DECIMAL(10,4) DEFAULT 1.18,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- wallets + wallet_accounts
-- ===========================
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `color` VARCHAR(20) NULL,
  `icon` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `wallet_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_wallet_account` (`wallet_id`, `account_id`),
  FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
  INDEX `idx_wallet_id` (`wallet_id`),
  INDEX `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
