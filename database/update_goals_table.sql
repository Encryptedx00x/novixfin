-- Atualiza tabela goals para suportar novos recursos
SET NAMES utf8mb4;

-- Adiciona coluna account_id se não existir
ALTER TABLE `goals` 
ADD COLUMN IF NOT EXISTS `account_id` INT UNSIGNED NULL AFTER `user_id`,
ADD INDEX IF NOT EXISTS `idx_account_id` (`account_id`);

-- Atualiza ENUM de type para incluir novos tipos
ALTER TABLE `goals` 
MODIFY COLUMN `type` ENUM('saving', 'spending_limit', 'income_target', 'balance_target', 'expense_limit') DEFAULT 'saving';

-- Adiciona coluna goal_category_id para vincular a categoria (opcional)
ALTER TABLE `goals` 
ADD COLUMN IF NOT EXISTS `goal_category_id` INT UNSIGNED NULL AFTER `account_id`,
ADD INDEX IF NOT EXISTS `idx_goal_category_id` (`goal_category_id`);

-- Adiciona coluna auto_update para indicar se atualiza automaticamente
ALTER TABLE `goals` 
ADD COLUMN IF NOT EXISTS `auto_update` BOOLEAN DEFAULT TRUE AFTER `completed`;

