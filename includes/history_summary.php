<?php

declare(strict_types=1);

/**
 * NovixFin — agrupamento e totais do Histórico.
 *
 * CONTRATO: a listagem e TODOS os cards de resumo do Histórico derivam do
 * MESMO array `$transactions` (o resultado de TransactionRepository::listByUser
 * com os filtros ativos). Nada de consulta paralela com critério próprio — foi
 * exatamente essa divergência que fez o Histórico esconder lançamentos que o
 * Dashboard e a exportação mostravam.
 */

/**
 * Agrupa por dia (chave Y-m-d) preservando TODAS as linhas.
 *
 * As linhas vão para uma lista (`rows[]`), nunca indexadas por descrição, valor
 * ou conta — indexar por qualquer um desses causaria colisão entre lançamentos
 * iguais no mesmo dia. Sem limite por dia.
 *
 * @param array<int, array<string, mixed>> $transactions
 * @return array<string, array{rows: array<int, array<string, mixed>>, subtotal: float}>
 */
function novix_history_group_by_day(array $transactions): array
{
    $grouped = [];

    foreach ($transactions as $transaction) {
        $dayKey = date('Y-m-d', strtotime((string) $transaction['transaction_date']));
        if (!isset($grouped[$dayKey])) {
            $grouped[$dayKey] = ['rows' => [], 'subtotal' => 0.0];
        }
        $grouped[$dayKey]['rows'][] = $transaction;
        $amount = (float) $transaction['amount'];
        $grouped[$dayKey]['subtotal'] += ($transaction['type'] === 'income') ? $amount : -$amount;
    }

    return $grouped;
}

/**
 * Totais do período já filtrado, a partir do MESMO conjunto da listagem.
 *
 * @param array<int, array<string, mixed>> $transactions
 * @return array{effective_balance: float, to_receive: float, to_pay: float, count: int}
 */
function novix_history_totals(array $transactions): array
{
    $effectiveBalance = 0.0;
    $toReceive = 0.0;
    $toPay = 0.0;

    foreach ($transactions as $transaction) {
        $amount = (float) $transaction['amount'];
        $isIncome = ($transaction['type'] ?? '') === 'income';

        if (!empty($transaction['is_effective'])) {
            $effectiveBalance += $isIncome ? $amount : -$amount;
        } elseif ($isIncome) {
            $toReceive += $amount;
        } else {
            $toPay += $amount;
        }
    }

    return [
        'effective_balance' => $effectiveBalance,
        'to_receive' => $toReceive,
        'to_pay' => $toPay,
        'count' => count($transactions),
    ];
}

/**
 * Pendentes por conta, a partir do MESMO conjunto da listagem.
 * Indexado pelo account_id (chave única), nunca por nome/descrição.
 *
 * @param array<int, array<string, mixed>> $transactions
 * @return array<int|string, array{income: float, expense: float, net: float}>
 */
function novix_history_pending_by_account(array $transactions): array
{
    $byAccount = [];

    foreach ($transactions as $transaction) {
        if (!empty($transaction['is_effective'])) {
            continue;
        }
        $accountId = $transaction['account_id'] ?? 'sem_conta';
        if (!isset($byAccount[$accountId])) {
            $byAccount[$accountId] = ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
        }
        $amount = (float) $transaction['amount'];
        if (($transaction['type'] ?? '') === 'income') {
            $byAccount[$accountId]['income'] += $amount;
        } else {
            $byAccount[$accountId]['expense'] += $amount;
        }
        $byAccount[$accountId]['net'] =
            $byAccount[$accountId]['income'] - $byAccount[$accountId]['expense'];
    }

    return $byAccount;
}
