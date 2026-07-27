<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/goals.php');
}

verifyCsrf($_POST['_token'] ?? '');

$goalId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
$title = trim((string) ($_POST['title'] ?? ''));
$targetAmount = filter_input(INPUT_POST, 'target_amount', FILTER_VALIDATE_FLOAT);
$currentAmount = filter_input(INPUT_POST, 'current_amount', FILTER_VALIDATE_FLOAT);
$deadline = $_POST['deadline'] ?? null;
$type = $_POST['type'] ?? 'saving';
$accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT) ?: null;
$autoUpdate = isset($_POST['auto_update']) && $_POST['auto_update'] === '1';

if (!$title || strlen($title) < 3) {
    flash('error', 'Informe um título com pelo menos 3 caracteres.');
    redirect('/public/goals.php');
}

if ($targetAmount === false || $targetAmount <= 0) {
    flash('error', 'Informe um valor alvo válido.');
    redirect('/public/goals.php');
}

if ($currentAmount === false || $currentAmount < 0) {
    $currentAmount = 0;
}

if ($deadline) {
    try {
        $deadlineDate = new \DateTimeImmutable($deadline);
        $deadline = $deadlineDate->format('Y-m-d');
    } catch (Exception $e) {
        flash('error', 'Data inválida.');
        redirect('/public/goals.php');
    }
} else {
    $deadline = null;
}

if (!in_array($type, ['saving', 'spending_limit', 'income_target', 'balance_target', 'expense_limit'], true)) {
    $type = 'saving';
}

$pdo = Connection::get();

try {
    // Verifica se a coluna é 'title' ou 'name' (compatibilidade)
    try {
        $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM goals LIKE 'title'");
        $hasTitleColumn = $checkColumnStmt->rowCount() > 0;
        $titleColumn = $hasTitleColumn ? 'title' : 'name';
    } catch (Exception $e) {
        $titleColumn = 'name'; // Fallback padrão
    }
    
    // Verifica se account_id existe na tabela
    $checkAccountColumn = false;
    try {
        $checkAccountStmt = $pdo->query("SHOW COLUMNS FROM goals LIKE 'account_id'");
        $checkAccountColumn = $checkAccountStmt->rowCount() > 0;
    } catch (Exception $e) {
        $checkAccountColumn = false;
    }
    
    // Verifica se auto_update existe na tabela
    $checkAutoUpdateColumn = false;
    try {
        $checkAutoUpdateStmt = $pdo->query("SHOW COLUMNS FROM goals LIKE 'auto_update'");
        $checkAutoUpdateColumn = $checkAutoUpdateStmt->rowCount() > 0;
    } catch (Exception $e) {
        $checkAutoUpdateColumn = false;
    }
    
    if ($goalId) {
        if ($checkAccountColumn && $checkAutoUpdateColumn) {
            $stmt = $pdo->prepare("UPDATE goals SET {$titleColumn} = :title, target_amount = :target_amount, current_amount = :current_amount, deadline = :deadline, type = :type, account_id = :account_id, auto_update = :auto_update WHERE id = :id AND user_id = :user_id");
            $stmt->execute([
                'id' => $goalId,
                'user_id' => currentUserId(),
                'title' => $title,
                'target_amount' => $targetAmount,
                'current_amount' => $currentAmount,
                'deadline' => $deadline,
                'type' => $type,
                'account_id' => $accountId,
                'auto_update' => $autoUpdate ? 1 : 0,
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE goals SET {$titleColumn} = :title, target_amount = :target_amount, current_amount = :current_amount, deadline = :deadline, type = :type WHERE id = :id AND user_id = :user_id");
            $stmt->execute([
                'id' => $goalId,
                'user_id' => currentUserId(),
                'title' => $title,
                'target_amount' => $targetAmount,
                'current_amount' => $currentAmount,
                'deadline' => $deadline,
                'type' => $type,
            ]);
        }

        flash('success', 'Meta atualizada com sucesso.');
    } else {
        if ($checkAccountColumn && $checkAutoUpdateColumn) {
            $stmt = $pdo->prepare("INSERT INTO goals (user_id, {$titleColumn}, target_amount, current_amount, deadline, type, account_id, auto_update) VALUES (:user_id, :title, :target_amount, :current_amount, :deadline, :type, :account_id, :auto_update)");
            $stmt->execute([
                'user_id' => currentUserId(),
                'title' => $title,
                'target_amount' => $targetAmount,
                'current_amount' => $currentAmount,
                'deadline' => $deadline,
                'type' => $type,
                'account_id' => $accountId,
                'auto_update' => $autoUpdate ? 1 : 0,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO goals (user_id, {$titleColumn}, target_amount, current_amount, deadline, type) VALUES (:user_id, :title, :target_amount, :current_amount, :deadline, :type)");
            $stmt->execute([
                'user_id' => currentUserId(),
                'title' => $title,
                'target_amount' => $targetAmount,
                'current_amount' => $currentAmount,
                'deadline' => $deadline,
                'type' => $type,
            ]);
        }

        flash('success', 'Meta criada com sucesso.');
    }
} catch (PDOException $e) {
    error_log('Error saving goal: ' . $e->getMessage());
    flash('error', 'Erro ao salvar meta: ' . $e->getMessage());
    redirect('/public/goals.php');
    exit;
} catch (Exception $e) {
    error_log('Error saving goal: ' . $e->getMessage());
    flash('error', 'Erro inesperado ao salvar meta.');
    redirect('/public/goals.php');
    exit;
}

redirect('/public/goals.php');

