-- Adiciona campo de moeda (currency) na tabela transactions
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;

-- Adiciona coluna currency na tabela transactions
ALTER TABLE `transactions` 
ADD COLUMN `currency` ENUM('BRL', 'USD', 'EUR') DEFAULT 'BRL' AFTER `amount`;

-- Atualiza transações existentes para usar BRL como padrão
UPDATE `transactions` SET `currency` = 'BRL' WHERE `currency` IS NULL;

