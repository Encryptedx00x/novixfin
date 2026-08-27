-- NovixFin Seed Data
-- Dados iniciais para categorias

SET NAMES utf8mb4;

-- ===========================
-- Categorias de Receita
-- ===========================
INSERT INTO `categories` (`name`, `type`) VALUES
('Salário', 'income'),
('Freelance', 'income'),
('Investimentos', 'income'),
('Prêmios', 'income'),
('Vendas', 'income'),
('Arrecadação', 'income'),
('Outros (Receita)', 'income');

-- ===========================
-- Categorias de Despesa
-- ===========================
INSERT INTO `categories` (`name`, `type`) VALUES
('Alimentação', 'expense'),
('Transporte', 'expense'),
('Moradia', 'expense'),
('Saúde', 'expense'),
('Educação', 'expense'),
('Lazer', 'expense'),
('Compras', 'expense'),
('Contas', 'expense'),
('Outros (Despesa)', 'expense');

