-- Corrige categorias SEM deletar as existentes
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;

-- Remove apenas as duplicatas problemáticas (se existirem e não tiverem transações)
DELETE FROM categories 
WHERE name IN ('Outros (Receita)', 'Outros (Despesa)') 
AND id NOT IN (SELECT DISTINCT category_id FROM transactions WHERE category_id IS NOT NULL);

-- Adiciona categorias que podem estar faltando (INSERT IGNORE não dá erro se já existir)
-- Receitas
INSERT IGNORE INTO `categories` (`name`, `type`) VALUES
('Salário', 'income'),
('Freelance', 'income'),
('Investimentos', 'income'),
('Prêmios', 'income'),
('Vendas', 'income'),
('Mesada', 'income'),
('Doação', 'income'),
('Outros', 'income');

-- Despesas
INSERT IGNORE INTO `categories` (`name`, `type`) VALUES
('Alimentação', 'expense'),
('Transporte', 'expense'),
('Moradia', 'expense'),
('Saúde', 'expense'),
('Educação', 'expense'),
('Lazer', 'expense'),
('Compras', 'expense'),
('Contas', 'expense'),
('Mesada', 'expense'),
('Doação', 'expense'),
('Outros', 'expense');

-- Lista de categorias resultantes:
-- RECEITAS: Salário, Freelance, Investimentos, Prêmios, Vendas, Mesada, Doação, Outros
-- DESPESAS: Alimentação, Transporte, Moradia, Saúde, Educação, Lazer, Compras, Contas, Mesada, Doação, Outros

