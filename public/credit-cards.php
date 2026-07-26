<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\CreditCardBillRepository;

$pdo = Connection::get();
$userId = currentUserId() ?? 0;
$billRepository = new CreditCardBillRepository($pdo);

// Busca apenas contas do tipo crédito
$stmt = $pdo->prepare('
    SELECT id, name, icon, color, credit_limit, closing_day, due_day, interest_rate 
    FROM accounts 
    WHERE user_id = :user_id AND type = "credit" AND is_active = 1 
    ORDER BY name ASC
');
$stmt->execute(['user_id' => $userId]);
$creditCards = $stmt->fetchAll();

// Busca configurações de transações para cada cartão
$settingsByAccount = [];
try {
    // Verifica se a tabela existe, se não, cria
    $checkTable = $pdo->query("SHOW TABLES LIKE 'credit_card_transaction_settings'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec('
            CREATE TABLE `credit_card_transaction_settings` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `account_id` INT UNSIGNED NOT NULL,
              `user_id` INT UNSIGNED NOT NULL,
              `register_bill_as_expense` BOOLEAN DEFAULT TRUE,
              `register_limit_as_income` BOOLEAN DEFAULT FALSE,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `unique_account_user` (`account_id`, `user_id`),
              INDEX `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }
    
    
    $stmt = $pdo->prepare('
        SELECT account_id, register_bill_as_expense, register_limit_as_income 
        FROM credit_card_transaction_settings 
        WHERE user_id = :user_id
    ');
    $stmt->execute(['user_id' => $userId]);
    $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settingsData as $setting) {
        $settingsByAccount[(int) $setting['account_id']] = [
            'register_bill_as_expense' => (bool) $setting['register_bill_as_expense'],
            'register_limit_as_income' => (bool) $setting['register_limit_as_income'],
        ];
    }
} catch (PDOException $e) {
    // Tabela não existe ou erro, continua com configurações vazias
    error_log('Erro ao buscar configurações de cartão: ' . $e->getMessage());
}

// Busca faturas para cada cartão
$cardsWithBills = [];
foreach ($creditCards as $card) {
    $cardSettings = $settingsByAccount[(int) $card['id']] ?? [
        'register_bill_as_expense' => true, // Padrão: habilitado
        'register_limit_as_income' => false,
    ];
    $card['transaction_settings'] = $cardSettings;
    
    $bills = $billRepository->listByAccount((int) $card['id'], $userId, 12);
    
    // Calcula juros e atualiza total para cada fatura
    foreach ($bills as &$bill) {
        // Atualiza o total baseado nas transações vinculadas
        $billRepository->updateBillTotal((int) $bill['id']);
        
        if ($bill['status'] === 'open' || $bill['status'] === 'overdue') {
            $billRepository->calculateInterest((int) $bill['id']);
        }
        
            // Recarrega a fatura atualizada
            $stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
            $stmt->execute(['id' => $bill['id']]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcula valor total com juros
        $bill['total_with_interest'] = (float) ($bill['total_amount'] ?? 0) + 
                                       (float) ($bill['interest_amount'] ?? 0) + 
                                       (float) ($bill['fine_amount'] ?? 0);
        $bill['remaining'] = $bill['total_with_interest'] - (float) ($bill['paid_amount'] ?? 0);
    }
    
    // Busca fatura atual
    $currentBill = $billRepository->getCurrentBill((int) $card['id'], $userId);
    if ($currentBill) {
        $billRepository->calculateInterest((int) $currentBill['id']);
        // Atualiza total da fatura atual baseado nas transações
        $billRepository->updateBillTotal((int) $currentBill['id']);
        
        // Recarrega
        $stmt = $pdo->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
        $stmt->execute(['id' => $currentBill['id']]);
        $currentBill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentBill['total_with_interest'] = (float) ($currentBill['total_amount'] ?? 0) + 
                                             (float) ($currentBill['interest_amount'] ?? 0) + 
                                             (float) ($currentBill['fine_amount'] ?? 0);
        $currentBill['remaining'] = $currentBill['total_with_interest'] - (float) ($currentBill['paid_amount'] ?? 0);
        
        // Remove fatura atual do histórico para evitar duplicação
        $bills = array_filter($bills, function($bill) use ($currentBill) {
            return (int) $bill['id'] !== (int) $currentBill['id'];
        });
    }
    
    $cardsWithBills[] = [
        'card' => $card,
        'bills' => $bills,
        'current_bill' => $currentBill,
    ];
}

$title = 'Cartões de Crédito | NovixFin';

$pageStyles = ['styles/cards.css'];

include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

?>

<section class="space-y-8 cards-screen">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-white">Cartões de Crédito</h1>
            <p class="text-sm text-slate-400">Gerencie suas faturas e acompanhe seus gastos mensais.</p>
        </div>
        <a href="/public/accounts.php" class="px-6 py-3 rounded-xl bg-violet-500 hover:bg-violet-400 transition text-white font-medium">+ Novo Cartão</a>
    </div>

    <?php if ($success) : ?>
        <div class="p-4 rounded-2xl bg-emerald-600/10 border border-emerald-500/40 text-emerald-200 text-sm">
            <?php echo sanitize($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="p-4 rounded-2xl bg-rose-600/10 border border-rose-500/40 text-rose-200 text-sm">
            <?php echo sanitize($error); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($creditCards)) : ?>
        <div class="p-8 rounded-3xl bg-slate-900/70 border border-white/5 text-center">
            <p class="text-slate-400 mb-4">Nenhum cartão de crédito cadastrado.</p>
            <a href="/public/accounts.php" class="inline-block px-6 py-3 rounded-xl bg-violet-500 hover:bg-violet-400 transition text-white font-medium">
                Cadastrar Cartão de Crédito
            </a>
        </div>
    <?php else : ?>
        <?php foreach ($cardsWithBills as $item) : 
            $card = $item['card'];
            $currentBill = $item['current_bill'];
            $bills = $item['bills'];
        ?>
            <div class="p-6 rounded-3xl bg-slate-900/70 border border-white/5 space-y-6">
                <!-- Informações do Cartão -->
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <span class="text-4xl"><?php echo sanitize($card['icon']); ?></span>
                        <div>
                            <h2 class="text-xl font-semibold text-white"><?php echo sanitize($card['name']); ?></h2>
                            <?php if ($card['credit_limit']) : ?>
                                <p class="text-sm text-slate-400">
                                    Limite: <span data-balance><?php echo formatCurrency((float) $card['credit_limit'], 'BRL'); ?></span>
                                </p>
                            <?php endif; ?>
                            <?php if ($card['closing_day'] && $card['due_day']) : ?>
                                <p class="text-xs text-slate-500">
                                    Fechamento: dia <?php echo (int) $card['closing_day']; ?> • 
                                    Vencimento: dia <?php echo (int) $card['due_day']; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="/public/accounts.php" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 transition text-white text-sm">
                        Editar
                    </a>
                </div>
                
                 <!-- Botão discreto para configurações de transações -->
                 <div class="flex items-center justify-end">
                     <button 
                         onclick="openTransactionSettingsModal(<?php echo (int) $card['id']; ?>, <?php echo htmlspecialchars(json_encode($card['transaction_settings']), ENT_QUOTES); ?>, '<?php echo sanitize($card['name']); ?>')"
                         class="p-2 rounded-lg bg-slate-800/50 hover:bg-slate-700/50 border border-white/5 transition text-slate-400 hover:text-white"
                         title="Configurações de transações"
                     >
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                         </svg>
                     </button>
                </div>

                <!-- Fatura Atual -->
                <?php if ($currentBill) : 
                    $billStatus = $currentBill['status'] ?? 'open';
                    $statusColors = [
                        'open' => 'bg-blue-500/20 text-blue-200 border-blue-500/40',
                        'paid' => 'bg-emerald-500/20 text-emerald-200 border-emerald-500/40',
                        'overdue' => 'bg-rose-500/20 text-rose-200 border-rose-500/40',
                        'cancelled' => 'bg-slate-500/20 text-slate-200 border-slate-500/40',
                    ];
                    $statusLabels = [
                        'open' => 'Aberta',
                        'paid' => 'Paga',
                        'overdue' => 'Vencida',
                        'cancelled' => 'Cancelada',
                    ];
                ?>
                    <div class="p-4 rounded-2xl bg-slate-800/50 border border-white/5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Fatura Atual</h3>
                                <p class="text-xs text-slate-400">
                                    <?php echo date('d/m/Y', strtotime($currentBill['closing_date'])); ?> - 
                                    Vence em: <?php echo date('d/m/Y', strtotime($currentBill['due_date'])); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs <?php echo $statusColors[$billStatus] ?? $statusColors['open']; ?> border">
                                <?php echo $statusLabels[$billStatus] ?? 'Aberta'; ?>
                            </span>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Total da fatura:</span>
                                <span class="text-white font-semibold" data-balance>
                                    <?php echo formatCurrency((float) ($currentBill['total_amount'] ?? 0), $currentBill['currency'] ?? 'BRL'); ?>
                                </span>
                            </div>
                            
                            <?php if ((float) ($currentBill['interest_amount'] ?? 0) > 0) : ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-400">Juros:</span>
                                    <span class="text-rose-300" data-balance>
                                        + <?php echo formatCurrency((float) $currentBill['interest_amount'], $currentBill['currency'] ?? 'BRL'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ((float) ($currentBill['paid_amount'] ?? 0) > 0) : ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-400">Já pago:</span>
                                    <span class="text-emerald-300" data-balance>
                                        - <?php echo formatCurrency((float) $currentBill['paid_amount'], $currentBill['currency'] ?? 'BRL'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="pt-2 border-t border-white/5 flex justify-between items-center">
                                <span class="text-base font-semibold text-white">Restante:</span>
                                <span class="text-xl font-bold <?php echo $currentBill['remaining'] > 0 ? 'text-rose-300' : 'text-emerald-300'; ?>" data-balance>
                                    <?php echo formatCurrency(max(0, $currentBill['remaining']), $currentBill['currency'] ?? 'BRL'); ?>
                                </span>
                            </div>
                            
                            <div class="mt-3 flex gap-2">
                            <?php if ($currentBill['remaining'] > 0 && $billStatus !== 'paid') : ?>
                                <button 
                                    onclick="openPayBillModal(<?php echo htmlspecialchars(json_encode($currentBill), ENT_QUOTES); ?>, '<?php echo sanitize($card['name']); ?>')"
                                        class="flex-1 px-4 py-2 rounded-lg bg-violet-500 hover:bg-violet-400 transition text-white text-sm font-medium"
                                >
                                    Registrar Pagamento
                                </button>
                            <?php endif; ?>
                                
                                <?php if ($billStatus === 'paid' && (float) ($currentBill['paid_amount'] ?? 0) > 0) : ?>
                                    <form method="POST" action="/actions/credit-cards/revert-payment.php" class="flex-1" onsubmit="return confirm('Tem certeza que deseja reverter o pagamento desta fatura? As transações relacionadas não serão revertidas automaticamente.');">
                                        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                                        <input type="hidden" name="bill_id" value="<?php echo (int) $currentBill['id']; ?>">
                                        <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/40 transition text-rose-200 text-sm font-medium">
                                            Reverter Pagamento
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Histórico de Faturas -->
                <?php if (!empty($bills)) : ?>
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-4">Histórico de Faturas</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-slate-400 border-b border-white/5">
                                    <tr>
                                        <th class="text-left py-3">Mês</th>
                                        <th class="text-right py-3">Fechamento</th>
                                        <th class="text-right py-3">Vencimento</th>
                                        <th class="text-right py-3">Total</th>
                                        <th class="text-right py-3">Pago</th>
                                        <th class="text-right py-3">Juros</th>
                                        <th class="text-center py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <?php foreach ($bills as $bill) : 
                                        $billStatus = $bill['status'] ?? 'open';
                                        $statusColors = [
                                            'open' => 'bg-blue-500/10 text-blue-200',
                                            'paid' => 'bg-emerald-500/10 text-emerald-200',
                                            'overdue' => 'bg-rose-500/10 text-rose-200',
                                            'cancelled' => 'bg-slate-500/10 text-slate-200',
                                        ];
                                        $statusLabels = [
                                            'open' => 'Aberta',
                                            'paid' => 'Paga',
                                            'overdue' => 'Vencida',
                                            'cancelled' => 'Cancelada',
                                        ];
                                    ?>
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="py-3 text-white">
                                                <?php 
                                                $month = explode('-', $bill['billing_month']);
                                                echo date('M/Y', mktime(0, 0, 0, (int) $month[1], 1, (int) $month[0]));
                                                ?>
                                            </td>
                                            <td class="py-3 text-right text-slate-300">
                                                <?php echo date('d/m/Y', strtotime($bill['closing_date'])); ?>
                                            </td>
                                            <td class="py-3 text-right text-slate-300">
                                                <?php echo date('d/m/Y', strtotime($bill['due_date'])); ?>
                                            </td>
                                            <td class="py-3 text-right text-white font-medium" data-balance>
                                                <?php echo formatCurrency((float) ($bill['total_amount'] ?? 0), $bill['currency'] ?? 'BRL'); ?>
                                            </td>
                                            <td class="py-3 text-right text-emerald-300" data-balance>
                                                <?php echo formatCurrency((float) ($bill['paid_amount'] ?? 0), $bill['currency'] ?? 'BRL'); ?>
                                            </td>
                                            <td class="py-3 text-right text-rose-300" data-balance>
                                                <?php echo formatCurrency((float) ($bill['interest_amount'] ?? 0), $bill['currency'] ?? 'BRL'); ?>
                                            </td>
                                            <td class="py-3 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <span class="px-2 py-1 rounded text-xs <?php echo $statusColors[$billStatus] ?? $statusColors['open']; ?>">
                                                        <?php echo $statusLabels[$billStatus] ?? 'Aberta'; ?>
                                                    </span>
                                                    <?php 
                                                    $billRemaining = (float) ($bill['total_amount'] ?? 0) + 
                                                                    (float) ($bill['interest_amount'] ?? 0) + 
                                                                    (float) ($bill['fine_amount'] ?? 0) - 
                                                                    (float) ($bill['paid_amount'] ?? 0);
                                                    if ($billRemaining > 0 && $billStatus !== 'paid') : 
                                                    ?>
                                                        <button 
                                                            onclick="openPayBillModal(<?php echo htmlspecialchars(json_encode($bill), ENT_QUOTES); ?>, '<?php echo sanitize($card['name']); ?>')"
                                                            class="px-2 py-1 rounded text-xs bg-violet-500/20 hover:bg-violet-500/30 text-violet-200 transition"
                                                            title="Pagar fatura"
                                                        >
                                                            Pagar
                                                        </button>
                                                    <?php elseif ($billStatus === 'paid' && (float) ($bill['paid_amount'] ?? 0) > 0) : ?>
                                                        <form method="POST" action="/actions/credit-cards/revert-payment.php" class="inline" onsubmit="return confirm('Tem certeza que deseja reverter o pagamento desta fatura?');">
                                                            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                                                            <input type="hidden" name="bill_id" value="<?php echo (int) $bill['id']; ?>">
                                                            <button type="submit" class="px-2 py-1 rounded text-xs bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 transition" title="Reverter pagamento">
                                                                Reverter
                                                            </button>
                                                        </form>
                                                    <?php elseif ($billRemaining <= 0 && (float) ($bill['total_amount'] ?? 0) <= 0 && (float) ($bill['paid_amount'] ?? 0) <= 0) : ?>
                                                        <form method="POST" action="/actions/credit-cards/delete-bill.php" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir esta fatura vazia?');">
                                                            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                                                            <input type="hidden" name="bill_id" value="<?php echo (int) $bill['id']; ?>">
                                                            <button type="submit" class="px-2 py-1 rounded text-xs bg-slate-500/20 hover:bg-slate-500/30 text-slate-200 transition" title="Excluir fatura vazia">
                                                                Excluir
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- Modal de Pagamento -->
<dialog id="payBillModal" class="nvx-modal cards-modal bg-slate-900 rounded-3xl p-6 max-w-2xl w-full border border-white/10">
        <h2 class="text-2xl font-semibold text-white mb-4">Registrar Pagamento</h2>
        <p class="text-sm text-slate-400 mb-6" id="payBillCardName"></p>
        
        <form method="POST" action="/actions/credit-cards/pay-bill.php" id="payBillForm" class="space-y-4">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" id="pay_bill_id" name="bill_id" value="">
            
            <div>
                <label class="text-sm text-slate-300" for="paid_amount">Valor Pago</label>
                <input type="number" id="paid_amount" name="paid_amount" min="0.01" step="0.01" required class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1">Informe o valor que você está pagando agora. Se estiver debitando de contas, esse valor será ajustado automaticamente para o total debitado.</p>
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="paid_at">Data do Pagamento</label>
                <input type="date" id="paid_at" name="paid_at" required value="<?php echo date('Y-m-d'); ?>" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
            </div>
            
            <!-- Opção de débito de contas -->
            <div class="p-4 rounded-xl bg-slate-800/50 border border-white/5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="debit_accounts" name="debit_accounts" value="1" onchange="toggleAccountDebit()" class="w-5 h-5 rounded bg-slate-900 border-white/10">
                    <span class="text-sm text-white font-medium">Debitar valor de uma ou mais contas</span>
                </label>
                <p class="text-xs text-slate-400 mt-2 ml-8">Se marcado, será criada uma transação de despesa em cada conta selecionada para o pagamento.</p>
            </div>
            
            <!-- Seção de seleção de contas -->
            <div id="accountsSection" class="hidden space-y-4">
                <div>
                    <label class="text-sm text-slate-300 mb-2 block">Contas para Débito</label>
                    <div id="accountsList" class="space-y-3 max-h-64 overflow-y-auto">
                        <?php
                        // Busca contas ativas (excluindo cartões de crédito)
                        $accountsStmt = $pdo->prepare('
                            SELECT id, name, icon, type 
                            FROM accounts 
                            WHERE user_id = :user_id 
                            AND is_active = 1 
                            AND type != "credit"
                            ORDER BY name ASC
                        ');
                        $accountsStmt->execute(['user_id' => $userId]);
                        $availableAccounts = $accountsStmt->fetchAll();
                        ?>
                        <?php if (empty($availableAccounts)) : ?>
                            <p class="text-sm text-slate-400 p-3 rounded-lg bg-slate-800/50">
                                Nenhuma conta disponível. <a href="/public/accounts.php" class="text-violet-300 hover:text-white">Cadastre uma conta</a>
                            </p>
                        <?php else : ?>
                            <?php foreach ($availableAccounts as $account) : ?>
                                <div class="account-item p-3 rounded-lg bg-slate-800/50 border border-white/5">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            class="account-checkbox w-5 h-5 rounded bg-slate-900 border-white/10" 
                                            data-account-id="<?php echo (int) $account['id']; ?>"
                                            onchange="updateAccountDistribution()"
                                        >
                                        <span class="text-2xl"><?php echo sanitize($account['icon'] ?? '🏦'); ?></span>
                                        <span class="flex-1 text-sm text-white"><?php echo sanitize($account['name']); ?></span>
                                    </label>
                                    <div class="mt-2 ml-10 hidden account-amount-input">
                                        <label class="text-xs text-slate-400 block mb-1">Valor a debitar</label>
                                        <input 
                                            type="number" 
                                            class="account-amount w-full px-3 py-2 rounded-lg bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-sm" 
                                            min="0.01" 
                                            step="0.01" 
                                            placeholder="0.00"
                                            data-account-id="<?php echo (int) $account['id']; ?>"
                                            oninput="updateAccountDistribution()"
                                        >
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Resumo da distribuição -->
                <div id="distributionSummary" class="hidden p-4 rounded-xl bg-violet-500/10 border border-violet-500/30">
                    <h3 class="text-sm font-semibold text-violet-200 mb-2">Resumo da Distribuição</h3>
                    <div id="distributionList" class="space-y-1 text-xs text-slate-300"></div>
                    <div class="mt-3 pt-3 border-t border-violet-500/20">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-white">Total distribuído:</span>
                            <span id="totalDistributed" class="text-sm font-semibold text-violet-200" data-balance>R$ 0,00</span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-sm text-slate-400">Valor pago:</span>
                            <span id="totalPaidDisplay" class="text-sm text-white" data-balance>R$ 0,00</span>
                        </div>
                        <div id="distributionWarning" class="mt-2 text-xs text-rose-300 hidden"></div>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closePayBillModal()" class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 transition text-white">Cancelar</button>
                <button type="submit" id="submitPayBill" class="flex-1 py-3 rounded-xl bg-violet-500 hover:bg-violet-400 transition text-white font-medium">Confirmar</button>
            </div>
        </form>
</dialog>

<script>
let currentBillData = null;

function openPayBillModal(bill, cardName) {
    currentBillData = bill;
    document.getElementById('payBillModal').showModal();
    document.getElementById('pay_bill_id').value = bill.id;
    
    // Calcula valor restante
    const totalAmount = parseFloat(bill.total_amount || 0);
    const interestAmount = parseFloat(bill.interest_amount || 0);
    const fineAmount = parseFloat(bill.fine_amount || 0);
    const paidAmount = parseFloat(bill.paid_amount || 0);
    const remaining = totalAmount + interestAmount + fineAmount - paidAmount;
    
    const paidAmountInput = document.getElementById('paid_amount');
    paidAmountInput.value = remaining > 0 ? remaining.toFixed(2) : '';
    paidAmountInput.max = remaining > 0 ? remaining.toFixed(2) : '';
    
    const monthParts = bill.billing_month ? bill.billing_month.split('-') : [];
    const monthLabel = monthParts.length === 2 
        ? new Date(monthParts[0], parseInt(monthParts[1]) - 1).toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' })
        : '';
    
    document.getElementById('payBillCardName').textContent = 'Cartão: ' + cardName + (monthLabel ? ' • ' + monthLabel : '');
    
    // Reset form
    document.getElementById('debit_accounts').checked = false;
    toggleAccountDebit();
    document.querySelectorAll('.account-checkbox').forEach(cb => {
        cb.checked = false;
        const accountItem = cb.closest('.account-item');
        accountItem.querySelector('.account-amount-input').classList.add('hidden');
        accountItem.querySelector('.account-amount').value = '';
    });
    updateAccountDistribution();
}

function closePayBillModal() {
    document.getElementById('payBillModal').close();
    currentBillData = null;
}

function toggleAccountDebit() {
    const checkbox = document.getElementById('debit_accounts');
    const accountsSection = document.getElementById('accountsSection');
    
    if (checkbox.checked) {
        accountsSection.classList.remove('hidden');
    } else {
        accountsSection.classList.add('hidden');
        // Desmarca todas as contas
        document.querySelectorAll('.account-checkbox').forEach(cb => {
            cb.checked = false;
            const accountItem = cb.closest('.account-item');
            accountItem.querySelector('.account-amount-input').classList.add('hidden');
            accountItem.querySelector('.account-amount').value = '';
        });
    }
    updateAccountDistribution();
}

function updateAccountDistribution() {
    const debitEnabled = document.getElementById('debit_accounts').checked;
    const distributionSummary = document.getElementById('distributionSummary');
    const distributionList = document.getElementById('distributionList');
    const totalDistributedEl = document.getElementById('totalDistributed');
    const totalPaidDisplay = document.getElementById('totalPaidDisplay');
    const distributionWarning = document.getElementById('distributionWarning');
    
    if (!debitEnabled) {
        distributionSummary.classList.add('hidden');
        return;
    }
    
    const paidAmount = parseFloat(document.getElementById('paid_amount').value || 0);
    totalPaidDisplay.textContent = formatCurrency(paidAmount);
    
    const checkedAccounts = Array.from(document.querySelectorAll('.account-checkbox:checked'));
    let totalDistributed = 0;
    const distributions = [];
    
    checkedAccounts.forEach(checkbox => {
        const accountId = checkbox.dataset.accountId;
        const accountItem = checkbox.closest('.account-item');
        const accountName = accountItem.querySelector('span:last-of-type').textContent.trim();
        const amountInput = accountItem.querySelector('.account-amount');
        const amount = parseFloat(amountInput.value || 0);
        
        if (amount > 0) {
            totalDistributed += amount;
            distributions.push({
                name: accountName,
                amount: amount
            });
        }
    });
    
    if (checkedAccounts.length === 0) {
        distributionSummary.classList.add('hidden');
    } else {
        distributionSummary.classList.remove('hidden');
        
        // Atualiza lista de distribuições
        if (distributions.length === 0) {
            distributionList.innerHTML = '<p class="text-slate-400 text-xs">Nenhum valor definido ainda</p>';
        } else {
            distributionList.innerHTML = distributions.map(d => 
                `<div class="flex justify-between">
                    <span>${d.name}:</span>
                    <span class="font-medium" data-balance>${formatCurrency(d.amount)}</span>
                </div>`
            ).join('');
        }
        
        totalDistributedEl.textContent = formatCurrency(totalDistributed);
        
        // Validação
        const difference = Math.abs(paidAmount - totalDistributed);
        if (difference > 0.01) {
            if (totalDistributed > paidAmount) {
                distributionWarning.textContent = `Total distribuído (${formatCurrency(totalDistributed)}) é maior que o valor pago.`;
                distributionWarning.classList.remove('hidden');
                document.getElementById('submitPayBill').disabled = true;
                document.getElementById('submitPayBill').classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                distributionWarning.textContent = `Restante: ${formatCurrency(paidAmount - totalDistributed)} não será debitado de nenhuma conta.`;
                distributionWarning.classList.remove('hidden');
                distributionWarning.classList.remove('text-rose-300');
                distributionWarning.classList.add('text-slate-400');
                document.getElementById('submitPayBill').disabled = false;
                document.getElementById('submitPayBill').classList.remove('opacity-50', 'cursor-not-allowed');
            }
        } else {
            distributionWarning.classList.add('hidden');
            document.getElementById('submitPayBill').disabled = false;
            document.getElementById('submitPayBill').classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
}

// Mostra/esconde campo de valor quando conta é selecionada
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.account-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const accountItem = this.closest('.account-item');
            const amountInput = accountItem.querySelector('.account-amount-input');
            if (this.checked) {
                amountInput.classList.remove('hidden');
                amountInput.querySelector('.account-amount').focus();
            } else {
                amountInput.classList.add('hidden');
                amountInput.querySelector('.account-amount').value = '';
            }
            updateAccountDistribution();
        });
    });
    
    // Adiciona listener para atualizar distribuição quando valor pago mudar
    const paidAmountInput = document.getElementById('paid_amount');
    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', updateAccountDistribution);
    }
});

// Formatação de moeda
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Validação antes de enviar
document.getElementById('payBillForm').addEventListener('submit', function(e) {
    const debitEnabled = document.getElementById('debit_accounts').checked;
    
    if (debitEnabled) {
        const checkedAccounts = Array.from(document.querySelectorAll('.account-checkbox:checked'));
        if (checkedAccounts.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos uma conta para debitar ou desmarque a opção de débito de contas.');
            return false;
        }
        
        // Coleta dados das contas selecionadas
        const accountDebits = [];
        let hasValidAmount = false;
        
        checkedAccounts.forEach(checkbox => {
            const accountId = checkbox.dataset.accountId;
            const accountItem = checkbox.closest('.account-item');
            const amountInput = accountItem.querySelector('.account-amount');
            const amount = parseFloat(amountInput.value || 0);
            
            if (amount > 0) {
                hasValidAmount = true;
                accountDebits.push({
                    account_id: accountId,
                    amount: amount
                });
            }
        });
        
        if (!hasValidAmount) {
            e.preventDefault();
            alert('Defina pelo menos um valor maior que zero para uma das contas selecionadas.');
            return false;
        }
        
        // Remove campos hidden anteriores (se houver)
        document.querySelectorAll('input[name^="account_debits"]').forEach(input => input.remove());
        
        // Adiciona campos hidden com os dados
        accountDebits.forEach((debit, index) => {
            const accountIdInput = document.createElement('input');
            accountIdInput.type = 'hidden';
            accountIdInput.name = `account_debits[${index}][account_id]`;
            accountIdInput.value = debit.account_id;
            this.appendChild(accountIdInput);
            
            const amountInput = document.createElement('input');
            amountInput.type = 'hidden';
            amountInput.name = `account_debits[${index}][amount]`;
            amountInput.value = debit.amount;
            this.appendChild(amountInput);
        });
    } else {
        // Remove campos hidden se débito não está habilitado
        document.querySelectorAll('input[name^="account_debits"]').forEach(input => input.remove());
    }
});

// Fecha modal ao clicar fora
document.getElementById('payBillModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePayBillModal();
    }
});

function openTransactionSettingsModal(accountId, settings, cardName) {
    document.getElementById('transactionSettingsModal').showModal();
    document.getElementById('transaction_settings_account_id').value = accountId;
    document.getElementById('transaction_settings_card_name').textContent = cardName;
    // Usa o valor exato que veio do PHP (que já aplica o padrão quando não existe configuração)
    document.getElementById('transaction_settings_register_bill_as_expense').checked = !!settings.register_bill_as_expense;
    document.getElementById('transaction_settings_register_limit_as_income').checked = !!settings.register_limit_as_income;
}

function closeTransactionSettingsModal() {
    document.getElementById('transactionSettingsModal').close();
}

// Fecha modal de configurações ao clicar fora
document.addEventListener('DOMContentLoaded', function() {
    const transactionSettingsModal = document.getElementById('transactionSettingsModal');
    if (!transactionSettingsModal) {
        return;
    }

    transactionSettingsModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTransactionSettingsModal();
        }
    });
});
</script>

<!-- Modal de Configurações de Transações -->
<dialog id="transactionSettingsModal" class="nvx-modal cards-modal cards-modal--sm bg-slate-900 rounded-3xl p-6 max-w-md w-full border border-white/10">
        <h2 class="text-2xl font-semibold text-white mb-2">Configurações de Transações</h2>
        <p class="text-sm text-slate-400 mb-6" id="transaction_settings_card_name"></p>
        
        <form method="POST" action="/actions/credit-cards/save-transaction-settings.php" class="space-y-4">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" id="transaction_settings_account_id" name="account_id" value="">
            
            <div class="p-4 rounded-xl bg-slate-800/50 border border-white/5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        id="transaction_settings_register_bill_as_expense"
                        name="register_bill_as_expense" 
                        value="1" 
                        class="w-5 h-5 rounded bg-slate-900 border-white/10"
                    >
                    <span class="text-sm text-white">Registrar faturas como despesa efetivada</span>
                </label>
                <p class="text-xs text-slate-400 mt-2 ml-8">Cria uma transação de despesa efetivada com o valor total de cada fatura.</p>
            </div>
            
            <div class="p-4 rounded-xl bg-slate-800/50 border border-white/5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        id="transaction_settings_register_limit_as_income"
                        name="register_limit_as_income" 
                        value="1" 
                        class="w-5 h-5 rounded bg-slate-900 border-white/10"
                    >
                    <span class="text-sm text-white">Registrar limite como receita</span>
                </label>
                <p class="text-xs text-slate-400 mt-2 ml-8">Cria uma transação de receita com o valor do limite de crédito do cartão.</p>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeTransactionSettingsModal()" class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 transition text-white">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-violet-500 hover:bg-violet-400 transition text-white font-medium">Salvar</button>
            </div>
        </form>
</dialog>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

