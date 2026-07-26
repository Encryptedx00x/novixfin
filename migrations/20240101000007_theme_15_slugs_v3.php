<?php

declare(strict_types=1);

/**
 * NovixFin Entrada slice — migrate users.theme to the 15-slug tokenized
 * theme system (see includes/themes.php).
 *
 * - users.theme becomes VARCHAR(20) NOT NULL DEFAULT 'ametista'.
 * - Backfill mapping for legacy values (documented decision):
 *     'light'          -> 'ametista'  (light default)
 *     'dark' / 'black' -> 'tinta'     (dark default)
 *   NULL/empty/unknown -> 'ametista'.
 *
 * Non destructive: only remaps the theme preference string, never touches
 * financial data. Idempotent: re-running finds no legacy values and the
 * MODIFY COLUMN is a no-op shape-wise.
 *
 * Returns a callable: function(PDO $pdo, callable $log): void.
 */

return static function (PDO $pdo, callable $log): void {
    $dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?? '');
    if ($dbName === '') {
        throw new RuntimeException('Could not determine current database.');
    }

    $columnExists = static function (string $table, string $column) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = :db AND table_name = :t AND column_name = :c LIMIT 1'
        );
        $stmt->execute([':db' => $dbName, ':t' => $table, ':c' => $column]);
        return (bool) $stmt->fetchColumn();
    };

    // Legacy installs that never ran the reconcile migration may miss the column.
    if (!$columnExists('users', 'theme')) {
        $log("  + users.theme (VARCHAR(20) DEFAULT 'ametista')");
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `theme` VARCHAR(20) NOT NULL DEFAULT 'ametista'");
    }

    $validThemes = [
        'ametista', 'cafe', 'sereno2', 'sereno1', 'cofre', 'tinta', 'banca',
        'suica', 'pulso', 'concreto', 'adega', 'vitrola', 'cabine', 'sumie', 'deco',
    ];

    // Backfill legacy slugs BEFORE tightening the column definition.
    $log("  ~ users.theme backfill: light -> ametista");
    $stmt = $pdo->prepare("UPDATE `users` SET `theme` = 'ametista' WHERE `theme` = 'light'");
    $stmt->execute();

    $log("  ~ users.theme backfill: dark/black -> tinta");
    $stmt = $pdo->prepare("UPDATE `users` SET `theme` = 'tinta' WHERE `theme` IN ('dark', 'black')");
    $stmt->execute();

    // Anything else unknown (NULL, empty, typos) -> default.
    $placeholders = implode(',', array_fill(0, count($validThemes), '?'));
    $stmt = $pdo->prepare(
        "UPDATE `users` SET `theme` = 'ametista'
         WHERE `theme` IS NULL OR `theme` NOT IN ({$placeholders})"
    );
    $stmt->execute($validThemes);
    $log('  ~ users.theme backfill: unknown -> ametista (' . $stmt->rowCount() . ' rows)');

    $log("  ~ users.theme MODIFY VARCHAR(20) NOT NULL DEFAULT 'ametista'");
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `theme` VARCHAR(20) NOT NULL DEFAULT 'ametista'");
};
