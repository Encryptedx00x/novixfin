<?php

declare(strict_types=1);

/**
 * Task B4 — semântica NOVA do pagamento de fatura (multi-origem).
 *
 * O QUE SE EXIGE (valores exatos ao centavo):
 *   Fatura de R$ 1.000,00 paga com 400,00 (conta X) + 600,00 (conta Y):
 *     - conta X fica −400,00 e conta Y fica −600,00;
 *     - a fatura ZERA (restante 0,00, status paid);
 *     - o saldo do CARTÃO **não fica mais negativo** — pagar a fatura não é um
 *       gasto novo no cartão;
 *     - cada origem vira uma linha em `bill_payments`.
 *   Revertendo:
 *     - as contas voltam aos valores exatos de antes;
 *     - a fatura volta a 1.000,00 em aberto.
 *
 * POR QUE FALHA NO CÓDIGO ATUAL (esperado até a Task B5):
 *   `pay-bill.php` cria, por padrão (`register_bill_as_expense` = true), uma
 *   despesa do total DA FATURA na conta DO PRÓPRIO CARTÃO — então pagar deixa
 *   o cartão MAIS negativo (−1.000 vira −2.000) e essa despesa, por ter o
 *   bill_id, volta a somar no total da fatura. Além disso o handler só entende
 *   o formato antigo `account_debits[]` e não grava origem nenhuma.
 *   Ver reports/auditoria_cartoes_faturas.md.
 *
 * Roda por HTTP real: pay-bill.php lê a requisição com filter_input(INPUT_POST).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/http_client.php';

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];

if (!preg_match('/(^|[_-])(test|app)$/i', (string) $db['database'])) {
    t_skip('banco da aplicação (' . $db['database'] . ') não é descartável — pulando');
}
if (http_base() === null) {
    t_skip('HTTP_BASE não configurada — pay-bill.php exige requisição HTTP real');
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
$email = 'pay+' . bin2hex(random_bytes(6)) . '@novixfin.test';
$senha = 'SenhaTeste123!';
$pdo->prepare('INSERT INTO users (name, email, password) VALUES (:n, :e, :p)')
    ->execute(['n' => 'Pay Teste', 'e' => $email, 'p' => password_hash($senha, PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();

$cleanup = static function () use ($pdo, $userId): void {
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
};

$mkAccount = static function (string $name, float $balance) use ($pdo, $userId): int {
    $pdo->prepare('INSERT INTO accounts (user_id, name, type, balance) VALUES (:u, :n, "bank", :b)')
        ->execute(['u' => $userId, 'n' => $name, 'b' => $balance]);
    return (int) $pdo->lastInsertId();
};

$accountX = $mkAccount('Conta X', 1000.00);
$accountY = $mkAccount('Conta Y', 1000.00);

$pdo->prepare(
    'INSERT INTO accounts (user_id, name, type, balance, closing_day, due_day, credit_limit)
     VALUES (:u, :n, "credit", 0, 5, 15, 50000)'
)->execute(['u' => $userId, 'n' => 'Cartão Pagamento']);
$cardId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (:u, :n, "expense")')
    ->execute(['u' => $userId, 'n' => 'Cartão Teste']);
$categoryId = (int) $pdo->lastInsertId();

$session = http_new_session();
if (!http_login($email, $senha, $session)) {
    $cleanup();
    t_skip('não foi possível autenticar no servidor de teste');
}

$balance = static function (int $accountId) use ($pdo): float {
    $stmt = $pdo->prepare('SELECT balance FROM accounts WHERE id = :id');
    $stmt->execute(['id' => $accountId]);
    return (float) $stmt->fetchColumn();
};

// ---------------------------------------------- compra de 1.000,00 no cartão
http_post('/actions/transactions/create.php', [
    'account_id' => (string) $cardId,
    'type' => 'expense',
    'category_id' => (string) $categoryId,
    'amount' => '1000.00',
    'currency' => 'BRL',
    'description' => 'Compra para fechar fatura',
    'transaction_date' => '2026-01-03',
    'is_effective' => '1',
], $session);

$bill = $pdo->query(
    'SELECT * FROM credit_card_bills WHERE account_id = ' . $cardId . ' ORDER BY id ASC LIMIT 1'
)->fetch();

if (!$bill) {
    $cleanup();
    t_ok('a compra gerou uma fatura', false);
    t_finish();
}

$billId = (int) $bill['id'];

echo "\n== Antes do pagamento ==\n";
t_money('fatura total', (float) $bill['total_amount'], 1000.00);
t_money('saldo do cartão', $balance($cardId), -1000.00);
t_money('conta X', $balance($accountX), 1000.00);
t_money('conta Y', $balance($accountY), 1000.00);

$cardBalanceBeforePayment = $balance($cardId);

// ------------------------------------------- pagamento multi-origem 400 + 600
echo "\n== Pagamento: 400,00 (X) + 600,00 (Y) ==\n";
http_post('/actions/credit-cards/pay-bill.php', [
    'bill_id' => (string) $billId,
    'paid_amount' => '1000.00',
    'paid_at' => '2026-01-15',
    'sources' => [
        ['source_type' => 'account', 'source_account_id' => (string) $accountX, 'amount' => '400.00'],
        ['source_type' => 'account', 'source_account_id' => (string) $accountY, 'amount' => '600.00'],
    ],
], $session);

t_money('conta X debitada em 400,00', $balance($accountX), 600.00);
t_money('conta Y debitada em 600,00', $balance($accountY), 400.00);

$billAfter = $pdo->query('SELECT * FROM credit_card_bills WHERE id = ' . $billId)->fetch();
t_money('fatura paga: paid_amount', (float) $billAfter['paid_amount'], 1000.00);
t_money(
    'fatura ZERA (total − pago)',
    (float) $billAfter['total_amount'] - (float) $billAfter['paid_amount'],
    0.00
);
t_same('status da fatura', $billAfter['status'], 'paid');

// O ponto central: pagar NÃO é um gasto novo no cartão.
t_money('saldo do cartão NÃO ficou mais negativo', $balance($cardId), $cardBalanceBeforePayment);

// Cada origem tem de estar registrada.
$sources = $pdo->query(
    'SELECT source_type, source_account_id, amount FROM bill_payments
      WHERE bill_id = ' . $billId . ' ORDER BY amount ASC'
)->fetchAll();
t_same('duas origens gravadas em bill_payments', count($sources), 2);
if (count($sources) === 2) {
    t_money('origem 1 = 400,00', (float) $sources[0]['amount'], 400.00);
    t_same('origem 1 é da conta X', (int) $sources[0]['source_account_id'], $accountX);
    t_money('origem 2 = 600,00', (float) $sources[1]['amount'], 600.00);
    t_same('origem 2 é da conta Y', (int) $sources[1]['source_account_id'], $accountY);
}

// ------------------------------------------------------------------ reverter
echo "\n== Reversão ==\n";
http_post('/actions/credit-cards/revert-payment.php', ['bill_id' => (string) $billId], $session);

t_money('conta X voltou exata', $balance($accountX), 1000.00);
t_money('conta Y voltou exata', $balance($accountY), 1000.00);
t_money('saldo do cartão inalterado pela reversão', $balance($cardId), $cardBalanceBeforePayment);

$billReverted = $pdo->query('SELECT * FROM credit_card_bills WHERE id = ' . $billId)->fetch();
t_money('fatura volta a 1.000,00', (float) $billReverted['total_amount'], 1000.00);
t_money('nada mais consta como pago', (float) $billReverted['paid_amount'], 0.00);
t_ok('fatura volta a estar em aberto', in_array($billReverted['status'], ['open', 'overdue'], true));

$left = (int) $pdo->query('SELECT COUNT(*) FROM bill_payments WHERE bill_id = ' . $billId)->fetchColumn();
t_same('origens do pagamento removidas na reversão', $left, 0);

$cleanup();
t_finish();
