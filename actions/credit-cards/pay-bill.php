<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use DateTimeImmutable;
use Novix\Database\Connection;
use Novix\Repositories\CreditCardBillRepository;
use Novix\Repositories\TransactionRepository;
use Novix\Services\GamificationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/credit-cards.php');
}

verifyCsrf($_POST['_token'] ?? '');

$billId = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);
$paidAmount = filter_input(INPUT_POST, 'paid_amount', FILTER_VALIDATE_FLOAT);
$paidAt = $_POST['paid_at'] ?? '';
$debitAccounts = isset($_POST['debit_accounts']) && $_POST['debit_accounts'] === '1';
$accountDebits = $_POST['account_debits'] ?? [];

if (!$billId || $billId <= 0) {
    flash('error', 'Fatura inválida.');
    redirect('/public/credit-cards.php');
}

if ($paidAmount === false || $paidAmount <= 0) {
    flash('error', 'Valor pago inválido.');
    redirect('/public/credit-cards.php');
}

try {
    $paidAtDate = new DateTimeImmutable($paidAt);
} catch (Exception $e) {
    flash('error', 'Data inválida.');
    redirect('/public/credit-cards.php');
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;
$billRepository = new CreditCardBillRepository($pdo);
$transactionRepository = new TransactionRepository($pdo);

// Verifica se a fatura pertence ao usuário
$stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $billId, 'user_id' => $userId]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Fatura não encontrada.');
    redirect('/public/credit-cards.php');
}

// Busca informações do cartão (incluindo limite)
$stmt = $pdo->prepare('SELECT name, credit_limit FROM accounts WHERE id = :account_id AND user_id = :user_id');
$stmt->execute(['account_id' => $bill['account_id'], 'user_id' => $userId]);
$card = $stmt->fetch();
$cardName = $card['name'] ?? 'Cartão de Crédito';
$creditLimit = (float) ($card['credit_limit'] ?? 0);

// Busca o total atualizado (com juros)
$totalAmount = (float) ($bill['total_amount'] ?? 0);
$interestAmount = (float) ($bill['interest_amount'] ?? 0);
$fineAmount = (float) ($bill['fine_amount'] ?? 0);
$currentPaidAmount = (float) ($bill['paid_amount'] ?? 0);

$totalWithInterest = $totalAmount + $interestAmount + $fineAmount;
$newPaidAmount = $currentPaidAmount + $paidAmount;

// Não permite pagar mais do que o devido
if ($newPaidAmount > $totalWithInterest) {
    flash('error', 'O valor pago não pode ser maior que o total da fatura.');
    redirect('/public/credit-cards.php');
}

// v3: `credit_card_transaction_settings` is created by the canonical migration
// (migrations/20240101000000_baseline_v3.sql). The defensive runtime CREATE-
// TABLE-IF-NOT-EXIST that lived here is removed so the FK-bearing canonical
// definition is the only one. Deploy requirement: run `php scripts/migrate.php up`.

// Busca configurações salvas do cartão
$stmt = $pdo->prepare('
    SELECT register_bill_as_expense, register_limit_as_income 
    FROM credit_card_transaction_settings 
    WHERE account_id = :account_id AND user_id = :user_id
');
$stmt->execute(['account_id' => $bill['account_id'], 'user_id' => $userId]);
$settings = $stmt->fetch();

// Opções de registro de transações (busca das configurações salvas)
// Padrão: register_bill_as_expense = true, register_limit_as_income = false
$registerBillAsExpense = $settings ? (bool) $settings['register_bill_as_expense'] : true;
$registerLimitAsIncome = $settings ? (bool) $settings['register_limit_as_income'] : false;

// Verifica se user_id existe na tabela categories
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Ignora erro
}

// Busca categorias
$expenseCategory = null;
$incomeCategory = null;

if ($registerBillAsExpense) {
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = :user_id AND type = "expense" AND (name LIKE "%Cartão%" OR name LIKE "%Fatura%" OR name LIKE "%Crédito%") LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $expenseCategory = $stmt->fetch();
        
        if (!$expenseCategory) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = :user_id AND type = "expense" LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $expenseCategory = $stmt->fetch();
        }
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE type = "expense" AND (name LIKE "%Cartão%" OR name LIKE "%Fatura%" OR name LIKE "%Crédito%") LIMIT 1');
        $stmt->execute();
        $expenseCategory = $stmt->fetch();
        
        if (!$expenseCategory) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE type = "expense" LIMIT 1');
            $stmt->execute();
            $expenseCategory = $stmt->fetch();
        }
    }
    
    if (!$expenseCategory) {
        flash('error', 'Nenhuma categoria de despesa encontrada. Por favor, cadastre uma categoria de despesa.');
        redirect('/public/credit-cards.php');
    }
}

if ($registerLimitAsIncome) {
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
    
    if (!$incomeCategory) {
        flash('error', 'Nenhuma categoria de receita encontrada. Por favor, cadastre uma categoria de receita.');
        redirect('/public/credit-cards.php');
    }
}

// Busca categoria de despesa para débito de contas (sempre despesa)
if ($hasUserIdColumn) {
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = :user_id AND type = "expense" AND (name LIKE "%Cartão%" OR name LIKE "%Fatura%" OR name LIKE "%Crédito%") LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $debitExpenseCategory = $stmt->fetch();
    
    if (!$debitExpenseCategory) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE user_id = :user_id AND type = "expense" LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $debitExpenseCategory = $stmt->fetch();
    }
} else {
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE type = "expense" AND (name LIKE "%Cartão%" OR name LIKE "%Fatura%" OR name LIKE "%Crédito%") LIMIT 1');
    $stmt->execute();
    $debitExpenseCategory = $stmt->fetch();
    
    if (!$debitExpenseCategory) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE type = "expense" LIMIT 1');
        $stmt->execute();
        $debitExpenseCategory = $stmt->fetch();
    }
}

if (!$debitExpenseCategory) {
    flash('error', 'Nenhuma categoria de despesa encontrada. Por favor, cadastre uma categoria de despesa.');
    redirect('/public/credit-cards.php');
}

$debitExpenseCategoryId = (int) $debitExpenseCategory['id'];

// v3 Batch F: wrap the WHOLE payment (auto-tx creations + bill markAsPaid) in
// a single transaction. Any sub-step failure rolls back the bill-paid state
// AND every already-created helper transaction, so balances and bills stay
// consistent. Previously the inner catches silently swallowed errors, which
// could leave a bill marked paid with some helper transactions missing —
// exactly the inconsistency the roadmap flags. Those catches now rethrow so
// the outer atomic rollback can fire.
$paymentTransactionStarted = false;
try {
    $pdo->beginTransaction();
    $paymentTransactionStarted = true;

    // Processa débitos de contas se habilitado
    if ($debitAccounts && !empty($accountDebits) && is_array($accountDebits)) {
        $totalDebited = 0;
        $processedDebits = [];

        foreach ($accountDebits as $debit) {
            if (!is_array($debit)) {
                continue;
            }

            // Extrai account_id e amount do array
            $debitAccountId = isset($debit['account_id']) ? filter_var($debit['account_id'], FILTER_VALIDATE_INT) : 0;
            $debitAmount = isset($debit['amount']) ? filter_var($debit['amount'], FILTER_VALIDATE_FLOAT) : 0;

            if ($debitAccountId <= 0 || $debitAmount <= 0) {
                continue;
            }

            // Verifica se a conta pertence ao usuário e não é cartão de crédito
        $stmt = $pdo->prepare('SELECT id, name FROM accounts WHERE id = :id AND user_id = :user_id AND type != "credit" AND is_active = 1');
        $stmt->execute(['id' => $debitAccountId, 'user_id' => $userId]);
        $account = $stmt->fetch();
        
        if (!$account) {
            continue;
        }
        
        $totalDebited += $debitAmount;
        $processedDebits[] = [
            'account_id' => $debitAccountId,
            'amount' => $debitAmount,
            'account_name' => $account['name']
        ];
    }
    
    // Valida que o total debitado não excede o valor pago
    if ($totalDebited > $paidAmount) {
        flash('error', 'O total debitado das contas (' . number_format($totalDebited, 2, ',', '.') . ') não pode ser maior que o valor pago (' . number_format($paidAmount, 2, ',', '.') . ').');
        redirect('/public/credit-cards.php');
    }
    
    // Se há débitos, ajusta o valor pago para ser igual ao total debitado (evita inconsistências)
    // O usuário pode ter informado um valor maior, mas o que realmente foi debitado é o que importa
    if ($totalDebited > 0 && abs($totalDebited - $paidAmount) > 0.01) {
        // Ajusta o valor pago para refletir o que foi realmente debitado
        $paidAmount = $totalDebited;
        $newPaidAmount = $currentPaidAmount + $paidAmount;
        
        // Revalida após ajuste
        if ($newPaidAmount > $totalWithInterest) {
            flash('error', 'O total debitado das contas (' . number_format($totalDebited, 2, ',', '.') . ') excede o valor restante da fatura.');
            redirect('/public/credit-cards.php');
        }
    }
    
    // Cria transações de despesa para cada conta (sempre despesa para débito)
    foreach ($processedDebits as $debit) {
        $description = 'Pagamento de fatura - ' . $cardName;
        if (count($processedDebits) > 1) {
            $description .= ' (Parte: ' . number_format($debit['amount'], 2, ',', '.') . ')';
        }
        
        try {
            $transactionRepository->create(
                $userId,
                $debit['account_id'],
                $debitExpenseCategoryId,
                'expense',
                $debit['amount'],
                $description,
                $paidAtDate,
                $bill['currency'] ?? 'BRL',
                true, // isEffective
                false, // isRecurring
                null, // billId (não vincula à fatura, pois é o pagamento dela)
                null, // effectiveDate
                null, // recurrenceType
                null, // recurrenceDay
                null  // recurrenceEndDate
            );
        } catch (Exception $e) {
            error_log('Erro ao criar transação de débito: ' . $e->getMessage());
            // v3 Batch F: rethrow so the outer transaction rolls back atomically
            // (the previous "continua processando outras contas mesmo se uma
            // falhar" semantic left inconsistent balances — bill marked paid
            // with some debit transactions missing).
            throw $e;
        }
    }
}

// Registra fatura como despesa (se solicitado) - usa a conta do cartão de crédito
if ($registerBillAsExpense && $expenseCategory) {
    try {
        $expenseCategoryId = (int) $expenseCategory['id'];
        $description = 'Fatura do cartão - ' . $cardName;
        
        $transactionRepository->create(
            $userId,
            (int) $bill['account_id'], // Usa a conta do cartão de crédito
            $expenseCategoryId,
            'expense',
            $totalWithInterest,
            $description,
            $paidAtDate,
            $bill['currency'] ?? 'BRL',
            true, // isEffective
            false, // isRecurring
            $billId, // billId - vincula à fatura
            null, // effectiveDate
            null, // recurrenceType
            null, // recurrenceDay
            null  // recurrenceEndDate
        );
    } catch (Exception $e) {
        error_log('Erro ao criar transação de despesa da fatura: ' . $e->getMessage());
        // v3 Batch F: rethrow so the outer transaction rolls back atomically.
        throw $e;
    }
}

// Registra limite como receita (se solicitado) - usa a conta do cartão de crédito
if ($registerLimitAsIncome && $incomeCategory && (float) ($card['credit_limit'] ?? 0) > 0) {
    try {
        $incomeCategoryId = (int) $incomeCategory['id'];
        $description = 'Limite de crédito - ' . $cardName;
        
        $transactionRepository->create(
            $userId,
            (int) $bill['account_id'], // Usa a conta do cartão de crédito
            $incomeCategoryId,
            'income',
            (float) $card['credit_limit'], // Valor do limite de crédito
            $description,
            $paidAtDate,
            $bill['currency'] ?? 'BRL',
            true, // isEffective
            false, // isRecurring
            null, // billId - não vincula à fatura
            null, // effectiveDate
            null, // recurrenceType
            null, // recurrenceDay
            null  // recurrenceEndDate
        );
    } catch (Exception $e) {
        error_log('Erro ao criar transação de receita do limite: ' . $e->getMessage());
        // v3 Batch F: rethrow so the outer transaction rolls back atomically.
        throw $e;
    }
} elseif ($registerLimitAsIncome && !$incomeCategory) {
    // v3 Batch F: this isn't an exception path — it's a validation miss raised
    // earlier (we already `flash('error')` + redirect when categories are
    // missing). Under the atomic wrap we want to refuse the payment outright.
    throw new RuntimeException('Não foi possível registrar o limite como receita: nenhuma categoria de receita encontrada.');
}

// Registra o pagamento da fatura usando o valor informado pelo usuário (não o total)
// O valor pago é o que o usuário informou, não necessariamente o total da fatura
$billRepository->markAsPaid($billId, $newPaidAmount, $paidAtDate);

    // Everything above succeeded → commit the atomic payment.
    $pdo->commit();
    $paymentTransactionStarted = false;

    // v3 Phase 6: award gamification points for paying a bill. Done after
    // commit so the award only runs on a successfully persisted payment.
    try {
        (new GamificationService($pdo))->award($userId, 'bill_paid');
    } catch (Throwable $ge) {
        error_log('Gamification award (bill paid) failed: ' . $ge->getMessage());
    }
} catch (Throwable $e) {
    if ($paymentTransactionStarted) {
        try { $pdo->rollBack(); } catch (Throwable $rb) { /* already rolled back */ }
        $paymentTransactionStarted = false;
    }
    error_log('pay-bill: atomic payment failed: ' . $e->getMessage());
    flash('error', 'Falha ao registrar o pagamento. Nenhuma alteração foi aplicada — tente novamente. Detalhe técnico: ' . $e->getMessage());
    redirect('/public/credit-cards.php');
}

// Mensagem de sucesso
if ($debitAccounts && !empty($processedDebits)) {
    $accountsList = implode(', ', array_column($processedDebits, 'account_name'));
    flash('success', 'Pagamento registrado com sucesso! Valor debitado de: ' . $accountsList);
} else {
    flash('success', 'Pagamento registrado com sucesso!');
}

redirect('/public/credit-cards.php');

