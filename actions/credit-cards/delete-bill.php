<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

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

// Verifica se a fatura pertence ao usuário e se não tem transações vinculadas
$stmt = $pdo->prepare('
    SELECT b.*, COUNT(t.id) as transaction_count
    FROM credit_card_bills b
    LEFT JOIN transactions t ON t.bill_id = b.id
    WHERE b.id = :id AND b.user_id = :user_id
    GROUP BY b.id
');
$stmt->execute(['id' => $billId, 'user_id' => $userId]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Fatura não encontrada.');
    redirect('/public/credit-cards.php');
}

$transactionCount = (int) ($bill['transaction_count'] ?? 0);
$totalAmount = (float) ($bill['total_amount'] ?? 0);
$paidAmount = (float) ($bill['paid_amount'] ?? 0);

// Só permite excluir se não tiver transações vinculadas e não tiver valor pago
if ($transactionCount > 0) {
    flash('error', 'Não é possível excluir uma fatura que possui transações vinculadas. Exclua as transações primeiro.');
    redirect('/public/credit-cards.php');
}

if ($paidAmount > 0) {
    flash('error', 'Não é possível excluir uma fatura que possui pagamentos registrados.');
    redirect('/public/credit-cards.php');
}

// Exclui a fatura
$stmt = $pdo->prepare('DELETE FROM credit_card_bills WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $billId, 'user_id' => $userId]);

flash('success', 'Fatura excluída com sucesso!');
redirect('/public/credit-cards.php');

