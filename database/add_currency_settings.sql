-- Adiciona configurações de moedas por usuário
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===========================
-- Tabela: user_currency_settings
-- ===========================
CREATE TABLE IF NOT EXISTS `user_currency_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `primary_currency` ENUM('BRL', 'USD', 'EUR') DEFAULT 'BRL',
  `enabled_currencies` JSON DEFAULT '["BRL", "USD", "EUR"]',
  `currency_order` JSON DEFAULT '["BRL", "USD", "EUR"]',
  `convert_all_to_one` BOOLEAN DEFAULT FALSE,
  `conversion_target_currency` ENUM('BRL', 'USD', 'EUR') NULL,
  `conversion_rate_brl_to_usd` DECIMAL(10, 4) DEFAULT 0.18,
  `conversion_rate_brl_to_eur` DECIMAL(10, 4) DEFAULT 0.18,
  `conversion_rate_usd_to_brl` DECIMAL(10, 4) DEFAULT 5.61,
  `conversion_rate_usd_to_eur` DECIMAL(10, 4) DEFAULT 0.85,
  `conversion_rate_eur_to_brl` DECIMAL(10, 4) DEFAULT 5.61,
  `conversion_rate_eur_to_usd` DECIMAL(10, 4) DEFAULT 1.18,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

