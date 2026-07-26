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
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    
    if ($categoryId <= 0) {
        flash('error', 'Categoria inválida.');
        redirect('categories.php');
    }
    
    // Verifica se user_id existe na tabela
    $hasUserIdColumn = false;
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
        $hasUserIdColumn = $checkColumn->rowCount() > 0;
    } catch (PDOException $e) {
        // Ignora erro
    }
    
    // Verifica se a categoria pertence ao usuário
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
        $stmt->execute(['id' => $categoryId]);
    }
    $category = $stmt->fetch();
    
    if (!$category) {
        flash('error', 'Categoria não encontrada ou você não tem permissão para removê-la.');
        redirect('categories.php');
    }
    
    // Verifica se há transações usando esta categoria
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = :category_id AND user_id = :user_id');
    $stmt->execute(['category_id' => $categoryId, 'user_id' => $userId]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        flash('error', "Não é possível remover esta categoria. Existem $count transações vinculadas a ela.");
        redirect('categories.php');
    }
    
    // Remove categoria (apenas se pertencer ao usuário)
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $categoryId]);
    }
    
    flash('success', 'Categoria removida com sucesso!');
    
} catch (Throwable $e) {
    error_log('Delete category error: ' . $e->getMessage());
    flash('error', 'Erro ao remover categoria.');
}

redirect('categories.php');

