-- Melhora sistema de transações recorrentes e efetivação
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;

-- Adiciona campos para melhor controle de recorrência
ALTER TABLE `transactions`
ADD COLUMN `recurrence_type` ENUM('none', 'daily', 'weekly', 'monthly', 'yearly', 'same_day') DEFAULT 'none' AFTER `is_recurring`,
ADD COLUMN `recurrence_day` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Dia do mês para recorrências mensais (1-31) ou dia da semana para semanais (0=Domingo, 6=Sábado)' AFTER `recurrence_type`,
ADD COLUMN `recurrence_end_date` DATE NULL DEFAULT NULL COMMENT 'Data final da recorrência (opcional)' AFTER `recurrence_day`,
ADD COLUMN `effective_date` DATE NULL DEFAULT NULL COMMENT 'Data de efetivação automática (opcional)' AFTER `recurrence_end_date`,
ADD COLUMN `effective_at` DATETIME NULL DEFAULT NULL COMMENT 'Data/hora em que foi efetivada' AFTER `is_effective`;

-- Adiciona índices
ALTER TABLE `transactions`
ADD INDEX `idx_recurrence_type` (`recurrence_type`),
ADD INDEX `idx_effective_date` (`effective_date`),
ADD INDEX `idx_effective_at` (`effective_at`);

-- Atualiza transações existentes
UPDATE `transactions` SET `recurrence_type` = 'none' WHERE `recurrence_type` IS NULL;
UPDATE `transactions` SET `recurrence_type` = 'monthly' WHERE `is_recurring` = 1 AND `recurrence_type` = 'none';

