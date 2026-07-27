<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/credit-cards.php');
}

verifyCsrf($_POST['_token'] ?? '');

$accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
$registerBillAsExpense = isset($_POST['register_bill_as_expense']) && $_POST['register_bill_as_expense'] === '1';
$registerLimitAsIncome = isset($_POST['register_limit_as_income']) && $_POST['register_limit_as_income'] === '1';

if (!$accountId || $accountId <= 0) {
    flash('error', 'Cartão inválido.');
    redirect('/public/credit-cards.php');
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

// Verifica se a conta pertence ao usuário e é um cartão de crédito
$stmt = $pdo->prepare('SELECT id, type, name, credit_limit FROM accounts WHERE id = :id AND user_id = :user_id AND type = "credit"');
$stmt->execute(['id' => $accountId, 'user_id' => $userId]);
$account = $stmt->fetch();

if (!$account) {
    flash('error', 'Cartão de crédito não encontrado.');
    redirect('/public/credit-cards.php');
}

// v3: `credit_card_transaction_settings` is created by the canonical migration
// (migrations/20240101000000_baseline_v3.sql). The defensive runtime CREATE-
// TABLE-IF-NOT-EXIST that lived here is removed so the FK-bearing canonical
// definition is the only one. Deploy requirement: run `php scripts/migrate.php up`.

// Busca configuração anterior para verificar se a opção foi ativada/desativada agora
$stmt = $pdo->prepare('
    SELECT register_bill_as_expense, register_limit_as_income 
    FROM credit_card_transaction_settings 
    WHERE account_id = :account_id AND user_id = :user_id
');
$stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
$previousSettings = $stmt->fetch();

// Define estado anterior: se não existe configuração, usa o padrão do sistema (TRUE para bill, FALSE para limit)
$wasBillAsExpenseEnabled = $previousSettings ? (bool) $previousSettings['register_bill_as_expense'] : true;
$wasLimitAsIncomeEnabled = $previousSettings ? (bool) $previousSettings['register_limit_as_income'] : false;

// Detecta transições (mudanças de estado)
$turnedOnLimitAsIncome = $registerLimitAsIncome && !$wasLimitAsIncomeEnabled;
$turnedOffLimitAsIncome = !$registerLimitAsIncome && $wasLimitAsIncomeEnabled;
$turnedOnBillAsExpense = $registerBillAsExpense && !$wasBillAsExpenseEnabled;
$turnedOffBillAsExpense = !$registerBillAsExpense && $wasBillAsExpenseEnabled;

// Salva ou atualiza as configurações usando REPLACE INTO para garantir que funcione
try {
    // REPLACE INTO deleta o registro existente (se houver) e insere um novo
    // Isso garante que o registro seja sempre atualizado corretamente
    $stmt = $pdo->prepare('
        REPLACE INTO credit_card_transaction_settings 
        (account_id, user_id, register_bill_as_expense, register_limit_as_income, created_at, updated_at)
        VALUES (:account_id, :user_id, :register_bill_as_expense, :register_limit_as_income, NOW(), NOW())
    ');
    $result = $stmt->execute([
        'account_id' => $accountId,
        'user_id' => $userId,
        'register_bill_as_expense' => $registerBillAsExpense ? 1 : 0,
        'register_limit_as_income' => $registerLimitAsIncome ? 1 : 0,
    ]);
    
    if (!$result) {
        error_log('Erro ao salvar configurações de cartão: ' . print_r($stmt->errorInfo(), true));
        flash('error', 'Erro ao salvar configurações.');
        redirect('/public/credit-cards.php');
    }
} catch (PDOException $e) {
    error_log('Exceção ao salvar configurações de cartão: ' . $e->getMessage());
    flash('error', 'Erro ao salvar configurações: ' . $e->getMessage());
    redirect('/public/credit-cards.php');
}

// Ajusta transações auto-criadas de acordo com o estado atual das configurações
// (não apenas quando há transição, para garantir consistência)
try {
    $transactionRepository = new TransactionRepository($pdo);
    
    // Verifica se a coluna bill_id existe
    $hasBillIdColumn = false;
    try {
        $checkStmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'bill_id'");
        $hasBillIdColumn = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        $hasBillIdColumn = false;
    }

    // DESPESAS DE FATURA: ajusta de acordo com o estado atual
    if ($hasBillIdColumn) {
        // Busca TODAS as transações de despesa vinculadas a faturas deste cartão
        $stmt = $pdo->prepare('
            SELECT id, amount, is_effective, bill_id FROM transactions
            WHERE user_id = :user_id
              AND account_id = :account_id
              AND type = "expense"
              AND bill_id IS NOT NULL
        ');
        $stmt->execute([
            'user_id' => $userId,
            'account_id' => $accountId,
        ]);
        $billTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($billTransactions)) {
            // Verifica o tipo da conta
            $stmt = $pdo->prepare('SELECT type FROM accounts WHERE id = :account_id AND user_id = :user_id');
            $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
            $accountType = $stmt->fetchColumn();
            
            if ($accountType === 'credit') {
                // Se a opção está ATIVADA: garante que todas as transações estejam efetivas
                if ($registerBillAsExpense) {
                    $totalToActivate = 0;
                    $billIdsToUpdate = [];
                    
                    foreach ($billTransactions as $transaction) {
                        $transactionId = (int) $transaction['id'];
                        $amount = (float) ($transaction['amount'] ?? 0);
                        $isEffective = (bool) ($transaction['is_effective'] ?? false);
                        $billId = $transaction['bill_id'] ? (int) $transaction['bill_id'] : null;
                        
                        // Se não está efetiva, ativa e soma para ajustar saldo
                        if (!$isEffective && $amount > 0) {
                            $totalToActivate += $amount;
                            $updateStmt = $pdo->prepare('UPDATE transactions SET is_effective = 1 WHERE id = :id AND user_id = :user_id');
                            $updateStmt->execute([
                                'id' => $transactionId,
                                'user_id' => $userId,
                            ]);
                        }
                        if ($billId) {
                            $billIdsToUpdate[$billId] = true;
                        }
                    }
                    
                    // Ajusta o saldo (adiciona as despesas de volta)
                    if ($totalToActivate > 0) {
                        $stmt = $pdo->prepare('UPDATE accounts SET balance = balance - :amount WHERE id = :account_id AND user_id = :user_id');
                        $stmt->execute([
                            'amount' => $totalToActivate,
                            'account_id' => $accountId,
                            'user_id' => $userId,
                        ]);
                    }
                    
                    // Atualiza totais das faturas
                    if (!empty($billIdsToUpdate)) {
                        $billRepository = new \Novix\Repositories\CreditCardBillRepository($pdo);
                        foreach (array_keys($billIdsToUpdate) as $billId) {
                            try {
                                $billRepository->updateBillTotal($billId);
                            } catch (Exception $e) {
                                error_log('Erro ao atualizar total da fatura: ' . $e->getMessage());
                            }
                        }
                    }
                }
                // Se a opção está DESATIVADA: garante que todas as transações estejam inativas
                else {
                    $totalToDeactivate = 0;
                    $billIdsToUpdate = [];
                    
                    foreach ($billTransactions as $transaction) {
                        $transactionId = (int) $transaction['id'];
                        $amount = (float) ($transaction['amount'] ?? 0);
                        $isEffective = (bool) ($transaction['is_effective'] ?? false);
                        $billId = $transaction['bill_id'] ? (int) $transaction['bill_id'] : null;
                        
                        // Se está efetiva, desativa e soma para ajustar saldo
                        if ($isEffective && $amount > 0) {
                            $totalToDeactivate += $amount;
                            $updateStmt = $pdo->prepare('UPDATE transactions SET is_effective = 0 WHERE id = :id AND user_id = :user_id');
                            $updateStmt->execute([
                                'id' => $transactionId,
                                'user_id' => $userId,
                            ]);
                        }
                        if ($billId) {
                            $billIdsToUpdate[$billId] = true;
                        }
                    }
                    
                    // Reverte o efeito no saldo (remove as despesas = adiciona ao saldo)
                    if ($totalToDeactivate > 0) {
                        $stmt = $pdo->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :account_id AND user_id = :user_id');
                        $stmt->execute([
                            'amount' => $totalToDeactivate,
                            'account_id' => $accountId,
                            'user_id' => $userId,
                        ]);
                    }
                    
                    // Atualiza totais das faturas
                    if (!empty($billIdsToUpdate)) {
                        $billRepository = new \Novix\Repositories\CreditCardBillRepository($pdo);
                        foreach (array_keys($billIdsToUpdate) as $billId) {
                            try {
                                $billRepository->updateBillTotal($billId);
                            } catch (Exception $e) {
                                error_log('Erro ao atualizar total da fatura: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    // RECEITAS DE LIMITE: Remove/cria de acordo com transições
    if ($turnedOffLimitAsIncome && (float) ($account['credit_limit'] ?? 0) > 0) {
        // Preferência: remove exatamente a descrição padrão; fallback: prefixo + valor do limite (caso o nome do cartão tenha mudado)
        $stmt = $pdo->prepare('
            SELECT id FROM transactions
            WHERE user_id = :user_id
              AND account_id = :account_id
              AND type = "income"
              AND amount = :amount
              AND (description = :desc_exact OR description LIKE :desc_prefix)
        ');
        $stmt->execute([
            'user_id' => $userId,
            'account_id' => $accountId,
            'amount' => (float) $account['credit_limit'],
            'desc_exact' => 'Limite de crédito - ' . (string) ($account['name'] ?? ''),
            'desc_prefix' => 'Limite de crédito - %',
        ]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($ids as $id) {
            $transactionRepository->delete((int) $id, $userId);
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao ajustar transações automáticas: ' . $e->getMessage());
}

// Se a opção foi ativada agora e o cartão tem limite, registra o limite como receita
if ($turnedOnLimitAsIncome && (float) ($account['credit_limit'] ?? 0) > 0) {
    // Verifica se user_id existe na tabela categories
    $hasUserIdColumn = false;
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
        $hasUserIdColumn = $checkColumn->rowCount() > 0;
    } catch (PDOException $e) {
        // Ignora erro
    }
    
    // Busca categoria de receita
    $incomeCategory = null;
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = :user_id AND type = "income" AND (name LIKE "%Cartão%" OR name LIKE "%Crédito%" OR name LIKE "%Limite%") LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $incomeCategory = $stmt->fetch();
        
        if (!$incomeCategory) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = :user_id AND type = "income" LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $incomeCategory = $stmt->fetch();
        }
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE type = "income" AND (name LIKE "%Cartão%" OR name LIKE "%Crédito%" OR name LIKE "%Limite%") LIMIT 1');
        $stmt->execute();
        $incomeCategory = $stmt->fetch();
        
        if (!$incomeCategory) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE type = "income" LIMIT 1');
            $stmt->execute();
            $incomeCategory = $stmt->fetch();
        }
    }
    
    if ($incomeCategory) {
        // Verifica se já existe uma transação de receita para este limite (evita duplicatas)
        $stmt = $pdo->prepare('
            SELECT id FROM transactions 
            WHERE user_id = :user_id 
            AND account_id = :account_id 
            AND category_id = :category_id 
            AND type = "income" 
            AND description LIKE :description 
            AND amount = :amount
            LIMIT 1
        ');
        $descriptionPattern = 'Limite de crédito - ' . $account['name'] . '%';
        $stmt->execute([
            'user_id' => $userId,
            'account_id' => $accountId,
            'category_id' => (int) $incomeCategory['id'],
            'description' => $descriptionPattern,
            'amount' => (float) $account['credit_limit']
        ]);
        $existingTransaction = $stmt->fetch();
        
        // Se não existe, cria a transação
        if (!$existingTransaction) {
            try {
                $transactionRepository = new TransactionRepository($pdo);
                $description = 'Limite de crédito - ' . $account['name'];
                
                $transactionRepository->create(
                    $userId,
                    $accountId,
                    (int) $incomeCategory['id'],
                    'income',
                    (float) $account['credit_limit'],
                    $description,
                    new \DateTimeImmutable(),
                    'BRL',
                    true, // isEffective
                    false, // isRecurring
                    null, // billId
                    null, // effectiveDate
                    null, // recurrenceType
                    null, // recurrenceDay
                    null  // recurrenceEndDate
                );
            } catch (Exception $e) {
                error_log('Erro ao criar transação de receita do limite: ' . $e->getMessage());
                // Não interrompe o fluxo, apenas loga o erro
            }
        }
    }
}

flash('success', 'Configurações salvas com sucesso!');
redirect('/public/credit-cards.php');

