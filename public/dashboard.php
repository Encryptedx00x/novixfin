<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/forecast.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;
use Novix\Repositories\UserRepository;
use Novix\Repositories\CurrencySettingsRepository;
use Novix\Repositories\CreditCardBillRepository;
use Novix\Services\InsightService;

$pdo = Connection::get();
$transactionRepository = new TransactionRepository($pdo);
$userRepository = new UserRepository($pdo);
$currencySettingsRepository = new CurrencySettingsRepository($pdo);
$billRepository = new CreditCardBillRepository($pdo);

$userId = currentUserId();
$user = $userRepository->findById($userId ?? 0);
$currencySettings = $currencySettingsRepository->getSettings($userId ?? 0);

// Filtros
$filterAccountIds = [];
if (isset($_GET['account_ids']) && is_array($_GET['account_ids'])) {
    $filterAccountIds = array_map('intval', array_filter($_GET['account_ids']));
} elseif (isset($_GET['account_id']) && $_GET['account_id'] !== '') {
    // Compatibilidade: suporta account_id único para URLs antigas
    $filterAccountIds = [(int) $_GET['account_id']];
}
$filterAccountId = !empty($filterAccountIds) && count($filterAccountIds) === 1 ? $filterAccountIds[0] : null; // Mantido para compatibilidade
$filterCategoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;
$filterType = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null; // income, expense
$filterIsEffective = isset($_GET['is_effective']) && $_GET['is_effective'] !== '' ? ($_GET['is_effective'] === '1' ? true : ($_GET['is_effective'] === '0' ? false : null)) : null;
$filterIsRecurring = isset($_GET['is_recurring']) && $_GET['is_recurring'] !== '' ? ($_GET['is_recurring'] === '1' ? true : ($_GET['is_recurring'] === '0' ? false : null)) : null;
$periodType = $_GET['period'] ?? 'monthly'; // daily, weekly, monthly, yearly

// Calcula totais por moeda (considerando filtro de conta se houver)
// Por padrão, mostra apenas transações efetivadas, mas permite filtrar
// null = todas, true = apenas efetivadas, false = apenas não efetivadas
$onlyEffective = $filterIsEffective; // Passa o filtro diretamente (null, true ou false)
$totalsByCurrency = $transactionRepository->totalsByCurrency($userId ?? 0, !empty($filterAccountIds) ? $filterAccountIds : $filterAccountId, $onlyEffective);

// Filtra apenas moedas habilitadas e ordena
$enabledCurrencies = $currencySettings['enabled_currencies'] ?? ['BRL', 'USD', 'EUR'];
$currencyOrder = $currencySettings['currency_order'] ?? $enabledCurrencies;
$primaryCurrency = $currencySettings['primary_currency'] ?? 'BRL';

// Garante que a moeda principal está nas habilitadas
if (!in_array($primaryCurrency, $enabledCurrencies, true)) {
    $enabledCurrencies[] = $primaryCurrency;
}

// Se está usando conversão para uma moeda única
if ($currencySettings['convert_all_to_one'] && $currencySettings['conversion_target_currency']) {
    $targetCurrency = $currencySettings['conversion_target_currency'];
    $convertedTotals = ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
    
    foreach ($totalsByCurrency as $currency => $totals) {
        if ($currency === $targetCurrency) {
            $convertedTotals['income'] += $totals['income'];
            $convertedTotals['expense'] += $totals['expense'];
        } else {
            // Converte para moeda alvo
            $convertedTotals['income'] += convertCurrency($totals['income'], $currency, $targetCurrency, $currencySettings);
            $convertedTotals['expense'] += convertCurrency($totals['expense'], $currency, $targetCurrency, $currencySettings);
        }
    }
    
    $convertedTotals['balance'] = $convertedTotals['income'] - $convertedTotals['expense'];
    
    // Substitui totais por moeda pelos valores convertidos
    $totalsByCurrency = [$targetCurrency => $convertedTotals];
    
    // Define moeda principal como alvo da conversão para exibição
    $primaryCurrency = $targetCurrency;
    $displayTotals = [$targetCurrency => $convertedTotals];
} else {
    // Ordena: principal primeiro, depois as outras na ordem configurada
    $orderedCurrencies = array_unique(array_merge([$primaryCurrency], $currencyOrder));
    $orderedCurrencies = array_filter($orderedCurrencies, fn($c) => in_array($c, $enabledCurrencies, true));

// Prepara totais ordenados (apenas moedas habilitadas e que têm valores)
$displayTotals = [];
foreach ($orderedCurrencies as $currency) {
    if (isset($totalsByCurrency[$currency]) && in_array($currency, $enabledCurrencies, true)) {
        // Só adiciona se tiver valores diferentes de zero ou se for a moeda principal
        $hasValue = ($totalsByCurrency[$currency]['income'] != 0 || 
                    $totalsByCurrency[$currency]['expense'] != 0 ||
                    $currency === $primaryCurrency);
        if ($hasValue) {
            $displayTotals[$currency] = $totalsByCurrency[$currency];
        }
    }
}
}

// Verifica se user_id existe na tabela categories
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Ignora erro
}

// Busca totais por categoria (apenas despesas de contas ativas)
if ($hasUserIdColumn) {
    $categoriesQuery = '
        SELECT c.name, SUM(t.amount) AS total 
        FROM transactions t 
        INNER JOIN categories c ON t.category_id = c.id AND c.user_id = :user_id_cat
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = :user_id 
        AND t.type = "expense"
        AND (t.account_id IS NULL OR a.is_active = 1)';
    $categoriesParams = ['user_id' => $userId, 'user_id_cat' => $userId];
} else {
    $categoriesQuery = '
        SELECT c.name, SUM(t.amount) AS total 
        FROM transactions t 
        INNER JOIN categories c ON t.category_id = c.id
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = :user_id 
        AND t.type = "expense"
        AND (t.account_id IS NULL OR a.is_active = 1)';
    $categoriesParams = ['user_id' => $userId];
}

if ($filterIsEffective === true) {
    $categoriesQuery .= ' AND t.is_effective = 1';
} elseif ($filterIsEffective === false) {
    $categoriesQuery .= ' AND t.is_effective = 0';
}
// Se $filterIsEffective === null, não adiciona filtro (mostra todas)

$categoriesQuery .= ' GROUP BY c.id, c.name ORDER BY total DESC';

$stmt = $pdo->prepare($categoriesQuery);
$stmt->execute($categoriesParams);
$categoriesTotals = $stmt->fetchAll();

// Busca variação de gastos por categoria (mês atual vs mês anterior)
// MODERNIZAÇÃO: Comparação de gastos por categoria
$categoryComparisonQuery = '
    SELECT 
        c.id,
        c.name,
        COALESCE(SUM(CASE 
            WHEN DATE_FORMAT(t.transaction_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")
            AND t.is_effective = 1
            THEN t.amount ELSE 0 END), 0) as current_month,
        COALESCE(SUM(CASE 
            WHEN DATE_FORMAT(t.transaction_date, "%Y-%m") = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), "%Y-%m")
            AND t.is_effective = 1
            THEN t.amount ELSE 0 END), 0) as previous_month,
        COALESCE(MAX(CASE WHEN t.is_recurring = 1 THEN 1 ELSE 0 END), 0) as has_recurring
    FROM categories c
    LEFT JOIN transactions t ON t.category_id = c.id 
        AND t.user_id = :user_id 
        AND t.type = "expense"
    LEFT JOIN accounts a ON t.account_id = a.id
    WHERE (t.account_id IS NULL OR a.is_active = 1 OR a.id IS NULL)';
if ($hasUserIdColumn) {
    $categoryComparisonQuery .= ' AND c.user_id = :user_id_cat';
    $categoryComparisonParams = ['user_id' => $userId, 'user_id_cat' => $userId];
} else {
    $categoryComparisonParams = ['user_id' => $userId];
}
$categoryComparisonQuery .= '
    GROUP BY c.id, c.name
    HAVING current_month > 0 OR previous_month > 0 OR has_recurring > 0
    ORDER BY current_month DESC';

$stmt = $pdo->prepare($categoryComparisonQuery);
$stmt->execute($categoryComparisonParams);
$categoryComparison = $stmt->fetchAll();

// Calcula variações
$categoryVariations = [];
foreach ($categoryComparison as $cat) {
    $current = (float) $cat['current_month'];
    $previous = (float) $cat['previous_month'];
    $difference = $current - $previous;
    $percentage = $previous > 0 ? (($difference / $previous) * 100) : ($current > 0 ? 100 : 0);
    
    $categoryVariations[] = [
        'id' => $cat['id'],
        'name' => $cat['name'],
        'current' => $current,
        'previous' => $previous,
        'difference' => $difference,
        'percentage' => $percentage,
        'is_positive' => $difference < 0, // Redução de gasto é positivo
        'has_recurring' => ((int) ($cat['has_recurring'] ?? 0)) === 1,
    ];
}

// Busca totais de despesas e receitas por conta (para gráficos)
// Inclui cartões de crédito apenas se tiverem a opção de registrar faturas como despesa habilitada
$expensesByAccount = [];
$incomesByAccount = [];

// Busca configurações de cartões de crédito que têm register_bill_as_expense = true
// Também inclui cartões sem configuração (padrão é true)
$creditCardsWithExpenseEnabled = [];
try {
    // Verifica se a tabela existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'credit_card_transaction_settings'");
    if ($checkTable->rowCount() > 0) {
        // Busca TODOS os cartões de crédito primeiro
        $stmt = $pdo->prepare('
            SELECT id as account_id
            FROM accounts
            WHERE user_id = :user_id 
            AND type = "credit" 
            AND is_active = 1
        ');
        $stmt->execute(['user_id' => $userId]);
        $allCreditCards = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'account_id');
        
        // Busca cartões que têm a opção DESABILITADA (register_bill_as_expense = 0)
        $stmt = $pdo->prepare('
            SELECT account_id
            FROM credit_card_transaction_settings
            WHERE user_id = :user_id 
            AND register_bill_as_expense = 0
        ');
        $stmt->execute(['user_id' => $userId]);
        $disabledCards = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'account_id');
        
        // Remove os cartões desabilitados da lista (mantém os habilitados e sem configuração)
        $creditCardsWithExpenseEnabled = array_diff($allCreditCards, $disabledCards);
    } else {
        // Se a tabela não existe, inclui todos os cartões de crédito (padrão = true)
        $stmt = $pdo->prepare('
            SELECT id as account_id
            FROM accounts
            WHERE user_id = :user_id 
            AND type = "credit" 
            AND is_active = 1
        ');
        $stmt->execute(['user_id' => $userId]);
        $creditCardsWithExpenseEnabled = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'account_id');
    }
} catch (PDOException $e) {
    // Em caso de erro, tenta buscar todos os cartões (padrão = true)
    error_log('Erro ao buscar cartões com despesa habilitada: ' . $e->getMessage());
    try {
        $stmt = $pdo->prepare('
            SELECT id as account_id
            FROM accounts
            WHERE user_id = :user_id 
            AND type = "credit" 
            AND is_active = 1
        ');
        $stmt->execute(['user_id' => $userId]);
        $creditCardsWithExpenseEnabled = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'account_id');
    } catch (PDOException $e2) {
        $creditCardsWithExpenseEnabled = [];
    }
}

// Monta a query incluindo cartões de crédito quando apropriado
if (!empty($creditCardsWithExpenseEnabled)) {
    $creditCardsPlaceholder = implode(',', array_map('intval', $creditCardsWithExpenseEnabled));
    $accountTotalsQuery = '
        SELECT 
            a.id,
            a.name,
            a.icon,
            a.type,
            COALESCE(SUM(CASE WHEN t.type = "expense" AND t.is_effective = 1 THEN t.amount ELSE 0 END), 0) as total_expense,
            COALESCE(SUM(CASE WHEN t.type = "income" AND t.is_effective = 1 THEN t.amount ELSE 0 END), 0) as total_income
        FROM accounts a
        LEFT JOIN transactions t ON t.account_id = a.id AND t.user_id = :user_id_join
        WHERE a.user_id = :user_id_where AND a.is_active = 1 
        AND (
            a.type != "credit" 
            OR (a.type = "credit" AND a.id IN (' . $creditCardsPlaceholder . '))
        )
        GROUP BY a.id, a.name, a.icon, a.type
        HAVING total_expense > 0 OR total_income > 0
        ORDER BY a.name ASC
    ';
} else {
    // Se não há cartões com a opção habilitada, inclui apenas contas não-crédito
    $accountTotalsQuery = '
        SELECT 
            a.id,
            a.name,
            a.icon,
            a.type,
            COALESCE(SUM(CASE WHEN t.type = "expense" AND t.is_effective = 1 THEN t.amount ELSE 0 END), 0) as total_expense,
            COALESCE(SUM(CASE WHEN t.type = "income" AND t.is_effective = 1 THEN t.amount ELSE 0 END), 0) as total_income
        FROM accounts a
        LEFT JOIN transactions t ON t.account_id = a.id AND t.user_id = :user_id_join
        WHERE a.user_id = :user_id_where AND a.is_active = 1 AND a.type != "credit"
        GROUP BY a.id, a.name, a.icon, a.type
        HAVING total_expense > 0 OR total_income > 0
        ORDER BY a.name ASC
    ';
}
$stmt = $pdo->prepare($accountTotalsQuery);
$stmt->execute(['user_id_join' => $userId, 'user_id_where' => $userId]);
$accountTotalsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($accountTotalsData as $row) {
    $expense = (float) $row['total_expense'];
    $income = (float) $row['total_income'];
    
    if ($expense > 0) {
        $expensesByAccount[] = [
            'name' => $row['name'],
            'icon' => $row['icon'] ?? '🏦',
            'amount' => $expense,
        ];
    }
    
    if ($income > 0) {
        $incomesByAccount[] = [
            'name' => $row['name'],
            'icon' => $row['icon'] ?? '🏦',
            'amount' => $income,
        ];
    }
}

// MODERNIZAÇÃO: Busca histórico estendido para projeção (últimos 12 meses)
$extendedHistoryData = $transactionRepository->monthlyHistory($userId ?? 0, 12, $onlyEffective);

// MODERNIZAÇÃO: Calcula média móvel e projeção para próximos 3 meses + previsões semanais
$projectionData = [];
$weeklyProjectionData = [];
$forecastCurrentExpense = 0.0;
$forecastPrevExpense = 0.0;
$forecastAvgExpense = 0.0;
$forecastWeeklyExpense = 0.0;

if (!empty($extendedHistoryData) && count($extendedHistoryData) > 0) {
    // Calcula média dos últimos meses (mínimo 1, máximo 3)
    $monthsToAvg = min(3, count($extendedHistoryData));
    $lastMonths = array_slice($extendedHistoryData, -$monthsToAvg);
    $avgExpense = array_sum(array_column($lastMonths, 'expense')) / $monthsToAvg;
    $avgIncome = array_sum(array_column($lastMonths, 'income')) / $monthsToAvg;
    $forecastAvgExpense = $avgExpense;
    
    // Calcula média semanal (média mensal dividida por ~4.33 semanas no mês)
    $forecastWeeklyExpense = $avgExpense / 4.33;
    
    // Calcula valores para o gráfico de previsão
    $lastIndex = count($extendedHistoryData) - 1;
    $forecastCurrentExpense = (float) ($extendedHistoryData[$lastIndex]['expense'] ?? 0);
    $forecastPrevExpense = $lastIndex > 0 ? (float) ($extendedHistoryData[$lastIndex - 1]['expense'] ?? 0) : 0;
    
    // Projeta próximos 3 meses
    $currentMonth = date('Y-m');
    for ($i = 1; $i <= 3; $i++) {
        $projectedMonth = date('Y-m', strtotime($currentMonth . " +{$i} month"));
        $projectionData[] = [
            'month' => $projectedMonth,
            'expense' => $avgExpense,
            'income' => $avgIncome,
            'is_projection' => true,
        ];
    }
    
    // MODERNIZAÇÃO: Calcula previsões semanais para as próximas 4 semanas
    $currentWeek = date('Y-W'); // Ano-Semana (ex: 2024-15)
    $currentDate = new DateTime();
    
    for ($i = 1; $i <= 4; $i++) {
        $projectedDate = clone $currentDate;
        $projectedDate->modify("+{$i} week");
        $projectedWeek = $projectedDate->format('Y-W');
        $weekStart = clone $projectedDate;
        $weekStart->modify('monday this week');
        $weekLabel = $weekStart->format('d/m');
        
        $weeklyProjectionData[] = [
            'week' => $projectedWeek,
            'week_label' => 'Sem ' . $weekLabel,
            'expense' => $forecastWeeklyExpense,
            'income' => $avgIncome / 4.33,
            'is_projection' => true,
        ];
    }
}

// Busca histórico baseado no período selecionado
switch ($periodType) {
    case 'daily':
        $days = 30; // últimos 30 dias
        $query = '
            SELECT DATE(t.transaction_date) AS period, 
                SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS income,
                SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS expense
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            AND (t.account_id IS NULL OR a.is_active = 1)';
        $historyParams = ['user_id' => $userId, 'days' => $days];
        
        if ($filterIsEffective === true) {
            $query .= ' AND t.is_effective = 1';
        } elseif ($filterIsEffective === false) {
            $query .= ' AND t.is_effective = 0';
        }
        
        if (!empty($filterAccountIds)) {
            $placeholders = [];
            foreach ($filterAccountIds as $idx => $accountId) {
                $paramKey = 'daily_account_id_' . $idx;
                $placeholders[] = ':' . $paramKey;
                $historyParams[$paramKey] = $accountId;
            }
            $query .= ' AND t.account_id IN (' . implode(',', $placeholders) . ')';
        }
        
        $query .= ' GROUP BY DATE(t.transaction_date) ORDER BY period ASC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($historyParams);
        $historyData = $stmt->fetchAll();
        break;
    
    case 'weekly':
        $weeks = 12; // últimas 12 semanas
        $query = '
            SELECT DATE_FORMAT(t.transaction_date, "%Y-%U") AS period,
                YEARWEEK(t.transaction_date) AS week_num,
                SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS income,
                SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS expense
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
            AND (t.account_id IS NULL OR a.is_active = 1)';
        $historyParams = ['user_id' => $userId, 'weeks' => $weeks];
        
        if ($filterIsEffective === true) {
            $query .= ' AND t.is_effective = 1';
        } elseif ($filterIsEffective === false) {
            $query .= ' AND t.is_effective = 0';
        }
        
        if (!empty($filterAccountIds)) {
            $placeholders = [];
            foreach ($filterAccountIds as $idx => $accountId) {
                $paramKey = 'weekly_account_id_' . $idx;
                $placeholders[] = ':' . $paramKey;
                $historyParams[$paramKey] = $accountId;
            }
            $query .= ' AND t.account_id IN (' . implode(',', $placeholders) . ')';
        }
        
        $query .= ' GROUP BY week_num ORDER BY week_num ASC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($historyParams);
        $historyData = $stmt->fetchAll();
        break;
    
    case 'yearly':
        $query = '
            SELECT YEAR(t.transaction_date) AS period,
                SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS income,
                SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS expense
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id
            AND (t.account_id IS NULL OR a.is_active = 1)';
        $historyParams = ['user_id' => $userId];
        
        if ($filterIsEffective === true) {
            $query .= ' AND t.is_effective = 1';
        } elseif ($filterIsEffective === false) {
            $query .= ' AND t.is_effective = 0';
        }
        
        if (!empty($filterAccountIds)) {
            $placeholders = [];
            foreach ($filterAccountIds as $idx => $accountId) {
                $paramKey = 'yearly_account_id_' . $idx;
                $placeholders[] = ':' . $paramKey;
                $historyParams[$paramKey] = $accountId;
            }
            $query .= ' AND t.account_id IN (' . implode(',', $placeholders) . ')';
        }
        
        $query .= ' GROUP BY YEAR(t.transaction_date) ORDER BY period ASC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($historyParams);
        $historyData = $stmt->fetchAll();
        break;
    
    default: // monthly
        $historyData = $transactionRepository->monthlyHistory($userId ?? 0, 6, $onlyEffective);
}

// Calcula o saldo inicial (antes do período do gráfico)
$initialBalance = 0;
if (!empty($historyData)) {
    $firstPeriod = $historyData[0]['period'] ?? $historyData[0]['month'] ?? null;
    if ($firstPeriod) {
        // Determina a data de início do período
        switch ($periodType) {
            case 'daily':
                $startDate = $firstPeriod;
                break;
            case 'weekly':
                // Pega o primeiro dia da semana
                $startDate = date('Y-m-d', strtotime($firstPeriod . ' -7 days'));
                break;
            case 'yearly':
                $startDate = $firstPeriod . '-01-01';
                break;
            default: // monthly
                $startDate = $firstPeriod . '-01';
        }
        
        // Busca o saldo de transações anteriores ao período
        $initialQuery = '
            SELECT 
                SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) -
                SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS balance
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND t.transaction_date < :start_date
            AND (t.account_id IS NULL OR a.is_active = 1)';
        
        $initialParams = ['user_id' => $userId, 'start_date' => $startDate];
        
        if ($filterIsEffective === true) {
            $initialQuery .= ' AND t.is_effective = 1';
        } elseif ($filterIsEffective === false) {
            $initialQuery .= ' AND t.is_effective = 0';
        }
        
        if (!empty($filterAccountIds)) {
            $placeholders = [];
            foreach ($filterAccountIds as $idx => $accountId) {
                $paramKey = 'initial_account_id_' . $idx;
                $placeholders[] = ':' . $paramKey;
                $initialParams[$paramKey] = $accountId;
            }
            $initialQuery .= ' AND t.account_id IN (' . implode(',', $placeholders) . ')';
        }
        
        $stmt = $pdo->prepare($initialQuery);
        $stmt->execute($initialParams);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $initialBalance = (float) ($result['balance'] ?? 0);
    }
}

// Busca TODAS as contas ativas (para filtros e formulários)
$allAccountsStmt = $pdo->prepare('SELECT id, name, icon, balance, type FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC');
$allAccountsStmt->execute(['user_id' => $userId]);
$accounts = $allAccountsStmt->fetchAll();

// Calcula conquistas baseadas em dados reais
$achievements = [];

// 1. Conquista: Metas alcançadas
$completedGoalsStmt = $pdo->prepare('SELECT COUNT(*) as count FROM goals WHERE user_id = :user_id AND (completed = 1 OR (current_amount >= target_amount AND target_amount > 0))');
$completedGoalsStmt->execute(['user_id' => $userId]);
$completedGoals = (int) ($completedGoalsStmt->fetch()['count'] ?? 0);
if ($completedGoals > 0) {
    $achievements[] = [
        'type' => 'badge',
        'icon' => '🏆',
        'title' => $completedGoals === 1 ? 'Primeira Meta' : ($completedGoals >= 5 ? 'Mestre das Metas' : 'Meta Alcançada'),
        'description' => $completedGoals === 1 ? 'Você alcançou sua primeira meta!' : "Você alcançou {$completedGoals} metas!",
        'color' => 'violet'
    ];
}

// 2. Conquista: Meses dentro do orçamento (últimos 3 meses)
$budgetMonthsStmt = $pdo->prepare('
    SELECT DATE_FORMAT(transaction_date, "%Y-%m") as month,
           SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
           SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
    FROM transactions
    WHERE user_id = :user_id 
    AND is_effective = 1
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, "%Y-%m")
    HAVING income >= expense
    ORDER BY month DESC
');
$budgetMonthsStmt->execute(['user_id' => $userId]);
$budgetMonths = $budgetMonthsStmt->fetchAll();
$consecutiveMonths = count($budgetMonths);
if ($consecutiveMonths >= 3) {
    $achievements[] = [
        'type' => 'medal',
        'icon' => '🎖️',
        'title' => 'Economista Consistente',
        'description' => "{$consecutiveMonths} meses seguidos dentro do orçamento.",
        'color' => 'violet'
    ];
}

// 3. Conquista: Nível baseado em metas alcançadas
$userLevel = 'Iniciante';
if ($completedGoals >= 5) {
    $userLevel = 'Avançado';
    $achievements[] = [
        'type' => 'level',
        'icon' => '⭐',
        'title' => 'Avançado',
        'description' => "Atingiu {$completedGoals} metas no trimestre.",
        'color' => 'emerald'
    ];
} elseif ($completedGoals >= 2) {
    $userLevel = 'Intermediário';
}

// 4. Conquista: Primeira transação
$firstTransactionStmt = $pdo->prepare('SELECT COUNT(*) as count FROM transactions WHERE user_id = :user_id');
$firstTransactionStmt->execute(['user_id' => $userId]);
$transactionCount = (int) ($firstTransactionStmt->fetch()['count'] ?? 0);
if ($transactionCount === 1) {
    $achievements[] = [
        'type' => 'badge',
        'icon' => '🎯',
        'title' => 'Primeiro Passo',
        'description' => 'Você registrou sua primeira transação!',
        'color' => 'emerald'
    ];
}

// Gera insights baseados em análise de dados financeiros (v3 Phase 4+8 — extracted into src/Services/InsightService.php)
$currentBalance = $displayTotals[$primaryCurrency]['balance'] ?? 0;
$insights = (new InsightService())->forUser($pdo, (int) $userId, (float) $currentBalance);

// Busca contas para exibir na dashboard (apenas as marcadas)
$dashboardAccountsStmt = $pdo->prepare('SELECT id, name, icon, balance, type FROM accounts WHERE user_id = :user_id AND is_active = 1 AND show_in_dashboard = 1 ORDER BY name ASC');
$dashboardAccountsStmt->execute(['user_id' => $userId]);
$dashboardAccounts = $dashboardAccountsStmt->fetchAll();

// Busca faturas vencidas e próximas do vencimento
$overdueBills = $billRepository->getOverdueBills($userId ?? 0);
$upcomingBills = [];
$stmt = $pdo->prepare('
    SELECT b.*, a.name as account_name, a.icon as account_icon
    FROM credit_card_bills b
    INNER JOIN accounts a ON b.account_id = a.id
    WHERE b.user_id = :user_id 
    AND b.status = "open"
    AND b.due_date >= CURDATE()
    AND b.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY b.due_date ASC
');
$stmt->execute(['user_id' => $userId ?? 0]);
$upcomingBills = $stmt->fetchAll();

// Calcula juros para faturas vencidas
foreach ($overdueBills as &$bill) {
    // Preserva dados da conta antes de recarregar
    $accountIcon = $bill['account_icon'] ?? '💳';
    $accountName = $bill['account_name'] ?? 'Cartão';
    
    $billRepository->calculateInterest((int) $bill['id']);
    $stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
    $stmt->execute(['id' => $bill['id']]);
    $billData = $stmt->fetch();
    
    // Mescla dados atualizados mantendo dados da conta
    $bill = array_merge($billData, [
        'account_icon' => $accountIcon,
        'account_name' => $accountName,
    ]);
    
    $bill['total_with_interest'] = (float) ($bill['total_amount'] ?? 0) + 
                                   (float) ($bill['interest_amount'] ?? 0) + 
                                   (float) ($bill['fine_amount'] ?? 0);
    $bill['remaining'] = $bill['total_with_interest'] - (float) ($bill['paid_amount'] ?? 0);
}

// Busca a meta mais relevante para destacar (prioriza: não completada, com prazo mais próximo, ou maior progresso)
// Usa COALESCE para compatibilidade com diferentes versões do schema (title ou name)
try {
    $featuredGoalStmt = $pdo->prepare('
        SELECT id, COALESCE(title, name) as title, target_amount, current_amount, deadline, type, completed
        FROM goals 
        WHERE user_id = :user_id 
        AND (completed = 0 OR completed IS NULL)
        ORDER BY 
            CASE WHEN deadline IS NOT NULL AND deadline >= CURDATE() THEN 0 ELSE 1 END,
            deadline ASC,
            (current_amount / target_amount) DESC
        LIMIT 1
    ');
    $featuredGoalStmt->execute(['user_id' => $userId]);
    $featuredGoal = $featuredGoalStmt->fetch();
} catch (Exception $e) {
    // Se der erro, tenta apenas com 'name'
    $featuredGoalStmt = $pdo->prepare('
        SELECT id, name as title, target_amount, current_amount, deadline, type, completed
        FROM goals 
        WHERE user_id = :user_id 
        AND (completed = 0 OR completed IS NULL)
        ORDER BY 
            CASE WHEN deadline IS NOT NULL AND deadline >= CURDATE() THEN 0 ELSE 1 END,
            deadline ASC,
            (current_amount / target_amount) DESC
        LIMIT 1
    ');
    $featuredGoalStmt->execute(['user_id' => $userId]);
    $featuredGoal = $featuredGoalStmt->fetch();
}

// Calcula receitas e despesas APENAS para contas da dashboard (por moeda)
// Respeita o filtro de efetivadas e diferencia entre efetivadas e não efetivadas
$accountsWithTotals = [];
foreach ($dashboardAccounts as $account) {
    // Determina qual filtro aplicar baseado no filtro selecionado
    $effectiveFilter = '';
    
    if ($filterIsEffective === true) {
        $effectiveFilter = ' AND is_effective = 1';
    } elseif ($filterIsEffective === false) {
        $effectiveFilter = ' AND is_effective = 0';
    }
    // Se $filterIsEffective === null, não aplica filtro (mostra todas)
    
    // Calcula totais efetivadas (já tem)
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
    
    // Calcula totais não efetivadas (vai entrar)
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
    
    // Calcula totais filtrados (baseado no filtro selecionado)
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(currency, "BRL") as currency,
            SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
        FROM transactions 
        WHERE user_id = :user_id AND account_id = :account_id' . $effectiveFilter . '
        GROUP BY COALESCE(currency, "BRL")
    ');
    $stmt->execute(['user_id' => $userId, 'account_id' => $account['id']]);
    $totalsData = $stmt->fetchAll();
    
    // Organiza totais por moeda (efetivadas)
    $effectiveTotalsByCurrency = [];
    foreach ($effectiveTotalsData as $row) {
        $currency = $row['currency'] ?? 'BRL';
        $effectiveTotalsByCurrency[$currency] = [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
            'balance' => (float) ($row['income'] ?? 0) - (float) ($row['expense'] ?? 0),
        ];
    }
    
    // Organiza totais por moeda (não efetivadas)
    $pendingTotalsByCurrency = [];
    foreach ($pendingTotalsData as $row) {
        $currency = $row['currency'] ?? 'BRL';
        $pendingTotalsByCurrency[$currency] = [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
            'balance' => (float) ($row['income'] ?? 0) - (float) ($row['expense'] ?? 0),
        ];
    }
    
    // Organiza totais por moeda (filtrados)
    $totalsByCurrency = [];
    foreach ($totalsData as $row) {
        $currency = $row['currency'] ?? 'BRL';
        $totalsByCurrency[$currency] = [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
            'balance' => (float) ($row['income'] ?? 0) - (float) ($row['expense'] ?? 0),
        ];
    }
    
    // Se está convertendo tudo para uma moeda única, converte
    if ($currencySettings['convert_all_to_one'] && $currencySettings['conversion_target_currency']) {
        $targetCurrency = $currencySettings['conversion_target_currency'];
        
        // Converte totais filtrados
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
        
        // Converte totais efetivadas
        $convertedEffectiveTotals = ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        foreach ($effectiveTotalsByCurrency as $currency => $totals) {
            if ($currency === $targetCurrency) {
                $convertedEffectiveTotals['income'] += $totals['income'];
                $convertedEffectiveTotals['expense'] += $totals['expense'];
            } else {
                $convertedEffectiveTotals['income'] += convertCurrency($totals['income'], $currency, $targetCurrency, $currencySettings);
                $convertedEffectiveTotals['expense'] += convertCurrency($totals['expense'], $currency, $targetCurrency, $currencySettings);
            }
        }
        $convertedEffectiveTotals['balance'] = $convertedEffectiveTotals['income'] - $convertedEffectiveTotals['expense'];
        $effectiveTotalsByCurrency = [$targetCurrency => $convertedEffectiveTotals];
        
        // Converte totais não efetivadas
        $convertedPendingTotals = ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        foreach ($pendingTotalsByCurrency as $currency => $totals) {
            if ($currency === $targetCurrency) {
                $convertedPendingTotals['income'] += $totals['income'];
                $convertedPendingTotals['expense'] += $totals['expense'];
            } else {
                $convertedPendingTotals['income'] += convertCurrency($totals['income'], $currency, $targetCurrency, $currencySettings);
                $convertedPendingTotals['expense'] += convertCurrency($totals['expense'], $currency, $targetCurrency, $currencySettings);
            }
        }
        $convertedPendingTotals['balance'] = $convertedPendingTotals['income'] - $convertedPendingTotals['expense'];
        $pendingTotalsByCurrency = [$targetCurrency => $convertedPendingTotals];
    }
    
    $accountsWithTotals[] = [
        'id' => $account['id'],
        'name' => $account['name'],
        'icon' => $account['icon'],
        'type' => $account['type'],
        'balance' => $account['balance'], // Mantém o balance original da conta
        'totalsByCurrency' => $totalsByCurrency, // Totais filtrados
        'effectiveTotalsByCurrency' => $effectiveTotalsByCurrency, // Totais efetivadas (já tem)
        'pendingTotalsByCurrency' => $pendingTotalsByCurrency, // Totais não efetivadas (vai entrar)
        'primaryCurrency' => $primaryCurrency,
    ];
}

$title = 'Dashboard | NovixFin';
$pageStyles = ['styles/dashboard.css', 'styles/sheet.css'];

include __DIR__ . '/../includes/layout/header.php';

?>

<section class="space-y-6 dash-screen">
<?php
// ===================================================================
// Dados reais do miolo (read-only; nenhum cálculo de saldo/atomicidade
// é alterado — só religamos os mesmos dados já preparados acima).
// ===================================================================

// Rótulos PT por tipo de conta para a quebra do hero.
$typeLabels = [
    'checking' => 'Corrente', 'corrente' => 'Corrente',
    'savings' => 'Poupança', 'poupanca' => 'Poupança', 'poupança' => 'Poupança',
    'cash' => 'Dinheiro', 'dinheiro' => 'Dinheiro', 'wallet' => 'Carteira',
    'investment' => 'Investimento', 'investimento' => 'Investimento',
];

// Saldo consolidado + quebra por tipo (exclui cartão de crédito: é passivo e
// vive na tela Cartões).
$consolidatedBalance = 0.0;
$balanceByType = [];
foreach ($accounts as $acc) {
    if (($acc['type'] ?? '') === 'credit') {
        continue;
    }
    $bal = (float) ($acc['balance'] ?? 0);
    $consolidatedBalance += $bal;
    $tkey = $typeLabels[strtolower((string) ($acc['type'] ?? ''))]
        ?? ucfirst((string) ($acc['type'] ?? 'Conta'));
    $balanceByType[$tkey] = ($balanceByType[$tkey] ?? 0) + $bal;
}

// Totais do mês (último ponto do histórico mensal já preparado).
$monthIncome = 0.0;
$monthExpense = 0.0;
if (!empty($extendedHistoryData)) {
    $lastM = $extendedHistoryData[count($extendedHistoryData) - 1];
    $monthIncome = (float) ($lastM['income'] ?? 0);
    $monthExpense = (float) ($lastM['expense'] ?? 0);
}
$prevMonthExpense = (count($extendedHistoryData) >= 2)
    ? (float) ($extendedHistoryData[count($extendedHistoryData) - 2]['expense'] ?? 0)
    : 0.0;
$expDeltaPct = $prevMonthExpense > 0
    ? (($monthExpense - $prevMonthExpense) / $prevMonthExpense) * 100
    : null;

// Pendentes (não efetivadas): contagem + total de despesa na moeda principal.
$pendingCount = 0;
$pendingExpense = 0.0;
try {
    $pstmt = $pdo->prepare('SELECT COUNT(*) AS c,
        COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS exp
        FROM transactions
        WHERE user_id = :user_id AND is_effective = 0
        AND (currency = :pc OR currency IS NULL)');
    $pstmt->execute(['user_id' => $userId, 'pc' => $primaryCurrency]);
    $prow = $pstmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $pendingCount = (int) ($prow['c'] ?? 0);
    $pendingExpense = (float) ($prow['exp'] ?? 0);
} catch (Throwable $e) {
    // mantém zeros
}

// Metas em andamento (lista real para o painel 4b).
try {
    $goalsStmt = $pdo->prepare('SELECT id, COALESCE(title, name) AS title,
        target_amount, current_amount, deadline
        FROM goals WHERE user_id = :user_id AND (completed = 0 OR completed IS NULL)
        ORDER BY (deadline IS NULL), deadline ASC,
        (current_amount / NULLIF(target_amount, 0)) DESC LIMIT 4');
    $goalsStmt->execute(['user_id' => $userId]);
    $activeGoals = $goalsStmt->fetchAll();
} catch (Throwable $e) {
    $goalsStmt = $pdo->prepare('SELECT id, name AS title, target_amount, current_amount, deadline
        FROM goals WHERE user_id = :user_id AND (completed = 0 OR completed IS NULL)
        ORDER BY (deadline IS NULL), deadline ASC LIMIT 4');
    $goalsStmt->execute(['user_id' => $userId]);
    $activeGoals = $goalsStmt->fetchAll();
}

// Média móvel dos últimos 3 meses — usada APENAS para o nível de confiança
// (o quanto o gasto do mês foge da média histórica).

// PREVISÃO DE SALDO = saldo realizado + líquido do que AINDA falta efetivar.
//
// Correção de dupla contagem: antes, $projNet era a média móvel acima
// ($avgIncomeF - $avgExpenseF). Como $extendedHistoryData inclui o mês corrente
// com TODOS os lançamentos (o filtro padrão is_effective é null), essa média
// carregava também a parte JÁ EFETIVADA do mês — que já está dentro de
// $consolidatedBalance (Σ accounts.balance). Somar os dois contava os
// efetivados duas vezes (com 1 mês de histórico a média degenera no líquido do
// mês inteiro). Agora $projNet contém SOMENTE os pendentes. Ver
// includes/forecast.php e tests/forecast_balance.php.
// Regra: SOMENTE o que já está registrado no banco como pendente. Inclui
// parcelas já lançadas (ex.: a 1/6 que existe) e pendentes de meses futuros;
// NÃO projeta parcelas/recorrências que ainda não viraram transação.
$pendingIncomeTotal = 0.0;
$pendingExpenseTotal = 0.0;
try {
    $projStmt = $pdo->prepare('
        SELECT COALESCE(SUM(CASE WHEN type = "income"  THEN amount ELSE 0 END), 0) AS inc,
               COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS exp
          FROM transactions
         WHERE user_id = :user_id
           AND is_effective = 0
           AND (currency = :pc OR currency IS NULL)');
    $projStmt->execute(['user_id' => $userId, 'pc' => $primaryCurrency]);
    $projRow = $projStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $pendingIncomeTotal = (float) ($projRow['inc'] ?? 0);
    $pendingExpenseTotal = (float) ($projRow['exp'] ?? 0);
} catch (Throwable $e) {
    $pendingIncomeTotal = 0.0;
    $pendingExpenseTotal = 0.0;
}
$projNet = novix_pending_net_totals($pendingIncomeTotal, $pendingExpenseTotal);
$previstoSaldo = novix_forecast_balance($consolidatedBalance, $projNet);

$monthLabelPt = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho',
    'Agosto','Setembro','Outubro','Novembro','Dezembro'][(int) date('n') - 1];
$monthLabelShort = strtolower($monthLabelPt);
$hasDashData = !empty($accounts);
?>

<?php if (!$hasDashData) : ?>
    <!-- ESTADO VAZIO: usuária nova, ainda sem contas -->
    <div style="background:var(--surface); border:1px solid var(--line-soft); border-radius:calc(var(--r) + 10px); padding:52px 32px; text-align:center;">
        <span style="width:60px; height:60px; margin:0 auto; display:grid; place-items:center; background:var(--grad); clip-path:var(--cut); border-radius:var(--r);"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="var(--accent-ink)" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18 L10 8 L14 14 L20 5"></path></svg></span>
        <div style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:24px; color:var(--ink); margin-top:18px;">Boas-vindas ao NovixFin</div>
        <div style="font-size:14.5px; color:var(--muted); margin-top:8px; line-height:1.55; max-width:440px; margin-left:auto; margin-right:auto;">Seu dinheiro ainda não tem contorno por aqui. Tres passos e o dashboard ganha vida:</div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:14px; max-width:760px; margin:28px auto 0; text-align:left;">
            <div style="border:1px solid var(--line-soft); border-radius:var(--r); padding:18px; background:var(--bg);"><span style="font-family:var(--f-m); font-size:11px; font-weight:700; letter-spacing:.14em; color:var(--accent);">PASSO 1</span><div style="font-size:15px; font-weight:700; color:var(--ink); margin-top:8px;">Crie sua primeira conta</div><div style="font-size:12.5px; color:var(--muted); margin-top:4px; line-height:1.5;">Corrente, poupança ou dinheiro vivo.</div></div>
            <div style="border:1px solid var(--line-soft); border-radius:var(--r); padding:18px; background:var(--bg);"><span style="font-family:var(--f-m); font-size:11px; font-weight:700; letter-spacing:.14em; color:var(--accent);">PASSO 2</span><div style="font-size:15px; font-weight:700; color:var(--ink); margin-top:8px;">Organize as categorias</div><div style="font-size:12.5px; color:var(--muted); margin-top:4px; line-height:1.5;">As essenciais já vem prontas; ajuste as suas.</div></div>
            <div style="border:1px solid var(--line-soft); border-radius:var(--r); padding:18px; background:var(--bg);"><span style="font-family:var(--f-m); font-size:11px; font-weight:700; letter-spacing:.14em; color:var(--accent);">PASSO 3</span><div style="font-size:15px; font-weight:700; color:var(--ink); margin-top:8px;">Registre um lançamento</div><div style="font-size:12.5px; color:var(--muted); margin-top:4px; line-height:1.5;">Pelo botao "Lançar", em segundos.</div></div>
        </div>
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-top:26px;">
            <a href="/public/accounts.php" style="background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14px; padding:12px 22px; clip-path:var(--cut); border-radius:var(--r-btn); text-decoration:none;">Criar primeira conta</a>
            <a href="/public/import.php" style="background:var(--surface); color:var(--accent); font-weight:700; font-size:14px; padding:11px 21px; border:1.5px solid var(--accent); border-radius:var(--r-btn); text-decoration:none;">Importar extrato (CSV)</a>
        </div>
    </div>
<?php else : ?>

    <!-- =========================================================
         LINHA 1 — HERO Saldo consolidado + 3 KPIs
         ========================================================= -->
    <div class="dash-stats">
        <!-- HERO: saldo consolidado -->
        <div style="background:var(--grad); color:var(--stage-ink); border-radius:var(--r); clip-path:var(--cut-card); padding:20px 22px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:12.5px; font-weight:600; color:var(--stage-muted);">Saldo consolidado</span>
                <button id="balance-visibility-btn" type="button" title="Mostrar ou ocultar saldos" aria-label="Mostrar ou ocultar saldos" style="margin-left:auto; width:30px; height:30px; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--stage-muted); border:1px solid var(--stage-line); background:none; cursor:pointer;">
                    <span id="balance-visibility-icon" style="font-size:14px; line-height:1;">&#128065;</span>
                    <span id="balance-visibility-text" style="position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap;">Ocultar saldos</span>
                </button>
            </div>
            <div style="font-family:var(--f-num); font-size:33px; font-weight:700; letter-spacing:-.02em; font-variant-numeric:tabular-nums; margin-top:6px;" data-balance><?php echo formatCurrency($consolidatedBalance, $primaryCurrency); ?></div>
            <?php if (!empty($balanceByType)) : ?>
            <div style="display:flex; gap:16px; margin-top:12px; font-size:12.5px; color:var(--stage-muted); flex-wrap:wrap;">
                <?php foreach ($balanceByType as $lbl => $val) : ?>
                    <span><?php echo sanitize($lbl); ?> <b style="color:var(--stage-ink); font-family:var(--f-m); font-weight:700;" data-balance><?php echo formatCurrency($val, $primaryCurrency); ?></b></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php
            $rateChips = [];
            foreach (['USD', 'EUR'] as $fc) {
                if ($fc === $primaryCurrency) {
                    continue;
                }
                $rate = function_exists('convertCurrency')
                    ? (float) convertCurrency(1.0, $fc, $primaryCurrency, $currencySettings)
                    : 0.0;
                if ($rate > 0) {
                    $rateChips[$fc] = $rate;
                }
            }
            ?>
            <?php if (!empty($rateChips)) : ?>
            <div style="display:flex; align-items:center; gap:8px; margin-top:12px; flex-wrap:wrap;">
                <?php foreach ($rateChips as $fc => $rate) : ?>
                    <span style="padding:4px 10px; border-radius:999px; border:1px solid var(--stage-line); font-family:var(--f-m); font-size:11.5px; color:var(--stage-ink);"><?php echo $fc; ?> <?php echo formatCurrency($rate, $primaryCurrency); ?></span>
                <?php endforeach; ?>
                <span style="font-size:11px; color:var(--stage-muted);">câmbio manual</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- KPI: Receitas do mês -->
        <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:20px 22px;">
            <div style="display:flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--muted);"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="var(--pos)" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M17 17H7V7"></path><path d="M17 7 7 17"></path></svg>Receitas do mês</div>
            <div style="font-family:var(--f-num); font-size:25px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--pos); margin-top:9px;" data-balance><?php echo formatCurrency($monthIncome, $primaryCurrency); ?></div>
            <div style="font-size:12.5px; color:var(--muted); margin-top:9px;">Entradas de <?php echo $monthLabelShort; ?></div>
        </div>

        <!-- KPI: Despesas do mês -->
        <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:20px 22px;">
            <div style="display:flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--muted);"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="var(--neg)" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg>Despesas do mês</div>
            <div style="font-family:var(--f-num); font-size:25px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink); margin-top:9px;" data-balance><?php echo formatCurrency($monthExpense, $primaryCurrency); ?></div>
            <?php if ($expDeltaPct !== null) : ?>
                <div style="display:inline-flex; align-items:center; gap:5px; margin-top:9px; font-size:12.5px; font-weight:700; color:<?php echo $expDeltaPct > 0 ? 'var(--neg)' : 'var(--pos)'; ?>;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><?php echo $expDeltaPct > 0 ? '<path d="M7 7h10v10"></path><path d="M7 17 17 7"></path>' : '<path d="M17 17H7V7"></path><path d="M17 7 7 17"></path>'; ?></svg>
                    <?php echo ($expDeltaPct > 0 ? '+' : '') . number_format($expDeltaPct, 0); ?>% vs mês anterior
                </div>
            <?php else : ?>
                <div style="font-size:12.5px; color:var(--muted); margin-top:9px;">Saídas de <?php echo $monthLabelShort; ?></div>
            <?php endif; ?>
        </div>

        <!-- KPI: Pendentes -->
        <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:20px 22px;">
            <div style="display:flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--muted);"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="var(--gold)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3"></path></svg>Pendentes</div>
            <div style="font-family:var(--f-num); font-size:25px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--gold); margin-top:9px;" data-balance><?php echo formatCurrency($pendingExpense, $primaryCurrency); ?></div>
            <div style="font-size:12.5px; color:var(--muted); margin-top:9px;"><?php echo $pendingCount; ?> <?php echo $pendingCount === 1 ? 'lançamento a efetivar' : 'lançamentos a efetivar'; ?></div>
        </div>
    </div>

    <!-- =========================================================
         ABAS internas: Visão geral / Kanban de categorias
         ========================================================= -->
    <div style="display:flex; align-items:center; gap:2px; border-bottom:1px solid var(--line-soft);">
        <button type="button" class="dash-tab" data-tab="visao" aria-pressed="true" style="padding:12px 18px; font-size:14px; font-weight:700; color:var(--accent); border-bottom:2px solid var(--accent); margin-bottom:-1px; transition:color .16s ease;">Visão geral</button>
        <button type="button" class="dash-tab" data-tab="kanban" aria-pressed="false" style="padding:12px 18px; font-size:14px; font-weight:700; color:var(--muted); border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .16s ease;">Kanban de categorias</button>
        <button type="button" class="dash-tab" data-tab="planilha" aria-pressed="false" style="padding:12px 18px; font-size:14px; font-weight:700; color:var(--muted); border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .16s ease;">Planilha</button>
        <a href="/public/history.php" style="margin-left:auto; font-size:13px; font-weight:700; color:var(--accent); padding:12px 4px; text-decoration:none;">Ver histórico completo &rarr;</a>
    </div>

    <!-- PAINEL: Visão geral -->
    <div class="dash-tabpanel" data-panel="visao">
        <div class="dash-dgrid">
            <!-- Previsão de saldo (gráfico real via forecast-chart.js, lendo tokens) -->
            <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px 22px 16px; min-width:0;">
                <div style="display:flex; align-items:baseline; gap:8px;">
                    <span style="font-size:12.5px; font-weight:600; color:var(--muted);">Saldo final previsto</span>
                    <span id="forecastSimTag" class="dash-sim-tag" hidden>simulação</span>
                </div>
                <div id="forecastHero" style="font-family:var(--f-num); font-size:30px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink); margin-top:4px;" data-balance><?php echo formatCurrency($previstoSaldo, $primaryCurrency); ?></div>

                <!-- Decomposição: é ela que explica o número acima. -->
                <div id="forecastBreakdown" style="display:flex; flex-direction:column; gap:7px; margin-top:14px; padding-top:14px; border-top:1px solid var(--line-soft);">
                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px;">
                        <span style="font-size:12.5px; color:var(--muted);">Saldo hoje</span>
                        <span id="forecastToday" style="font-family:var(--f-num); font-size:13.5px; font-weight:600; font-variant-numeric:tabular-nums; color:var(--ink);" data-balance><?php echo formatCurrency($consolidatedBalance, $primaryCurrency); ?></span>
                    </div>
                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px;">
                        <span style="font-size:12.5px; color:var(--muted);">+ a receber</span>
                        <span id="forecastIncoming" style="font-family:var(--f-num); font-size:13.5px; font-weight:600; font-variant-numeric:tabular-nums; color:var(--pos);" data-balance><?php echo formatCurrency($pendingIncomeTotal, $primaryCurrency); ?></span>
                    </div>
                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px;">
                        <span style="font-size:12.5px; color:var(--muted);">&minus; a pagar</span>
                        <span id="forecastOutgoing" style="font-family:var(--f-num); font-size:13.5px; font-weight:600; font-variant-numeric:tabular-nums; color:var(--neg);" data-balance><?php echo formatCurrency($pendingExpenseTotal, $primaryCurrency); ?></span>
                    </div>
                    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding-top:7px; border-top:1px solid var(--line-soft);">
                        <span style="font-size:12.5px; font-weight:700; color:var(--ink);">Saldo final</span>
                        <span id="forecastFinal" style="font-family:var(--f-num); font-size:15px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink);" data-balance><?php echo formatCurrency($previstoSaldo, $primaryCurrency); ?></span>
                    </div>
                </div>

                <!-- Aviso de simulação: só aparece com ≥1 transação oculta. -->
                <div id="forecastSimNotice" class="dash-sim-notice" hidden>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:none;"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5"></path><path d="M12 16.5v.01"></path></svg>
                    <span>Simulação &middot; <strong id="forecastSimCount">0 ocultas</strong> &middot; valor não é o saldo real</span>
                    <button type="button" id="forecastSimReset" class="dash-sim-reset">Restaurar</button>
                </div>
                <div style="height:230px; position:relative; margin-top:20px;">
                    <canvas id="chartForecast"></canvas>
                </div>
                <div style="font-size:12px; color:var(--muted); margin-top:12px;">Conta simples: o que você tem hoje mais o que já lançou como pendente. Não projeta gastos futuros nem parcelas que ainda não foram lançadas.</div>
            </div>

            <!-- Metas em andamento -->
            <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px; display:flex; flex-direction:column; gap:18px; min-width:0;">
                <div style="display:flex; align-items:baseline; justify-content:space-between; gap:10px;"><span style="font-size:12.5px; font-weight:600; color:var(--muted);">Metas em andamento</span><a href="/public/goals.php" style="font-size:12.5px; font-weight:700; color:var(--accent); text-decoration:none;">Todas &rarr;</a></div>
                <?php if (!empty($activeGoals)) : ?>
                    <?php foreach ($activeGoals as $goal) :
                        $gTarget = (float) ($goal['target_amount'] ?? 0);
                        $gCurrent = (float) ($goal['current_amount'] ?? 0);
                        $gPct = $gTarget > 0 ? min(100, ($gCurrent / $gTarget) * 100) : 0;
                        $gDeadline = !empty($goal['deadline']) ? date('d/m/Y', strtotime($goal['deadline'])) : 'sem prazo';
                    ?>
                    <div style="display:flex; flex-direction:column; gap:9px;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; gap:10px;"><span style="font-size:13.5px; font-weight:700; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($goal['title'] ?? 'Meta'); ?></span><span style="font-family:var(--f-m); font-size:12px; font-weight:700; color:var(--accent); flex:none;"><?php echo number_format($gPct, 0); ?>%</span></div>
                        <div style="height:8px; border-radius:999px; background:var(--line-soft); overflow:hidden;"><span style="display:block; height:100%; width:<?php echo $gPct; ?>%; background:var(--grad); border-radius:999px;"></span></div>
                        <div style="font-family:var(--f-num); font-size:12px; color:var(--muted); font-variant-numeric:tabular-nums;"><span data-balance><?php echo formatCurrency($gCurrent, $primaryCurrency); ?></span> <span style="opacity:.7;">/ <span data-balance><?php echo formatCurrency($gTarget, $primaryCurrency); ?></span></span> &middot; <?php echo $gDeadline; ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div style="font-size:13px; color:var(--muted); line-height:1.55;">Nenhuma meta em andamento. Crie uma para acompanhar seu progresso financeiro por aqui.</div>
                <?php endif; ?>
                <a href="/public/goals.php" style="margin-top:auto; padding:11px; border:1px dashed var(--line); border-radius:var(--r-btn); color:var(--muted); font-size:13px; font-weight:600; text-align:center; text-decoration:none;">+ Nova meta</a>
            </div>
        </div>

        <!-- =====================================================
             MOVIMENTAÇÕES (resumo recente, agrupado por dia)
             Reaproveita $recentTransactions (mesmo array do rodapé).
             Pendentes trazem "Efetivar" na linha, que reusa o handler
             update.php (mesmo que a edição usa) com is_effective=1.
             ===================================================== -->
        <?php
        if (!isset($recentTransactions)) {
            $recentTransactions = $transactionRepository->listByUser($userId ?? 0, null, null, $filterIsEffective, $filterIsRecurring);
        }

        // "Carregar mais": as linhas são renderizadas TODAS no servidor (até o
        // teto abaixo) e reveladas em lotes pelo cliente. Escolha deliberada —
        // $recentTransactions já está inteiro em memória (o mesmo array
        // alimenta o JSON do modal de edição), então paginar por requisição
        // custaria um endpoint novo sem economizar consulta alguma. O teto
        // existe só para não montar um DOM gigante em conta antiga; acima dele
        // o caminho continua sendo o histórico completo.
        $recentBatchSize = 8;
        $recentRenderCap = 200;
        $recentTransactionsRendered = array_slice($recentTransactions, 0, $recentRenderCap);
        $recentTotalCount = count($recentTransactions);
        $recentRenderedCount = count($recentTransactionsRendered);
        // Mantido pelo nome antigo: o bloco do rodapé usa este array para o
        // JSON do modal de edição, que precisa cobrir TODA linha renderizada
        // (senão editar uma linha revelada depois não acharia os dados).
        $recentTransactionsSlice = $recentTransactionsRendered;

        // Elegibilidade da SIMULAÇÃO (olhinho). Só pode entrar na simulação a
        // transação que realmente compõe a previsão:
        //   - pendente: entra em $pendingIncomeTotal/$pendingExpenseTotal, cuja
        //     consulta filtra `currency = primária OR NULL`;
        //   - efetivada: só está dentro de $consolidatedBalance se a conta dela
        //     for uma conta não-crédito (cartão é passivo e fica fora da soma).
        // O que não se encaixa mantém o olho desabilitado, para a simulação
        // nunca mexer num número que a transação não formou.
        $nonCreditAccountIds = [];
        foreach ($accounts as $accForSim) {
            if (($accForSim['type'] ?? '') !== 'credit') {
                $nonCreditAccountIds[(int) $accForSim['id']] = true;
            }
        }

        // Agrupa por dia mantendo a ordem (já vem por data desc). $seq é o
        // índice corrido da transação — o cliente revela tudo com seq < limite,
        // e o cabeçalho do dia herda o seq da sua primeira linha para aparecer
        // junto com ela.
        $groupedRecent = [];
        $seq = 0;
        foreach ($recentTransactionsRendered as $tx) {
            $dayKey = date('Y-m-d', strtotime($tx['transaction_date']));
            if (!isset($groupedRecent[$dayKey])) {
                $groupedRecent[$dayKey] = ['rows' => [], 'subtotal' => 0.0, 'seq' => $seq];
            }
            $signed = ($tx['type'] === 'income' ? 1 : -1) * (float) $tx['amount'];
            $tx['_seq'] = $seq;
            $groupedRecent[$dayKey]['rows'][] = $tx;
            $groupedRecent[$dayKey]['subtotal'] += $signed;
            $seq++;
        }
        $weekdayShort = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        ?>
        <div style="background:var(--surface); border:1px solid var(--line-soft); border-radius:calc(var(--r) + 8px); overflow:hidden; margin-top:18px;">
            <div style="display:flex; align-items:baseline; gap:9px; padding:16px 22px 12px; border-bottom:1px solid var(--line-soft);">
                <span style="font-size:13.5px; font-weight:700; color:var(--ink);">Movimentações recentes</span>
                <a href="/public/history.php" style="margin-left:auto; font-size:13px; font-weight:700; color:var(--accent); text-decoration:none;">Ver histórico completo &rarr;</a>
            </div>
            <?php if (empty($recentTransactionsSlice)) : ?>
                <div style="padding:34px 22px; text-align:center; font-size:13px; color:var(--muted);">Nenhuma movimentação recente. Use o botão Lançar para registrar a primeira.</div>
            <?php else : ?>
                <?php foreach ($groupedRecent as $dayKey => $group) :
                    $dayTs = strtotime($dayKey);
                    $subColor = $group['subtotal'] >= 0 ? 'var(--pos)' : 'var(--neg)';
                ?>
                <div class="dash-recent-item" data-recent-seq="<?php echo (int) $group['seq']; ?>"<?php echo $group['seq'] >= $recentBatchSize ? ' hidden' : ''; ?> style="display:flex; align-items:baseline; gap:9px; padding:12px 22px; background:color-mix(in srgb, var(--ink) 2.5%, var(--surface)); border-bottom:1px solid var(--line-soft);">
                    <span style="font-size:12.5px; font-weight:700; color:var(--ink);"><?php echo sanitize($weekdayShort[(int) date('w', $dayTs)]); ?>, <?php echo date('d/m', $dayTs); ?></span>
                    <span style="margin-left:auto; font-size:11px; letter-spacing:.05em; text-transform:uppercase; color:var(--muted);">subtotal</span>
                    <span style="font-family:var(--f-num); font-size:12.5px; font-weight:700; font-variant-numeric:tabular-nums; color:<?php echo $subColor; ?>;" data-balance><?php echo formatCurrency($group['subtotal'], $primaryCurrency); ?></span>
                </div>
                <?php foreach ($group['rows'] as $tx) :
                    $isIncome = $tx['type'] === 'income';
                    $isEffective = (bool) ($tx['is_effective'] ?? true);
                    $catName = (string) ($tx['category_name'] ?? '');
                    $initial = $catName !== '' ? strtoupper(substr($catName, 0, 1)) : '.';
                    $accent = $isIncome ? 'var(--pos)' : 'var(--neg)';
                    $desc = ($tx['description'] ?? '') !== '' ? $tx['description'] : $catName;
                    $canEffectivate = !$isEffective && (int) ($tx['account_id'] ?? 0) > 0;
                    $txCurrency = (string) ($tx['currency'] ?? 'BRL');
                    $simCurrencyOk = ($tx['currency'] ?? null) === null || $txCurrency === $primaryCurrency;
                    $simEligible = $isEffective
                        ? ($simCurrencyOk && isset($nonCreditAccountIds[(int) ($tx['account_id'] ?? 0)]))
                        : $simCurrencyOk;
                    $simTitle = $simEligible
                        ? 'Ocultar da previsão de saldo (simulação — nada é salvo)'
                        : 'Esta transação não entra na previsão de saldo, então não pode ser simulada.';
                ?>
                <div class="dash-recent-item dash-recent-row" data-recent-seq="<?php echo (int) $tx['_seq']; ?>"<?php echo $tx['_seq'] >= $recentBatchSize ? ' hidden' : ''; ?> data-sim-id="<?php echo (int) $tx['id']; ?>" data-sim-kind="<?php echo $isEffective ? 'effective' : 'pending'; ?>" data-sim-type="<?php echo sanitize($tx['type']); ?>" data-sim-amount="<?php echo (float) $tx['amount']; ?>" data-sim-eligible="<?php echo $simEligible ? '1' : '0'; ?>" style="display:flex; align-items:center; gap:14px; padding:13px 22px; border-bottom:1px solid var(--line-soft); flex-wrap:wrap;">
                    <span style="display:flex; align-items:center; gap:13px; min-width:0; flex:1 1 220px;">
                        <span style="width:30px; height:30px; flex:none; display:grid; place-items:center; transform:rotate(45deg); border-radius:6px; background:color-mix(in srgb, <?php echo $accent; ?> 16%, transparent);"><span style="transform:rotate(-45deg); font-size:11.5px; font-weight:800; color:<?php echo $accent; ?>;"><?php echo sanitize($initial); ?></span></span>
                        <span style="min-width:0;">
                            <span style="display:block; font-size:14px; font-weight:600; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($desc); ?></span>
                            <span style="display:block; font-size:12px; color:var(--muted);"><?php echo sanitize($catName); ?></span>
                        </span>
                    </span>
                    <?php if (!empty($tx['account_name'])) : ?>
                    <span style="font-size:12.5px; color:var(--muted); flex:0 0 auto; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px;"><?php echo sanitize($tx['account_name']); ?></span>
                    <?php endif; ?>
                    <span style="flex:0 0 auto;">
                        <?php if ($isEffective) : ?>
                            <span title="Efetivada" style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; background:color-mix(in srgb, var(--pos) 12%, transparent); color:var(--pos);"><span style="width:5px; height:5px; border-radius:50%; background:var(--pos);"></span>Efetivada</span>
                        <?php else : ?>
                            <span title="Pendente" style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; background:color-mix(in srgb, var(--gold) 14%, transparent); color:var(--gold);"><span style="width:5px; height:5px; border-radius:50%; background:var(--gold);"></span>Pendente</span>
                        <?php endif; ?>
                    </span>
                    <span style="margin-left:auto; text-align:right; font-family:var(--f-num); font-size:14.5px; font-weight:700; font-variant-numeric:tabular-nums; color:<?php echo $accent; ?>;" data-balance><?php echo $isIncome ? '+' : '-'; ?> <?php echo formatCurrency((float) $tx['amount'], $tx['currency'] ?? 'BRL'); ?></span>
                    <span style="flex:0 0 auto; display:flex; align-items:center; gap:8px;">
                        <!-- Olhinho de SIMULAÇÃO: tira a transação só do cálculo da
                             previsão de saldo, em memória. Não salva nada, não some
                             com a linha e não toca no saldo real. -->
                        <button type="button" class="dash-sim-eye" data-sim-eye aria-pressed="false" <?php echo $simEligible ? '' : 'disabled '; ?>title="<?php echo sanitize($simTitle); ?>" aria-label="<?php echo sanitize($simTitle); ?>">
                            <svg class="dash-sim-eye-on" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="dash-sim-eye-off" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M9.9 5.2A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.3 6.5A17.4 17.4 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 4.2-.9"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M3 3l18 18"></path></svg>
                        </button>
                        <?php if ($canEffectivate) : ?>
                        <form method="POST" action="/actions/transactions/update.php" style="display:inline;">
                            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="redirect_to" value="dashboard.php">
                            <input type="hidden" name="id" value="<?php echo (int) $tx['id']; ?>">
                            <input type="hidden" name="account_id" value="<?php echo (int) $tx['account_id']; ?>">
                            <input type="hidden" name="type" value="<?php echo sanitize($tx['type']); ?>">
                            <input type="hidden" name="category_id" value="<?php echo (int) $tx['category_id']; ?>">
                            <input type="hidden" name="amount" value="<?php echo (float) $tx['amount']; ?>">
                            <input type="hidden" name="currency" value="<?php echo sanitize($tx['currency'] ?? 'BRL'); ?>">
                            <input type="hidden" name="description" value="<?php echo sanitize($tx['description'] ?? ''); ?>">
                            <input type="hidden" name="transaction_date" value="<?php echo sanitize(date('Y-m-d', strtotime($tx['transaction_date']))); ?>">
                            <input type="hidden" name="is_effective" value="1">
                            <?php if (!empty($tx['is_recurring'])) : ?>
                                <input type="hidden" name="is_recurring" value="1">
                                <input type="hidden" name="recurrence_type" value="<?php echo sanitize($tx['recurrence_type'] ?? 'none'); ?>">
                                <?php if (isset($tx['recurrence_day']) && $tx['recurrence_day'] !== null && $tx['recurrence_day'] !== '') : ?>
                                    <input type="hidden" name="recurrence_day" value="<?php echo (int) $tx['recurrence_day']; ?>">
                                <?php endif; ?>
                                <?php if (!empty($tx['recurrence_end_date'])) : ?>
                                    <input type="hidden" name="recurrence_end_date" value="<?php echo sanitize(date('Y-m-d', strtotime($tx['recurrence_end_date']))); ?>">
                                <?php endif; ?>
                                <?php if (!empty($tx['recurrence_total_occurrences'])) : ?>
                                    <input type="hidden" name="recurrence_total_occurrences" value="<?php echo (int) $tx['recurrence_total_occurrences']; ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                            <button type="submit" title="Marcar como efetivada" style="display:inline-flex; align-items:center; gap:6px; padding:7px 11px; border-radius:var(--r-chip); border:1px solid color-mix(in srgb, var(--pos) 40%, transparent); background:color-mix(in srgb, var(--pos) 7%, transparent); color:var(--pos); font-size:12px; font-weight:700; cursor:pointer;"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>Efetivar</button>
                        </form>
                        <?php endif; ?>
                        <button type="button" data-edit="<?php echo (int) $tx['id']; ?>" title="Editar lançamento" aria-label="Editar lançamento" style="width:30px; height:30px; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><circle cx="12" cy="5.5" r="1.6"></circle><circle cx="12" cy="12" r="1.6"></circle><circle cx="12" cy="18.5" r="1.6"></circle></svg></button>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if ($recentRenderedCount > $recentBatchSize) : ?>
                <div style="display:flex; justify-content:center; padding:14px 22px 4px;">
                    <button type="button" id="dashRecentMore" class="dash-recent-more"
                        data-batch="<?php echo (int) $recentBatchSize; ?>"
                        data-total="<?php echo (int) $recentRenderedCount; ?>"
                        data-shown="<?php echo (int) $recentBatchSize; ?>">Carregar mais<span id="dashRecentMoreCount" style="opacity:.7; font-weight:600;"> (<?php echo (int) ($recentRenderedCount - $recentBatchSize); ?> restantes)</span></button>
                </div>
                <?php endif; ?>
                <div style="display:flex; align-items:center; gap:10px; padding:12px 22px; flex-wrap:wrap;">
                    <span style="font-size:12.5px; color:var(--muted);">Pendentes podem ser efetivadas direto na linha. O olho tira a linha só da previsão &mdash; é simulação, nada é salvo.</span>
                    <?php if ($recentTotalCount > $recentRenderedCount) : ?>
                    <span style="font-size:12px; color:var(--muted); opacity:.8;">Mostrando as <?php echo (int) $recentRenderedCount; ?> mais recentes de <?php echo (int) $recentTotalCount; ?>.</span>
                    <?php endif; ?>
                    <a href="/public/history.php" style="margin-left:auto; font-size:13px; font-weight:700; color:var(--accent); text-decoration:none;">Ver histórico completo &rarr;</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- =====================================================
             CONQUISTAS RECENTES (resumo) — dados REAIS de gamificação
             (users.points / users.badges + GamificationService), a mesma
             fonte do Perfil. Ícones em SVG, sem emoji.
             ===================================================== -->
        <?php
        $dashPoints = (int) ($user['points'] ?? 0);
        $dashEarned = [];
        if (!empty($user['badges']) && is_string($user['badges'])) {
            $decoded = json_decode($user['badges'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $slug) {
                    if (is_string($slug)) {
                        $dashEarned[$slug] = true;
                    }
                }
            }
        }
        $badgeMeta = [
            'first_transaction' => ['name' => 'Primeiro lançamento', 'color' => 'var(--accent)',   'svg' => '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4.5"></circle><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"></circle>'],
            'ten_transactions'  => ['name' => 'Pegando o ritmo',    'color' => 'var(--pos)',      'svg' => '<path d="M7 20V11"></path><path d="M12 20V4"></path><path d="M17 20v-6"></path><path d="M3 20h18"></path>'],
            'goal_complete'     => ['name' => 'Constância',         'color' => 'var(--gold)',     'svg' => '<circle cx="12" cy="14" r="6"></circle><path d="M9 9 7 3h10l-2 6"></path>'],
            'dedicated'         => ['name' => 'Dedicação',          'color' => 'var(--accent-2)', 'svg' => '<path d="M6 3h12l3 6-9 12L3 9z"></path><path d="M3 9h18"></path><path d="M12 3 9 9l3 12 3-12z"></path>'],
            'legendary'         => ['name' => 'Lendário',           'color' => 'var(--gold)',     'svg' => '<path d="M3 7l4 4 5-7 5 7 4-4v11H3z"></path><path d="M3 20h18"></path>'],
        ];
        $dashBadges = [];
        foreach (\Novix\Services\GamificationService::BADGE_MILESTONES as $threshold => $slug) {
            $earned = isset($dashEarned[$slug]) || $dashPoints >= (int) $threshold;
            if (!$earned) {
                continue;
            }
            $meta = $badgeMeta[$slug] ?? ['name' => $slug, 'color' => 'var(--accent)', 'svg' => '<path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v4a5 5 0 0 1-10 0z"></path>'];
            $dashBadges[] = [
                'name' => $meta['name'],
                'color' => $meta['color'],
                'svg' => $meta['svg'],
                'criterio' => number_format((int) $threshold, 0, ',', '.') . ' pontos',
            ];
        }
        // Mais recentes primeiro (limiares maiores no topo), até 4.
        $dashBadges = array_slice(array_reverse($dashBadges), 0, 4);
        ?>
        <div style="display:flex; flex-direction:column; gap:12px; margin-top:18px;">
            <div style="display:flex; align-items:baseline; gap:10px;">
                <span style="font-size:13.5px; font-weight:700; color:var(--ink);">Conquistas recentes</span>
                <a href="/public/profile.php" style="margin-left:auto; font-size:13px; font-weight:700; color:var(--accent); text-decoration:none;">Ver perfil &rarr;</a>
            </div>
            <?php if (!empty($dashBadges)) : ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px;">
                <?php foreach ($dashBadges as $badge) : ?>
                <div style="background:var(--surface); border:1px solid color-mix(in srgb, <?php echo $badge['color']; ?> 22%, var(--line-soft)); box-shadow:var(--card-sh); border-radius:var(--r); padding:16px 18px; display:flex; gap:13px; align-items:flex-start;">
                    <span style="width:38px; height:38px; flex:none; display:grid; place-items:center; border-radius:11px; background:color-mix(in srgb, <?php echo $badge['color']; ?> 12%, transparent); color:<?php echo $badge['color']; ?>;"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $badge['svg']; ?></svg></span>
                    <div style="min-width:0;">
                        <div style="font-family:var(--f-m); font-size:9.5px; letter-spacing:.24em; text-transform:uppercase; font-weight:700; color:<?php echo $badge['color']; ?>;">Emblema</div>
                        <div style="font-size:14.5px; font-weight:800; color:var(--ink); margin-top:3px;"><?php echo sanitize($badge['name']); ?></div>
                        <div style="font-size:12px; color:var(--muted); margin-top:2px; line-height:1.5;">Concedida ao alcançar <?php echo sanitize($badge['criterio']); ?>.</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div style="background:var(--surface); border:1px solid var(--line-soft); border-radius:var(--r); padding:18px; font-size:13px; color:var(--muted); line-height:1.55;">Nenhuma conquista ainda. Registre lançamentos e alcance metas para desbloquear emblemas &mdash; eles aparecem aqui e no Perfil.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAINEL: Kanban de categorias -->
    <div class="dash-tabpanel" data-panel="kanban" hidden>
        <div style="display:flex; align-items:baseline; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
            <span style="font-size:13px; color:var(--muted);">Arraste categorias entre colunas para classificar seu gasto &mdash; salvo automaticamente.</span>
            <span style="margin-left:auto; font-family:var(--f-m); font-size:11.5px; color:var(--muted);"><?php echo $monthLabelShort; ?> vs mês anterior</span>
        </div>
        <!-- Botao exigido por kanban.js para inicializar; controlado pelas abas, mantido oculto. -->
        <button type="button" id="toggleKanbanView" style="display:none;"><span id="kanbanViewText">Ocultar Kanban</span></button>
        <div id="kanbanContainer">
            <div id="kanbanBoard" class="dashboard-kanban-board dash-kan">
                <!-- Coluna: Fixas / Recorrentes -->
                <div style="background:color-mix(in srgb, var(--ink) 3%, var(--bg)); border:1px solid var(--line-soft); border-radius:calc(var(--r) + 6px); padding:12px; display:flex; flex-direction:column; gap:10px; min-height:230px;">
                    <div style="display:flex; align-items:center; gap:8px; padding:2px 4px;">
                        <span style="width:8px; height:8px; border-radius:50%; background:var(--accent); flex:none;"></span>
                        <span style="font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--ink);">Fixas / Recorrentes</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px; min-height:180px;" data-column="recurring" ondrop="drop(event)" ondragover="allowDrop(event)"></div>
                </div>
                <!-- Coluna: Essenciais -->
                <div style="background:color-mix(in srgb, var(--ink) 3%, var(--bg)); border:1px solid var(--line-soft); border-radius:calc(var(--r) + 6px); padding:12px; display:flex; flex-direction:column; gap:10px; min-height:230px;">
                    <div style="display:flex; align-items:center; gap:8px; padding:2px 4px;">
                        <span style="width:8px; height:8px; border-radius:50%; background:var(--neg); flex:none;"></span>
                        <span style="font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--ink);">Essenciais</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px; min-height:180px;" data-column="essential" ondrop="drop(event)" ondragover="allowDrop(event)"></div>
                </div>
                <!-- Coluna: Opcionais -->
                <div style="background:color-mix(in srgb, var(--ink) 3%, var(--bg)); border:1px solid var(--line-soft); border-radius:calc(var(--r) + 6px); padding:12px; display:flex; flex-direction:column; gap:10px; min-height:230px;">
                    <div style="display:flex; align-items:center; gap:8px; padding:2px 4px;">
                        <span style="width:8px; height:8px; border-radius:50%; background:var(--gold); flex:none;"></span>
                        <span style="font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--ink);">Opcionais</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px; min-height:180px;" data-column="optional" ondrop="drop(event)" ondragover="allowDrop(event)"></div>
                </div>
                <!-- Coluna: Supérfluos / Lazer -->
                <div style="background:color-mix(in srgb, var(--ink) 3%, var(--bg)); border:1px solid var(--line-soft); border-radius:calc(var(--r) + 6px); padding:12px; display:flex; flex-direction:column; gap:10px; min-height:230px;">
                    <div style="display:flex; align-items:center; gap:8px; padding:2px 4px;">
                        <span style="width:8px; height:8px; border-radius:50%; background:var(--muted); flex:none;"></span>
                        <span style="font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--ink);">Supérfluos / Lazer</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px; min-height:180px;" data-column="leisure" ondrop="drop(event)" ondragover="allowDrop(event)"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- PAINEL: Planilha (Modo Planilha — grade com fórmulas, autosave e histórico).
         Os ids abaixo são o contrato consumido por scripts/sheet.js. -->
    <div class="dash-tabpanel sheet-screen" data-panel="planilha" hidden>
        <div class="sheet-bar">
            <button type="button" class="ptool" id="sheetUndo" title="Desfazer (Ctrl+Z)" aria-label="Desfazer" disabled>
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"></path><path d="M3 13a9 9 0 1 0 3-7.7L3 8"></path></svg>
            </button>
            <button type="button" class="ptool" id="sheetRedo" title="Refazer (Ctrl+Y)" aria-label="Refazer" disabled>
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"></path><path d="M21 13a9 9 0 1 1-3-7.7L21 8"></path></svg>
            </button>
            <button type="button" class="ptool" id="sheetSnapshot" title="Salvar um marco no histórico (Ctrl+S)" aria-label="Salvar marco">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8M7 3v5h8"></path></svg>
            </button>
            <div class="sheet-hist">
                <button type="button" class="ptool" id="sheetHistBtn" title="Histórico de versões" aria-label="Histórico de versões">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3.5 2"></path></svg>
                </button>
                <div class="sheet-hist-menu" id="sheetHistMenu" hidden></div>
            </div>
            <span class="sheet-status" id="sheetStatus">Carregando…</span>
        </div>

        <div class="sheet-fx">
            <span class="sheet-ref" id="sheetRef">A1</span>
            <span class="sheet-fxi" aria-hidden="true">fx</span>
            <input type="text" class="sheet-fxin" id="sheetFx" autocomplete="off" spellcheck="false" aria-label="Barra de fórmula" placeholder="Digite um valor ou uma fórmula (=A1+B1)">
        </div>

        <div class="sheet-scroll">
            <div class="sheet-grid" id="sheetGrid" role="grid" aria-label="Grade da planilha"></div>
        </div>

        <div class="sheet-tabs">
            <button type="button" class="pt-add" id="sheetAddTab" title="Nova aba" aria-label="Nova aba">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>
            </button>
            <div class="sheet-tabs-scroll" id="sheetTabs"></div>
        </div>
    </div>

<?php endif; // hasDashData ?>

<!-- FAB de lançamento: abre o modal "Lançar" (form real preservado).
     filter:drop-shadow (não box-shadow, que o clip-path do tema recortaria). -->
<button type="button" id="dash-fab-launch" class="dash-fab" aria-label="Lançar transação">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
    <span class="dash-fab-label">Lançar</span>
</button>

<script>
// Abas internas (Visão geral / Kanban) — apresentação apenas.
(function () {
    var tabs = document.querySelectorAll('.dash-screen .dash-tab');
    var panels = document.querySelectorAll('.dash-screen .dash-tabpanel');
    function activate(name) {
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-tab') === name;
            t.setAttribute('aria-pressed', on ? 'true' : 'false');
            t.style.color = on ? 'var(--accent)' : 'var(--muted)';
            t.style.borderBottomColor = on ? 'var(--accent)' : 'transparent';
        });
        panels.forEach(function (p) {
            if (p.getAttribute('data-panel') === name) { p.removeAttribute('hidden'); }
            else { p.setAttribute('hidden', ''); }
        });
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function () { activate(t.getAttribute('data-tab')); });
    });
})();

// FAB abre o modal de criação (form real, CSRF preservado).
// Adiado para DOMContentLoaded — mesmo padrão do modal de edição em
// dashboard.js: este <script> roda no parse, ANTES de o <dialog> existir,
// então getElementById precisa esperar o DOM montar (senão o clique nunca liga).
(function () {
    function wireCreateModal() {
        var fab = document.getElementById('dash-fab-launch');
        var modal = document.getElementById('modal-create-dashboard');
        if (!fab || !modal) { return; }
        fab.addEventListener('click', function () {
            if (typeof modal.showModal === 'function') { modal.showModal(); }
            else { modal.setAttribute('open', ''); }
        });
        modal.querySelectorAll('[data-close-create-modal-dashboard]').forEach(function (btn) {
            btn.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) { modal.close(); }
        });
    }
    // Símbolo de moeda dinâmico (mSym do design): cada <span data-currency-symbol="#sel">
    // reflete R$ / US$ / € conforme o <select> alvo. Cobre criar e editar.
    function wireCurrencySymbols() {
        var SYM = { BRL: 'R$', USD: 'US$', EUR: '€' };
        document.querySelectorAll('[data-currency-symbol]').forEach(function (span) {
            var sel = document.querySelector(span.getAttribute('data-currency-symbol'));
            if (!sel) { return; }
            var sync = function () { span.textContent = SYM[sel.value] || sel.value; };
            sel.addEventListener('change', sync);
            sync();
        });
    }
    function wireAll() { wireCreateModal(); wireCurrencySymbols(); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wireAll);
    } else {
        wireAll();
    }
})();
</script>
</section>

<!-- =========================================================
     MODAL "Lançar" — form real de criação (movido do card inline).
     Mesma lógica, CSRF, campos, ids e inline-category do original;
     só migrou para o modal do FAB (fiel ao design). Definido ANTES
     do bloco JSON abaixo, que lê $transactionCategories daqui.
     ========================================================= -->
<dialog id="modal-create-dashboard" class="nvx-modal nvx-tx-modal dash-screen bg-slate-900/90 rounded-3xl border border-white/10 max-w-lg w-full p-6">
    <div class="nvx-mhead">
        <div class="nvx-mhead-txt">
            <div class="nvx-mtitle">Novo lançamento</div>
            <div class="nvx-msub">Registre em segundos.</div>
        </div>
        <button type="button" data-close-create-modal-dashboard aria-label="Fechar" class="nvx-mclose">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6 18 18M18 6 6 18"></path></svg>
        </button>
    </div>
            <form method="POST" action="/actions/transactions/create.php" class="space-y-4">
                <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                <!-- 2. Toggle Despesa/Receita (dirige o <select name=type> oculto) -->
                <div class="nvx-seg" data-seg-target="#type">
                    <button type="button" class="nvx-seg-btn" data-seg-val="expense">Despesa</button>
                    <button type="button" class="nvx-seg-btn" data-seg-val="income">Receita</button>
                </div>
                <select id="type" name="type" class="hidden" aria-hidden="true" tabindex="-1">
                    <option value="income">Receita</option>
                    <option value="expense">Despesa</option>
                </select>

                <!-- 3. Valor em destaque, com símbolo de moeda dinâmico -->
                <div class="nvx-mvalue">
                    <div class="nvx-mvalue-cap">Valor</div>
                    <div class="nvx-mval-row">
                        <span class="nvx-msym" data-currency-symbol="#currency">R$</span>
                        <input type="number" id="amount" name="amount" min="0.01" step="0.01" required placeholder="0,00" class="nvx-mval-input">
                    </div>
                    <div class="nvx-mval-line"></div>
                </div>

                <!-- 4. Descrição -->
                <input type="text" id="description" name="description" maxlength="150" placeholder="Descrição — ex.: iFood, salário, aluguel…" class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">

                <!-- 5. Chips de categoria (espelham o <select name=category_id> oculto) -->
                <div>
                    <span class="nvx-mlabel">Categoria</span>
                    <div class="nvx-chips" data-chips-target="#category" style="margin-top:8px;">
                        <button type="button" data-chip-keep data-inline-category-trigger="create" data-category-select="#category" class="nvx-chip nvx-chip--add" title="Criar nova categoria inline">+ Nova</button>
                    </div>
                </div>
                <select id="category" name="category_id" class="hidden" aria-hidden="true" tabindex="-1">
                    <?php
                    $typeSelected = 'income'; // Valor padrão
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
                    $transactionCategories = $stmt->fetchAll();
                    foreach ($transactionCategories as $category) : ?>
                        <option value="<?php echo (int) $category['id']; ?>" data-type="<?php echo $category['type']; ?>">
                            <?php echo sanitize($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- 6. Conta / carteira + Data do lançamento -->
                <div class="nvx-mgrid">
                    <label class="nvx-mfield">
                        <span class="nvx-mlabel">Conta / carteira</span>
                        <select id="account_id" name="account_id" required class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                            <?php
                            $accountsStmt = $pdo->prepare('SELECT id, name, icon FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC');
                            $accountsStmt->execute(['user_id' => $userId]);
                            foreach ($accountsStmt->fetchAll() as $account) : ?>
                                <option value="<?php echo (int) $account['id']; ?>">
                                    <?php echo sanitize(($account['icon'] ?? '🏦') . ' ' . $account['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="nvx-mfield">
                        <span class="nvx-mlabel">Data do lançamento</span>
                        <input type="date" id="transaction_date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                    </label>
                </div>

                <!-- 7. Toggle Efetivada/Pendente (dirige o checkbox is_effective) + Moeda -->
                <div class="nvx-mgrid nvx-mgrid--status">
                    <div class="nvx-mfield">
                        <span class="nvx-mlabel">Status</span>
                        <div class="nvx-seg nvx-seg--sm" data-segcheck-target="#is_effective">
                            <button type="button" class="nvx-seg-btn" data-seg-val="1">Efetivada</button>
                            <button type="button" class="nvx-seg-btn" data-seg-val="0">Pendente</button>
                        </div>
                    </div>
                    <label class="nvx-mfield">
                        <span class="nvx-mlabel">Moeda</span>
                        <select id="currency" name="currency" required class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                            <option value="BRL">R$</option>
                            <option value="USD">US$</option>
                            <option value="EUR">€</option>
                        </select>
                    </label>
                </div>
                <input type="checkbox" id="is_effective" name="is_effective" value="1" checked onchange="toggleEffectiveDate()" class="hidden">
                <div id="effectiveDateContainer" class="hidden">
                    <label class="text-sm text-slate-300 nvx-mlabel" for="effective_date">Data de efetivação</label>
                    <input type="date" id="effective_date" name="effective_date" min="<?php echo date('Y-m-d'); ?>" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                    <p class="text-xs text-slate-400 mt-1">A transação será efetivada automaticamente nesta data</p>
                </div>

                <!-- 8. Recorrente -->
                <div class="nvx-dashed">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="is_recurring" name="is_recurring" value="1" onchange="toggleRecurrenceFields()">
                        <span class="text-sm text-slate-300">Recorrente <span class="text-slate-400">— repete todo mês (vale pra receita e despesa)</span></span>
                    </label>
                </div>
                <div class="flex flex-col gap-3">
                    <div id="recurrenceFields" class="hidden space-y-3">
                        <div>
                            <label class="text-sm text-slate-300" for="recurrence_type">Tipo de Recorrência</label>
                            <select id="recurrence_type" name="recurrence_type" onchange="toggleRecurrenceDay()" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                                <option value="none">Não recorrente</option>
                                <option value="daily">Diária</option>
                                <option value="weekly">Semanal</option>
                                <option value="monthly" selected>Mensal (mesmo dia)</option>
                                <option value="same_day">Mensal (mesmo dia do mês)</option>
                                <option value="yearly">Anual</option>
                            </select>
                        </div>
                        <div id="recurrenceDayContainer" class="hidden">
                            <label class="text-sm text-slate-300" for="recurrence_day">Dia</label>
                            <select id="recurrence_day" name="recurrence_day" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                                <option value="">-- Selecione --</option>
                                <option value="0">Domingo</option>
                                <option value="1">Segunda-feira</option>
                                <option value="2">Terça-feira</option>
                                <option value="3">Quarta-feira</option>
                                <option value="4">Quinta-feira</option>
                                <option value="5">Sexta-feira</option>
                                <option value="6">Sábado</option>
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Para recorrências semanais</p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-300" for="recurrence_limit_mode">Finalizar recorrência</label>
                            <select id="recurrence_limit_mode" name="recurrence_limit_mode" onchange="toggleRecurrenceLimitFields()" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                                <option value="never" selected>Nunca</option>
                                <option value="date">Em uma data</option>
                                <option value="count">Após X parcelas</option>
                            </select>
                        </div>
                        <div id="recurrenceEndDateContainer" class="hidden">
                            <label class="text-sm text-slate-300" for="recurrence_end_date">Data Final</label>
                            <input type="date" id="recurrence_end_date" name="recurrence_end_date" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                        </div>
                        <div id="recurrenceTotalOccurrencesContainer" class="hidden">
                            <label class="text-sm text-slate-300" for="recurrence_total_occurrences">Quantidade de parcelas</label>
                            <input type="number" id="recurrence_total_occurrences" name="recurrence_total_occurrences" min="2" step="1" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                            <p class="text-xs text-slate-400 mt-1">A transação atual conta como a primeira parcela.</p>
                        </div>
                    </div>
                </div>
                <script>
                function toggleEffectiveDate() {
                    const isEffective = document.getElementById('is_effective').checked;
                    const container = document.getElementById('effectiveDateContainer');
                    if (!isEffective) {
                        container.classList.remove('hidden');
                        const today = new Date().toISOString().split('T')[0];
                        document.getElementById('effective_date').min = today;
                    } else {
                        container.classList.add('hidden');
                        document.getElementById('effective_date').value = '';
                    }
                }
                
                function toggleRecurrenceFields() {
                    const isRecurring = document.getElementById('is_recurring').checked;
                    const container = document.getElementById('recurrenceFields');
                    if (isRecurring) {
                        container.classList.remove('hidden');
                        toggleRecurrenceDay();
                        toggleRecurrenceLimitFields();
                    } else {
                        container.classList.add('hidden');
                        document.getElementById('recurrence_type').value = 'none';
                        document.getElementById('recurrence_day').value = '';
                        document.getElementById('recurrence_limit_mode').value = 'never';
                        document.getElementById('recurrence_end_date').value = '';
                        document.getElementById('recurrence_total_occurrences').value = '';
                        toggleRecurrenceLimitFields();
                    }
                }
                
                function toggleRecurrenceDay() {
                    const recurrenceType = document.getElementById('recurrence_type').value;
                    const dayContainer = document.getElementById('recurrenceDayContainer');
                    if (recurrenceType === 'weekly') {
                        dayContainer.classList.remove('hidden');
                    } else {
                        dayContainer.classList.add('hidden');
                        document.getElementById('recurrence_day').value = '';
                    }
                }

                function toggleRecurrenceLimitFields() {
                    const mode = document.getElementById('recurrence_limit_mode').value;
                    const dateContainer = document.getElementById('recurrenceEndDateContainer');
                    const countContainer = document.getElementById('recurrenceTotalOccurrencesContainer');
                    const endInput = document.getElementById('recurrence_end_date');
                    const countInput = document.getElementById('recurrence_total_occurrences');

                    dateContainer.classList.toggle('hidden', mode !== 'date');
                    countContainer.classList.toggle('hidden', mode !== 'count');

                    if (mode !== 'date') {
                        endInput.value = '';
                    }
                    if (mode !== 'count') {
                        countInput.value = '';
                    }
                }
                </script>
                <div class="nvx-mfoot">
                    <button type="button" data-close-create-modal-dashboard class="nvx-mcancel">Cancelar</button>
                    <button type="submit" class="nvx-mbtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        Lançar
                    </button>
                </div>
                <div class="nvx-mfoot-note">Enter salva · +10 pts por lançamento</div>
            </form>
</dialog>

<!-- Motor de cálculo do Modo Planilha. Sem defer, para já estar disponível
     quando scripts/sheet.js roda no DOMContentLoaded. -->
<script src="https://cdn.jsdelivr.net/npm/hyperformula@3/dist/hyperformula.full.min.js"></script>

<?php
// chart-tokens.js primeiro: expõe window.NVXCharts (paleta dos tokens) que
// forecast-chart.js e dashboard.js consomem para colorir os gráficos por tema.
$pageScripts = ['scripts/chart-tokens.js', 'scripts/forecast-chart.js', 'scripts/dashboard.js', 'scripts/kanban.js', 'scripts/tx-modal.js', 'scripts/sheet.js', 'scripts/forecast-sim.js'];

// Garante que os dados estão no formato correto para JSON
$categoriesJson = json_encode($categoriesTotals ?: [], JSON_THROW_ON_ERROR);
$historyJson = json_encode($historyData ?: [], JSON_THROW_ON_ERROR);
$extendedHistoryJson = json_encode($extendedHistoryData ?: [], JSON_THROW_ON_ERROR);
$projectionJson = json_encode($projectionData ?: [], JSON_THROW_ON_ERROR);
$weeklyProjectionJson = json_encode($weeklyProjectionData ?: [], JSON_THROW_ON_ERROR);
$expensesByAccountJson = json_encode($expensesByAccount ?: [], JSON_THROW_ON_ERROR);
$incomesByAccountJson = json_encode($incomesByAccount ?: [], JSON_THROW_ON_ERROR);
$categoryOptionsJson = json_encode(array_map(static function (array $category): array {
    return [
        'id' => (string) $category['id'],
        'name' => (string) $category['name'],
        'type' => (string) $category['type'],
    ];
}, $transactionCategories ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
?>
<script>
    window.NOVIX_CATEGORY_OPTIONS = <?php echo $categoryOptionsJson; ?>;
    const categoriesData = <?php echo $categoriesJson; ?>;
    const monthlyData = <?php echo $historyJson; ?>;
    const extendedHistoryData = <?php echo $extendedHistoryJson; ?>;
    const projectionData = <?php echo $projectionJson; ?>;
    const weeklyProjectionData = <?php echo $weeklyProjectionJson; ?>;
    const categoryVariationsData = <?php echo json_encode($categoryVariations ?: [], JSON_THROW_ON_ERROR); ?>;
    const forecastCurrentExpense = <?php echo (isset($forecastCurrentExpense) ? $forecastCurrentExpense : 0); ?>;
    const forecastPrevExpense = <?php echo (isset($forecastPrevExpense) ? $forecastPrevExpense : 0); ?>;
    const forecastWeeklyExpense = <?php echo (isset($forecastWeeklyExpense) ? $forecastWeeklyExpense : 0); ?>;
    const periodType = '<?php echo $periodType; ?>';
    // Base REAL da previsão determinística (saldo hoje + a receber − a pagar).
    // O olhinho de simulação parte sempre destes números e nunca os altera:
    // "Restaurar" é simplesmente reescrever a base.
    window.NOVIX_FORECAST_BASE = {
        today: <?php echo json_encode((float) $consolidatedBalance); ?>,
        incoming: <?php echo json_encode((float) $pendingIncomeTotal); ?>,
        outgoing: <?php echo json_encode((float) $pendingExpenseTotal); ?>,
        currency: <?php echo json_encode((string) $primaryCurrency); ?>
    };
    const initialBalance = <?php echo $initialBalance; ?>;
    const expensesByAccountData = <?php echo $expensesByAccountJson; ?>;
    const incomesByAccountData = <?php echo $incomesByAccountJson; ?>;
    const dashboardTransactionsData = <?php 
        // MODERNIZAÇÃO: Usa o mesmo array exibido na tabela para garantir correspondência
        if (!isset($recentTransactions)) {
            $recentTransactions = $transactionRepository->listByUser($userId ?? 0, null, null, $filterIsEffective, $filterIsRecurring);
        }
        // Precisa cobrir TODA linha renderizada na lista (inclusive as que só
        // aparecem depois do "Carregar mais"), senão o modal de edição não
        // encontraria os dados de uma linha revelada. $recentTransactionsSlice
        // já vem definido pelo bloco da lista quando há dados no dashboard.
        if (!isset($recentTransactionsSlice)) {
            $recentTransactionsSlice = array_slice($recentTransactions, 0, 200);
        }
        echo json_encode(array_map(static function (array $transaction): array {
        return [
            'id' => (int) $transaction['id'],
            'type' => $transaction['type'],
            'category_id' => (int) $transaction['category_id'],
            'account_id' => (int) ($transaction['account_id'] ?? 0),
            'amount' => (float) $transaction['amount'],
            'currency' => $transaction['currency'] ?? 'BRL',
            'description' => $transaction['description'] ?? '',
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
        }, $recentTransactionsSlice), JSON_THROW_ON_ERROR); 
    ?>;
    
    function clearAccountFilters() {
        const checkboxes = document.querySelectorAll('input[name="account_ids[]"]');
        checkboxes.forEach(cb => cb.checked = false);
        document.getElementById('filterForm').submit();
    }
</script>

<dialog id="inline-category-modal" class="nvx-modal dash-screen bg-slate-900/95 rounded-3xl border border-white/10 max-w-md w-full p-6">
    <form id="inline-category-form" class="space-y-4">
        <div class="nvx-mhead">
            <div class="nvx-mhead-txt">
                <div class="nvx-mtitle">Nova categoria</div>
                <div class="nvx-msub">Crie uma categoria sem sair do lançamento.</div>
            </div>
            <button type="button" data-close-inline-category aria-label="Fechar" class="nvx-mclose">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6 18 18M18 6 6 18"></path></svg>
            </button>
        </div>
        <div>
            <label class="text-sm text-slate-300" for="inline-category-name">Nome</label>
            <input type="text" id="inline-category-name" name="name" maxlength="80" required class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
        </div>
        <div>
            <label class="text-sm text-slate-300" for="inline-category-type">Tipo</label>
            <select id="inline-category-type" name="type" required class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
                <option value="income">Receita</option>
                <option value="expense">Despesa</option>
            </select>
        </div>
        <p id="inline-category-error" class="hidden text-sm text-rose-300"></p>
        <div class="nvx-mfoot">
            <button type="button" data-close-inline-category class="nvx-mcancel">Cancelar</button>
            <button type="submit" class="nvx-mbtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                Criar
            </button>
        </div>
    </form>
</dialog>

<dialog id="modal-edit-dashboard" class="nvx-modal nvx-tx-modal dash-screen bg-slate-900/90 rounded-3xl border border-white/10 max-w-lg w-full p-6">
    <form method="POST" action="/actions/transactions/update.php" class="space-y-4">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="redirect_to" value="dashboard.php">
        <input type="hidden" name="id" value="">
        <div class="nvx-mhead">
            <div class="nvx-mhead-txt">
                <div class="nvx-mtitle">Editar lançamento</div>
                <div class="nvx-msub">Ajuste e salve — mesmos campos do novo.</div>
            </div>
            <button type="button" data-close-edit-modal-dashboard aria-label="Fechar" class="nvx-mclose">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6 18 18M18 6 6 18"></path></svg>
            </button>
        </div>
                <!-- 2. Toggle Despesa/Receita (dirige o <select name=type> oculto) -->
                <div class="nvx-seg" data-seg-target="#dashboard-modal-type">
                    <button type="button" class="nvx-seg-btn" data-seg-val="expense">Despesa</button>
                    <button type="button" class="nvx-seg-btn" data-seg-val="income">Receita</button>
                </div>
                <select id="dashboard-modal-type" name="type" class="hidden" aria-hidden="true" tabindex="-1">
                    <option value="income">Receita</option>
                    <option value="expense">Despesa</option>
                </select>

                <!-- 3. Valor em destaque -->
                <div class="nvx-mvalue">
                    <div class="nvx-mvalue-cap">Valor</div>
                    <div class="nvx-mval-row">
                        <span class="nvx-msym" data-currency-symbol="#dashboard-modal-currency">R$</span>
                        <input type="number" id="dashboard-modal-amount" name="amount" min="0.01" step="0.01" placeholder="0,00" class="nvx-mval-input">
                    </div>
                    <div class="nvx-mval-line"></div>
                </div>

                <!-- 4. Descrição -->
                <input type="text" id="dashboard-modal-description" name="description" maxlength="150" placeholder="Descrição — ex.: iFood, salário, aluguel…" class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">

                <!-- 5. Chips de categoria (espelham o <select name=category_id> oculto) -->
                <div>
                    <span class="nvx-mlabel">Categoria</span>
                    <div class="nvx-chips" data-chips-target="#dashboard-modal-category" style="margin-top:8px;">
                        <button type="button" data-chip-keep data-inline-category-trigger="create" data-category-select="#dashboard-modal-category" class="nvx-chip nvx-chip--add" title="Criar nova categoria inline">+ Nova</button>
                    </div>
                </div>
                <select id="dashboard-modal-category" name="category_id" class="hidden" aria-hidden="true" tabindex="-1">
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
                        $categoriesStmt = $pdo->prepare('SELECT id, name, type FROM categories WHERE (user_id = :user_id OR user_id IS NULL) ORDER BY name ASC');
                        $categoriesStmt->execute(['user_id' => $userId]);
                    } else {
                        $categoriesStmt = $pdo->query('SELECT id, name, type FROM categories ORDER BY name ASC');
                    }
                    foreach ($categoriesStmt->fetchAll() as $category) : ?>
                        <option value="<?php echo (int) $category['id']; ?>" data-type="<?php echo $category['type']; ?>">
                            <?php echo sanitize($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- 6. Conta / carteira + Data -->
                <div class="nvx-mgrid">
                    <label class="nvx-mfield">
                        <span class="nvx-mlabel">Conta / carteira</span>
                        <select id="dashboard-modal-account" name="account_id" required class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm focus:border-violet-500 focus:outline-none">
                            <?php
                            $accountsStmt = $pdo->prepare('SELECT id, name, icon FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC');
                            $accountsStmt->execute(['user_id' => $userId]);
                            foreach ($accountsStmt->fetchAll() as $account) : ?>
                                <option value="<?php echo (int) $account['id']; ?>">
                                    <?php echo sanitize(($account['icon'] ?? '🏦') . ' ' . $account['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="nvx-mfield">
                        <span class="nvx-mlabel">Data do lançamento</span>
                        <input type="date" id="dashboard-modal-date" name="transaction_date" class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
                    </label>
                </div>

                <!-- 7. Toggle Efetivada/Pendente + Moeda -->
                <div class="nvx-mgrid nvx-mgrid--status">
                    <div class="nvx-mfield">
                        <span class="nvx-mlabel">Status</span>
                        <div class="nvx-seg nvx-seg--sm" data-segcheck-target="#dashboard-modal-is-effective">
                            <button type="button" class="nvx-seg-btn" data-seg-val="1">Efetivada</button>
                            <button type="button" class="nvx-seg-btn" data-seg-val="0">Pendente</button>
                        </div>
                    </div>
                    <label class="nvx-mfield">
                        <span class="nvx-mlabel">Moeda</span>
                        <select id="dashboard-modal-currency" name="currency" class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
                            <option value="BRL">R$</option>
                            <option value="USD">US$</option>
                            <option value="EUR">€</option>
                        </select>
                    </label>
                </div>
                <input type="checkbox" id="dashboard-modal-is-effective" name="is_effective" value="1" class="hidden" onchange="toggleEffectiveDateEditDashboard()">
                <div id="dashboard-modal-effective-date-container" class="hidden">
                    <label class="text-sm text-slate-300 nvx-mlabel" for="dashboard-modal-effective-date">Data de efetivação</label>
                    <input type="date" id="dashboard-modal-effective-date" name="effective_date" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                    <p class="text-xs text-slate-400 mt-1">Data em que a transação será efetivada automaticamente</p>
                </div>

                <!-- 8. Recorrente -->
                <div class="nvx-dashed">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="dashboard-modal-is-recurring" name="is_recurring" value="1" onchange="toggleRecurrenceFieldsEditDashboard()">
                        <span class="text-sm text-slate-300">Recorrente <span class="text-slate-400">— repete todo mês (vale pra receita e despesa)</span></span>
                    </label>
                </div>
                <div class="flex flex-col gap-3">
            <div id="dashboard-modal-recurrence-fields" class="hidden space-y-3">
                <div>
                    <label class="text-sm text-slate-300" for="dashboard-modal-recurrence-type">Tipo de Recorrência</label>
                    <select id="dashboard-modal-recurrence-type" name="recurrence_type" onchange="toggleRecurrenceDayEditDashboard()" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
                        <option value="none">Não recorrente</option>
                        <option value="daily">Diária</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensal (mesmo dia)</option>
                        <option value="same_day">Mensal (mesmo dia do mês)</option>
                        <option value="yearly">Anual</option>
                    </select>
                </div>
                <div id="dashboard-modal-recurrence-day-container" class="hidden">
                    <label class="text-sm text-slate-300" for="dashboard-modal-recurrence-day">Dia</label>
                    <select id="dashboard-modal-recurrence-day" name="recurrence_day" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
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
                <div>
                    <label class="text-sm text-slate-300" for="dashboard-modal-recurrence-limit-mode">Finalizar recorrência</label>
                    <select id="dashboard-modal-recurrence-limit-mode" name="recurrence_limit_mode" onchange="toggleRecurrenceLimitFieldsEditDashboard()" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
                        <option value="never">Nunca</option>
                        <option value="date">Em uma data</option>
                        <option value="count">Após X parcelas</option>
                    </select>
                </div>
                <div id="dashboard-modal-recurrence-end-date-container" class="hidden">
                    <label class="text-sm text-slate-300" for="dashboard-modal-recurrence-end-date">Data Final</label>
                    <input type="date" id="dashboard-modal-recurrence-end-date" name="recurrence_end_date" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
                </div>
                <div id="dashboard-modal-recurrence-total-container" class="hidden">
                    <label class="text-sm text-slate-300" for="dashboard-modal-recurrence-total-occurrences">Quantidade de parcelas</label>
                    <input type="number" id="dashboard-modal-recurrence-total-occurrences" name="recurrence_total_occurrences" min="2" step="1" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm">
                    <p class="text-xs text-slate-400 mt-1">A transação atual conta como a primeira parcela.</p>
                </div>
            </div>
        </div>
        <div class="nvx-mfoot">
            <button type="button" data-close-edit-modal-dashboard class="nvx-mcancel">Cancelar</button>
            <button type="submit" class="nvx-mbtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                Salvar alterações
            </button>
        </div>
        <div class="nvx-mfoot-note">Enter salva as alterações</div>
    </form>
</dialog>

<dialog id="modal-delete-dashboard" class="nvx-modal nvx-modal--danger dash-screen bg-slate-900/90 rounded-3xl border border-white/10 max-w-md w-full p-6">
    <div class="nvx-mdanger">
        <span class="nvx-mdanger-icon">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg>
        </span>
        <div class="nvx-mtitle" style="margin-top:15px;">Excluir lançamento?</div>
        <div class="nvx-msub" style="font-size:13.5px; margin-top:8px;">Tem certeza que deseja excluir esta transação? Não dá para desfazer.</div>
        <form method="POST" action="/actions/transactions/delete.php" class="nvx-mfoot" style="margin-top:18px;">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="redirect_to" value="dashboard.php">
            <input type="hidden" name="id" id="dashboard-delete-id" value="">
            <button type="button" data-close-delete-modal-dashboard class="nvx-mcancel" style="flex:1;">Cancelar</button>
            <button type="submit" class="nvx-mbtn nvx-mbtn--danger">Excluir</button>
        </form>
    </div>
</dialog>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

