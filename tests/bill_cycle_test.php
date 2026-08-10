<?php

declare(strict_types=1);

/**
 * Task B1 — ciclo de fatura do cartão de crédito.
 *
 * COMPORTAMENTO CORRETO (o que este teste exige):
 *   A fatura de destino de uma compra é definida pelo DIA DE FECHAMENTO do
 *   cartão, não pelo mês do calendário. Cartão que fecha dia 5:
 *     - compra em 03/jan  → ainda entra no ciclo que fecha 05/jan  → fatura 2026-01
 *     - compra em 20/jan  → já passou o fechamento                → fatura 2026-02
 *     - compra em 28/dez  → já passou o fechamento de dezembro    → fatura 2027-01
 *
 * BUG CONHECIDO (esperado FALHAR até a Task B2): `actions/transactions/create.php`
 * resolve a fatura com `$dateForBilling->format('Y-m')`, ou seja, o mês do
 * calendário da compra. `closing_day` só é usado depois, para preencher
 * closing_date/due_date da fatura. Resultado: todo gasto feito entre
 * closing_day+1 e o fim do mês cai numa fatura que JÁ FECHOU antes da compra.
 *
 * Roda por HTTP real porque create.php lê a requisição com filter_input(INPUT_POST).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/http_client.php';

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];

if (!preg_match('/(^|[_-])(test|app)$/i', (string) $db['database'])) {
    t_skip('banco da aplicação (' . $db['database'] . ') não é descartável — pulando');
}
if (http_base() === null) {
    t_skip('HTTP_BASE não configurada — create.php exige requisição HTTP real');
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    t_skip('banco da aplicação inacessível: ' . $e->getMessage());
}

// ------------------------------------------------------------------ cenário
$email = 'cycle+' . bin2hex(random_bytes(6)) . '@novixfin.test';
$senha = 'SenhaTeste123!';
$pdo->prepare('INSERT INTO users (name, email, password) VALUES (:n, :e, :p)')
    ->execute(['n' => 'Ciclo Teste', 'e' => $email, 'p' => password_hash($senha, PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();

$cleanup = static function () use ($pdo, $userId): void {
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
};

// Cartão que FECHA DIA 5 e vence dia 15.
$pdo->prepare(
    'INSERT INTO accounts (user_id, name, type, balance, closing_day, due_day, credit_limit)
     VALUES (:u, :n, "credit", 0, 5, 15, 50000)'
)->execute(['u' => $userId, 'n' => 'Cartão Ciclo']);
$cardId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (:u, :n, "expense")')
    ->execute(['u' => $userId, 'n' => 'Compras Teste']);
$categoryId = (int) $pdo->lastInsertId();

$session = http_new_session();
if (!http_login($email, $senha, $session)) {
    $cleanup();
    t_skip('não foi possível autenticar no servidor de teste');
}

/**
 * Lança uma despesa no cartão pelo handler REAL e devolve o billing_month da
 * fatura em que ela caiu (ou null se não vinculou).
 */
$billingMonthFor = static function (string $date, float $amount) use (&$session, $pdo, $cardId, $categoryId, $userId): ?string {
    http_post('/actions/transactions/create.php', [
        'account_id' => (string) $cardId,
        'type' => 'expense',
        'category_id' => (string) $categoryId,
        'amount' => (string) $amount,
        'currency' => 'BRL',
        'description' => 'Compra ' . $date,
        'transaction_date' => $date,
        'is_effective' => '1',
    ], $session);

    $stmt = $pdo->prepare(
        'SELECT b.billing_month
           FROM transactions t
           LEFT JOIN credit_card_bills b ON b.id = t.bill_id
          WHERE t.user_id = :u AND t.account_id = :a AND t.transaction_date = :d
          ORDER BY t.id DESC LIMIT 1'
    );
    $stmt->execute(['u' => $userId, 'a' => $cardId, 'd' => $date]);
    $value = $stmt->fetchColumn();

    return $value === false || $value === null ? null : (string) $value;
};

echo "\n== Cartão fecha dia 5 ==\n";

// 1) Antes do fechamento: ciclo do próprio mês.
t_same(
    'compra 03/01 (antes do fechamento) → fatura 2026-01',
    $billingMonthFor('2026-01-03', 100.00),
    '2026-01'
);

// 2) Depois do fechamento: próxima fatura.
t_same(
    'compra 20/01 (após o fechamento) → fatura 2026-02',
    $billingMonthFor('2026-01-20', 200.00),
    '2026-02'
);

// 3) Virada de ano.
t_same(
    'compra 28/12 (após o fechamento) → fatura 2027-01',
    $billingMonthFor('2026-12-28', 300.00),
    '2027-01'
);

// 4) O próprio dia do fechamento ainda pertence ao ciclo que fecha.
t_same(
    'compra 05/03 (no dia do fechamento) → fatura 2026-03',
    $billingMonthFor('2026-03-05', 50.00),
    '2026-03'
);

// 5) Dia seguinte ao fechamento já é o ciclo seguinte.
t_same(
    'compra 06/03 (dia seguinte ao fechamento) → fatura 2026-04',
    $billingMonthFor('2026-03-06', 60.00),
    '2026-04'
);

echo "\n== Coerência da fatura de destino ==\n";

// A fatura de fevereiro tem de somar só o que caiu nela (a compra de 20/01).
$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(t.amount), 0)
       FROM transactions t
       INNER JOIN credit_card_bills b ON b.id = t.bill_id
      WHERE b.account_id = :a AND b.billing_month = "2026-02" AND t.type = "expense"'
);
$stmt->execute(['a' => $cardId]);
t_money('total da fatura 2026-02', (float) $stmt->fetchColumn(), 200.00);

// E a data de fechamento da fatura nunca pode ser anterior à compra que ela contém.
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
       FROM transactions t
       INNER JOIN credit_card_bills b ON b.id = t.bill_id
      WHERE t.user_id = :u AND t.transaction_date > b.closing_date'
);
$stmt->execute(['u' => $userId]);
t_same('nenhuma compra caiu numa fatura já fechada antes dela', (int) $stmt->fetchColumn(), 0);

$cleanup();
t_finish();
