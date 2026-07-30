<?php

declare(strict_types=1);

/**
 * NovixFin — cálculo da "Previsão de saldo" do dashboard.
 *
 * REGRA (Abordagem A, a única usada — não misturar com a B):
 *
 *     previsão = saldo realizado + (receitas pendentes − despesas pendentes)
 *
 * `saldo realizado` é a soma de `accounts.balance`, que por contrato já contém
 * SOMENTE lançamentos efetivados (ver scripts/recompute-balances.php).
 * Portanto o segundo termo tem de conter SÓ os pendentes. Somar ao saldo o
 * líquido TOTAL do mês (efetivados + pendentes) conta os efetivados duas vezes.
 *
 * A Abordagem B (`receitas do mês − despesas do mês`) só coincide quando o
 * saldo inicial das contas é zero e todos os efetivados são do mês corrente;
 * fora disso ela ignora o saldo trazido de meses anteriores. Por isso o app
 * usa A.
 */

/**
 * Soma receitas/despesas de um conjunto de lançamentos.
 *
 * @param array<int, array{type?: string, amount?: mixed, is_effective?: mixed}> $rows
 * @param bool|null $onlyEffective true = só efetivados, false = só pendentes,
 *                                 null = todos.
 * @return array{income: float, expense: float, net: float}
 */
function novix_sum_transactions(array $rows, ?bool $onlyEffective = null): array
{
    $income = 0.0;
    $expense = 0.0;

    foreach ($rows as $row) {
        $isEffective = !empty($row['is_effective']);
        if ($onlyEffective === true && !$isEffective) {
            continue;
        }
        if ($onlyEffective === false && $isEffective) {
            continue;
        }

        $amount = (float) ($row['amount'] ?? 0);
        if (($row['type'] ?? '') === 'income') {
            $income += $amount;
        } else {
            $expense += $amount;
        }
    }

    return [
        'income' => $income,
        'expense' => $expense,
        'net' => $income - $expense,
    ];
}

/**
 * Líquido dos lançamentos AINDA NÃO efetivados (o que falta acontecer).
 *
 * @param array<int, array<string, mixed>> $rows
 */
function novix_pending_net(array $rows): float
{
    return novix_sum_transactions($rows, false)['net'];
}

/**
 * Mesma conta a partir de totais já agregados (ex.: vindos de um SUM do SQL).
 * Ambos os argumentos devem conter SOMENTE lançamentos pendentes.
 */
function novix_pending_net_totals(float $pendingIncome, float $pendingExpense): float
{
    return $pendingIncome - $pendingExpense;
}

/**
 * Previsão de saldo do mês.
 *
 * @param float $consolidatedBalance saldo realizado (Σ accounts.balance, sem cartão)
 * @param float $pendingNet          líquido SOMENTE dos pendentes
 */
function novix_forecast_balance(float $consolidatedBalance, float $pendingNet): float
{
    return $consolidatedBalance + $pendingNet;
}
