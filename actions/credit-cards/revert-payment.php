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

// ---------------------------------------------------------------------------
// Task B5: a reversão é por `bill_payments`, não mais por prefixo de descrição.
//
// O caminho antigo redescobria as transações auxiliares casando
// `description LIKE 'Pagamento de fatura - <nome do cartão>%'` + a data de
// pagamento. Bastava RENOMEAR o cartão entre pagar e reverter para o casamento
// falhar: as transações ficavam órfãs e o saldo não voltava. Agora cada origem
// gravou seu `transaction_id`, então a reversão é exata.
//
// Pagamentos anteriores a esta mudança não têm linha em `bill_payments`; para
// eles mantemos o casamento por descrição como FALLBACK, para não deixar
// fatura antiga irreversível.
// ---------------------------------------------------------------------------

$revertedCount = 0;
$transactionRepository = new TransactionRepository($pdo);
// Definido aqui porque as mensagens no fim do arquivo também o consultam.
$paidAt = $bill['paid_at'] ?? null;

$sourcesStmt = $pdo->prepare(
    'SELECT id, transaction_id FROM bill_payments WHERE bill_id = :bill_id AND user_id = :user_id'
);
$sourcesStmt->execute(['bill_id' => $billId, 'user_id' => $userId]);
$paymentSources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($paymentSources) {
    foreach ($paymentSources as $source) {
        // Origem 'external' não tem transação para reverter.
        if (empty($source['transaction_id'])) {
            continue;
        }
        try {
            $transactionRepository->delete((int) $source['transaction_id'], $userId);
            $revertedCount++;
        } catch (Throwable $e) {
            error_log('Revert payment: falha ao remover transação ' . $source['transaction_id'] . ': ' . $e->getMessage());
        }
    }

    $pdo->prepare('DELETE FROM bill_payments WHERE bill_id = :bill_id AND user_id = :user_id')
        ->execute(['bill_id' => $billId, 'user_id' => $userId]);
} else {
    // ---- FALLBACK: pagamentos gravados antes da Task B5 ----
    if (!empty($paidAt)) {
        $stmt = $pdo->prepare('SELECT name FROM accounts WHERE id = :account_id AND user_id = :user_id');
        $stmt->execute(['account_id' => (int) $bill['account_id'], 'user_id' => $userId]);
        $cardRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $cardNameLike = $cardRow ? addcslashes((string) $cardRow['name'], '%_\\') : '';

        if ($cardNameLike !== '') {
            $findAndRevert = static function (string $likeDesc, string $type, bool $requiresBillLink) use (
                $pdo, $userId, $billId, $paidAt, $cardNameLike, $transactionRepository, &$revertedCount
            ): void {
                $sql = 'SELECT id FROM transactions
                         WHERE user_id = :user_id AND type = :type
                           AND description LIKE :pattern
                           AND DATE(transaction_date) = DATE(:paid_at)'
                    . ($requiresBillLink ? ' AND bill_id = :bill_id' : ' AND bill_id IS NULL');

                $stmt = $pdo->prepare($sql);
                $params = [
                    ':user_id' => $userId,
                    ':type' => $type,
                    ':pattern' => $likeDesc . $cardNameLike . '%',
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
                        error_log('Revert payment (legado): falha ao remover ' . $txId . ': ' . $e->getMessage());
                    }
                }
            };

            $findAndRevert('Fatura do cartão - ', 'expense', true);
            $findAndRevert('Pagamento de fatura - ', 'expense', false);
            $findAndRevert('Limite de crédito - ', 'income', false);
        }
    }
}

// Recalcula o total da fatura depois das remoções.
try {
    $billRepository->updateBillTotal($billId);
} catch (Throwable $e) {
    error_log('Revert payment: falha ao recalcular o total da fatura: ' . $e->getMessage());
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

