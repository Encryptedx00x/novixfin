-- Adiciona campos is_effective e is_recurring na tabela transactions
-- is_effective: indica se a transação foi efetivada (padrão: TRUE)
-- is_recurring: indica se é uma transação fixa/recorrente (padrão: FALSE)

SET NAMES utf8mb4;

-- Adiciona coluna is_effective na tabela transactions
ALTER TABLE `transactions`
ADD COLUMN `is_effective` BOOLEAN DEFAULT TRUE AFTER `transaction_date`,
ADD COLUMN `is_recurring` BOOLEAN DEFAULT FALSE AFTER `is_effective`;

-- Adiciona índices para melhor performance nas consultas filtradas
ALTER TABLE `transactions`
ADD INDEX `idx_is_effective` (`is_effective`),
ADD INDEX `idx_is_recurring` (`is_recurring`);

-- Atualiza todas as transações existentes para serem efetivadas por padrão
UPDATE `transactions` SET `is_effective` = TRUE WHERE `is_effective` IS NULL;
UPDATE `transactions` SET `is_recurring` = FALSE WHERE `is_recurring` IS NULL;

