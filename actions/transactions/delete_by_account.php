<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('history.php');
}

$redirectTo = $_POST['redirect_to'] ?? 'history.php';
if (!in_array($redirectTo, ['history.php'], true)) {
    $redirectTo = 'history.php';
}

verifyCsrf($_POST['_token'] ?? '');

$accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);

if (!$accountId || $accountId <= 0) {
    flash('error', 'Conta inválida.');
    redirect($redirectTo);
}

$pdo = Connection::get();
$transactionRepository = new TransactionRepository($pdo);

$deletedCount = $transactionRepository->deleteByAccount($accountId, currentUserId() ?? 0);

if ($deletedCount > 0) {
    flash('success', "Todas as transações da conta foram excluídas com sucesso ({$deletedCount} transação(ões)).");
} else {
    flash('info', 'Nenhuma transação encontrada para esta conta.');
}

redirect($redirectTo);

