<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

/**
 * NovixFin v3 — Phase 7: data export endpoint.
 *
 * Streams the authenticated user's data as CSV or JSON for backup / migration.
 * The user keeps ownership scoping on every SELECT (no other users' data).
 *
 * Usage:
 *   /public/export.php                        → defaults: CSV of transactions
 *   /public/export.php?format=json&scope=all  → JSON of transactions+accounts+goals
 *   /public/export.php?format=csv&scope=accounts
 */

$userId = currentUserId() ?? 0;
if ($userId <= 0) {
    http_response_code(401);
    exit('Unauthorized.');
}

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
$scope  = strtolower((string) ($_GET['scope']  ?? 'transactions'));
if (!in_array($format, ['csv', 'json'], true)) {
    $format = 'csv';
}
if (!in_array($scope, ['transactions', 'accounts', 'goals', 'all'], true)) {
    $scope = 'transactions';
}

$pdo = Connection::get();

/**
 * Fetch a scoped dataset for the user. Returns array<string,array{cols:list<string>, rows:list<array>}>.
 */
$fetch = static function (string $table, string $sql) use ($pdo, $userId): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cols = $rows ? array_keys($rows[0]) : [];
    return ['cols' => $cols, 'rows' => $rows];
};

$datasets = [];
if (in_array($scope, ['transactions', 'all'], true)) {
    $datasets['transactions'] = $fetch('transactions', <<<SQL
        SELECT id, transaction_date AS date, type, amount, currency, description,
               is_effective, is_recurring, recurrence_type, recurrence_day,
               effective_date, account_id, category_id, bill_id,
               created_at, updated_at
          FROM transactions
         WHERE user_id = :user_id
         ORDER BY transaction_date DESC, created_at DESC
    SQL);
}
if (in_array($scope, ['accounts', 'all'], true)) {
    // Pick the canonical column set; ignore columns that may not exist on a
    // not-yet-migrated DB by using try/catch + a fallback list.
    try {
        $datasets['accounts'] = $fetch('accounts', 'SELECT id, name, type, balance, is_active, show_in_dashboard, credit_limit, closing_day, due_day, interest_rate, created_at, updated_at FROM accounts WHERE user_id = :user_id ORDER BY name ASC');
    } catch (PDOException $e) {
        $datasets['accounts'] = $fetch('accounts', 'SELECT id, name, type, balance, is_active, created_at, updated_at FROM accounts WHERE user_id = :user_id ORDER BY name ASC');
    }
}
if (in_array($scope, ['goals', 'all'], true)) {
    // Try canonical (title) schema first; fall back to legacy (name) if needed.
    try {
        $datasets['goals'] = $fetch('goals', 'SELECT id, type, title, target_amount, current_amount, deadline, completed, auto_update, account_id, created_at, updated_at FROM goals WHERE user_id = :user_id ORDER BY created_at ASC');
    } catch (PDOException $e) {
        $datasets['goals'] = $fetch('goals', 'SELECT id, name, type, target_amount, current_amount, deadline, completed, created_at, updated_at FROM goals WHERE user_id = :user_id ORDER BY id ASC');
    }
}

$baseName = sprintf('novixfin_%s_%s', $scope, date('Y-m-d_His'));

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.json"');

    $out = [];
    foreach ($datasets as $name => $ds) {
        $out[$name] = $ds['rows'];
    }
    echo json_encode(['exported_at' => date('c'), 'user_id' => $userId, 'data' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// CSV — if a single scope, one CSV; if all=scope, write a CSV per dataset into a sectioned header.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');

$out = fopen('php://output', 'w');
foreach ($datasets as $name => $ds) {
    fputcsv($out, ['# SECTION: ' . $name]);
    if (!empty($ds['cols'])) {
        fputcsv($out, $ds['cols']);
    } else {
        fputcsv($out, ['(no rows)']);
    }
    foreach ($ds['rows'] as $row) {
        fputcsv($out, array_map(static fn($v) => $v ?? '', $row));
    }
    fputcsv($out, []);
}
fclose($out);