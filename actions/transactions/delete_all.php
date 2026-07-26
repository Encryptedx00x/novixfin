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

$pdo = Connection::get();
$transactionRepository = new TransactionRepository($pdo);

try {
    $deletedCount = $transactionRepository->deleteAll(currentUserId() ?? 0);
} catch (Throwable $e) {
    error_log('delete_all transactions error: ' . $e->getMessage());
    flash('error', 'Nao foi possivel excluir todas as transacoes.');
    redirect($redirectTo);
}

if ($deletedCount > 0) {
    flash('success', "Todas as transações foram excluídas com sucesso ({$deletedCount} transação(ões)).");
} else {
    flash('info', 'Nenhuma transação encontrada.');
}

redirect($redirectTo);

