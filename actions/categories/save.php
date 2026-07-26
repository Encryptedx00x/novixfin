<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('categories.php');
}

try {
    $token = $_POST['_token'] ?? '';
    verifyCsrf($token);
    
    $pdo = Connection::get();
    $userId = currentUserId() ?? 0;
    
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    
    if (empty($name)) {
        flash('error', 'Nome da categoria é obrigatório.');
        redirect('categories.php');
    }
    
    if (!in_array($type, ['income', 'expense'], true)) {
        flash('error', 'Tipo de categoria inválido.');
        redirect('categories.php');
    }
    
    // v3: `categories.user_id` is added by the canonical migration
// (migrations/20240101000001_reconcile_v3.php). The defensive ALTER-TABLE
// that lived here is removed so the migration owns the column definition.
//
// Probes for `user_id` existence are scheduled for removal in Phase 3 Batch C
// (once the migration is confirmed applied on prod). Until then the probe
// keeps the handler working on DBs where the migration hasn't run yet.

// Verifica se user_id existe na tabela
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Ignora erro
}
    
    // Verifica se já existe para este usuário e tipo
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = :name AND type = :type AND user_id = :user_id');
        $stmt->execute(['name' => $name, 'type' => $type, 'user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = :name AND type = :type');
        $stmt->execute(['name' => $name, 'type' => $type]);
    }
    if ($stmt->fetchColumn() > 0) {
        flash('error', 'Esta categoria já existe para este tipo.');
        redirect('categories.php');
    }
    
    // Insere a categoria apenas para o tipo selecionado e usuário
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, type, user_id) VALUES (:name, :type, :user_id)');
        $stmt->execute(['name' => $name, 'type' => $type, 'user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO categories (name, type) VALUES (:name, :type)');
        $stmt->execute(['name' => $name, 'type' => $type]);
    }
    
    flash('success', 'Categoria adicionada com sucesso!');
    
} catch (Throwable $e) {
    error_log('Save category error: ' . $e->getMessage());
    flash('error', 'Erro ao salvar categoria.');
}

redirect('categories.php');

