-- Adiciona funcionalidades extras para contas
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;

-- Adiciona coluna para controlar exibição na dashboard
ALTER TABLE `accounts` 
ADD COLUMN `show_in_dashboard` BOOLEAN DEFAULT TRUE AFTER `is_active`;

-- Por padrão, todas as contas existentes aparecem na dashboard
UPDATE `accounts` SET `show_in_dashboard` = TRUE WHERE `show_in_dashboard` IS NULL;

