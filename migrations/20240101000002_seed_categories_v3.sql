-- NovixFin v3 — canonical default categories (idempotent seed).
--
-- Idempotent regardless of uniqueness constraints: each INSERT is guarded by
-- a WHERE NOT EXISTS clause that checks for a category with the same
-- (name, type) and NULL user_id (global). Per-user categories created later
-- by users themselves are untouched.

SET NAMES utf8mb4;

-- Income defaults
INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Salário', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Salário' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Freelance', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Freelance' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Investimentos', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Investimentos' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Prêmios', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Prêmios' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Vendas', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Vendas' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Arrecadação', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Arrecadação' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Mesada', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Mesada' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Doação', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Doação' AND `type` = 'income' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Outros', 'income', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Outros' AND `type` = 'income' AND `user_id` IS NULL);

-- Expense defaults
INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Alimentação', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Alimentação' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Transporte', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Transporte' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Moradia', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Moradia' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Saúde', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Saúde' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Educação', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Educação' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Lazer', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Lazer' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Compras', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Compras' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Contas', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Contas' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Mesada', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Mesada' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Doação', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Doação' AND `type` = 'expense' AND `user_id` IS NULL);

INSERT INTO `categories` (`name`, `type`, `user_id`)
SELECT 'Outros', 'expense', NULL
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Outros' AND `type` = 'expense' AND `user_id` IS NULL);

-- The legacy "Outros (Receita)" / "Outros (Despesa)" rows from `seed.sql` are
-- intentionally NOT created here, they were removed by fix_categories_safe.sql
-- in v2 and stay removed in v3.
