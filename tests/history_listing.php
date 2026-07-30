<?php

declare(strict_types=1);

/**
 * NovixFin — testes do Histórico: nenhuma transação omitida e resumos
 * derivados do MESMO conjunto da listagem.
 *
 * Rodar no servidor (CLI):  php tests/history_listing.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/history_summary.php';

$failures = 0;
$checks = 0;

function eq(string $label, $got, $want, float $eps = 0.005): void
{
    global $failures, $checks;
    $checks++;
    $ok = (is_float($got) || is_float($want))
        ? abs((float) $got - (float) $want) <= $eps
        : $got === $want;
    if ($ok) {
        printf("  ok    %-58s %s\n", $label, is_float($got) ? number_format((float) $got, 2, ',', '.') : var_export($got, true));
    } else {
        $failures++;
        printf("  FAIL  %-58s esperado %s, obtido %s\n", $label, var_export($want, true), var_export($got, true));
    }
}

/** Linha de transação (mesma forma que listByUser devolve). */
function t(int $id, string $date, string $type, float $amount, int $effective, $accountId = 30, ?string $desc = 'x', $effDate = null): array
{
    return [
        'id' => $id, 'transaction_date' => $date, 'type' => $type,
        'amount' => (string) $amount, 'is_effective' => $effective,
        'account_id' => $accountId, 'description' => $desc, 'effective_date' => $effDate,
    ];
}

// ---------------------------------------------------------------------------
echo "== A. Cenário de 27/07/2026 (IDs 137 e 145 incluídos) ==\n";
// Itaú = 30, Caixa = 28, Financiamento = 29, PicPay = 31
$set = [
    // receitas pendentes
    t(140, '2026-07-27', 'income', 1900.00, 0, 30, 'Salário'),
    t(141, '2026-07-27', 'income', 1900.00, 0, 28, 'Vale Alimentação'),
    t(142, '2026-07-27', 'income', 180.00, 0, 30, 'Empréstimo'),
    t(145, '2026-07-27', 'income', 923.00, 0, 30, 'Bônus do quarter', '2026-07-30'), // era omitida
    // despesas pendentes
    t(130, '2026-07-27', 'expense', 210.00, 0, 30, 'Seguro'),
    t(131, '2026-07-27', 'expense', 558.40, 0, 30, 'Pagamentos de conserto'),
    t(132, '2026-07-27', 'expense', 195.00, 0, 30, 'Jiu-Jitsu'),
    t(133, '2026-07-27', 'expense', 1328.74, 0, 29, 'Financiamento do carro'),
    t(134, '2026-07-27', 'expense', 335.37, 0, 30, 'Fatura Itaú'),
    t(135, '2026-07-27', 'expense', 1176.91, 0, 31, 'Fatura PicPay'),
    t(137, '2026-07-27', 'expense', 1045.76, 0, 28, 'Fatura Caixa', '2026-08-15'), // era omitida
];
$tot = novix_history_totals($set);
eq('quantidade listada', $tot['count'], 11);
eq('A receber', $tot['to_receive'], 4903.00);
eq('A pagar', $tot['to_pay'], 4850.18);
eq('saldo efetivado do período', $tot['effective_balance'], 0.00);

echo "\n== B. Resumo por conta (mesmo conjunto) ==\n";
$byAcc = novix_history_pending_by_account($set);
eq('Caixa(28) receita pendente', $byAcc[28]['income'], 1900.00);
eq('Caixa(28) despesa pendente', $byAcc[28]['expense'], 1045.76);
eq('Caixa(28) líquido', $byAcc[28]['net'], 854.24);
eq('Itaú(30) receitas', $byAcc[30]['income'], 3003.00);
eq('Itaú(30) despesas', $byAcc[30]['expense'], 1298.77);
eq('Itaú(30) líquido', $byAcc[30]['net'], 1704.23);
eq('Financiamento(29) despesa', $byAcc[29]['expense'], 1328.74);
eq('PicPay(31) despesa', $byAcc[31]['expense'], 1176.91);

echo "\n== C. Agrupamento por dia não perde linhas ==\n";
$g = novix_history_group_by_day($set);
eq('dias agrupados', count($g), 1);
eq('linhas no dia 27/07', count($g['2026-07-27']['rows']), 11);
eq('subtotal do dia', $g['2026-07-27']['subtotal'], 4903.00 - 4850.18);

echo "\n== D. Mais de 10 transações na mesma data (sem limite por dia) ==\n";
$many = [];
for ($i = 1; $i <= 25; $i++) { $many[] = t(1000 + $i, '2026-07-27', 'expense', 10.00, 0); }
$gm = novix_history_group_by_day($many);
eq('25 linhas preservadas', count($gm['2026-07-27']['rows']), 25);
eq('total de 25x10', novix_history_totals($many)['to_pay'], 250.00);

echo "\n== E. Colisões: descrições iguais / sem descrição / mesmo valor+conta ==\n";
$dup = [
    t(1, '2026-07-27', 'expense', 50.00, 0, 30, 'Uber'),
    t(2, '2026-07-27', 'expense', 50.00, 0, 30, 'Uber'),   // igual em tudo menos o id
    t(3, '2026-07-27', 'expense', 50.00, 0, 30, null),      // sem descrição
    t(4, '2026-07-27', 'expense', 50.00, 0, 30, ''),        // descrição vazia
];
eq('4 linhas idênticas preservadas', count(novix_history_group_by_day($dup)['2026-07-27']['rows']), 4);
eq('soma sem colisão', novix_history_totals($dup)['to_pay'], 200.00);

echo "\n== F. effective_date não remove nem altera o período ==\n";
// pendente cuja efetivação cai em outro mês (caso do ID 137) segue no dia 27/07
$outro = [t(137, '2026-07-27', 'expense', 1045.76, 0, 28, 'Fatura Caixa', '2026-08-15')];
eq('agrupada por transaction_date', array_key_first(novix_history_group_by_day($outro)), '2026-07-27');
eq('entra em A pagar', novix_history_totals($outro)['to_pay'], 1045.76);
// effective_date nula
$nula = [t(200, '2026-07-27', 'income', 10.00, 0, 30, 'x', null)];
eq('effective_date nula não some', novix_history_totals($nula)['to_receive'], 10.00);

echo "\n== G. Efetivadas e pendentes no mesmo dia ==\n";
$mix = [
    t(1, '2026-07-27', 'income', 260.00, 1),
    t(2, '2026-07-27', 'expense', 37.90, 1),
    t(3, '2026-07-27', 'income', 100.00, 0),
    t(4, '2026-07-27', 'expense', 40.00, 0),
];
$tm = novix_history_totals($mix);
eq('saldo efetivado', $tm['effective_balance'], 222.10);
eq('a receber (só pendente)', $tm['to_receive'], 100.00);
eq('a pagar (só pendente)', $tm['to_pay'], 40.00);
eq('efetivadas fora do resumo por conta', isset(novix_history_pending_by_account($mix)[30]) ? novix_history_pending_by_account($mix)[30]['income'] : 0.0, 100.00);

echo "\n== H. Saldo negativo, decimais e sem conta ==\n";
eq('resultado negativo', novix_history_totals([t(1, '2026-07-27', 'expense', 500.00, 1)])['effective_balance'], -500.00);
$dec = [t(1, '2026-07-27', 'expense', 0.01, 0), t(2, '2026-07-27', 'expense', 0.02, 0)];
eq('centavos somam certo', novix_history_totals($dec)['to_pay'], 0.03);
$semConta = [t(1, '2026-07-27', 'expense', 25.00, 0, null)];
eq('sem conta vai para bucket próprio', novix_history_pending_by_account($semConta)['sem_conta']['expense'], 25.00);

echo "\n== I. Conjunto vazio ==\n";
$vazio = novix_history_totals([]);
eq('sem transações: contagem', $vazio['count'], 0);
eq('sem transações: a receber', $vazio['to_receive'], 0.00);
eq('sem transações: a pagar', $vazio['to_pay'], 0.00);
eq('sem transações: agrupamento', count(novix_history_group_by_day([])), 0);

echo "\n== J. REGRESSÃO: a query não pode filtrar por dono da categoria ==\n";
$repo = file_get_contents(__DIR__ . '/../src/Repositories/TransactionRepository.php');
$checks++;
if (strpos($repo, 'INNER JOIN categories c ON t.category_id = c.id AND c.user_id') !== false) {
    $failures++;
    echo "  FAIL  listByUser voltou a usar INNER JOIN filtrando c.user_id\n";
} else {
    echo "  ok    sem INNER JOIN filtrando c.user_id\n";
}
$checks++;
if (strpos($repo, 'LEFT JOIN categories c ON t.category_id = c.id') !== false) {
    echo "  ok    categoria entra por LEFT JOIN (só exibição)\n";
} else {
    $failures++;
    echo "  FAIL  LEFT JOIN de categorias ausente\n";
}
$checks++;
if (strpos($repo, 'WHERE t.user_id = :user_id') !== false) {
    echo "  ok    escopo de segurança por t.user_id preservado\n";
} else {
    $failures++;
    echo "  FAIL  escopo por t.user_id ausente\n";
}

echo "\n";
if ($failures > 0) {
    printf("FALHOU: %d de %d verificações.\n", $failures, $checks);
    exit(1);
}
printf("OK: %d verificações passaram.\n", $checks);
exit(0);
