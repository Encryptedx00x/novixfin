<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use DateTimeImmutable;
use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;
use Novix\Repositories\CurrencySettingsRepository;

$pdo = Connection::get();
$transactionRepository = new TransactionRepository($pdo);
$currencySettingsRepository = new CurrencySettingsRepository($pdo);

$userId = currentUserId() ?? 0;
$currencySettings = $currencySettingsRepository->getSettings($userId);

$from = null;
$to = null;
$filterAccountId = null;
$filterIsEffective = isset($_GET['is_effective']) && $_GET['is_effective'] !== '' ? ($_GET['is_effective'] === '1' ? true : ($_GET['is_effective'] === '0' ? false : null)) : null;
$filterIsRecurring = isset($_GET['is_recurring']) && $_GET['is_recurring'] !== '' ? ($_GET['is_recurring'] === '1' ? true : ($_GET['is_recurring'] === '0' ? false : null)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fromInput = $_GET['from'] ?? null;
    $toInput = $_GET['to'] ?? null;
    $filterAccountId = isset($_GET['account_id']) && $_GET['account_id'] !== '' ? (int) $_GET['account_id'] : null;

    try {
        if ($fromInput) {
            $from = new DateTimeImmutable($fromInput);
        }
        if ($toInput) {
            $to = new DateTimeImmutable($toInput);
        }
    } catch (Exception $e) {
        flash('error', 'Datas inválidas.');
        redirect('history.php');
    }
}

// Busca transações com filtros opcionais
$transactions = $transactionRepository->listByUser(currentUserId() ?? 0, $from, $to, $filterIsEffective, $filterIsRecurring);

// Aplica filtro de conta se necessário
if ($filterAccountId) {
    $transactions = array_filter($transactions, function($transaction) use ($filterAccountId) {
        return isset($transaction['account_id']) && (int)$transaction['account_id'] === $filterAccountId;
    });
    $transactions = array_values($transactions);
}

// Busca contas para exibir saldos
$accountsStmt = $pdo->prepare('SELECT id, name, icon, balance, type FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC');
$accountsStmt->execute(['user_id' => $userId]);
$accountsData = $accountsStmt->fetchAll();

// Configurações de moeda
$enabledCurrencies = $currencySettings['enabled_currencies'] ?? ['BRL', 'USD', 'EUR'];
$primaryCurrency = $currencySettings['primary_currency'] ?? 'BRL';

// Calcula totais por conta e por moeda
$accountsWithTotals = [];
foreach ($accountsData as $account) {
    // Busca totais efetivadas por moeda para esta conta
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(currency, "BRL") as currency,
            SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
        FROM transactions 
        WHERE user_id = :user_id AND account_id = :account_id
        AND is_effective = 1
        GROUP BY COALESCE(currency, "BRL")
    ');
    $stmt->execute(['user_id' => $userId, 'account_id' => $account['id']]);
    $effectiveTotalsData = $stmt->fetchAll();
    
    // Busca totais não efetivadas por moeda para esta conta
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(currency, "BRL") as currency,
            SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
        FROM transactions 
        WHERE user_id = :user_id AND account_id = :account_id
        AND is_effective = 0
        GROUP BY COALESCE(currency, "BRL")
    ');
    $stmt->execute(['user_id' => $userId, 'account_id' => $account['id']]);
    $pendingTotalsData = $stmt->fetchAll();
    
    // Organiza totais efetivadas por moeda
    $effectiveTotalsByCurrency = [];
    foreach ($effectiveTotalsData as $row) {
        $currency = $row['currency'] ?? 'BRL';
        $effectiveTotalsByCurrency[$currency] = [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
            'balance' => (float) ($row['income'] ?? 0) - (float) ($row['expense'] ?? 0),
        ];
    }
    
    // Organiza totais não efetivadas por moeda
    $pendingTotalsByCurrency = [];
    foreach ($pendingTotalsData as $row) {
        $currency = $row['currency'] ?? 'BRL';
        $pendingTotalsByCurrency[$currency] = [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
            'balance' => (float) ($row['income'] ?? 0) - (float) ($row['expense'] ?? 0),
        ];
    }
    
    // Combina totais (efetivadas + não efetivadas) para compatibilidade
    $totalsByCurrency = [];
    $allCurrencies = array_unique(array_merge(array_keys($effectiveTotalsByCurrency), array_keys($pendingTotalsByCurrency)));
    foreach ($allCurrencies as $currency) {
        $effective = $effectiveTotalsByCurrency[$currency] ?? ['income' => 0, 'expense' => 0, 'balance' => 0];
        $pending = $pendingTotalsByCurrency[$currency] ?? ['income' => 0, 'expense' => 0, 'balance' => 0];
        $totalsByCurrency[$currency] = [
            'income' => $effective['income'] + $pending['income'],
            'expense' => $effective['expense'] + $pending['expense'],
            'balance' => $effective['balance'] + $pending['balance'],
        ];
    }
    
    // Se está convertendo tudo para uma moeda única, converte
    if ($currencySettings['convert_all_to_one'] && $currencySettings['conversion_target_currency']) {
        $targetCurrency = $currencySettings['conversion_target_currency'];
        $convertedTotals = ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        
        foreach ($totalsByCurrency as $currency => $totals) {
            if ($currency === $targetCurrency) {
                $convertedTotals['income'] += $totals['income'];
                $convertedTotals['expense'] += $totals['expense'];
            } else {
                $convertedTotals['income'] += convertCurrency($totals['income'], $currency, $targetCurrency, $currencySettings);
                $convertedTotals['expense'] += convertCurrency($totals['expense'], $currency, $targetCurrency, $currencySettings);
            }
        }
        
        $convertedTotals['balance'] = $convertedTotals['income'] - $convertedTotals['expense'];
        $totalsByCurrency = [$targetCurrency => $convertedTotals];
    }
    
    $accountsWithTotals[] = [
        'account' => $account,
        'totalsByCurrency' => $totalsByCurrency,
        'effectiveTotalsByCurrency' => $effectiveTotalsByCurrency,
        'pendingTotalsByCurrency' => $pendingTotalsByCurrency,
    ];
}

// ---------------------------------------------------------------------------
// Apresentação: agrupa por dia e calcula subtotais. Somente leitura sobre as
// transações já carregadas — nenhuma consulta ou regra de saldo nova.
// ---------------------------------------------------------------------------
$groupedTransactions = [];
foreach ($transactions as $transaction) {
    $dayKey = date('Y-m-d', strtotime($transaction['transaction_date']));
    if (!isset($groupedTransactions[$dayKey])) {
        $groupedTransactions[$dayKey] = ['rows' => [], 'subtotal' => 0.0];
    }
    $groupedTransactions[$dayKey]['rows'][] = $transaction;
    $signedAmount = (float) $transaction['amount'] * ($transaction['type'] === 'income' ? 1 : -1);
    $groupedTransactions[$dayKey]['subtotal'] += $signedAmount;
}

// Totais do período já filtrado, para os cards de resumo.
$periodEffectiveBalance = 0.0;
$periodToReceive = 0.0;
$periodToPay = 0.0;
foreach ($transactions as $transaction) {
    $amount = (float) $transaction['amount'];
    $isIncome = $transaction['type'] === 'income';
    if ($transaction['is_effective'] ?? true) {
        $periodEffectiveBalance += $isIncome ? $amount : -$amount;
    } elseif ($isIncome) {
        $periodToReceive += $amount;
    } else {
        $periodToPay += $amount;
    }
}

$weekdayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$hasActiveFilters = isset($_GET['from']) || isset($_GET['to']) || isset($_GET['account_id'])
    || $filterIsEffective !== null || $filterIsRecurring !== null;

$title = 'Histórico | NovixFin';
$pageStyles = ['styles/history.css'];

include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

?>

<?php
// Modo de visualização ('table' ou 'spreadsheet') — comportamento preservado.
$viewMode = $_GET['view'] ?? 'table';
?>

<section class="hist-screen">
    <div>
        <h1 class="hist-title">Histórico de transações</h1>
        <p class="hist-sub">Filtre por período e gerencie suas movimentações.</p>
    </div>

    <?php if ($success) : ?>
        <div class="hist-alert hist-alert--ok" role="status"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="hist-alert hist-alert--err" role="alert"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <!-- ============ FILTROS (mesmos campos e query string de sempre) ============ -->
    <form method="GET" class="hist-card hist-filters">
        <?php if (isset($_GET['view'])) : ?>
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($_GET['view'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="hist-filter-row">
            <label class="hist-field" for="from">
                <span class="hist-label">De</span>
                <input type="date" id="from" name="from" value="<?php echo sanitize($_GET['from'] ?? ''); ?>" class="hist-input">
            </label>
            <label class="hist-field" for="to">
                <span class="hist-label">Até</span>
                <input type="date" id="to" name="to" value="<?php echo sanitize($_GET['to'] ?? ''); ?>" class="hist-input">
            </label>
            <label class="hist-field" for="account_id">
                <span class="hist-label">Conta</span>
                <select id="account_id" name="account_id" class="hist-select">
                    <option value="">Todas</option>
                    <?php foreach ($accountsData as $account) : ?>
                        <option value="<?php echo $account['id']; ?>" <?php echo (isset($_GET['account_id']) && $_GET['account_id'] == $account['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($account['icon'] . ' ' . $account['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="hist-field" for="is_effective">
                <span class="hist-label">Status</span>
                <select id="is_effective" name="is_effective" class="hist-select">
                    <option value="">Todas</option>
                    <option value="1" <?php echo $filterIsEffective === true ? 'selected' : ''; ?>>Efetivadas</option>
                    <option value="0" <?php echo $filterIsEffective === false ? 'selected' : ''; ?>>Não efetivadas</option>
                </select>
            </label>
            <label class="hist-field" for="is_recurring">
                <span class="hist-label">Tipo</span>
                <select id="is_recurring" name="is_recurring" class="hist-select">
                    <option value="">Todas</option>
                    <option value="1" <?php echo $filterIsRecurring === true ? 'selected' : ''; ?>>Fixas/Recorrentes</option>
                    <option value="0" <?php echo $filterIsRecurring === false ? 'selected' : ''; ?>>Não fixas</option>
                </select>
            </label>
            <div class="hist-filter-actions">
                <button type="submit" class="hist-btn">Filtrar</button>
                <?php if ($hasActiveFilters) : ?>
                    <a href="/public/history.php" class="hist-btn hist-btn--ghost">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ============ RESUMOS ============ -->
    <div class="hist-sums">
        <div class="hist-card hist-sum">
            <div class="hist-sum-title" style="margin-bottom:12px; display:flex; align-items:baseline; gap:10px;">
                <span>Resumo por conta</span>
                <a href="/public/accounts.php" style="margin-left:auto; font-size:12px; font-weight:700; color:var(--accent-text); text-decoration:none;">Gerenciar →</a>
            </div>
            <div>
                <?php foreach ($accountsWithTotals as $item) :
                    $account = $item['account'];

                    // Moeda de referência da conta (mesma lógica de antes).
                    $filteredAccountTotals = [];
                    foreach ($item['totalsByCurrency'] as $currency => $totals) {
                        if (in_array($currency, $enabledCurrencies, true)) {
                            $filteredAccountTotals[$currency] = $totals;
                        }
                    }
                    if ($currencySettings['convert_all_to_one'] && $currencySettings['conversion_target_currency']) {
                        $accountPrimaryCurrency = $currencySettings['conversion_target_currency'];
                    } elseif (isset($filteredAccountTotals[$primaryCurrency])) {
                        $accountPrimaryCurrency = $primaryCurrency;
                    } else {
                        $firstCurrency = array_key_first($filteredAccountTotals);
                        $accountPrimaryCurrency = $firstCurrency ? $firstCurrency : $primaryCurrency;
                    }

                    $effectivePrimaryValue = $item['effectiveTotalsByCurrency'][$accountPrimaryCurrency] ?? ['balance' => 0, 'income' => 0, 'expense' => 0];
                    $pendingPrimaryValue = $item['pendingTotalsByCurrency'][$accountPrimaryCurrency] ?? ['balance' => 0, 'income' => 0, 'expense' => 0];
                    $pendingBalance = $pendingPrimaryValue['balance'] ?? 0;
                ?>
                    <div class="hist-acc-row">
                        <span class="hist-acc-name">
                            <span style="flex:none;"><?php echo sanitize($account['icon']); ?></span>
                            <span><?php echo sanitize($account['name']); ?></span>
                        </span>
                        <span class="hist-num" data-balance><?php echo formatCurrency($effectivePrimaryValue['balance'] ?? 0, $accountPrimaryCurrency); ?></span>
                        <span class="hist-num hist-num--pend" data-balance><?php echo $pendingBalance != 0 ? formatCurrency($pendingBalance, $accountPrimaryCurrency) : '—'; ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="hist-acc-legend">
                    <span></span><span>efetivado</span><span>pendente</span>
                </div>
            </div>
        </div>

        <div class="hist-card hist-sum">
            <div class="hist-sum-title">Saldo efetivado</div>
            <div class="hist-sum-big" style="color: <?php echo $periodEffectiveBalance >= 0 ? 'var(--pos)' : 'var(--neg)'; ?>;" data-balance>
                <?php echo formatCurrency($periodEffectiveBalance, $primaryCurrency); ?>
            </div>
            <div class="hist-sum-note">soma do período, considerando os filtros ativos</div>
        </div>

        <div class="hist-card hist-sum" style="display:flex; flex-direction:column; gap:10px;">
            <div class="hist-sum-title">A receber / a pagar</div>
            <div class="hist-sum-line">
                <span class="hist-sum-line-label">A receber</span>
                <span class="hist-sum-line-value" style="color:var(--pos);" data-balance><?php echo formatCurrency($periodToReceive, $primaryCurrency); ?></span>
            </div>
            <div class="hist-sum-line">
                <span class="hist-sum-line-label">A pagar</span>
                <span class="hist-sum-line-value" style="color:var(--neg);" data-balance><?php echo formatCurrency($periodToPay, $primaryCurrency); ?></span>
            </div>
            <div class="hist-sum-note" style="margin-top:auto;">pendentes do período filtrado</div>
        </div>
    </div>

    <!-- ============ VISÃO + AÇÕES DESTRUTIVAS ============ -->
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <div class="hist-viewtabs">
            <button type="button" onclick="changeView('table')" class="hist-viewtab" aria-pressed="<?php echo $viewMode === 'table' ? 'true' : 'false'; ?>">Lista</button>
            <button type="button" onclick="changeView('spreadsheet')" class="hist-viewtab" aria-pressed="<?php echo $viewMode === 'spreadsheet' ? 'true' : 'false'; ?>">Planilha</button>
        </div>
        <!-- Gatilhos destrutivos: mesmos data-attributes e mesma condição de
             exibição de antes. Ambos abrem o diálogo de confirmação (history.js);
             nenhum handler foi alterado. -->
        <div style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;">
            <?php if ($filterAccountId) : ?>
                <button type="button" data-delete-account="<?php echo $filterAccountId; ?>" class="hist-btn hist-btn--danger">Excluir todas desta conta</button>
            <?php endif; ?>
            <button type="button" data-delete-all class="hist-btn hist-btn--danger">Excluir todas as transações</button>
        </div>
    </div>

    <?php if (empty($transactions)) : ?>
        <div class="hist-empty">
            <span class="hist-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4-4"></path><path d="M8.5 11h5"></path></svg>
            </span>
            <div class="hist-empty-title"><?php echo $hasActiveFilters ? 'Nenhum resultado para esses filtros' : 'Nenhum lançamento por aqui ainda'; ?></div>
            <p class="hist-empty-text">
                <?php if ($hasActiveFilters) : ?>
                    Nenhum lançamento combina com a seleção atual. Afrouxe um filtro ou limpe tudo.
                <?php else : ?>
                    Assim que você lançar a primeira transação, ela aparece aqui.
                <?php endif; ?>
            </p>
            <?php if ($hasActiveFilters) : ?>
                <a href="/public/history.php" class="hist-btn hist-btn--ghost" style="margin-top:20px;">Limpar filtros</a>
            <?php endif; ?>
        </div>

    <?php elseif ($viewMode === 'spreadsheet') : ?>
        <!-- ============ VISÃO PLANILHA ============ -->
        <div class="hist-sheet-wrap">
            <table class="hist-sheet">
                <thead>
                    <tr>
                        <th>Data</th><th>Conta</th><th>Categoria</th><th>Tipo</th><th>Descrição</th>
                        <th style="text-align:right;">Valor</th><th>Moeda</th>
                        <th class="hist-sheet-center">Efetivada</th><th class="hist-sheet-center">Fixa</th>
                        <th class="hist-sheet-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) :
                        $installmentTotal = isset($transaction['recurrence_total_occurrences']) ? (int) $transaction['recurrence_total_occurrences'] : null;
                        $installmentIndex = isset($transaction['installment_index']) ? (int) $transaction['installment_index'] : null;
                        if ($installmentTotal !== null && $installmentIndex === null && ($transaction['is_recurring'] ?? false)) {
                            $installmentIndex = 1;
                        }
                    ?>
                        <tr>
                            <td style="font-family:var(--f-num); font-variant-numeric:tabular-nums;"><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                            <td>
                                <?php if ($transaction['account_name']) : ?>
                                    <?php echo sanitize(($transaction['account_icon'] ?? '') . ' ' . $transaction['account_name']); ?>
                                <?php else : ?>
                                    <span style="color:var(--muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize($transaction['category_name']); ?></td>
                            <td>
                                <span class="hist-pill <?php echo $transaction['type'] === 'income' ? 'hist-pill--income' : 'hist-pill--expense'; ?>">
                                    <?php echo $transaction['type'] === 'income' ? 'Receita' : 'Despesa'; ?>
                                </span>
                            </td>
                            <td><?php echo sanitize($transaction['description'] ?? '—'); ?></td>
                            <td class="hist-num" style="color: <?php echo $transaction['type'] === 'income' ? 'var(--pos)' : 'var(--neg)'; ?>;" data-balance>
                                <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?> <?php echo formatCurrency((float) $transaction['amount'], $transaction['currency'] ?? 'BRL'); ?>
                            </td>
                            <td style="font-family:var(--f-num);"><?php echo sanitize($transaction['currency'] ?? 'BRL'); ?></td>
                            <td class="hist-sheet-center"><?php echo ($transaction['is_effective'] ?? true) ? '✓' : '—'; ?></td>
                            <td class="hist-sheet-center">
                                <?php echo ($transaction['is_recurring'] ?? false) ? '✓' : '—'; ?>
                                <?php if ($installmentTotal !== null && $installmentIndex !== null) : ?>
                                    <div style="margin-top:4px; font-family:var(--f-num); font-size:11px; color:var(--accent-2);"><?php echo $installmentIndex; ?>/<?php echo $installmentTotal; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="hist-sheet-center">
                                <div style="display:inline-flex; gap:6px;">
                                    <button type="button" data-edit="<?php echo (int) $transaction['id']; ?>" class="hist-iconbtn" title="Editar lançamento" aria-label="Editar lançamento">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><circle cx="12" cy="5.5" r="1.6"></circle><circle cx="12" cy="12" r="1.6"></circle><circle cx="12" cy="18.5" r="1.6"></circle></svg>
                                    </button>
                                    <button type="button" data-delete="<?php echo (int) $transaction['id']; ?>" class="hist-iconbtn hist-iconbtn--danger" title="Excluir lançamento" aria-label="Excluir lançamento">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else : ?>
        <!-- ============ VISÃO LISTA (agrupada por dia) ============ -->
        <div class="hist-list">
            <?php foreach ($groupedTransactions as $dayKey => $group) : ?>
                <?php $dayTimestamp = strtotime($dayKey); ?>
                <div class="hist-group">
                    <span class="hist-group-label"><?php echo date('d/m/Y', $dayTimestamp); ?></span>
                    <span class="hist-group-sub"><?php echo sanitize($weekdayNames[(int) date('w', $dayTimestamp)]); ?></span>
                    <span class="hist-group-subtotal-label">subtotal</span>
                    <span class="hist-group-subtotal" style="color: <?php echo $group['subtotal'] >= 0 ? 'var(--pos)' : 'var(--neg)'; ?>;" data-balance>
                        <?php echo formatCurrency($group['subtotal'], $primaryCurrency); ?>
                    </span>
                </div>

                <?php foreach ($group['rows'] as $transaction) :
                    $installmentTotal = isset($transaction['recurrence_total_occurrences']) ? (int) $transaction['recurrence_total_occurrences'] : null;
                    $installmentIndex = isset($transaction['installment_index']) ? (int) $transaction['installment_index'] : null;
                    if ($installmentTotal !== null && $installmentIndex === null && ($transaction['is_recurring'] ?? false)) {
                        $installmentIndex = 1;
                    }
                    $isIncome = $transaction['type'] === 'income';
                    $isEffective = $transaction['is_effective'] ?? true;
                    $categoryName = (string) ($transaction['category_name'] ?? '');
                    $initial = $categoryName !== '' ? strtoupper(substr($categoryName, 0, 1)) : '•';
                    $accentColor = $isIncome ? 'var(--pos)' : 'var(--neg)';
                ?>
                    <div class="hist-txrow">
                        <span class="hist-txmain">
                            <span class="hist-txicon" style="background: color-mix(in srgb, <?php echo $accentColor; ?> 16%, transparent);" aria-hidden="true">
                                <span style="color: <?php echo $accentColor; ?>;"><?php echo sanitize($initial); ?></span>
                            </span>
                            <span style="min-width:0;">
                                <span class="hist-txdesc"><?php echo sanitize(($transaction['description'] ?? '') !== '' ? $transaction['description'] : $categoryName); ?></span>
                                <span class="hist-txcat"><?php echo sanitize($categoryName); ?></span>
                            </span>
                        </span>

                        <span class="hist-txconta">
                            <?php if ($transaction['account_name']) : ?>
                                <span><?php echo sanitize(($transaction['account_icon'] ?? '') . ' ' . $transaction['account_name']); ?></span>
                            <?php else : ?>
                                <span>Sem conta</span>
                            <?php endif; ?>
                        </span>

                        <span class="hist-chips">
                            <span class="hist-pill <?php echo $isIncome ? 'hist-pill--income' : 'hist-pill--expense'; ?>" title="<?php echo $isIncome ? 'Receita' : 'Despesa'; ?>">
                                <span class="hist-dot" aria-hidden="true"></span><span class="hist-stlab"><?php echo $isIncome ? 'Receita' : 'Despesa'; ?></span>
                            </span>
                            <?php if (!$isEffective) : ?>
                                <span class="hist-pill hist-pill--pending" title="Não efetivada">
                                    <span class="hist-dot" aria-hidden="true"></span><span class="hist-stlab">Não efetivada</span>
                                </span>
                            <?php endif; ?>
                            <?php if ($transaction['is_recurring'] ?? false) : ?>
                                <span class="hist-pill hist-pill--fixed" title="Fixa/recorrente">
                                    <span class="hist-dot" aria-hidden="true"></span><span class="hist-stlab">Fixa</span>
                                </span>
                            <?php endif; ?>
                            <?php if ($installmentTotal !== null && $installmentIndex !== null) : ?>
                                <span class="hist-pill hist-pill--installment" title="Parcela <?php echo $installmentIndex; ?> de <?php echo $installmentTotal; ?>">
                                    <span class="hist-dot" aria-hidden="true"></span><span class="hist-stlab"><?php echo $installmentIndex; ?>/<?php echo $installmentTotal; ?></span>
                                </span>
                            <?php endif; ?>
                        </span>

                        <span class="hist-txamount" style="color: <?php echo $accentColor; ?>;" data-balance>
                            <?php echo $isIncome ? '+' : '-'; ?> <?php echo formatCurrency((float) $transaction['amount'], $transaction['currency'] ?? 'BRL'); ?>
                        </span>

                        <span class="hist-txactions">
                            <button type="button" data-edit="<?php echo (int) $transaction['id']; ?>" class="hist-iconbtn" title="Editar lançamento" aria-label="Editar lançamento">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><circle cx="12" cy="5.5" r="1.6"></circle><circle cx="12" cy="12" r="1.6"></circle><circle cx="12" cy="18.5" r="1.6"></circle></svg>
                            </button>
                            <button type="button" data-delete="<?php echo (int) $transaction['id']; ?>" class="hist-iconbtn hist-iconbtn--danger" title="Excluir lançamento" aria-label="Excluir lançamento">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg>
                            </button>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal de edição. IDs, names e data-attributes preservados: history.js
     depende deles (modal.querySelector('input[name="..."]') etc.). -->
<dialog id="modal-edit" class="hist-modal">
    <form method="POST" action="/actions/transactions/update.php">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="redirect_to" value="history.php">
        <input type="hidden" name="id" value="">
        <div class="hist-modal-title">Editar lançamento</div>
        <div class="hist-modal-sub">As alterações valem a partir de agora.</div>
        <div class="hist-modal-body">
        <div class="hist-field">
            <label class="hist-label" for="modal-account">Conta/Banco</label>
            <select id="modal-account" name="account_id" required class="hist-select">
                <?php
                $accountsStmt = $pdo->prepare('SELECT id, name, icon FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC');
                $accountsStmt->execute(['user_id' => $userId]);
                foreach ($accountsStmt->fetchAll() as $account) : ?>
                    <option value="<?php echo (int) $account['id']; ?>">
                        <?php echo sanitize(($account['icon'] ?? '🏦') . ' ' . $account['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hist-field">
            <label class="hist-label" for="modal-type">Tipo</label>
            <select id="modal-type" name="type" class="hist-select">
                <option value="income">Receita</option>
                <option value="expense">Despesa</option>
            </select>
        </div>
        <div class="hist-field">
            <label class="hist-label" for="modal-category">Categoria</label>
            <select id="modal-category" name="category_id" class="hist-select">
                <?php
                // Verifica se user_id existe na tabela
                $hasUserIdColumn = false;
                try {
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
                    $hasUserIdColumn = $checkColumn->rowCount() > 0;
                } catch (PDOException $e) {
                    // Ignora erro
                }
                
                if ($hasUserIdColumn) {
                    $stmt = $pdo->prepare('SELECT id, name, type FROM categories WHERE (user_id = :user_id OR user_id IS NULL) ORDER BY name ASC');
                    $stmt->execute(['user_id' => $userId]);
                } else {
                    $stmt = $pdo->query('SELECT id, name, type FROM categories ORDER BY name ASC');
                }
                foreach ($stmt->fetchAll() as $category) : ?>
                    <option value="<?php echo (int) $category['id']; ?>" data-type="<?php echo $category['type']; ?>">
                        <?php echo sanitize($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <script>
            // Filtra categorias baseado no tipo selecionado
            document.addEventListener('DOMContentLoaded', function() {
                const typeSelect = document.getElementById('modal-type');
                const categorySelect = document.getElementById('modal-category');
                
                if (typeSelect && categorySelect) {
                    function filterCategories() {
                        const selectedType = typeSelect.value;
                        const options = categorySelect.querySelectorAll('option');
                        
                        options.forEach(option => {
                            const categoryType = option.getAttribute('data-type');
                            if (categoryType === selectedType) {
                                option.style.display = '';
                            } else {
                                option.style.display = 'none';
                            }
                        });
                        
                        // Se a categoria selecionada não corresponde ao tipo, limpa a seleção
                        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                        if (selectedOption && selectedOption.getAttribute('data-type') !== selectedType) {
                            categorySelect.value = '';
                        }
                    }
                    
                    typeSelect.addEventListener('change', filterCategories);
                    filterCategories(); // Filtra ao carregar
                }
            });
            </script>
        </div>
        <div class="hist-modal-grid hist-modal-grid--3">
            <div class="hist-field">
                <label class="hist-label" for="modal-amount">Valor</label>
                <input type="number" id="modal-amount" name="amount" min="0.01" step="0.01" class="hist-input" style="font-family:var(--f-num);">
            </div>
            <div class="hist-field">
                <label class="hist-label" for="modal-currency">Moeda</label>
                <select id="modal-currency" name="currency" class="hist-select">
                    <option value="BRL">R$ BRL</option>
                    <option value="USD">US$ USD</option>
                    <option value="EUR">€ EUR</option>
                </select>
            </div>
        </div>
        <div class="hist-field">
            <label class="hist-label" for="modal-description">Descrição</label>
            <input type="text" id="modal-description" name="description" maxlength="150" class="hist-input">
        </div>
        <div class="hist-field">
            <label class="hist-label" for="modal-date">Data</label>
            <input type="date" id="modal-date" name="transaction_date" class="hist-input">
        </div>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <label class="hist-check">
                <input type="checkbox" id="modal-is-effective" name="is_effective" value="1" onchange="toggleEffectiveDateEdit()">
                <span>Transação efetivada</span>
            </label>
            <div id="modal-effective-date-container" class="hidden hist-modal-sublock">
                <div class="hist-field">
                    <label class="hist-label" for="modal-effective-date">Data de efetivação</label>
                    <input type="date" id="modal-effective-date" name="effective_date" class="hist-input">
                    <p class="hist-help">Data em que a transação será efetivada automaticamente.</p>
                </div>
            </div>
            <label class="hist-check">
                <input type="checkbox" id="modal-is-recurring" name="is_recurring" value="1" onchange="toggleRecurrenceFieldsEdit()">
                <span>Transação fixa/recorrente</span>
            </label>
            <div id="modal-recurrence-fields" class="hidden hist-modal-sublock">
                <div class="hist-field">
                    <label class="hist-label" for="modal-recurrence-type">Tipo de recorrência</label>
                    <select id="modal-recurrence-type" name="recurrence_type" onchange="toggleRecurrenceDayEdit()" class="hist-select">
                        <option value="none">Não recorrente</option>
                        <option value="daily">Diária</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensal (mesmo dia)</option>
                        <option value="same_day">Mensal (mesmo dia do mês)</option>
                        <option value="yearly">Anual</option>
                    </select>
                </div>
                <div id="modal-recurrence-day-container" class="hidden hist-field">
                    <label class="hist-label" for="modal-recurrence-day">Dia</label>
                    <select id="modal-recurrence-day" name="recurrence_day" class="hist-select">
                        <option value="">-- Selecione --</option>
                        <option value="0">Domingo</option>
                        <option value="1">Segunda-feira</option>
                        <option value="2">Terça-feira</option>
                        <option value="3">Quarta-feira</option>
                        <option value="4">Quinta-feira</option>
                        <option value="5">Sexta-feira</option>
                        <option value="6">Sábado</option>
                    </select>
                </div>
                <div class="hist-field">
                    <label class="hist-label" for="modal-recurrence-limit-mode">Finalizar recorrência</label>
                    <select id="modal-recurrence-limit-mode" name="recurrence_limit_mode" onchange="toggleRecurrenceLimitFieldsEdit()" class="hist-select">
                        <option value="never">Nunca</option>
                        <option value="date">Em uma data</option>
                        <option value="count">Após X parcelas</option>
                    </select>
                </div>
                <div id="modal-recurrence-end-date-container" class="hidden hist-field">
                    <label class="hist-label" for="modal-recurrence-end-date">Data final</label>
                    <input type="date" id="modal-recurrence-end-date" name="recurrence_end_date" class="hist-input">
                </div>
                <div id="modal-recurrence-total-container" class="hidden hist-field">
                    <label class="hist-label" for="modal-recurrence-total-occurrences">Quantidade de parcelas</label>
                    <input type="number" id="modal-recurrence-total-occurrences" name="recurrence_total_occurrences" min="2" step="1" class="hist-input" style="font-family:var(--f-num);">
                    <p class="hist-help">A transação atual conta como a primeira parcela.</p>
                </div>
            </div>
        </div>
        </div>
        <div class="hist-modal-actions">
            <button type="button" data-close-modal class="hist-btn hist-btn--ghost">Cancelar</button>
            <button type="submit" class="hist-btn">Salvar</button>
        </div>
    </form>
</dialog>

<!-- ===== CONFIRMAÇÕES DESTRUTIVAS =====
     Os três diálogos abaixo mantêm exatamente a proteção que já existia:
     confirmação explícita antes de qualquer exclusão, o mesmo texto de aviso,
     CSRF e redirect_to. Os handlers não foram alterados. -->
<dialog id="modal-delete" class="hist-modal hist-modal--sm hist-modal--danger">
    <div class="hist-modal-title">Confirmar exclusão</div>
    <p class="hist-modal-sub" id="delete-message">Tem certeza que deseja excluir esta transação?</p>
    <form method="POST" id="delete-form" class="hist-modal-actions">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="id" id="delete-id" value="">
        <button type="button" data-close-delete-modal class="hist-btn hist-btn--ghost">Cancelar</button>
        <button type="submit" class="hist-btn" style="background:var(--neg); color:#fff;">Confirmar exclusão</button>
    </form>
</dialog>

<dialog id="modal-delete-account" class="hist-modal hist-modal--sm hist-modal--danger">
    <div class="hist-modal-title">Confirmar exclusão</div>
    <p class="hist-modal-sub">Tem certeza que deseja excluir TODAS as transações desta conta? Esta ação não pode ser desfeita.</p>
    <form method="POST" action="/actions/transactions/delete_by_account.php" class="hist-modal-actions">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="redirect_to" value="history.php">
        <input type="hidden" name="account_id" id="delete-account-id" value="">
        <button type="button" data-close-delete-account-modal class="hist-btn hist-btn--ghost">Cancelar</button>
        <button type="submit" class="hist-btn" style="background:var(--neg); color:#fff;">Confirmar exclusão</button>
    </form>
</dialog>

<dialog id="modal-delete-all" class="hist-modal hist-modal--sm hist-modal--danger">
    <div class="hist-modal-title">Confirmar exclusão</div>
    <p class="hist-modal-sub">Tem certeza que deseja excluir TODAS as transações de TODAS as contas? Esta ação não pode ser desfeita.</p>
    <form method="POST" action="/actions/transactions/delete_all.php" class="hist-modal-actions">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="redirect_to" value="history.php">
        <button type="button" data-close-delete-all-modal class="hist-btn hist-btn--ghost">Cancelar</button>
        <button type="submit" class="hist-btn" style="background:var(--neg); color:#fff;">Confirmar exclusão</button>
    </form>
</dialog>

<?php
$pageScripts = ['scripts/history.js'];
?>

<script>
    const transactionsData = <?php echo json_encode(array_map(static function (array $transaction): array {
        return [
            'id' => (int) $transaction['id'],
            'type' => $transaction['type'],
            'category_id' => (int) $transaction['category_id'],
            'account_id' => (int) ($transaction['account_id'] ?? 0),
            'amount' => (float) $transaction['amount'],
            'currency' => $transaction['currency'] ?? 'BRL',
            'description' => $transaction['description'],
            'transaction_date' => $transaction['transaction_date'],
            'is_effective' => (bool) ($transaction['is_effective'] ?? true),
            'is_recurring' => (bool) ($transaction['is_recurring'] ?? false),
            'effective_date' => $transaction['effective_date'] ?? null,
            'recurrence_type' => $transaction['recurrence_type'] ?? 'none',
            'recurrence_day' => isset($transaction['recurrence_day']) ? (int) $transaction['recurrence_day'] : null,
            'recurrence_end_date' => $transaction['recurrence_end_date'] ?? null,
            'parent_transaction_id' => isset($transaction['parent_transaction_id']) ? (int) $transaction['parent_transaction_id'] : null,
            'installment_index' => isset($transaction['installment_index']) ? (int) $transaction['installment_index'] : null,
            'recurrence_total_occurrences' => isset($transaction['recurrence_total_occurrences']) ? (int) $transaction['recurrence_total_occurrences'] : null,
        ];
    }, $transactions), JSON_THROW_ON_ERROR); ?>;
</script>

<script>
function changeView(mode) {
    const url = new URL(window.location);
    url.searchParams.set('view', mode);
    window.location.href = url.toString();
}
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

