<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('accounts.php');
}

try {
    $token = $_POST['_token'] ?? '';
    verifyCsrf($token);
    
    $pdo = Connection::get();
    $userId = currentUserId() ?? 0;
    
    $accountId = isset($_POST['account_id']) && $_POST['account_id'] !== '' ? (int) $_POST['account_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'bank';
    $icon = trim($_POST['icon'] ?? '') ?: '🏦';
    $color = trim($_POST['color'] ?? '') ?: '#6366f1';
    
    // Campos específicos de cartão de crédito
    $creditLimit = isset($_POST['credit_limit']) && $_POST['credit_limit'] !== '' ? filter_input(INPUT_POST, 'credit_limit', FILTER_VALIDATE_FLOAT) : null;
    $closingDay = isset($_POST['closing_day']) && $_POST['closing_day'] !== '' ? filter_input(INPUT_POST, 'closing_day', FILTER_VALIDATE_INT) : null;
    $dueDay = isset($_POST['due_day']) && $_POST['due_day'] !== '' ? filter_input(INPUT_POST, 'due_day', FILTER_VALIDATE_INT) : null;
    $interestRate = isset($_POST['interest_rate']) && $_POST['interest_rate'] !== '' ? filter_input(INPUT_POST, 'interest_rate', FILTER_VALIDATE_FLOAT) : null;
    
    if (empty($name)) {
        flash('error', 'Nome da conta é obrigatório.');
        redirect('accounts.php');
    }
    
    if (!in_array($type, ['bank', 'cash', 'investment', 'credit', 'other'])) {
        flash('error', 'Tipo de conta inválido.');
        redirect('accounts.php');
    }
    
    // Validações para cartão de crédito
    if ($type === 'credit') {
        if ($closingDay !== null && ($closingDay < 1 || $closingDay > 31)) {
            flash('error', 'Dia de fechamento deve estar entre 1 e 31.');
            redirect('accounts.php');
        }
        if ($dueDay !== null && ($dueDay < 1 || $dueDay > 31)) {
            flash('error', 'Dia de vencimento deve estar entre 1 e 31.');
            redirect('accounts.php');
        }
        if ($interestRate !== null && ($interestRate < 0 || $interestRate > 100)) {
            flash('error', 'Taxa de juros deve estar entre 0 e 100%.');
            redirect('accounts.php');
        }
    }
    
    if ($accountId) {
        // Atualizar conta existente
        if ($type === 'credit') {
            $stmt = $pdo->prepare('
                UPDATE accounts 
                SET name = :name, type = :type, icon = :icon, color = :color,
                    credit_limit = :credit_limit, closing_day = :closing_day,
                    due_day = :due_day, interest_rate = :interest_rate
                WHERE id = :id AND user_id = :user_id
            ');
            $stmt->execute([
                'id' => $accountId,
                'user_id' => $userId,
                'name' => $name,
                'type' => $type,
                'icon' => $icon,
                'color' => $color,
                'credit_limit' => $creditLimit,
                'closing_day' => $closingDay,
                'due_day' => $dueDay,
                'interest_rate' => $interestRate,
            ]);
        } else {
            // Limpa campos de crédito se não for mais cartão de crédito
            $stmt = $pdo->prepare('
                UPDATE accounts 
                SET name = :name, type = :type, icon = :icon, color = :color,
                    credit_limit = NULL, closing_day = NULL,
                    due_day = NULL, interest_rate = NULL
                WHERE id = :id AND user_id = :user_id
            ');
            $stmt->execute([
                'id' => $accountId,
                'user_id' => $userId,
                'name' => $name,
                'type' => $type,
                'icon' => $icon,
                'color' => $color,
            ]);
        }
        
        flash('success', 'Conta atualizada com sucesso!');
    } else {
        // Criar nova conta
        if ($type === 'credit') {
            $stmt = $pdo->prepare('
                INSERT INTO accounts (user_id, name, type, icon, color, balance, is_active, credit_limit, closing_day, due_day, interest_rate) 
                VALUES (:user_id, :name, :type, :icon, :color, 0.00, 1, :credit_limit, :closing_day, :due_day, :interest_rate)
            ');
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name,
                'type' => $type,
                'icon' => $icon,
                'color' => $color,
                'credit_limit' => $creditLimit,
                'closing_day' => $closingDay,
                'due_day' => $dueDay,
                'interest_rate' => $interestRate,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO accounts (user_id, name, type, icon, color, balance, is_active) VALUES (:user_id, :name, :type, :icon, :color, 0.00, 1)');
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name,
                'type' => $type,
                'icon' => $icon,
                'color' => $color,
            ]);
        }
        
        flash('success', 'Conta criada com sucesso!');
    }
    
} catch (Throwable $e) {
    error_log('Save account error: ' . $e->getMessage());
    flash('error', 'Erro ao salvar conta.');
}

redirect('accounts.php');

