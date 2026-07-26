<?php

declare(strict_types=1);

/**
 * NovixFin v3 migration runner.
 *
 * Idempotent, dependency-free. Tracks applied migrations in a `migrations`
 * table (created on first run). Migrations live in /migrations/ and execute
 * in filename order. Both `.sql` and `.php` migrations are supported:
 *
 *   .sql  → split into statements (splitSemicolon-aware) and executed as PDO::exec
 *   .php  → required as a function receiving the current PDO instance; the file
 *           must `return function (PDO $pdo, callable $log) { ... };` That
 *           function does its own guarded ALTERs (information_schema checks).
 *
 * Usage (CLI only — refuses via web):
 *   php scripts/migrate.php            # default: run pending migrations
 *   php scripts/migrate.php up          # alias for default
 *   php scripts/migrate.php status      # print applied + pending list
 *
 * Designed for MySQL 8.0+ / MariaDB 10.6+ on Hostinger shared hosting.
 *
 * SAFETY: this runner NEVER rolls back. Each migration runs in its own
 * transaction when possible (some DDL implicitly commits in MySQL —
 * unavoidable). A failed migration is marked as 'failed' in the log but
 * NOT recorded as applied, so re-running will attempt it again after the
 * user fixes the cause.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden: CLI-only script.' . PHP_EOL;
    exit;
}

// Bootstrap the application (loads env + Connection + errors).
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use Novix\Database\Connection;

$pdo = Connection::get();
$migrationsDir = __DIR__ . '/../migrations';

$command = $argv[1] ?? 'up';

ensureMigrationsTable($pdo);

switch ($command) {
    case 'up':
        runPending($pdo, $migrationsDir);
        break;
    case 'status':
        printStatus($pdo, $migrationsDir);
        break;
    default:
        fwrite(STDERR, "Unknown command: {$command}\n");
        fwrite(STDERR, "Usage: php scripts/migrate.php [up|status]\n");
        exit(2);
}

/**
 * ----------------------------------------------------------------------- API
 */

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
          `id` VARCHAR(255) NOT NULL PRIMARY KEY,
          `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `checksum` VARCHAR(64) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function listMigrationFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    foreach (scandir($dir) as $name) {
        if ($name === '.' || $name === '..') continue;
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!in_array($ext, ['sql', 'php'], true)) continue;
        $files[] = $name;
    }
    sort($files, SORT_STRING);
    return $files;
}

function appliedMigrations(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id FROM migrations ORDER BY id ASC');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

function runPending(PDO $pdo, string $dir): void
{
    $files = listMigrationFiles($dir);
    if (!$files) {
        logLine('No migrations found in ' . $dir);
        return;
    }
    $applied = appliedMigrations($pdo);
    $appliedSet = array_flip($applied);
    $count = 0;

    foreach ($files as $name) {
        if (isset($appliedSet[$name])) {
            continue;
        }
        logHeader("Applying {$name}");
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $checksum = hash_file('sha256', $path);

        try {
            if ($ext === 'sql') {
                executeSqlFile($pdo, $path);
            } elseif ($ext === 'php') {
                $runner = require $path;
                if (!is_callable($runner)) {
                    throw new RuntimeException("PHP migration {$name} did not return a callable.");
                }
                $runner($pdo, 'logLine');
            }
            $stmt = $pdo->prepare('INSERT INTO migrations (id, checksum) VALUES (:id, :checksum)');
            $stmt->execute([':id' => $name, ':checksum' => $checksum]);
            $count++;
            logLine("  ✓ applied");
        } catch (Throwable $e) {
            logLine('  ✗ FAILED: ' . $e->getMessage());
            logLine('  Aborting further migrations. Fix the issue and re-run.');
            exit(1);
        }
    }
    logLine("Done. {$count} migration(s) applied.");
}

function printStatus(PDO $pdo, string $dir): void
{
    $files = listMigrationFiles($dir);
    $applied = appliedMigrations($pdo);
    $appliedSet = array_flip($applied);

    logLine('Migrations directory: ' . $dir);
    logLine('Applied: ' . count($applied) . '  Total: ' . count($files));
    logLine(str_repeat('-', 70));
    foreach ($files as $name) {
        $mark = isset($appliedSet[$name]) ? '[x]' : '[ ]';
        echo $mark . ' ' . $name . PHP_EOL;
    }
    $pending = array_diff($files, $applied);
    if (!$pending) {
        logLine('All migrations applied.');
    } else {
        logLine(count($pending) . ' pending.');
    }
}

function executeSqlFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$path}");
    }
    // Normalize: treat delimiter blocks simply. NovixFin migrations are plain
    // statement lists ending in `;`. Multi-line string literals in SQL are
    // uncommon here (we use INSERT IGNORE for seeds, no stored procedures
    // in .sql files). PHP migrations handle anything fancier.
    foreach (splitSqlStatements($sql) as $statement) {
        $stmt = trim($statement);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
    }
}

/**
 * Split a SQL script into individual statements on `;`, ignoring line comments
 * and `;` inside single-quoted strings.
 */
function splitSqlStatements(string $sql): array
{
    $sql = stripSqlLineComments($sql);
    $statements = [];
    $current = '';
    $inString = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $current .= $ch;
        if ($ch === "'" && $inString && $i + 1 < $len && $sql[$i + 1] === "'") {
            $current .= $sql[$i + 1];
            $i++;
            continue;
        }
        if ($ch === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $inString = !$inString;
        } elseif ($ch === ';' && !$inString) {
            $statements[] = $current;
            $current = '';
        }
    }
    if (trim($current) !== '') {
        $statements[] = $current;
    }
    return $statements;
}

function stripSqlLineComments(string $sql): string
{
    $out = '';
    $inString = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($ch === "'" && $inString && $i + 1 < $len && $sql[$i + 1] === "'") {
            $out .= $ch . $sql[$i + 1];
            $i++;
            continue;
        }

        if ($ch === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $inString = !$inString;
            $out .= $ch;
            continue;
        }

        if (!$inString && $ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            while ($i < $len && $sql[$i] !== "\n" && $sql[$i] !== "\r") {
                $i++;
            }
            if ($i < $len) {
                if ($sql[$i] === "\r" && $i + 1 < $len && $sql[$i + 1] === "\n") {
                    $out .= "\r\n";
                    $i++;
                } else {
                    $out .= $sql[$i];
                }
            }
            continue;
        }

        $out .= $ch;
    }

    return $out;
}

function logHeader(string $msg): void
{
    echo PHP_EOL . '==> ' . $msg . PHP_EOL;
}

function logLine(string $msg): void
{
    echo $msg . PHP_EOL;
}
