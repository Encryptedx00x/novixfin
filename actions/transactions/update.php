<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/errors.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/goals.php';
requireAuth();

use DateTimeImmutable;
use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;
use Novix\Repositories\CreditCardBillRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('history.php');
}

verifyCsrf($_POST['_token'] ?? '');

$transactionId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
$type = $_POST['type'] ?? '';
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$currency = $_POST['currency'] ?? 'BRL';
$description = trim((string) ($_POST['description'] ?? ''));
$transactionDate = $_POST['transaction_date'] ?? '';
$isEffective = isset($_POST['is_effective']) && $_POST['is_effective'] === '1';
$isRecurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] === '1';
$effectiveDate = $_POST['effective_date'] ?? '';
$recurrenceType = $_POST['recurrence_type'] ?? 'none';
$recurrenceDay = isset($_POST['recurrence_day']) && $_POST['recurrence_day'] !== '' ? filter_input(INPUT_POST, 'recurrence_day', FILTER_VALIDATE_INT) : null;
$recurrenceEndDate = $_POST['recurrence_end_date'] ?? null;
$recurrenceLimitMode = $_POST['recurrence_limit_mode'] ?? (($recurrenceEndDate ?? '') !== '' ? 'date' : 'never');
$recurrenceTotalOccurrences = isset($_POST['recurrence_total_occurrences']) && $_POST['recurrence_total_occurrences'] !== ''
    ? filter_input(INPUT_POST, 'recurrence_total_occurrences', FILTER_VALIDATE_INT)
    : null;

// Converte data brasileira DD/MM/YYYY para YYYY-MM-DD se necessário
if ($transactionDate && strpos($transactionDate, '/') !== false) {
    $parts = explode('/', $transactionDate);
    if (count($parts) === 3) {
        $transactionDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
}

$redirectTo = $_POST['redirect_to'] ?? 'history.php';

if (!$transactionId || $transactionId <= 0) {
    flash('error', 'Transação inválida.');
    redirect($redirectTo);
}

if (!$accountId || $accountId <= 0) {
    flash('error', 'Selecione uma conta/banco válida.');
    redirect($redirectTo);
}

if (!in_array($type, ['income', 'expense'], true)) {
    flash('error', 'Tipo de transação inválido.');
    redirect($redirectTo);
}

if (!$categoryId || $categoryId <= 0) {
    flash('error', 'Selecione uma categoria válida.');
    redirect($redirectTo);
}

if ($amount === false || $amount <= 0) {
    flash('error', 'Informe um valor válido.');
    redirect($redirectTo);
}

if (!in_array($currency, ['BRL', 'USD', 'EUR'], true)) {
    flash('error', 'Moeda inválida.');
    redirect($redirectTo);
}

try {
    $date = new DateTimeImmutable($transactionDate);
} catch (Exception $e) {
    flash('error', 'Data inválida.');
    redirect($redirectTo);
}

if ($isRecurring) {
    if (!in_array($recurrenceType, ['daily', 'weekly', 'monthly', 'yearly', 'same_day'], true)) {
        flash('error', 'Tipo de recorrência inválido.');
        redirect($redirectTo);
    }

    if ($recurrenceType === 'weekly' && ($recurrenceDay === null || $recurrenceDay < 0 || $recurrenceDay > 6)) {
        flash('error', 'Selecione um dia da semana para recorrência semanal.');
        redirect($redirectTo);
    }

    if ($recurrenceType === 'same_day') {
        $recurrenceDay = (int) $date->format('d');
    }
} else {
    $recurrenceType = 'none';
    $recurrenceDay = null;
    $recurrenceEndDate = null;
}

if ($isRecurring) {
    if (!in_array($recurrenceLimitMode, ['never', 'date', 'count'], true)) {
        flash('error', 'Forma de finalizar recorrência inválida.');
        redirect($redirectTo);
    }

    if ($recurrenceLimitMode === 'never') {
        $recurrenceEndDate = null;
        $recurrenceTotalOccurrences = null;
    } elseif ($recurrenceLimitMode === 'date') {
        $recurrenceTotalOccurrences = null;
        if (!$recurrenceEndDate) {
            flash('error', 'Informe a data final da recorrência.');
            redirect($redirectTo);
        }
    } elseif ($recurrenceLimitMode === 'count') {
        $recurrenceEndDate = null;
        if ($recurrenceTotalOccurrences === false || $recurrenceTotalOccurrences === null || $recurrenceTotalOccurrences < 2) {
            flash('error', 'Informe uma quantidade de parcelas maior ou igual a 2.');
            redirect($redirectTo);
        }
    }
} else {
    $recurrenceTotalOccurrences = null;
}

$recurrenceEndDateObj = null;
if ($recurrenceEndDate) {
    try {
        $recurrenceEndDateObj = new DateTimeImmutable($recurrenceEndDate);
        if ($recurrenceEndDateObj < $date) {
            flash('error', 'Data final da recorrência não pode ser anterior à data da transação.');
            redirect($redirectTo);
        }
    } catch (Exception $e) {
        flash('error', 'Data final de recorrência inválida.');
        redirect($redirectTo);
    }
}

// Processa data de efetivação (opcional)
$effectiveDateObj = null;
if (!empty($effectiveDate) && !$isEffective) {
    try {
        $effectiveDateObj = new DateTimeImmutable($effectiveDate);
    } catch (Exception $e) {
        // Ignora se inválida
    }
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

if ($userId === 0) {
    flash('error', 'Usuário não autenticado.');
    redirect($redirectTo);
}

// v3 Batch D: validate that the chosen category exists, belongs to this user
// (when categories.user_id is in scope), AND matches the transaction type.
// Parity with actions/transactions/create.php:60-90 which already enforced
// this on insert — previously update allowed editing a transaction into a
// category whose `type` differed from the transaction's `type`, producing
// inconsistent reports/totals-by-category.
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // probe; harmless fallback to no-user_id scope
}
if ($hasUserIdColumn) {
    $stmt = $pdo->prepare('SELECT type FROM categories WHERE id = :category_id AND (user_id = :user_id OR user_id IS NULL)');
    $stmt->execute(['category_id' => $categoryId, 'user_id' => $userId]);
} else {
    $stmt = $pdo->prepare('SELECT type FROM categories WHERE id = :category_id');
    $stmt->execute(['category_id' => $categoryId]);
}
$category = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$category || !isset($category['type'])) {
    flash('error', 'Categoria não encontrada.');
    redirect($redirectTo);
}
if ($category['type'] !== $type) {
    flash('error', 'A categoria selecionada não corresponde ao tipo de transação (receita/despesa).');
    redirect($redirectTo);
}

// Validate account ownership (also not in the pre-v3 update.php).
$stmt = $pdo->prepare('SELECT id FROM accounts WHERE id = :account_id AND user_id = :user_id');
$stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
if (!$stmt->fetch()) {
    flash('error', 'Conta inválida ou não pertence ao usuário.');
    redirect($redirectTo);
}

$transactionRepository = new TransactionRepository($pdo);

// Busca transação atual
$stmt = $pdo->prepare('SELECT account_id, type, bill_id FROM transactions WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $transactionId, 'user_id' => $userId]);
$oldTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
$oldBillId = $oldTransaction ? (int) ($oldTransaction['bill_id'] ?? 0) : 0;

$preserveInstallmentFields = false;
try {
    $stmt = $pdo->prepare('SELECT parent_transaction_id FROM transactions WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['id' => $transactionId, 'user_id' => $userId]);
    $existingParentTransactionId = $stmt->fetchColumn();
    $preserveInstallmentFields = $existingParentTransactionId !== false && $existingParentTransactionId !== null;
} catch (PDOException $e) {
    $preserveInstallmentFields = false;
}

if ($isRecurring && $recurrenceTotalOccurrences !== null) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM recurring_generated WHERE parent_transaction_id = :parent_transaction_id');
    $stmt->execute(['parent_transaction_id' => $transactionId]);
    $consumedOccurrences = 1 + (int) $stmt->fetchColumn();

    if ($recurrenceTotalOccurrences < $consumedOccurrences) {
        flash('error', 'A quantidade de parcelas não pode ser menor que as parcelas já geradas.');
        redirect($redirectTo);
    }
}

// Atualiza a transação
$transactionRepository->update(
    $transactionId,
    $userId,
    $accountId,
    $categoryId,
    $type,
    $amount,
    $description,
    $date,
    $currency,
    $isEffective,
    $isRecurring,
    $effectiveDateObj,
    $recurrenceType,
    $recurrenceDay,
    $recurrenceEndDateObj,
    $isRecurring && $recurrenceTotalOccurrences !== null ? 1 : null,
    $recurrenceTotalOccurrences,
    !$preserveInstallmentFields
);

// Atualiza fatura se for cartão de crédito
if ($type === 'expense') {
    $stmt = $pdo->prepare('SELECT type FROM accounts WHERE id = :account_id AND user_id = :user_id');
    $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account && $account['type'] === 'credit') {
        $billRepository = new CreditCardBillRepository($pdo);
        
        // Usa data de efetivação se não estiver efetivada e tiver uma data definida
        $dateForBilling = $date;
        if (!$isEffective && $effectiveDateObj !== null) {
            // Verifica se a data de efetivação é posterior ao vencimento da fatura atual
            $stmt = $pdo->prepare('
                SELECT * FROM credit_card_bills 
                WHERE account_id = :account_id AND user_id = :user_id AND status IN ("open", "overdue")
                ORDER BY due_date ASC LIMIT 1
            ');
            $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
            $currentBill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentBill && isset($currentBill['due_date'])) {
                $currentDueDate = new DateTimeImmutable($currentBill['due_date']);
                if ($effectiveDateObj > $currentDueDate && isset($currentBill['billing_month'])) {
                    // Vai para próxima fatura
                    $currentBillingDate = new DateTimeImmutable($currentBill['billing_month'] . '-01');
                    $billingMonth = $currentBillingDate->modify('+1 month')->format('Y-m');
                    $dateForBilling = null; // Já temos o billingMonth
                } else {
                    $dateForBilling = $date;
                }
            } else {
                $dateForBilling = $effectiveDateObj;
            }
        }
        
        if (!isset($billingMonth)) {
            $billingMonth = $dateForBilling->format('Y-m');
        }
        
        try {
            $bill = $billRepository->findOrCreateBill($accountId, $userId, $billingMonth);
            $newBillId = (int) $bill['id'];
            
            if ($oldBillId !== $newBillId) {
                $stmt = $pdo->prepare('UPDATE transactions SET bill_id = :bill_id WHERE id = :id');
                $stmt->execute(['bill_id' => $newBillId, 'id' => $transactionId]);
                
                if ($oldBillId > 0) {
                    $billRepository->updateBillTotal($oldBillId);
                }
            }
            
            $billRepository->updateBillTotal($newBillId);
        } catch (Exception $e) {
            // Ignora erros de fatura
        }
    }
} elseif ($oldBillId > 0) {
    // Se não é mais despesa mas estava vinculada a uma fatura, remove o vínculo
    $stmt = $pdo->prepare('UPDATE transactions SET bill_id = NULL WHERE id = :id');
    $stmt->execute(['id' => $transactionId]);
    
    $billRepository = new CreditCardBillRepository($pdo);
    $billRepository->updateBillTotal($oldBillId);
}

// Recalcula metas
try {
    recalculateAllGoals($userId);
} catch (Exception $e) {
    // Ignora erros de metas
}

flash('success', 'Transação atualizada com sucesso.');
redirect($redirectTo);
