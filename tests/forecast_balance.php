<?php

declare(strict_types=1);

/**
 * NovixFin — testes da "Previsão de saldo" (dupla contagem do saldo realizado).
 *
 * Sem Composer / PHPUnit, no estilo dos demais testes do repo.
 * Rodar no servidor (CLI):
 *
 *   php tests/forecast_balance.php
 *
 * Contrato sob teste (includes/forecast.php):
 *   previsão = saldo realizado + (receitas pendentes − despesas pendentes)
 * e NUNCA `saldo realizado + líquido total do mês`, que contaria os
 * lançamentos já efetivados duas vezes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/forecast.php';

$failures = 0;
$checks = 0;

function eq(string $label, float $got, float $want, float $eps = 0.005): void
{
    global $failures, $checks;
    $checks++;
    if (abs($got - $want) > $eps) {
        $failures++;
        printf("  FAIL  %-56s esperado %10.2f, obtido %10.2f\n", $label, $want, $got);
    } else {
        printf("  ok    %-56s %10.2f\n", $label, $got);
    }
}

function ne(string $label, float $got, float $notWant, float $eps = 0.005): void
{
    global $failures, $checks;
    $checks++;
    if (abs($got - $notWant) <= $eps) {
        $failures++;
        printf("  FAIL  %-56s NAO deveria ser %.2f\n", $label, $notWant);
    } else {
        printf("  ok    %-56s != %.2f\n", $label, $notWant);
    }
}

/** Atalho: linha de lançamento. */
function tx(string $type, float $amount, bool $effective): array
{
    return ['type' => $type, 'amount' => $amount, 'is_effective' => $effective ? 1 : 0];
}

echo "== 1. Somente lançamentos EFETIVADOS ==\n";
$rows = [tx('income', 260.00, true), tx('income', 4.80, true),
         tx('expense', 37.90, true), tx('expense', 30.00, true)];
$eff = novix_sum_transactions($rows, true);
eq('receitas efetivadas', $eff['income'], 264.80);
eq('despesas efetivadas', $eff['expense'], 67.90);
eq('saldo realizado', $eff['net'], 196.90);
// Sem pendentes, a previsão é o próprio saldo realizado.
eq('previsão sem pendências', novix_forecast_balance(196.90, novix_pending_net($rows)), 196.90);

echo "\n== 2. Somente lançamentos PENDENTES ==\n";
$rows = [tx('income', 1900.00, false), tx('expense', 210.00, false)];
$pend = novix_sum_transactions($rows, false);
eq('receitas pendentes', $pend['income'], 1900.00);
eq('despesas pendentes', $pend['expense'], 210.00);
eq('líquido pendente', novix_pending_net($rows), 1690.00);
// Conta zerada: a previsão é só o que está por vir.
eq('previsão com saldo 0', novix_forecast_balance(0.0, novix_pending_net($rows)), 1690.00);

echo "\n== 3. MISTURA de efetivados e pendentes (arquivo do usuário) ==\n";
$rows = [
    // efetivados
    tx('income', 260.00, true), tx('income', 4.80, true),
    tx('expense', 37.90, true), tx('expense', 30.00, true),
    // pendentes — receitas
    tx('income', 923.00, false), tx('income', 1900.00, false),
    tx('income', 1900.00, false), tx('income', 180.00, false),
    // pendentes — despesas
    tx('expense', 210.00, false), tx('expense', 558.40, false),
    tx('expense', 195.00, false), tx('expense', 1328.74, false),
    tx('expense', 1045.76, false), tx('expense', 335.37, false),
    tx('expense', 1176.91, false),
];
$eff = novix_sum_transactions($rows, true);
$pend = novix_sum_transactions($rows, false);
$all = novix_sum_transactions($rows, null);

eq('saldo realizado', $eff['net'], 196.90);
eq('receitas pendentes', $pend['income'], 4903.00);
eq('despesas pendentes', $pend['expense'], 4850.18);
eq('líquido pendente', $pend['net'], 52.82);
eq('receitas totais do mês', $all['income'], 5167.80);
eq('despesas totais do mês', $all['expense'], 4918.08);
eq('líquido total do mês', $all['net'], 249.72);

$forecast = novix_forecast_balance($eff['net'], $pend['net']);
eq('PREVISÃO (critério de aceite)', $forecast, 249.72);

echo "\n== 4. PREVENÇÃO DE DUPLA CONTAGEM ==\n";
// O bug: somar ao saldo realizado o líquido TOTAL do mês.
$bug = $eff['net'] + $all['net'];
eq('valor do bug reproduzido', $bug, 446.62);
ne('previsão NÃO pode ser o valor do bug', $forecast, 446.62);
// Abordagem B (receitas totais − despesas totais) coincide com A quando o
// saldo das contas começa em zero e todos os efetivados são do mês.
eq('abordagem B coincide com A neste caso', $all['income'] - $all['expense'], $forecast);
// Invariante geral: previsão − saldo realizado == líquido pendente.
eq('invariante previsão − realizado', $forecast - $eff['net'], $pend['net']);

echo "\n== 5. RECEITAS e DESPESAS isoladas ==\n";
eq('só receita pendente', novix_forecast_balance(100.00, novix_pending_net([tx('income', 50.00, false)])), 150.00);
eq('só despesa pendente', novix_forecast_balance(100.00, novix_pending_net([tx('expense', 50.00, false)])), 50.00);

echo "\n== 6. SALDO BANCÁRIO NEGATIVO ==\n";
$rows = [tx('income', 300.00, false), tx('expense', 80.00, false)];
eq('saldo negativo + pendentes', novix_forecast_balance(-500.00, novix_pending_net($rows)), -280.00);
eq('negativo sem pendências', novix_forecast_balance(-500.00, novix_pending_net([])), -500.00);
// Pendentes que pioram um saldo já negativo.
eq('negativo agravado', novix_forecast_balance(-120.00, novix_pending_net([tx('expense', 30.00, false)])), -150.00);

echo "\n== 7. DINHEIRO EM ESPÉCIE agregado com banco/outras ==\n";
// O saldo consolidado soma contas de todos os tipos menos cartão de crédito.
$contas = ['bank' => 4.80, 'cash' => 260.00, 'other' => 35.20, 'investment' => 100.00];
$consolidado = array_sum($contas);
eq('consolidado (bank+cash+other+investment)', $consolidado, 400.00);
eq('previsão sobre o consolidado', novix_forecast_balance($consolidado, novix_pending_net([tx('expense', 50.00, false)])), 350.00);

echo "\n== 8. AUSÊNCIA DE PENDÊNCIAS ==\n";
eq('lista vazia', novix_pending_net([]), 0.00);
eq('só efetivados não entram no pendente', novix_pending_net([tx('income', 999.00, true)]), 0.00);
eq('previsão == saldo realizado', novix_forecast_balance(196.90, novix_pending_net([tx('income', 999.00, true)])), 196.90);

echo "\n== 9. Totais agregados (caminho SQL do dashboard) ==\n";
eq('novix_pending_net_totals', novix_pending_net_totals(4903.00, 4850.18), 52.82);
eq('previsão via totais do SQL', novix_forecast_balance(196.90, novix_pending_net_totals(4903.00, 4850.18)), 249.72);

echo "\n== 10. Regra determinística (caso real do usuário) ==\n";
// Saldo final previsto = saldo efetivado + pendentes a receber − pendentes a pagar,
// considerando SOMENTE o que já está registrado no banco.
$saldoHoje = 2887.26; $aReceber = 1103.00; $aPagar = 3550.76;
eq('líquido pendente', novix_pending_net_totals($aReceber, $aPagar), -2447.76);
eq('SALDO FINAL PREVISTO', novix_forecast_balance($saldoHoje, novix_pending_net_totals($aReceber, $aPagar)), 439.50);
// Efetivar uma pendente não muda o saldo final: sai do "a receber" e entra no saldo.
eq('efetivar receita mantém o final',
   novix_forecast_balance($saldoHoje + 500.00, novix_pending_net_totals($aReceber - 500.00, $aPagar)), 439.50);
eq('efetivar despesa mantém o final',
   novix_forecast_balance($saldoHoje - 200.00, novix_pending_net_totals($aReceber, $aPagar - 200.00)), 439.50);

echo "\n";
if ($failures > 0) {
    printf("FALHOU: %d de %d verificações.\n", $failures, $checks);
    exit(1);
}
printf("OK: %d verificações passaram.\n", $checks);
exit(0);
