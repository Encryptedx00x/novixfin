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

verifyCsrf($_POST['_token'] ?? '');

$transactionId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$transactionId || $transactionId <= 0) {
    flash('error', 'Transação inválida.');
    redirect('history.php');
}

$pdo = Connection::get();
$transactionRepository = new TransactionRepository($pdo);

$transactionRepository->delete($transactionId, currentUserId() ?? 0);

flash('success', 'Transação excluída com sucesso.');

// Redireciona baseado na origem da requisição
$redirectTo = $_POST['redirect_to'] ?? 'history.php';
redirect($redirectTo);

