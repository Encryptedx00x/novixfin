-- Adiciona categoria "Arrecadação" se não existir
SET NAMES utf8mb4;

INSERT IGNORE INTO `categories` (`name`, `type`) VALUES
('Arrecadação', 'income');

