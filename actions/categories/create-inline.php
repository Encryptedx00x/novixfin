<?php

declare(strict_types=1);

/**
 * NovixFin v3.1 — Task 6: inline category creation endpoint.
 *
 * Returns JSON (never a redirect) so the dashboard/history transaction forms
 * can create a new category without leaving the page. Auth + CSRF required;
 * reuses the probe-dedup-insert pattern of `actions/categories/save.php`
 * (Kept here for migration-not-applied-yet compatibility).
 *
 * Request  : POST application/x-www-form-urlencoded
 *           _token         CSRF
 *           name           Trimmed non-empty string
 *           type           "income" | "expense"
 * Response : 200 {"ok":true,"id":N,"name":"...","type":"expense"} on success
 *           4xx/5xx {"ok":false,"error":"..."} on failure
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload + ['ok' => $ok], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['error' => 'Método não permitido.'], 405);
}

try {
    verifyCsrf($_POST['_token'] ?? '');
} catch (Throwable $e) {
    respond(false, ['error' => 'Token CSRF inválido.'], 419);
}

$name = trim((string) ($_POST['name'] ?? ''));
$type = (string) ($_POST['type'] ?? '');

if ($name === '') {
    respond(false, ['error' => 'Nome da categoria é obrigatório.'], 422);
}
if (!in_array($type, ['income', 'expense'], true)) {
    respond(false, ['error' => 'Tipo de categoria inválido.'], 422);
}

try {
    $pdo    = Connection::get();
    $userId = currentUserId() ?? 0;

    // Probe — same defensive guard save.php uses for migration-not-applied.
    $hasUserIdColumn = false;
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
        $hasUserIdColumn = (bool) $checkColumn->fetchColumn();
    } catch (PDOException $e) {
        // ignore
    }

    // Detect duplicates for this (name, type, user-scope).
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE name = :name AND type = :type AND (user_id = :user_id OR user_id IS NULL) LIMIT 1');
        $stmt->execute([':name' => $name, ':type' => $type, ':user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE name = :name AND type = :type LIMIT 1');
        $stmt->execute([':name' => $name, ':type' => $type]);
    }
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        // Already exists — reuse it instead of treating as error. The dashboard
        // select then highlights the existing option; the user perceives this as
        // "the same category I just typed is now selected".
        respond(true, [
            'id'   => (int) $existing['id'],
            'name' => $existing['name'],
            'type' => $type,
            'reused' => true,
        ]);
    }

    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, type, user_id) VALUES (:name, :type, :user_id)');
        $stmt->execute([':name' => $name, ':type' => $type, ':user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO categories (name, type) VALUES (:name, :type)');
        $stmt->execute([':name' => $name, ':type' => $type]);
    }

    $newId = (int) $pdo->lastInsertId();

    respond(true, [
        'id'   => $newId,
        'name' => $name,
        'type' => $type,
        'reused' => false,
    ]);
} catch (Throwable $e) {
    error_log('create-inline category error: ' . $e->getMessage());
    respond(false, ['error' => 'Erro interno ao criar categoria.'], 500);
}