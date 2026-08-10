<?php

declare(strict_types=1);

/**
 * NovixFin — pagamento de fatura de cartão (Task B5: multi-origem).
 *
 * SEMÂNTICA (nova):
 *   O pagamento tem uma ou mais ORIGENS. Cada origem é
 *   {source_type, source_account_id?, amount}:
 *     - 'account'  → debita a conta informada (cria uma despesa efetivada nela);
 *     - 'external' → pago por fora do app; não debita conta nenhuma.
 *   A fatura é abatida pela SOMA das origens, e cada origem vira uma linha em
 *   `bill_payments` — é isso que permite a reversão por id.
 *
 * O QUE MUDOU E POR QUÊ:
 *   Antes, com `register_bill_as_expense` (ligado por padrão), o pagamento
 *   criava uma despesa do total da fatura NA CONTA DO PRÓPRIO CARTÃO, com o
 *   bill_id da fatura. Isso (a) deixava o saldo do cartão MAIS negativo ao
 *   pagar e (b) voltava a somar no total da fatura, porque `updateBillTotal()`
 *   soma tudo que tem aquele bill_id. Pagar uma fatura não é um gasto novo no
 *   cartão: é a liquidação de uma dívida. Essa despesa deixou de ser criada.
 *   Ver reports/auditoria_cartoes_faturas.md e tests/bill_payment_semantics_test.php.
 *
 * COMPATIBILIDADE: o formato antigo (`debit_accounts` + `account_debits[]`) da
 * tela atual continua aceito e é convertido para origens 'account'. Sem
 * nenhuma origem, o valor informado entra como uma origem 'external' — que é
 * o comportamento de quem só registra "paguei" sem dizer de onde saiu.
 *
 * Atomicidade preservada: tudo dentro de uma transação; qualquer falha
 * desfaz débitos, linhas de origem e o estado pago da fatura.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\CreditCardBillRepository;
use Novix\Repositories\TransactionRepository;
use Novix\Services\GamificationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/credit-cards.php');
}

verifyCsrf($_POST['_token'] ?? '');

$billId = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);
$paidAt = $_POST['paid_at'] ?? '';

if (!$billId || $billId <= 0) {
    flash('error', 'Fatura inválida.');
    redirect('/public/credit-cards.php');
}

try {
    $paidAtDate = new \DateTimeImmutable($paidAt !== '' ? $paidAt : 'today');
} catch (Exception $e) {
    flash('error', 'Data inválida.');
    redirect('/public/credit-cards.php');
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;
$billRepository = new CreditCardBillRepository($pdo);
$transactionRepository = new TransactionRepository($pdo);

// ----------------------------------------------------------- posse da fatura
$stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $billId, 'user_id' => $userId]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Fatura não encontrada.');
    redirect('/public/credit-cards.php');
}

$stmt = $pdo->prepare('SELECT name FROM accounts WHERE id = :account_id AND user_id = :user_id');
$stmt->execute(['account_id' => $bill['account_id'], 'user_id' => $userId]);
$card = $stmt->fetch();
$cardName = $card['name'] ?? 'Cartão de Crédito';

// Total atualizado antes de aceitar o pagamento.
$billRepository->updateBillTotal($billId);
$stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
$stmt->execute(['id' => $billId]);
$bill = $stmt->fetch();

$totalWithInterest = (float) ($bill['total_amount'] ?? 0)
    + (float) ($bill['interest_amount'] ?? 0)
    + (float) ($bill['fine_amount'] ?? 0);
$currentPaidAmount = (float) ($bill['paid_amount'] ?? 0);

// ------------------------------------------------------------------ origens
/**
 * Normaliza as origens vindas do POST.
 *
 * @return array<int, array{type:string, account_id:?int, amount:float}>
 */
function pay_bill_collect_sources(array $post): array
{
    $sources = [];

    // Formato NOVO: sources[][source_type|source_account_id|amount]
    if (isset($post['sources']) && is_array($post['sources'])) {
        foreach ($post['sources'] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $type = ($raw['source_type'] ?? 'account') === 'external' ? 'external' : 'account';
            $amount = isset($raw['amount']) ? (float) filter_var($raw['amount'], FILTER_VALIDATE_FLOAT) : 0.0;
            $accountId = isset($raw['source_account_id'])
                ? (int) filter_var($raw['source_account_id'], FILTER_VALIDATE_INT)
                : 0;

            if ($amount <= 0) {
                continue;
            }
            $sources[] = [
                'type' => $type,
                'account_id' => ($type === 'account' && $accountId > 0) ? $accountId : null,
                'amount' => round($amount, 2),
            ];
        }
    }

    // Formato ANTIGO da tela atual: account_debits[][account_id|amount].
    if ($sources === [] && isset($post['account_debits']) && is_array($post['account_debits'])) {
        foreach ($post['account_debits'] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $amount = isset($raw['amount']) ? (float) filter_var($raw['amount'], FILTER_VALIDATE_FLOAT) : 0.0;
            $accountId = isset($raw['account_id']) ? (int) filter_var($raw['account_id'], FILTER_VALIDATE_INT) : 0;
            if ($amount <= 0 || $accountId <= 0) {
                continue;
            }
            $sources[] = ['type' => 'account', 'account_id' => $accountId, 'amount' => round($amount, 2)];
        }
    }

    return $sources;
}

$sources = pay_bill_collect_sources($_POST);

// Sem origem declarada: registra o valor informado como pagamento EXTERNO.
if ($sources === []) {
    $fallbackAmount = filter_input(INPUT_POST, 'paid_amount', FILTER_VALIDATE_FLOAT);
    if ($fallbackAmount === false || $fallbackAmount === null || $fallbackAmount <= 0) {
        flash('error', 'Informe ao menos uma origem de pagamento ou um valor pago.');
        redirect('/public/credit-cards.php');
    }
    $sources[] = ['type' => 'external', 'account_id' => null, 'amount' => round((float) $fallbackAmount, 2)];
}

$totalPaid = 0.0;
foreach ($sources as $source) {
    $totalPaid += $source['amount'];
}
$totalPaid = round($totalPaid, 2);

if ($totalPaid <= 0) {
    flash('error', 'Valor pago inválido.');
    redirect('/public/credit-cards.php');
}

// Não permite pagar mais do que o devido (mesma regra de antes).
if (round($currentPaidAmount + $totalPaid, 2) > round($totalWithInterest, 2)) {
    flash('error', 'O valor pago não pode ser maior que o total da fatura.');
    redirect('/public/credit-cards.php');
}

// Valida cada conta de origem ANTES de abrir a transação.
foreach ($sources as $index => $source) {
    if ($source['type'] !== 'account') {
        continue;
    }
    if ($source['account_id'] === null) {
        flash('error', 'Selecione a conta de origem de cada parcela do pagamento.');
        redirect('/public/credit-cards.php');
    }
    $stmt = $pdo->prepare(
        'SELECT id, name FROM accounts
          WHERE id = :id AND user_id = :user_id AND type <> "credit" AND is_active = 1'
    );
    $stmt->execute(['id' => $source['account_id'], 'user_id' => $userId]);
    $account = $stmt->fetch();
    if (!$account) {
        flash('error', 'Conta de origem inválida (não pode ser um cartão de crédito).');
        redirect('/public/credit-cards.php');
    }
    $sources[$index]['account_name'] = $account['name'];
}

// Categoria de despesa usada nos débitos das contas.
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Ignora: schema antigo sem user_id em categories.
}

if ($hasUserIdColumn) {
    $stmt = $pdo->prepare(
        'SELECT id FROM categories
          WHERE (user_id = :user_id OR user_id IS NULL) AND type = "expense"
            AND (name LIKE "%Cartão%" OR name LIKE "%Fatura%" OR name LIKE "%Crédito%") LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);
    $debitCategory = $stmt->fetch();
    if (!$debitCategory) {
        $stmt = $pdo->prepare(
            'SELECT id FROM categories WHERE (user_id = :user_id OR user_id IS NULL) AND type = "expense" LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $debitCategory = $stmt->fetch();
    }
} else {
    $stmt = $pdo->query('SELECT id FROM categories WHERE type = "expense" LIMIT 1');
    $debitCategory = $stmt ? $stmt->fetch() : null;
}

$needsCategory = false;
foreach ($sources as $source) {
    if ($source['type'] === 'account') {
        $needsCategory = true;
    }
}

if ($needsCategory && !$debitCategory) {
    flash('error', 'Nenhuma categoria de despesa encontrada. Cadastre uma categoria de despesa.');
    redirect('/public/credit-cards.php');
}

$debitCategoryId = $debitCategory ? (int) $debitCategory['id'] : 0;

// ------------------------------------------------------------- gravação atômica
$started = false;
try {
    $pdo->beginTransaction();
    $started = true;

    $insertSource = $pdo->prepare(
        'INSERT INTO bill_payments
            (bill_id, user_id, source_type, source_account_id, transaction_id, amount, paid_at)
         VALUES (:bill_id, :user_id, :source_type, :source_account_id, :transaction_id, :amount, :paid_at)'
    );

    foreach ($sources as $source) {
        $transactionId = null;

        if ($source['type'] === 'account') {
            // O dinheiro sai da conta: despesa efetivada, SEM bill_id — ela é
            // o pagamento da fatura, não um item dentro dela (se levasse
            // bill_id, voltaria a somar no total da própria fatura).
            $transactionId = $transactionRepository->create(
                $userId,
                (int) $source['account_id'],
                $debitCategoryId,
                'expense',
                $source['amount'],
                'Pagamento de fatura - ' . $cardName,
                $paidAtDate,
                $bill['currency'] ?? 'BRL',
                true,   // efetivada
                false,  // não recorrente
                null,   // bill_id: NUNCA vincula
                null,
                null,
                null,
                null
            );
        }

        $insertSource->execute([
            'bill_id' => $billId,
            'user_id' => $userId,
            'source_type' => $source['type'],
            'source_account_id' => $source['account_id'],
            'transaction_id' => $transactionId,
            'amount' => $source['amount'],
            'paid_at' => $paidAtDate->format('Y-m-d H:i:s'),
        ]);
    }

    // Nenhuma despesa é criada na conta do cartão — ver cabeçalho.
    $billRepository->markAsPaid($billId, round($currentPaidAmount + $totalPaid, 2), $paidAtDate);

    $pdo->commit();
    $started = false;

    try {
        (new GamificationService($pdo))->award($userId, 'bill_paid');
    } catch (Throwable $ge) {
        error_log('Gamification award (bill paid) failed: ' . $ge->getMessage());
    }
} catch (Throwable $e) {
    if ($started) {
        try { $pdo->rollBack(); } catch (Throwable $rb) { /* já revertida */ }
    }
    error_log('pay-bill: atomic payment failed: ' . $e->getMessage());
    flash('error', 'Falha ao registrar o pagamento. Nenhuma alteração foi aplicada — tente novamente.');
    redirect('/public/credit-cards.php');
}

$names = [];
foreach ($sources as $source) {
    $names[] = $source['type'] === 'external'
        ? 'origem externa'
        : ($source['account_name'] ?? 'conta');
}
flash('success', 'Pagamento registrado com sucesso! Origem: ' . implode(', ', $names) . '.');
redirect('/public/credit-cards.php');
