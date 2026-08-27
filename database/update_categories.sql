-- Atualiza categorias do NovixFin
-- Execute este SQL no phpMyAdmin

SET NAMES utf8mb4;

-- Remove categorias antigas (se existirem)
DELETE FROM categories WHERE name IN ('Outros (Receita)', 'Outros (Despesa)');

-- Adiciona novas categorias que podem estar faltando
INSERT IGNORE INTO `categories` (`name`, `type`) VALUES
('Mesada', 'income'),
('Doação', 'income'),
('Outros', 'income');

INSERT IGNORE INTO `categories` (`name`, `type`) VALUES
('Mesada', 'expense'),
('Doação', 'expense'),
('Outros', 'expense');

-- Lista final de categorias que devem existir:
-- RECEITAS: Salário, Freelance, Investimentos, Prêmios, Vendas, Mesada, Doação, Outros
-- DESPESAS: Alimentação, Transporte, Moradia, Saúde, Educação, Lazer, Compras, Contas, Mesada, Doação, Outros

