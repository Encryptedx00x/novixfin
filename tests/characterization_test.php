<?php

declare(strict_types=1);

/**
 * Task 0.2 — TESTES DE CARACTERIZAÇÃO (rede de segurança).
 *
 * Estes testes NÃO julgam se o comportamento atual é o desejado: eles
 * CONGELAM o comportamento de hoje, com valores exatos ao centavo, para que
 * qualquer mudança nas fases seguintes que mexa em saldo, efetivação, saldo
 * consolidado ou previsão apareça como falha imediata.
 *
 * REGRA: nenhuma task posterior pode quebrar este arquivo, EXCETO se a própria
 * task disser que muda a regra — e aí é a task que atualiza o teste, dizendo
 * no commit o que mudou e por quê.
 *
 * Cobertura exigida pela fila:
 *   A. saldo da conta após criar / editar / excluir transação
 *   B. efetivar um lançamento pendente
 *   C. saldo consolidado (Σ accounts.balance, cartão de crédito FORA)
 *   D. previsão determinística (saldo hoje + a receber − a pagar)
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/forecast.php';
require_once __DIR__ . '/../src/Repositories/CreditCardBillRepository.php';
require_once __DIR__ . '/../src/Repositories/TransactionRepository.php';

use Novix\Repositories\TransactionRepository;

if (!test_db_available()) {
    t_skip('sem TEST_DB_DATABASE — caracterização precisa de banco isolado');
}

test_with_rollback(static function (PDO $pdo): void {
    $repo = new TransactionRepository($pdo);

    $userId = make_user($pdo);
    $accountId = make_account($pdo, $userId, 'Conta A', 'bank', 0.00);
    $expenseCat = make_category($pdo, $userId, 'expense', 'Despesa Teste');
    $incomeCat = make_category($pdo, $userId, 'income', 'Receita Teste');

    // ================================================== A. criar / editar / excluir
    echo "\n== A. Saldo da conta: criar, editar, excluir ==\n";

    // A1 — receita efetivada soma no saldo.
    $incomeId = $repo->create(
        $userId, $accountId, $incomeCat, 'income', 1000.00,
        'Salário', new DateTimeImmutable('2026-01-10'), 'BRL', true
    );
    t_money('A1 receita efetivada 1.000,00 → saldo', account_balance($pdo, $accountId), 1000.00);

    // A2 — despesa efetivada subtrai.
    $expenseId = $repo->create(
        $userId, $accountId, $expenseCat, 'expense', 250.50,
        'Mercado', new DateTimeImmutable('2026-01-11'), 'BRL', true
    );
    t_money('A2 despesa efetivada 250,50 → saldo', account_balance($pdo, $accountId), 749.50);

    // A3 — despesa PENDENTE não move o saldo (contrato central do app).
    $pendingId = $repo->create(
        $userId, $accountId, $expenseCat, 'expense', 100.00,
        'Conta de luz', new DateTimeImmutable('2026-01-20'), 'BRL', false
    );
    t_money('A3 despesa PENDENTE não move o saldo', account_balance($pdo, $accountId), 749.50);

    // ================================================== B. efetivar pendente
    echo "\n== B. Efetivar um pendente ==\n";
    $repo->update(
        $pendingId, $userId, $accountId, $expenseCat, 'expense', 100.00,
        'Conta de luz', new DateTimeImmutable('2026-01-20'), 'BRL', true
    );
    t_money('B1 efetivar pendente de 100,00 → saldo', account_balance($pdo, $accountId), 649.50);

    // B2 — efetivar de novo (idempotência de saldo): já efetivada, nada muda.
    $repo->update(
        $pendingId, $userId, $accountId, $expenseCat, 'expense', 100.00,
        'Conta de luz', new DateTimeImmutable('2026-01-20'), 'BRL', true
    );
    t_money('B2 efetivar de novo não duplica o débito', account_balance($pdo, $accountId), 649.50);

    // ================================================== A (cont.) editar valor
    echo "\n== A. Editar valor de uma efetivada ==\n";
    // 250,50 → 300,00: estorna o antigo e aplica o novo.
    $repo->update(
        $expenseId, $userId, $accountId, $expenseCat, 'expense', 300.00,
        'Mercado', new DateTimeImmutable('2026-01-11'), 'BRL', true
    );
    t_money('A4 editar 250,50 → 300,00 no saldo', account_balance($pdo, $accountId), 600.00);

    // A5 — trocar o TIPO de despesa para receita inverte o efeito.
    $repo->update(
        $expenseId, $userId, $accountId, $incomeCat, 'income', 300.00,
        'Estorno mercado', new DateTimeImmutable('2026-01-11'), 'BRL', true
    );
    t_money('A5 despesa 300,00 vira receita 300,00', account_balance($pdo, $accountId), 1200.00);

    // A6 — excluir estorna exatamente.
    $repo->delete($expenseId, $userId);
    t_money('A6 excluir a receita de 300,00 → saldo', account_balance($pdo, $accountId), 900.00);

    // A7 — excluir uma PENDENTE não mexe no saldo.
    $pending2 = $repo->create(
        $userId, $accountId, $expenseCat, 'expense', 77.77,
        'Pendente a excluir', new DateTimeImmutable('2026-01-25'), 'BRL', false
    );
    $repo->delete($pending2, $userId);
    t_money('A7 excluir PENDENTE não mexe no saldo', account_balance($pdo, $accountId), 900.00);

    // ================================================== C. saldo consolidado
    echo "\n== C. Saldo consolidado (cartão FORA) ==\n";
    // accounts.type é ENUM('bank','cash','investment','credit','other').
    $savingsId = make_account($pdo, $userId, 'Investimento', 'investment', 2500.25);
    $cardId = make_card($pdo, $userId, 'Cartão', 5, 15, 10000.00);

    // Gasto no cartão: deixa o saldo do CARTÃO negativo (comportamento atual).
    $repo->create(
        $userId, $cardId, $expenseCat, 'expense', 480.00,
        'Compra no cartão', new DateTimeImmutable('2026-01-12'), 'BRL', true
    );
    t_money('C1 gasto no cartão deixa o saldo do cartão negativo', account_balance($pdo, $cardId), -480.00);

    // Regra do dashboard: Σ accounts.balance EXCLUINDO type='credit'.
    $consolidated = (float) $pdo->query(
        'SELECT COALESCE(SUM(balance), 0) FROM accounts
          WHERE user_id = ' . (int) $userId . ' AND type <> "credit"'
    )->fetchColumn();
    t_money('C2 consolidado = conta + investimento, sem o cartão', $consolidated, 3400.25);

    $withCard = (float) $pdo->query(
        'SELECT COALESCE(SUM(balance), 0) FROM accounts WHERE user_id = ' . (int) $userId
    )->fetchColumn();
    t_money('C3 (controle) com o cartão daria outro número', $withCard, 2920.25);

    // ================================================== D. previsão determinística
    echo "\n== D. Previsão: saldo hoje + a receber − a pagar ==\n";

    // Pendentes conhecidos: +1.500,00 a receber e −400,00 a pagar.
    $repo->create(
        $userId, $accountId, $incomeCat, 'income', 1500.00,
        'Freela a receber', new DateTimeImmutable('2026-02-05'), 'BRL', false
    );
    $repo->create(
        $userId, $accountId, $expenseCat, 'expense', 400.00,
        'Aluguel a pagar', new DateTimeImmutable('2026-02-10'), 'BRL', false
    );

    // Mesma consulta do dashboard: só pendentes, moeda primária ou NULL.
    $row = $pdo->query(
        'SELECT COALESCE(SUM(CASE WHEN type = "income"  THEN amount ELSE 0 END), 0) AS inc,
                COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS exp
           FROM transactions
          WHERE user_id = ' . (int) $userId . '
            AND is_effective = 0
            AND (currency = "BRL" OR currency IS NULL)'
    )->fetch(PDO::FETCH_ASSOC);

    $pendingIncome = (float) $row['inc'];
    $pendingExpense = (float) $row['exp'];
    t_money('D1 total a receber (pendentes)', $pendingIncome, 1500.00);
    t_money('D2 total a pagar (pendentes)', $pendingExpense, 400.00);

    // O consolidado NÃO mudou: pendentes não tocam saldo.
    $consolidated = (float) $pdo->query(
        'SELECT COALESCE(SUM(balance), 0) FROM accounts
          WHERE user_id = ' . (int) $userId . ' AND type <> "credit"'
    )->fetchColumn();
    t_money('D3 saldo hoje inalterado pelos pendentes', $consolidated, 3400.25);

    // A conta real, via includes/forecast.php.
    $projNet = novix_pending_net_totals($pendingIncome, $pendingExpense);
    t_money('D4 líquido dos pendentes', $projNet, 1100.00);

    $forecast = novix_forecast_balance($consolidated, $projNet);
    t_money('D5 previsão = 3.400,25 + 1.500,00 − 400,00', $forecast, 4500.25);

    // D6 — o pendente do CARTÃO também entra em "a pagar" (comportamento atual;
    // a consulta do dashboard não filtra por tipo de conta).
    $repo->create(
        $userId, $cardId, $expenseCat, 'expense', 60.00,
        'Pendente no cartão', new DateTimeImmutable('2026-02-14'), 'BRL', false
    );
    $row = $pdo->query(
        'SELECT COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS exp
           FROM transactions
          WHERE user_id = ' . (int) $userId . ' AND is_effective = 0
            AND (currency = "BRL" OR currency IS NULL)'
    )->fetch(PDO::FETCH_ASSOC);
    t_money('D6 pendente do cartão entra em "a pagar"', (float) $row['exp'], 460.00);
});

t_finish();
