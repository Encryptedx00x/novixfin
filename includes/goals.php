<?php

declare(strict_types=1);

use Novix\Database\Connection;

/**
 * Atualiza automaticamente o progresso das metas baseado em transações
 */
function updateGoalsFromTransaction(int $userId, ?int $accountId, string $type, float $amount, bool $isEffective): void
{
    if (!$isEffective) {
        return; // Só atualiza metas com transações efetivadas
    }
    
    $pdo = Connection::get();
    
    // Busca metas que devem ser atualizadas automaticamente
    $query = 'SELECT id, type, target_amount, current_amount, account_id, auto_update 
              FROM goals 
              WHERE user_id = :user_id 
              AND (auto_update = 1 OR auto_update IS NULL)';
    
    $params = ['user_id' => $userId];
    
    // Se a transação tem conta, busca metas daquela conta ou globais
    if ($accountId) {
        $query .= ' AND (account_id = :account_id OR account_id IS NULL)';
        $params['account_id'] = $accountId;
    } else {
        $query .= ' AND account_id IS NULL';
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $goals = $stmt->fetchAll();
    
    foreach ($goals as $goal) {
        $goalType = $goal['type'] ?? 'saving';
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $newAmount = $currentAmount;
        
        // Atualiza baseado no tipo de meta
        switch ($goalType) {
            case 'saving':
                // Meta de economia: soma receitas, subtrai despesas
                if ($type === 'income') {
                    $newAmount = $currentAmount + $amount;
                } elseif ($type === 'expense') {
                    $newAmount = max(0, $currentAmount - $amount);
                }
                break;
                
            case 'income_target':
                // Meta de receita: soma apenas receitas
                if ($type === 'income') {
                    $newAmount = $currentAmount + $amount;
                }
                break;
                
            case 'expense_limit':
            case 'spending_limit':
                // Meta de limite de gastos: soma apenas despesas
                if ($type === 'expense') {
                    $newAmount = $currentAmount + $amount;
                }
                break;
                
            case 'balance_target':
                // Meta de saldo: calcula saldo total da conta
                if ($accountId && $goal['account_id'] == $accountId) {
                    $balanceStmt = $pdo->prepare('
                        SELECT balance FROM accounts WHERE id = :account_id
                    ');
                    $balanceStmt->execute(['account_id' => $accountId]);
                    $account = $balanceStmt->fetch();
                    if ($account) {
                        $newAmount = (float) ($account['balance'] ?? 0);
                    }
                } else {
                    // Para metas globais, calcula saldo total de todas as contas
                    $totalBalanceStmt = $pdo->prepare('
                        SELECT SUM(balance) as total_balance 
                        FROM accounts 
                        WHERE user_id = :user_id AND is_active = 1
                    ');
                    $totalBalanceStmt->execute(['user_id' => $userId]);
                    $total = $totalBalanceStmt->fetch();
                    $newAmount = (float) ($total['total_balance'] ?? 0);
                }
                break;
        }
        
        // Atualiza a meta
        $updateStmt = $pdo->prepare('UPDATE goals SET current_amount = :current_amount WHERE id = :id');
        $updateStmt->execute([
            'current_amount' => $newAmount,
            'id' => $goal['id'],
        ]);
        
        // Marca como completa se atingiu o objetivo
        if ($newAmount >= $targetAmount && $targetAmount > 0) {
            $completeStmt = $pdo->prepare('UPDATE goals SET completed = 1 WHERE id = :id');
            $completeStmt->execute(['id' => $goal['id']]);
        }
    }
}

/**
 * Recalcula todas as metas de um usuário (útil para sincronização)
 */
function recalculateAllGoals(int $userId): void
{
    $pdo = Connection::get();
    
    // Busca todas as metas com auto_update
    $stmt = $pdo->prepare('
        SELECT id, type, account_id 
        FROM goals 
        WHERE user_id = :user_id 
        AND (auto_update = 1 OR auto_update IS NULL)
    ');
    $stmt->execute(['user_id' => $userId]);
    $goals = $stmt->fetchAll();
    
    foreach ($goals as $goal) {
        $goalType = $goal['type'] ?? 'saving';
        $accountId = $goal['account_id'] ? (int) $goal['account_id'] : null;
        $newAmount = 0.0;
        
        switch ($goalType) {
            case 'saving':
                // Calcula economia: receitas - despesas
                $query = 'SELECT 
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) - 
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total
                    FROM transactions 
                    WHERE user_id = :user_id AND is_effective = 1';
                $params = ['user_id' => $userId];
                
                if ($accountId) {
                    $query .= ' AND account_id = :account_id';
                    $params['account_id'] = $accountId;
                }
                
                $calcStmt = $pdo->prepare($query);
                $calcStmt->execute($params);
                $result = $calcStmt->fetch();
                $newAmount = max(0, (float) ($result['total'] ?? 0));
                break;
                
            case 'income_target':
                // Calcula total de receitas
                $query = 'SELECT SUM(amount) as total
                    FROM transactions 
                    WHERE user_id = :user_id AND type = "income" AND is_effective = 1';
                $params = ['user_id' => $userId];
                
                if ($accountId) {
                    $query .= ' AND account_id = :account_id';
                    $params['account_id'] = $accountId;
                }
                
                $calcStmt = $pdo->prepare($query);
                $calcStmt->execute($params);
                $result = $calcStmt->fetch();
                $newAmount = (float) ($result['total'] ?? 0);
                break;
                
            case 'expense_limit':
            case 'spending_limit':
                // Calcula total de despesas
                $query = 'SELECT SUM(amount) as total
                    FROM transactions 
                    WHERE user_id = :user_id AND type = "expense" AND is_effective = 1';
                $params = ['user_id' => $userId];
                
                if ($accountId) {
                    $query .= ' AND account_id = :account_id';
                    $params['account_id'] = $accountId;
                }
                
                $calcStmt = $pdo->prepare($query);
                $calcStmt->execute($params);
                $result = $calcStmt->fetch();
                $newAmount = (float) ($result['total'] ?? 0);
                break;
                
            case 'balance_target':
                // Calcula saldo da conta ou total
                if ($accountId) {
                    $balanceStmt = $pdo->prepare('SELECT balance FROM accounts WHERE id = :account_id');
                    $balanceStmt->execute(['account_id' => $accountId]);
                    $account = $balanceStmt->fetch();
                    $newAmount = (float) ($account['balance'] ?? 0);
                } else {
                    $totalBalanceStmt = $pdo->prepare('
                        SELECT SUM(balance) as total_balance 
                        FROM accounts 
                        WHERE user_id = :user_id AND is_active = 1
                    ');
                    $totalBalanceStmt->execute(['user_id' => $userId]);
                    $total = $totalBalanceStmt->fetch();
                    $newAmount = (float) ($total['total_balance'] ?? 0);
                }
                break;
        }
        
        // Atualiza a meta
        $targetStmt = $pdo->prepare('SELECT target_amount FROM goals WHERE id = :id');
        $targetStmt->execute(['id' => $goal['id']]);
        $targetData = $targetStmt->fetch();
        $targetAmount = (float) ($targetData['target_amount'] ?? 0);
        
        $updateStmt = $pdo->prepare('UPDATE goals SET current_amount = :current_amount, completed = :completed WHERE id = :id');
        $updateStmt->execute([
            'current_amount' => $newAmount,
            'completed' => ($newAmount >= $targetAmount && $targetAmount > 0) ? 1 : 0,
            'id' => $goal['id'],
        ]);
    }
}

