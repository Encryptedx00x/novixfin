<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\CreditCardBillRepository;
use Novix\Repositories\TransactionRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/credit-cards.php');
}

verifyCsrf($_POST['_token'] ?? '');

$billId = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);

if (!$billId || $billId <= 0) {
    flash('error', 'Fatura inválida.');
    redirect('/public/credit-cards.php');
}

$pdo = Connection::get();
$userId = currentUserId() ?? 0;
$billRepository = new CreditCardBillRepository($pdo);

// Verifica se a fatura pertence ao usuário
$stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $billId, 'user_id' => $userId]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Fatura não encontrada.');
    redirect('/public/credit-cards.php');
}

$paidAmount = (float) ($bill['paid_amount'] ?? 0);

if ($paidAmount <= 0) {
    flash('error', 'Esta fatura não possui pagamento registrado.');
    redirect('/public/credit-cards.php');
}

// Busca o total atualizado (com juros)
$totalAmount = (float) ($bill['total_amount'] ?? 0);
$interestAmount = (float) ($bill['interest_amount'] ?? 0);
$fineAmount = (float) ($bill['fine_amount'] ?? 0);
$totalWithInterest = $totalAmount + $interestAmount + $fineAmount;

// Reverte o pagamento: zera o valor pago
$newPaidAmount = 0.0;

// Determina o novo status
$newStatus = 'open';
$dueDate = new \DateTimeImmutable($bill['due_date']);
$now = new \DateTimeImmutable();

if ($now > $dueDate) {
    $newStatus = 'overdue';
}

// Atualiza a fatura (reset do paid_amount, status, paid_at)
$stmt = $pdo->prepare('
    UPDATE credit_card_bills
    SET paid_amount = :paid_amount,
        status = :status,
        paid_at = NULL
    WHERE id = :id AND user_id = :user_id
');
$stmt->execute([
    'id' => $billId,
    'user_id' => $userId,
    'paid_amount' => $newPaidAmount,
    'status' => $newStatus,
]);

// v3 Batch E: revert the auto-generated payment transactions too.
//
// `actions/credit-cards/pay-bill.php` creates three kinds of helper
// transactions at payment time:
//   1. Per-debit-account expense tx -> description `Pagamento de fatura - <cardName>[%]`, bill_id NULL.
//   2. Bill-as-expense tx           -> description `Fatura do cartão - <cardName>`,        bill_id == $billId.
//   3. Limit-as-income tx            -> description `Limite de crédito - <cardName>`,        bill_id NULL, type 'income'.
//
// We identify them by description-prefix match + matching the payment date
// (paid_at) + the bill id where applicable. We then call TransactionRepository::delete
// for each so that account balances and bill totals are reverted cleanly
// (delete() already accounts for is_effective balance changes & bill links).
//
// Robustness:
//   - Legacy bills (paid_at NULL): cannot reliably date-match the helper tx —
//     we skip auto-reversion and flash a manual-cleanup hint (old behaviour).
//   - The handler also defends itself against accidental user-created tx
//     with the same description by ALSO restricting to the paid_at date.

$revertedCount = 0;
$paidAt = $bill['paid_at'] ?? null;

if (!empty($paidAt)) {
    // Nome do cartão para construir a busca por descrição.
    $stmt = $pdo->prepare('SELECT name FROM accounts WHERE id = :account_id AND user_id = :user_id');
    $stmt->execute(['account_id' => (int) $bill['account_id'], 'user_id' => $userId]);
    $cardRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $cardName = $cardRow ? (string) $cardRow['name'] : '';

    // Escape underscores/wildcards in LIKE-safe card name.
    $cardNameLike = $cardName !== ''
        ? addcslashes($cardName, '%_\\')
        : '';

    $transactionRepository = new TransactionRepository($pdo);

    // Helper to find and revert matching transactions.
    $findAndRevert = static function (string $likeDesc, string $type, bool $requiresBillLink) use (
        $pdo, $userId, $billId, $paidAt, $cardNameLike, $transactionRepository, &$revertedCount
    ): void {
        if ($cardNameLike === '') {
            return;
        }
        $pattern = $likeDesc . $cardNameLike . '%';

        $sql = 'SELECT id FROM transactions WHERE user_id = :user_id AND type = :type AND description LIKE :pattern AND DATE(transaction_date) = DATE(:paid_at)';
        if ($requiresBillLink) {
            $sql .= ' AND bill_id = :bill_id';
        } else {
            $sql .= ' AND bill_id IS NULL';
        }

        $stmt = $pdo->prepare($sql);
        $params = [
            ':user_id' => $userId,
            ':type' => $type,
            ':pattern' => $pattern,
            ':paid_at' => $paidAt,
        ];
        if ($requiresBillLink) {
            $params[':bill_id'] = $billId;
        }
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $txId) {
            try {
                $transactionRepository->delete((int) $txId, $userId);
                $revertedCount++;
            } catch (Throwable $e) {
                error_log('Revert payment: failed to delete auto-tx ' . $txId . ': ' . $e->getMessage());
            }
        }
    };

    // 1) bill-as-expense (linked to the bill via bill_id)
    $findAndRevert('Fatura do cartão - ', 'expense', true);
    // 2) per-debit-account expense (bill_id NULL)
    $findAndRevert('Pagamento de fatura - ', 'expense', false);
    // 3) limit-as-income (bill_id NULL, type income)
    $findAndRevert('Limite de crédito - ', 'income', false);

    // Refresh the bill total after the bill-linked auto-expense was deleted
    // (delete() already calls updateBillTotal for the bill_id link, but
    // additional refresh keeps the totals in sync after all reverts).
    try {
        $billRepository->updateBillTotal($billId);
    } catch (Throwable $e) {
        error_log('Revert payment: failed to refresh bill total: ' . $e->getMessage());
    }
}

if ($revertedCount > 0) {
    flash('success', 'Pagamento revertido com sucesso! A fatura voltou ao status "' . ($newStatus === 'overdue' ? 'Vencida' : 'Aberta') . '". ' . $revertedCount . ' transação(ões) automática(s) do pagamento foram removidas e os saldos revertidos.');
} elseif (!empty($paidAt)) {
    flash('success', 'Pagamento revertido com sucesso! A fatura voltou ao status "' . ($newStatus === 'overdue' ? 'Vencida' : 'Aberta') . '". Nenhuma transação automática foi encontrada para reverter (provavelmente o pagamento foi registrado sem opção "registrar como despesa/limite").');
} else {
    // Legacy bills that predate this feature: cannot reliably identify auto-tx.
    flash('success', 'Pagamento revertido com sucesso! A fatura voltou ao status "' . ($newStatus === 'overdue' ? 'Vencida' : 'Aberta') . '".');
    flash('info', 'Esta fatura foi paga antes do recurso de reversão automática existir. Se havia transações automáticas (despesa da fatura / limite / débito), exclua-as manualmente no histórico.');
}

redirect('/public/credit-cards.php');

