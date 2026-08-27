-- Adiciona sistema de contas/bancos ao NovixFin
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===========================
-- Tabela: accounts (Contas/Bancos)
-- ===========================
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('bank', 'cash', 'investment', 'other') DEFAULT 'bank',
  `icon` VARCHAR(10) DEFAULT '🏦',
  `color` VARCHAR(20) DEFAULT '#6366f1',
  `balance` DECIMAL(15, 2) DEFAULT 0.00,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================
-- Adiciona coluna account_id em transactions
-- ===========================
ALTER TABLE `transactions` 
ADD COLUMN `account_id` INT UNSIGNED NULL AFTER `user_id`,
ADD FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
ADD INDEX `idx_account_id` (`account_id`);

-- ===========================
-- Insere contas padrão para usuários existentes
-- ===========================
INSERT INTO `accounts` (`user_id`, `name`, `type`, `icon`, `color`, `balance`)
SELECT 
    id as user_id,
    'Nubank' as name,
    'bank' as type,
    '💜' as icon,
    '#8A05BE' as color,
    0.00 as balance
FROM `users`;

INSERT INTO `accounts` (`user_id`, `name`, `type`, `icon`, `color`, `balance`)
SELECT 
    id as user_id,
    'XP Investimentos' as name,
    'investment' as type,
    '📈' as icon,
    '#000000' as color,
    0.00 as balance
FROM `users`;

INSERT INTO `accounts` (`user_id`, `name`, `type`, `icon`, `color`, `balance`)
SELECT 
    id as user_id,
    'Itaú' as name,
    'bank' as type,
    '🔶' as icon,
    '#EC7000' as color,
    0.00 as balance
FROM `users`;

INSERT INTO `accounts` (`user_id`, `name`, `type`, `icon`, `color`, `balance`)
SELECT 
    id as user_id,
    'Dinheiro' as name,
    'cash' as type,
    '💵' as icon,
    '#10b981' as color,
    0.00 as balance
FROM `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================
-- Notas:
-- - Todas as transações antigas terão account_id = NULL
-- - Novas transações devem obrigatoriamente ter uma conta associada
-- - O saldo das contas será calculado dinamicamente pelas transações
-- ===========================

