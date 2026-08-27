-- Adiciona suporte a temas
ALTER TABLE users ADD COLUMN IF NOT EXISTS theme VARCHAR(20) DEFAULT 'dark' AFTER badges;

