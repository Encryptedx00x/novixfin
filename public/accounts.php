<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\CurrencySettingsRepository;

$pdo = Connection::get();
$userId = currentUserId() ?? 0;
$currencySettingsRepository = new CurrencySettingsRepository($pdo);
$currencySettings = $currencySettingsRepository->getSettings($userId);

$stmt = $pdo->prepare('SELECT id, name, type, icon, color, balance, is_active, show_in_dashboard, credit_limit, closing_day, due_day, interest_rate FROM accounts WHERE user_id = :user_id ORDER BY is_active DESC, name ASC');
$stmt->execute(['user_id' => $userId]);
$accountsData = $stmt->fetchAll();

// Calcula totais por conta e por moeda
$enabledCurrencies = $currencySettings['enabled_currencies'] ?? ['BRL', 'USD', 'EUR'];
$primaryCurrency = $currencySettings['primary_currency'] ?? 'BRL';

$accounts = [];
foreach ($accountsData as $account) {
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(currency, "BRL") as currency,
            SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
        FROM transactions 
        WHERE user_id = :user_id AND account_id = :account_id
        GROUP BY COALESCE(currency, "BRL")
    ');
    $stmt->execute(['user_id' => $userId, 'account_id' => $account['id']]);
    $totalsData = $stmt->fetchAll();
    
    // Organiza totais por moeda
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
    
    $accounts[] = [
        'data' => $account,
        'totalsByCurrency' => $totalsByCurrency,
    ];
}

$title = 'Contas e Bancos | NovixFin';
$pageStyles = ['styles/accounts.css'];

include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

?>
<!-- Modais renderizados primeiro para garantir que existam no DOM -->
<!-- Modal Nova/Editar Conta -->
<dialog id="accountModal" class="acc-overlay bg-slate-900 rounded-3xl p-6 max-w-md w-full border border-white/10">
        <h2 id="modalTitle" class="text-2xl font-semibold text-white mb-6">Nova conta</h2>
        
        <form id="accountForm" method="POST" action="/actions/accounts/save.php" class="space-y-4">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" id="account_id" name="account_id" value="">
            
            <div>
                <label class="text-sm text-slate-300" for="icon">Ícone</label>
                <input type="text" id="icon" name="icon" maxlength="10" placeholder="🏦" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="name">Nome</label>
                <input type="text" id="name" name="name" required maxlength="100" placeholder="Ex: Nubank, Itaú, Dinheiro" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="type">Tipo</label>
                <select id="type" name="type" required onchange="toggleCreditFields()" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                    <option value="bank">Banco</option>
                    <option value="cash">Dinheiro</option>
                    <option value="investment">Investimento</option>
                    <option value="credit">Cartão de Crédito</option>
                    <option value="other">Outro</option>
                </select>
            </div>
            
            <div id="creditFields" class="hidden space-y-4">
                <div>
                    <label class="text-sm text-slate-300" for="credit_limit">Limite de Crédito</label>
                    <input type="number" id="credit_limit" name="credit_limit" min="0" step="0.01" placeholder="0.00" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-300" for="closing_day">Dia de Fechamento</label>
                        <input type="number" id="closing_day" name="closing_day" min="1" max="31" placeholder="10" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm text-slate-300" for="due_day">Dia de Vencimento</label>
                        <input type="number" id="due_day" name="due_day" min="1" max="31" placeholder="20" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                    </div>
                </div>
                
                <div>
                    <label class="text-sm text-slate-300" for="interest_rate">Taxa de Juros Mensal (%)</label>
                    <input type="number" id="interest_rate" name="interest_rate" min="0" max="100" step="0.01" placeholder="2.5" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
                </div>
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="color">Cor (hex)</label>
                <input type="text" id="color" name="color" maxlength="20" placeholder="#6366f1" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 transition text-white">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-violet-500 hover:bg-violet-400 transition text-white font-medium">Salvar</button>
            </div>
        </form>
</dialog>

<!-- Modal Excluir Conta -->
<dialog id="deleteModal" class="acc-overlay bg-slate-900 rounded-3xl p-6 max-w-md w-full border border-white/10">
        <h2 class="text-2xl font-semibold text-white mb-4">Excluir conta</h2>
        <p class="text-slate-300 mb-6">Tem certeza que deseja excluir a conta <strong id="deleteAccountName"></strong>?</p>
        
        <form method="POST" action="/actions/accounts/delete.php" class="space-y-4">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" id="delete_account_id" name="account_id" value="">
            
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="delete_transactions" value="1" class="w-5 h-5 rounded bg-slate-900 border-white/10">
                    <span class="text-sm text-white">Excluir também todas as transações desta conta</span>
                </label>
                <p class="text-xs text-slate-400 mt-2">Se desmarcar, as transações ficarão sem conta vinculada.</p>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeDeleteModal()" class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 transition text-white">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-rose-500 hover:bg-rose-400 transition text-white font-medium">Excluir</button>
            </div>
        </form>
</dialog>

<script>
// Define funções globalmente ANTES dos botões serem renderizados
// As funções verificam os elementos no momento da chamada com retry
(function() {
    window.openModal = function() {
        // Função auxiliar para encontrar elemento com retry
        const findElement = (id, retries = 10) => {
            let element = document.getElementById(id);
            if (element || retries <= 0) return element;
            
            // Aguarda um pouco e tenta novamente
            return new Promise(resolve => {
                setTimeout(() => {
                    element = document.getElementById(id);
                    resolve(element);
                }, 50);
            });
        };
        
        // Tenta encontrar os elementos
        const modal = document.getElementById('accountModal');
        if (!modal) {
            // Se não encontrou, aguarda um pouco e tenta novamente
            setTimeout(() => {
                const retryModal = document.getElementById('accountModal');
                const title = document.getElementById('modalTitle');
                const form = document.getElementById('accountForm');
                const accountId = document.getElementById('account_id');
                
                if (!retryModal) {
                    console.error('Modal accountModal não encontrado após retry!');
                    alert('Erro: Modal não encontrado. Por favor, recarregue a página.');
                    return;
                }
                
                if (title) title.textContent = 'Nova conta';
                if (form) form.reset();
                if (accountId) accountId.value = '';
                
                retryModal.showModal();
            }, 100);
            return;
        }
        
        const title = document.getElementById('modalTitle');
        const form = document.getElementById('accountForm');
        const accountId = document.getElementById('account_id');
        
        if (title) title.textContent = 'Nova conta';
        if (form) form.reset();
        if (accountId) accountId.value = '';
        
        modal.showModal();
    };

    window.closeModal = function() {
        const modal = document.getElementById('accountModal');
        if (modal) {
            modal.close();
        }
    };

    window.toggleCreditFields = function() {
        const typeSelect = document.getElementById('type');
        const creditFields = document.getElementById('creditFields');
        if (typeSelect && creditFields) {
            if (typeSelect.value === 'credit') {
                creditFields.classList.remove('hidden');
            } else {
                creditFields.classList.add('hidden');
            }
        }
    };

    window.editAccountFromButton = function(button) {
        try {
            const accountData = button.getAttribute('data-account');
            if (!accountData) {
                console.error('Dados da conta não encontrados no atributo data-account');
                return;
            }
            const account = JSON.parse(accountData);
            window.editAccount(account);
        } catch (error) {
            console.error('Erro ao parsear dados da conta:', error, button);
            alert('Erro ao abrir o formulário de edição. Por favor, recarregue a página.');
        }
    };

    window.editAccount = function(account) {
        try {
            const modal = document.getElementById('accountModal');
            if (!modal) {
                // Se não encontrou, aguarda um pouco e tenta novamente
                setTimeout(() => {
                    const retryModal = document.getElementById('accountModal');
                    if (!retryModal) {
                        console.error('Modal accountModal não encontrado após retry!');
                        alert('Erro: Modal não encontrado. Por favor, recarregue a página.');
                        return;
                    }
                    
                    const title = document.getElementById('modalTitle');
                    const accountId = document.getElementById('account_id');
                    const icon = document.getElementById('icon');
                    const name = document.getElementById('name');
                    const type = document.getElementById('type');
                    const color = document.getElementById('color');
                    const creditLimit = document.getElementById('credit_limit');
                    const closingDay = document.getElementById('closing_day');
                    const dueDay = document.getElementById('due_day');
                    const interestRate = document.getElementById('interest_rate');
                    
                    if (title) title.textContent = 'Editar conta';
                    if (accountId) accountId.value = account.id;
                    if (icon) icon.value = account.icon || '';
                    if (name) name.value = account.name;
                    if (type) type.value = account.type || 'bank';
                    if (color) color.value = account.color || '';
                    
                    // Campos de crédito
                    if (creditLimit) creditLimit.value = account.credit_limit || '';
                    if (closingDay) closingDay.value = account.closing_day || '';
                    if (dueDay) dueDay.value = account.due_day || '';
                    if (interestRate) interestRate.value = account.interest_rate || '';
                    
                    window.toggleCreditFields();
                    retryModal.showModal();
                }, 100);
                return;
            }
            
            const title = document.getElementById('modalTitle');
            const accountId = document.getElementById('account_id');
            const icon = document.getElementById('icon');
            const name = document.getElementById('name');
            const type = document.getElementById('type');
            const color = document.getElementById('color');
            const creditLimit = document.getElementById('credit_limit');
            const closingDay = document.getElementById('closing_day');
            const dueDay = document.getElementById('due_day');
            const interestRate = document.getElementById('interest_rate');
            
            if (title) title.textContent = 'Editar conta';
            if (accountId) accountId.value = account.id;
            if (icon) icon.value = account.icon || '';
            if (name) name.value = account.name;
            if (type) type.value = account.type || 'bank';
            if (color) color.value = account.color || '';
            
            // Campos de crédito
            if (creditLimit) creditLimit.value = account.credit_limit || '';
            if (closingDay) closingDay.value = account.closing_day || '';
            if (dueDay) dueDay.value = account.due_day || '';
            if (interestRate) interestRate.value = account.interest_rate || '';
            
            window.toggleCreditFields();
            modal.showModal();
        } catch (error) {
            console.error('Erro ao editar conta:', error);
            alert('Erro ao abrir o formulário de edição. Por favor, recarregue a página.');
        }
    };

    window.confirmDelete = function(accountId, accountName) {
        const modal = document.getElementById('deleteModal');
        const nameElement = document.getElementById('deleteAccountName');
        const idElement = document.getElementById('delete_account_id');
        
        if (modal && nameElement && idElement) {
            modal.showModal();
            nameElement.textContent = accountName;
            idElement.value = accountId;
        }
    };

    window.closeDeleteModal = function() {
        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.close();
        }
    };
})();
</script>

<section class="space-y-6 accounts-screen">
    <?php
    $acctTypeLabels = [
        'bank' => 'Banco', 'cash' => 'Dinheiro', 'investment' => 'Investimento',
        'credit' => 'Cartão de crédito', 'other' => 'Outro',
    ];
    $activeCount = 0;
    foreach ($accounts as $item) {
        if (!empty($item['data']['is_active'])) { $activeCount++; }
    }
    ?>
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink);">Suas contas</span>
        <span style="font-size:12.5px; color:var(--muted);"><?php echo count($accounts); ?> <?php echo count($accounts) === 1 ? 'conta' : 'contas'; ?> &middot; <?php echo $activeCount; ?> ativas</span>
        <button type="button" onclick="openModal()" style="margin-left:auto; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:13.5px; padding:10px 18px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Nova conta</button>
    </div>

    <?php if ($success) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--pos) 11%, transparent); border:1px solid color-mix(in srgb, var(--pos) 30%, transparent); color:var(--pos); font-size:13px;"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--neg) 11%, transparent); border:1px solid color-mix(in srgb, var(--neg) 30%, transparent); color:var(--neg); font-size:13px;"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (empty($accounts)) : ?>
        <div style="background:var(--surface); border:1px dashed var(--line); border-radius:calc(var(--r) + 10px); padding:56px 32px; text-align:center;">
            <span style="width:58px; height:58px; margin:0 auto; display:grid; place-items:center; border-radius:50%; background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--accent);"><svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-6 9 6"></path><path d="M5 10v10M19 10v10M3 20h18M9 20v-5h6v5"></path></svg></span>
            <div style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink); margin-top:16px;">Nenhuma conta ainda</div>
            <div style="font-size:13.5px; color:var(--muted); margin-top:8px; line-height:1.6; max-width:440px; margin-left:auto; margin-right:auto;">Contas são de onde o dinheiro entra e sai &mdash; banco, dinheiro vivo, investimento ou cartão de crédito. Cadastre a primeira para começar a lançar.</div>
            <button type="button" onclick="openModal()" style="margin-top:22px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14px; padding:12px 24px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Criar primeira conta</button>
        </div>
    <?php else : ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px;">
        <?php foreach ($accounts as $item) :
            $account = $item['data'];
            $accountTotals = $item['totalsByCurrency'];
            $isActive = !empty($account['is_active']);
            $isCredit = ($account['type'] ?? '') === 'credit';
            $typeLabel = $acctTypeLabels[strtolower((string) ($account['type'] ?? ''))] ?? ucfirst((string) ($account['type'] ?? 'Conta'));
            $color = ($account['color'] ?? '') !== '' ? $account['color'] : 'var(--accent)';

            // Filtra moedas habilitadas e escolhe a principal (mesma lógica de antes).
            $filteredAccountTotals = [];
            foreach ($accountTotals as $currency => $totals) {
                if (in_array($currency, $enabledCurrencies, true)) {
                    $filteredAccountTotals[$currency] = $totals;
                }
            }
            if ($currencySettings['convert_all_to_one'] && $currencySettings['conversion_target_currency']) {
                $accountPrimaryCurrency = $currencySettings['conversion_target_currency'];
                $primaryValue = $filteredAccountTotals[$accountPrimaryCurrency] ?? ['balance' => 0, 'income' => 0, 'expense' => 0];
                $secondaryValues = [];
            } else {
                $accountPrimaryCurrency = $primaryCurrency;
                $primaryValue = $filteredAccountTotals[$primaryCurrency] ?? ['balance' => 0, 'income' => 0, 'expense' => 0];
                $secondaryValues = [];
                foreach ($filteredAccountTotals as $currency => $totals) {
                    if ($currency !== $primaryCurrency && (($totals['income'] ?? 0) > 0 || ($totals['expense'] ?? 0) > 0 || abs($totals['balance'] ?? 0) > 0.01)) {
                        $secondaryValues[$currency] = $totals;
                    }
                }
            }
            $saldoColor = ($primaryValue['balance'] ?? 0) < 0 ? 'var(--neg)' : 'var(--ink)';
        ?>
        <div class="acc-card" style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:19px 20px; display:flex; flex-direction:column; gap:13px; <?php echo $isActive ? '' : 'opacity:.55;'; ?>">
            <div style="display:flex; align-items:center; gap:11px;">
                <span style="width:40px; height:40px; flex:none; display:grid; place-items:center; font-size:19px; border-radius:12px; background:color-mix(in srgb, <?php echo $color; ?> 13%, transparent);"><?php echo sanitize($account['icon'] ?? ''); ?></span>
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:7px; min-width:0;">
                        <span style="font-size:15px; font-weight:800; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($account['name']); ?></span>
                        <?php if (!$isActive) : ?><span style="font-family:var(--f-m); font-size:9.5px; letter-spacing:.12em; text-transform:uppercase; padding:2px 8px; border-radius:999px; background:var(--line-soft); color:var(--muted); flex:none;">Inativa</span><?php endif; ?>
                    </div>
                    <div style="font-size:11.5px; color:var(--muted); margin-top:2px;"><?php echo sanitize($typeLabel); ?></div>
                </div>
                <button type="button" data-account='<?php echo json_encode($account); ?>' onclick="editAccountFromButton(this)" title="Editar conta" aria-label="Editar conta" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"></path></svg></button>
                <button type="button" onclick="confirmDelete(<?php echo (int) $account['id']; ?>, '<?php echo sanitize($account['name']); ?>')" title="Excluir conta" aria-label="Excluir conta" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg></button>
            </div>

            <div>
                <div style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Saldo</div>
                <div style="font-family:var(--f-num); font-size:22px; font-weight:700; font-variant-numeric:tabular-nums; color:<?php echo $saldoColor; ?>; margin-top:3px;" data-balance><?php echo formatCurrency($primaryValue['balance'] ?? 0, $accountPrimaryCurrency); ?></div>
                <?php if (!empty($secondaryValues)) : ?>
                    <?php foreach ($secondaryValues as $currency => $totals) : ?>
                        <div style="font-size:11.5px; color:var(--muted); margin-top:3px;" data-balance><?php echo formatCurrency($totals['balance'] ?? 0, $currency); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (($primaryValue['income'] ?? 0) > 0 || ($primaryValue['expense'] ?? 0) > 0) : ?>
            <div style="display:flex; gap:14px; font-size:12.5px; align-items:baseline;">
                <span style="color:var(--pos); font-family:var(--f-m); font-weight:600;" data-balance>&uarr; <?php echo formatCurrency($primaryValue['income'] ?? 0, $accountPrimaryCurrency); ?></span>
                <span style="color:var(--neg); font-family:var(--f-m); font-weight:600;" data-balance>&darr; <?php echo formatCurrency($primaryValue['expense'] ?? 0, $accountPrimaryCurrency); ?></span>
                <span style="color:var(--muted); margin-left:auto; font-size:11.5px;">total</span>
            </div>
            <?php endif; ?>

            <?php if ($isCredit) : ?>
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:11.5px; color:var(--muted); padding:9px 11px; border:1px dashed var(--line); border-radius:var(--r-chip);">
                <?php if (($account['credit_limit'] ?? null) !== null && $account['credit_limit'] !== '') : ?><span>Limite <span style="color:var(--ink); font-family:var(--f-m); font-weight:700;" data-balance><?php echo formatCurrency((float) $account['credit_limit'], $accountPrimaryCurrency); ?></span></span><span>&middot;</span><?php endif; ?>
                <?php if (!empty($account['closing_day'])) : ?><span>fecha dia <?php echo (int) $account['closing_day']; ?></span><span>&middot;</span><?php endif; ?>
                <?php if (!empty($account['due_day'])) : ?><span>vence dia <?php echo (int) $account['due_day']; ?></span><?php endif; ?>
                <?php if (($account['interest_rate'] ?? null) !== null && $account['interest_rate'] !== '') : ?><span>&middot;</span><span><?php echo number_format((float) $account['interest_rate'], 2, ',', '.'); ?>% a.m.</span><?php endif; ?>
                <a href="/public/credit-cards.php" style="margin-left:auto; color:var(--accent-text); font-weight:700; font-size:11.5px; text-decoration:none;">Ver fatura &rarr;</a>
            </div>
            <?php endif; ?>

            <div style="display:flex; align-items:center; gap:8px; padding-top:11px; border-top:1px solid var(--line-soft);">
                <form method="POST" action="/actions/accounts/toggle-dashboard.php" style="display:inline;">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="account_id" value="<?php echo (int) $account['id']; ?>">
                    <button type="submit" title="Mostrar ou esconder esta conta no dashboard" style="display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:600; color:<?php echo !empty($account['show_in_dashboard']) ? 'var(--accent-text)' : 'var(--muted)'; ?>; padding:5px 10px; border-radius:999px; border:1px solid <?php echo !empty($account['show_in_dashboard']) ? 'color-mix(in srgb, var(--accent) 40%, transparent)' : 'var(--line-soft)'; ?>; background:none; cursor:pointer;">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <?php echo !empty($account['show_in_dashboard']) ? 'No dashboard' : 'Fixar no dashboard'; ?>
                    </button>
                </form>
                <form method="POST" action="/actions/accounts/toggle.php" style="margin-left:auto; display:inline;">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="account_id" value="<?php echo (int) $account['id']; ?>">
                    <button type="submit" style="font-size:11.5px; font-weight:700; color:var(--muted); padding:5px 10px; border-radius:999px; border:1px solid var(--line-soft); background:none; cursor:pointer;"><?php echo $isActive ? 'Desativar' : 'Ativar'; ?></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<script>
// Fecha modais ao clicar fora (após DOM estar carregado)
document.addEventListener('DOMContentLoaded', function() {
    const accountModal = document.getElementById('accountModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (accountModal) {
        accountModal.addEventListener('click', function(e) {
            if (e.target === this) {
                window.closeModal();
            }
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                window.closeDeleteModal();
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

