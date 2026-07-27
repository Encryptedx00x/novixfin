<?php

declare(strict_types=1);

/**
 * NovixFin — Recompute de accounts.balance a partir das transações reais.
 *
 * CONTEXTO: o saldo (accounts.balance) é desnormalizado — mantido incremental
 * pelos handlers de transação (create/update/delete/pay-bill/...). Deletes
 * pré-v3 não revertiam o saldo, deixando balance descolado da soma real das
 * transações. Este script RECALCULA o saldo canônico e opcionalmente grava.
 *
 * FÓRMULA CANÔNICA (idêntica à dos handlers): para CADA conta,
 *   balance = SUM(amount WHERE type='income'  AND is_effective=1)
 *           - SUM(amount WHERE type='expense' AND is_effective=1)
 * Vale para TODOS os tipos de conta (inclusive credit/cartão): as transações
 * da conta do cartão dirigem o saldo pela mesma regra (fatura como despesa
 * reduz; limite como receita aumenta). Contas nascem com balance 0.00 e NÃO há
 * saldo de abertura fora de transações (verificado em actions/accounts/save.php),
 * então a soma das transações é a verdade completa do saldo.
 *
 * NÃO apaga nem altera transações — só recalcula accounts.balance.
 * Idempotente: rodar de novo após --apply mostra diff 0 em todas as contas.
 *
 * USO:
 *   php scripts/recompute-balances.php            # DRY-RUN (só relatório, não grava)
 *   php scripts/recompute-balances.php --apply    # grava os saldos recalculados
 *
 * FAÇA BACKUP ANTES do --apply (ritual de deploy):
 *   mariadb-dump -u <user> -p <db> accounts > backup_accounts_$(date +%F).sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden: CLI-only script.' . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use Novix\Database\Connection;

$apply = in_array('--apply', $argv, true);

$pdo = Connection::get();

// Saldo canônico por conta a partir das transações efetivadas.
$sql = '
    SELECT
        a.id,
        a.user_id,
        a.name,
        a.type,
        a.balance AS stored_balance,
        COALESCE(SUM(CASE WHEN t.type = "income"  AND t.is_effective = 1 THEN t.amount ELSE 0 END), 0)
      - COALESCE(SUM(CASE WHEN t.type = "expense" AND t.is_effective = 1 THEN t.amount ELSE 0 END), 0)
        AS computed_balance
    FROM accounts a
    LEFT JOIN transactions t ON t.account_id = a.id
    GROUP BY a.id, a.user_id, a.name, a.type, a.balance
    ORDER BY a.user_id ASC, a.id ASC
';

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$EPS = 0.005; // tolerância de arredondamento (centavos)
$mismatched = [];
foreach ($rows as $row) {
    $stored = (float) $row['stored_balance'];
    $computed = round((float) $row['computed_balance'], 2);
    if (abs($stored - $computed) > $EPS) {
        $mismatched[] = $row + ['stored_f' => $stored, 'computed_f' => $computed];
    }
}

// ---- Relatório antes -> depois ----
$mode = $apply ? 'APPLY (vai gravar)' : 'DRY-RUN (não grava)';
echo str_repeat('=', 78) . PHP_EOL;
echo 'NovixFin — recompute de accounts.balance   [' . $mode . ']' . PHP_EOL;
echo 'Contas no total: ' . count($rows) . '   |   Descoladas: ' . count($mismatched) . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;
printf("%-5s %-6s %-26s %-9s %12s %12s %12s\n", 'id', 'user', 'conta', 'tipo', 'antes', 'depois', 'diff');
echo str_repeat('-', 78) . PHP_EOL;

if (empty($mismatched)) {
    echo 'Nenhuma conta descolada — todos os saldos já batem com as transações.' . PHP_EOL;
} else {
    foreach ($mismatched as $m) {
        printf(
            "%-5d %-6d %-26.26s %-9s %12.2f %12.2f %12.2f\n",
            (int) $m['id'],
            (int) $m['user_id'],
            (string) $m['name'],
            (string) $m['type'],
            $m['stored_f'],
            $m['computed_f'],
            $m['computed_f'] - $m['stored_f']
        );
    }
}
echo str_repeat('-', 78) . PHP_EOL;

if (!$apply) {
    echo PHP_EOL . 'DRY-RUN: nada foi gravado. Revise o relatório acima.' . PHP_EOL;
    echo 'Para aplicar (após BACKUP): php scripts/recompute-balances.php --apply' . PHP_EOL;
    exit(0);
}

if (empty($mismatched)) {
    echo PHP_EOL . 'Nada a aplicar (já idempotente).' . PHP_EOL;
    exit(0);
}

// ---- Aplica dentro de UMA transação atômica ----
$pdo->beginTransaction();
try {
    $upd = $pdo->prepare('UPDATE accounts SET balance = :balance WHERE id = :id');
    $count = 0;
    foreach ($mismatched as $m) {
        $upd->execute(['balance' => $m['computed_f'], 'id' => (int) $m['id']]);
        $count++;
    }
    $pdo->commit();
    echo PHP_EOL . "APLICADO: {$count} conta(s) atualizada(s)." . PHP_EOL;
    echo 'Rode de novo (dry-run) para confirmar diff 0 em todas as contas.' . PHP_EOL;
} catch (\Throwable $e) {
    try { $pdo->rollBack(); } catch (\Throwable $rb) { /* ignore */ }
    fwrite(STDERR, 'FALHA ao aplicar — rollback feito. Nenhuma alteração. ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
