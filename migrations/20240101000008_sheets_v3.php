<?php

declare(strict_types=1);

/**
 * NovixFin — Modo Planilha: tabelas `sheets` e `sheet_versions`.
 *
 * IDEMPOTENTE: usa CREATE TABLE IF NOT EXISTS e checa a existência antes de
 * qualquer ALTER. Rodar de novo não altera nada.
 *
 * Modelo:
 *  - `sheets`: um documento por usuário (pode haver mais de um). `content` é o
 *    JSON com o ARRAY DE ABAS; cada aba tem nome e o mapa de células, e cada
 *    célula guarda o que o usuário digitou (valor OU fórmula "=SUM(A1:A5)"),
 *    nunca o resultado calculado.
 *  - `sheet_versions`: snapshots do histórico (marcos), não autosave.
 *
 * MariaDB 11.8: sem sintaxe MySQL-only. FKs com CASCADE para não deixar órfãos.
 */

return function (PDO $pdo, callable $log): void {
    $log('  ~ CREATE TABLE IF NOT EXISTS sheets');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS `sheets` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL DEFAULT "Rascunho",
            `content` LONGTEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_sheets_user` (`user_id`),
            CONSTRAINT `fk_sheets_user` FOREIGN KEY (`user_id`)
                REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $log('  ~ CREATE TABLE IF NOT EXISTS sheet_versions');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS `sheet_versions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `sheet_id` INT UNSIGNED NOT NULL,
            `label` VARCHAR(160) NULL,
            `content` LONGTEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_sheet_versions_sheet` (`sheet_id`, `created_at`),
            CONSTRAINT `fk_sheet_versions_sheet` FOREIGN KEY (`sheet_id`)
                REFERENCES `sheets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
};
