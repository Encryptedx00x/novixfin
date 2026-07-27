<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';
    require_once __DIR__ . '/../../includes/csrf.php';
    require_once __DIR__ . '/../../includes/goals.php';
    requireAuth();
} catch (Throwable $e) {
    error_log('Bootstrap error in create.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    die('Erro ao carregar aplicação. Verifique os logs do servidor.');
}

use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;
use Novix\Repositories\CreditCardBillRepository;
use Novix\Services\GamificationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

verifyCsrf($_POST['_token'] ?? '');

$accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
$type = $_POST['type'] ?? '';
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$currency = $_POST['currency'] ?? 'BRL';
$description = trim((string) ($_POST['description'] ?? ''));
$transactionDate = $_POST['transaction_date'] ?? '';
$isEffective = isset($_POST['is_effective']) && $_POST['is_effective'] === '1';
$isRecurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] === '1';
$effectiveDate = $_POST['effective_date'] ?? null;
$recurrenceType = $_POST['recurrence_type'] ?? 'none';
$recurrenceDay = isset($_POST['recurrence_day']) && $_POST['recurrence_day'] !== '' ? filter_input(INPUT_POST, 'recurrence_day', FILTER_VALIDATE_INT) : null;
$recurrenceEndDate = $_POST['recurrence_end_date'] ?? null;
$recurrenceLimitMode = $_POST['recurrence_limit_mode'] ?? (($recurrenceEndDate ?? '') !== '' ? 'date' : 'never');
$recurrenceTotalOccurrences = isset($_POST['recurrence_total_occurrences']) && $_POST['recurrence_total_occurrences'] !== ''
    ? filter_input(INPUT_POST, 'recurrence_total_occurrences', FILTER_VALIDATE_INT)
    : null;

if (!$accountId || $accountId <= 0) {
    flash('error', 'Selecione uma conta/banco válida.');
    redirect('dashboard.php');
}

if (!in_array($type, ['income', 'expense'], true)) {
    flash('error', 'Tipo de transação inválido.');
    redirect('dashboard.php');
}

if (!$categoryId || $categoryId <= 0) {
    flash('error', 'Selecione uma categoria válida.');
    redirect('dashboard.php');
}

// Valida se a categoria corresponde ao tipo de transação e pertence ao usuário
$pdo = Connection::get();
$userId = currentUserId() ?? 0;

// Verifica se user_id existe na tabela
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Ignora erro
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
    redirect('dashboard.php');
}

if ($category['type'] !== $type) {
    flash('error', 'A categoria selecionada não corresponde ao tipo de transação (receita/despesa).');
    redirect('dashboard.php');
}

if ($amount === false || $amount <= 0) {
    flash('error', 'Informe um valor válido.');
    redirect('dashboard.php');
}

if (!in_array($currency, ['BRL', 'USD', 'EUR'], true)) {
    flash('error', 'Moeda inválida.');
    redirect('dashboard.php');
}

try {
    $date = new \DateTimeImmutable($transactionDate);
} catch (Exception $e) {
    flash('error', 'Data inválida.');
    redirect('dashboard.php');
}

// Valida recorrência
if ($isRecurring) {
    if (!in_array($recurrenceType, ['daily', 'weekly', 'monthly', 'yearly', 'same_day'], true)) {
        flash('error', 'Tipo de recorrência inválido.');
        redirect('dashboard.php');
    }
    
    if ($recurrenceType === 'weekly' && ($recurrenceDay === null || $recurrenceDay < 0 || $recurrenceDay > 6)) {
        flash('error', 'Selecione um dia da semana para recorrência semanal.');
        redirect('dashboard.php');
    }
    
    // Se for same_day, extrai o dia do mês da data da transação
    if ($recurrenceType === 'same_day') {
        $recurrenceDay = (int) $date->format('d');
    }
}

if ($isRecurring) {
    if (!in_array($recurrenceLimitMode, ['never', 'date', 'count'], true)) {
        flash('error', 'Forma de finalizar recorrência inválida.');
        redirect('dashboard.php');
    }

    if ($recurrenceLimitMode === 'never') {
        $recurrenceEndDate = null;
        $recurrenceTotalOccurrences = null;
    } elseif ($recurrenceLimitMode === 'date') {
        $recurrenceTotalOccurrences = null;
        if (!$recurrenceEndDate) {
            flash('error', 'Informe a data final da recorrência.');
            redirect('dashboard.php');
        }
    } elseif ($recurrenceLimitMode === 'count') {
        $recurrenceEndDate = null;
        if ($recurrenceTotalOccurrences === false || $recurrenceTotalOccurrences === null || $recurrenceTotalOccurrences < 2) {
            flash('error', 'Informe uma quantidade de parcelas maior ou igual a 2.');
            redirect('dashboard.php');
        }
    }
} else {
    $recurrenceType = 'none';
    $recurrenceDay = null;
    $recurrenceEndDate = null;
    $recurrenceTotalOccurrences = null;
}

// Valida data final de recorrência
$recurrenceEndDateObj = null;
if ($recurrenceEndDate) {
    try {
        $recurrenceEndDateObj = new \DateTimeImmutable($recurrenceEndDate);
        if ($recurrenceEndDateObj < $date) {
            flash('error', 'Data final da recorrência não pode ser anterior à data da transação.');
            redirect('dashboard.php');
        }
    } catch (Exception $e) {
        flash('error', 'Data final de recorrência inválida.');
        redirect('dashboard.php');
    }
}

$userId = currentUserId() ?? 0;
$transactionRepository = new TransactionRepository($pdo);

// Valida data de efetivação se fornecida
$effectiveDateObj = null;
if ($effectiveDate && !$isEffective) {
    try {
        $effectiveDateObj = new \DateTimeImmutable($effectiveDate);
        if ($effectiveDateObj < $date) {
            flash('error', 'Data de efetivação não pode ser anterior à data da transação.');
            redirect('dashboard.php');
        }
    } catch (Exception $e) {
        flash('error', 'Data de efetivação inválida.');
        redirect('dashboard.php');
    }
} elseif ($isEffective) {
    // Se estiver marcando como efetivada, usa a data/hora atual
    $effectiveDateObj = new \DateTimeImmutable();
}

// Verifica se é uma despesa em cartão de crédito
$billId = null;
$billRepository = null;
if ($type === 'expense') {
    $stmt = $pdo->prepare('SELECT type, credit_limit FROM accounts WHERE id = :account_id AND user_id = :user_id');
    $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account && $account['type'] === 'credit') {
        // Valida limite do cartão de crédito
        if ($account['credit_limit']) {
            $creditLimit = (float) $account['credit_limit'];
            
            // Calcula gasto atual (despesas efetivadas e não efetivadas)
            $stmt = $pdo->prepare('
                SELECT COALESCE(SUM(amount), 0) as total_spent
                FROM transactions
                WHERE account_id = :account_id
                AND user_id = :user_id
                AND type = "expense"
                AND is_effective = 1
            ');
            $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
            $spent = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalSpent = (float) ($spent['total_spent'] ?? 0);
            
            // Se a transação será efetivada, considera no total
            if ($isEffective || ($effectiveDateObj && $effectiveDateObj <= new \DateTimeImmutable())) {
                $totalSpent += $amount;
            }
            
            if ($totalSpent > $creditLimit) {
                $remaining = $creditLimit - ($totalSpent - $amount);
                flash('error', 'Limite do cartão excedido. Limite disponível: ' . formatCurrency($remaining, $currency));
                redirect('dashboard.php');
            }
        }
        
        // Busca ou cria a fatura do mês correspondente
        // Se tiver data de efetivação e não estiver efetivada, verifica qual fatura usar
        $billRepository = new CreditCardBillRepository($pdo);
        $dateForBilling = $date;
        
        // Se não estiver efetivada e tiver data de efetivação, verifica a fatura
        if (!$isEffective && $effectiveDateObj) {
            // Busca faturas abertas do cartão para verificar qual usar
            try {
                $stmt = $pdo->prepare('
                    SELECT * FROM credit_card_bills 
                    WHERE account_id = :account_id 
                    AND user_id = :user_id 
                    AND status IN ("open", "overdue")
                    ORDER BY due_date ASC
                    LIMIT 1
                ');
                $stmt->execute(['account_id' => $accountId, 'user_id' => $userId]);
                $currentBill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($currentBill && isset($currentBill['due_date'])) {
                    $currentDueDate = new \DateTimeImmutable($currentBill['due_date']);
                    
                    // Se a data de efetivação for posterior ao vencimento da fatura atual, vai para a próxima fatura
                    if ($effectiveDateObj > $currentDueDate) {
                        // Calcula o billing_month da fatura seguinte baseado na data de vencimento da atual
                        // O billing_month é o mês da data de fechamento, não da data de vencimento
                        // Então se a fatura atual fecha em jan/2026 e vence em fev/2026,
                        // a próxima fecha em fev/2026 e vence em mar/2026
                        $currentBillingMonth = $currentBill['billing_month']; // formato: YYYY-MM
                        $currentBillingDate = new \DateTimeImmutable($currentBillingMonth . '-01');
                        $nextBillingMonth = $currentBillingDate->modify('+1 month')->format('Y-m');
                        
                        // Usa o billing_month calculado para buscar/criar a fatura
                        try {
                            $bill = $billRepository->findOrCreateBill($accountId, $userId, $nextBillingMonth);
                            $billId = (int) $bill['id'];
                        } catch (Exception $e) {
                            error_log('Erro ao criar fatura futura: ' . $e->getMessage());
                            // Fallback: usa a data de efetivação
                            $dateForBilling = $effectiveDateObj;
                        }
                    } else {
                        // Se a data de efetivação for antes ou igual ao vencimento, usa a fatura atual
                        $bill = $currentBill;
                        $billId = (int) $bill['id'];
                    }
                } else {
                    // Se não houver fatura aberta, usa a data de efetivação
                    $dateForBilling = $effectiveDateObj;
                }
            } catch (Exception $e) {
                // Em caso de erro, usa a data de efetivação
                error_log('Erro ao buscar fatura atual: ' . $e->getMessage());
                $dateForBilling = $effectiveDateObj;
            }
        }
        
        // Se ainda não determinou a fatura, busca ou cria baseado na data
        if ($billId === null) {
            $billingMonth = $dateForBilling->format('Y-m');
            try {
                $bill = $billRepository->findOrCreateBill($accountId, $userId, $billingMonth);
                $billId = (int) $bill['id'];
            } catch (Exception $e) {
                error_log('Erro ao criar/vincular fatura: ' . $e->getMessage());
            }
        }
    }
}

try {
    // Cria a transação vinculando à fatura se for cartão de crédito
    $transactionId = $transactionRepository->create(
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
        $billId,
        $effectiveDateObj,
        $isRecurring ? $recurrenceType : 'none',
        $recurrenceDay,
        $recurrenceEndDateObj,
        null,
        $isRecurring && $recurrenceTotalOccurrences !== null ? 1 : null,
        $recurrenceTotalOccurrences
    );

    // Atualiza o total da fatura se for cartão de crédito
    if ($billId && $billRepository) {
        $billRepository->updateBillTotal($billId);
    }

    // Atualiza metas automaticamente
    if ($isEffective) {
        updateGoalsFromTransaction($userId, $accountId, $type, $amount, $isEffective);
    }

    // v3 Phase 6: award gamification points for the new transaction.
    // Inside the outer try so a failure also rolls back any partial state up
    // the stack; the award itself is non-fatal (logged-only) per the service contract.
    try {
        (new GamificationService($pdo))->award($userId, $isEffective ? 'tx_created_effective' : 'tx_created_pending');
    } catch (Throwable $ge) {
        error_log('Gamification award (tx create) failed: ' . $ge->getMessage());
    }

    flash('success', 'Transação adicionada com sucesso.');
    redirect('dashboard.php');
} catch (Throwable $e) {
    error_log('Erro ao criar transação: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    flash('error', 'Erro ao criar transação: ' . $e->getMessage());
    redirect('dashboard.php');
}

