<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

// v3: tables `wallets` and `wallet_accounts` are created by the canonical
// migration (migrations/20240101000000_baseline_v3.sql). The defensive
// CREATE-TABLE-IF-NOT-EXIST block that lived here is removed so the FK-bearing
// canonical schema (with CASCADE on users/accounts) is the only definition.
// See docs/migration-order.md for the deploy requirement to run migrations.

// Busca todas as carteiras do usuário
$wallets = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = :user_id ORDER BY name ASC');
    $stmt->execute(['user_id' => $userId]);
    $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela não existe ainda, continua com array vazio
    error_log('Erro ao buscar carteiras: ' . $e->getMessage());
}

// Busca todas as contas disponíveis
$allAccounts = [];
try {
    $stmt = $pdo->prepare('
        SELECT id, name, icon, type, is_active 
        FROM accounts 
        WHERE user_id = :user_id AND is_active = 1
        ORDER BY name ASC
    ');
    $stmt->execute(['user_id' => $userId]);
    $allAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao buscar contas: ' . $e->getMessage());
}

// Verifica se está visualizando uma carteira específica
$viewWalletId = filter_input(INPUT_GET, 'wallet_id', FILTER_VALIDATE_INT);
$viewWallet = null;
$viewWalletAccounts = [];
$totalsByCurrency = [];

if ($viewWalletId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM wallets WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $viewWalletId, 'user_id' => $userId]);
        $viewWallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($viewWallet) {
            $stmt = $pdo->prepare('
                SELECT a.id, a.name, a.icon, a.type, a.balance
                FROM wallet_accounts wa
                INNER JOIN accounts a ON a.id = wa.account_id
                WHERE wa.wallet_id = :wallet_id AND a.user_id = :user_id AND a.is_active = 1
                ORDER BY a.name ASC
            ');
            $stmt->execute(['wallet_id' => $viewWalletId, 'user_id' => $userId]);
            $viewWalletAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Busca totais de transações para cada conta da carteira (simplificado)
            $accountIds = array_column($viewWalletAccounts, 'id');
            
            if (!empty($accountIds)) {
                $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(currency, 'BRL') as currency,
                        SUM(CASE WHEN type = 'income' AND is_effective = 1 THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type = 'expense' AND is_effective = 1 THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE user_id = ? AND account_id IN ($placeholders)
                    GROUP BY COALESCE(currency, 'BRL')
                ");
                $params = array_merge([$userId], $accountIds);
                $stmt->execute($params);
                $totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($totals as $row) {
                    $totalsByCurrency[$row['currency']] = [
                        'income' => (float) $row['income'],
                        'expense' => (float) $row['expense'],
                        'balance' => (float) $row['income'] - (float) $row['expense'],
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar carteira: ' . $e->getMessage());
    }
}

// Para cada carteira, busca as contas vinculadas
$walletsWithAccounts = [];
try {
    foreach ($wallets as $wallet) {
        $stmt = $pdo->prepare('
            SELECT a.id, a.name, a.icon, a.type
            FROM wallet_accounts wa
            INNER JOIN accounts a ON a.id = wa.account_id
            WHERE wa.wallet_id = :wallet_id AND a.user_id = :user_id
            ORDER BY a.name ASC
        ');
        $stmt->execute(['wallet_id' => $wallet['id'], 'user_id' => $userId]);
        $walletAccounts = $stmt->fetchAll();
        
        $walletsWithAccounts[] = [
            'wallet' => $wallet,
            'accounts' => $walletAccounts,
        ];
    }
} catch (PDOException $e) {
    // Tabela não existe ainda, continua com array vazio
    error_log('Erro ao buscar contas das carteiras: ' . $e->getMessage());
}

$title = $viewWallet ? 'Carteira: ' . sanitize($viewWallet['name']) . ' | NovixFin' : 'Carteiras | NovixFin';
$pageStyles = ['styles/wallets.css'];

include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

// Se está visualizando uma carteira específica
if ($viewWallet) :
?>

<section class="space-y-6 wallets-screen">
    <a href="/public/wallets.php" style="align-self:flex-start; display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:var(--accent); padding:6px 10px; border-radius:var(--r-chip); text-decoration:none;"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 6-6 6 6 6"></path></svg>Todas as carteiras</a>

    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <span style="width:52px; height:52px; flex:none; display:grid; place-items:center; font-size:26px; border-radius:14px; background:color-mix(in srgb, var(--accent) 10%, transparent);"><?php echo sanitize($viewWallet['icon'] ?? ''); ?></span>
        <div style="flex:1; min-width:0;">
            <div style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:24px; color:var(--ink);"><?php echo sanitize($viewWallet['name']); ?></div>
            <?php if (!empty($viewWallet['description'])) : ?><div style="font-size:13px; color:var(--muted); margin-top:2px;"><?php echo sanitize($viewWallet['description']); ?></div><?php endif; ?>
        </div>
        <button type="button" onclick="editWallet(<?php echo htmlspecialchars(json_encode($viewWallet), ENT_QUOTES); ?>)" style="padding:10px 18px; border-radius:var(--r-btn); border:1.5px solid var(--accent); color:var(--accent); font-weight:700; font-size:13.5px; background:none; cursor:pointer;">Editar carteira</button>
    </div>

    <?php if (!empty($viewWalletAccounts)) : ?>
        <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); overflow:hidden;">
            <div style="display:grid; grid-template-columns:minmax(180px,1fr) 120px 120px 130px; gap:14px; padding:11px 22px; border-bottom:1px solid var(--line-soft); font-family:var(--f-m); font-size:10px; letter-spacing:.16em; text-transform:uppercase; color:var(--muted);">
                <span>Conta</span><span style="text-align:right;">Receitas</span><span style="text-align:right;">Despesas</span><span style="text-align:right;">Saldo</span>
            </div>
            <?php foreach ($viewWalletAccounts as $account) :
                $stmt = $pdo->prepare('
                    SELECT COALESCE(currency, "BRL") as currency,
                        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
                    FROM transactions
                    WHERE user_id = :user_id AND account_id = :account_id AND is_effective = 1
                    GROUP BY COALESCE(currency, "BRL")');
                $stmt->execute(['user_id' => $userId, 'account_id' => $account['id']]);
                $accountTotals = $stmt->fetchAll();
                $primaryTotal = $accountTotals[0] ?? ['income' => 0, 'expense' => 0, 'currency' => 'BRL'];
                $accBalance = (float) $primaryTotal['income'] - (float) $primaryTotal['expense'];
            ?>
            <div style="display:grid; grid-template-columns:minmax(180px,1fr) 120px 120px 130px; gap:14px; padding:12px 22px; border-bottom:1px solid var(--line-soft); align-items:center;">
                <span style="display:flex; align-items:center; gap:10px; min-width:0;">
                    <span style="width:32px; height:32px; flex:none; display:grid; place-items:center; font-size:15px; border-radius:9px; background:color-mix(in srgb, var(--accent) 13%, transparent);"><?php echo sanitize($account['icon'] ?? ''); ?></span>
                    <span style="min-width:0;"><span style="display:block; font-size:13.5px; font-weight:700; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($account['name']); ?></span><span style="display:block; font-size:11px; color:var(--muted); text-transform:capitalize;"><?php echo sanitize($account['type']); ?></span></span>
                </span>
                <span style="text-align:right; font-family:var(--f-m); font-size:12.5px; font-weight:600; color:var(--pos);" data-balance><?php echo formatCurrency((float) $primaryTotal['income'], $primaryTotal['currency']); ?></span>
                <span style="text-align:right; font-family:var(--f-m); font-size:12.5px; font-weight:600; color:var(--neg);" data-balance><?php echo formatCurrency((float) $primaryTotal['expense'], $primaryTotal['currency']); ?></span>
                <span style="text-align:right; font-family:var(--f-num); font-size:13.5px; font-weight:700; font-variant-numeric:tabular-nums; color:<?php echo $accBalance < 0 ? 'var(--neg)' : 'var(--ink)'; ?>;" data-balance><?php echo formatCurrency($accBalance, $primaryTotal['currency']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($totalsByCurrency)) : ?>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <span style="font-family:var(--f-m); font-size:10.5px; letter-spacing:.18em; text-transform:uppercase; color:var(--muted);">Totais por moeda</span>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px;">
                <?php foreach ($totalsByCurrency as $currency => $totals) : ?>
                <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:15px 17px; display:flex; flex-direction:column; gap:6px;">
                    <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.16em; text-transform:uppercase; color:var(--muted);"><?php echo sanitize($currency); ?></span>
                    <span style="font-size:12.5px; color:var(--pos); font-family:var(--f-m); font-weight:600;" data-balance><?php echo formatCurrency($totals['income'], $currency); ?></span>
                    <span style="font-size:12.5px; color:var(--neg); font-family:var(--f-m); font-weight:600;" data-balance><?php echo formatCurrency($totals['expense'], $currency); ?></span>
                    <span style="font-family:var(--f-num); font-size:16px; font-weight:700; font-variant-numeric:tabular-nums; color:<?php echo $totals['balance'] < 0 ? 'var(--neg)' : 'var(--ink)'; ?>; padding-top:5px; border-top:1px solid var(--line-soft);" data-balance><?php echo formatCurrency($totals['balance'], $currency); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php else : ?>
        <div style="background:var(--surface); border:1px dashed var(--line); border-radius:calc(var(--r) + 10px); padding:44px 24px; text-align:center; font-size:13.5px; color:var(--muted);">Nenhuma conta ativa vinculada a esta carteira.</div>
    <?php endif; ?>
</section>

<?php else : ?>

<section class="space-y-6 wallets-screen">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink);">Suas carteiras</span>
        <span style="font-size:12.5px; color:var(--muted);"><?php echo count($walletsWithAccounts); ?> <?php echo count($walletsWithAccounts) === 1 ? 'carteira' : 'carteiras'; ?></span>
        <button type="button" onclick="openWalletModal()" style="margin-left:auto; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:13.5px; padding:10px 18px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Nova carteira</button>
    </div>

    <?php if ($success) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--pos) 11%, transparent); border:1px solid color-mix(in srgb, var(--pos) 30%, transparent); color:var(--pos); font-size:13px;"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--neg) 11%, transparent); border:1px solid color-mix(in srgb, var(--neg) 30%, transparent); color:var(--neg); font-size:13px;"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (empty($walletsWithAccounts)) : ?>
        <div style="background:var(--surface); border:1px dashed var(--line); border-radius:calc(var(--r) + 10px); padding:56px 32px; text-align:center;">
            <span style="width:58px; height:58px; margin:0 auto; display:grid; place-items:center; border-radius:50%; background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--accent);"><svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 6V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"></path><path d="M3 6h14M16 13h.01"></path></svg></span>
            <div style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink); margin-top:16px;">Nenhuma carteira ainda</div>
            <div style="font-size:13.5px; color:var(--muted); margin-top:8px; line-height:1.6; max-width:460px; margin-left:auto; margin-right:auto;">Uma carteira agrupa contas por contexto &mdash; Pessoal, Trabalho, Investimentos &mdash; e mostra os saldos consolidados do grupo. Crie a primeira e vincule suas contas.</div>
            <button type="button" onclick="openWalletModal()" style="margin-top:22px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14px; padding:12px 24px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Criar primeira carteira</button>
        </div>
    <?php else : ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px;">
        <?php foreach ($walletsWithAccounts as $item) :
            $wallet = $item['wallet'];
            $walletAccounts = $item['accounts'];
        ?>
        <div class="card-hover" style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:19px 20px; display:flex; flex-direction:column; gap:12px;">
            <div style="display:flex; align-items:center; gap:11px;">
                <span style="width:40px; height:40px; flex:none; display:grid; place-items:center; font-size:19px; border-radius:12px; background:color-mix(in srgb, var(--accent) 13%, transparent);"><?php echo sanitize($wallet['icon'] ?? ''); ?></span>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:15px; font-weight:800; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($wallet['name']); ?></div>
                    <div style="font-size:11.5px; color:var(--muted); margin-top:2px;"><?php echo count($walletAccounts); ?> <?php echo count($walletAccounts) === 1 ? 'conta' : 'contas'; ?></div>
                </div>
                <button type="button" onclick="editWallet(<?php echo htmlspecialchars(json_encode($wallet), ENT_QUOTES); ?>)" title="Editar carteira" aria-label="Editar carteira" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"></path></svg></button>
                <form method="POST" action="/actions/wallets/delete.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta carteira? As contas não serão excluídas.');">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="wallet_id" value="<?php echo (int) $wallet['id']; ?>">
                    <button type="submit" title="Excluir carteira" aria-label="Excluir carteira" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg></button>
            </div>
            <?php if (!empty($wallet['description'])) : ?><div style="font-size:12.5px; color:var(--muted); line-height:1.5;"><?php echo sanitize($wallet['description']); ?></div><?php endif; ?>
            <?php if (!empty($walletAccounts)) : ?>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach ($walletAccounts as $account) : ?>
                        <span style="font-size:11.5px; font-weight:600; color:var(--ink); padding:4px 10px; border-radius:999px; border:1px solid var(--line-soft); background:var(--bg);"><?php echo sanitize(trim(($account['icon'] ?? '') . ' ' . $account['name'])); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div style="font-size:12px; color:var(--muted);">Nenhuma conta vinculada ainda &mdash; edite para vincular.</div>
            <?php endif; ?>
            <a href="/public/wallets.php?wallet_id=<?php echo (int) $wallet['id']; ?>" style="margin-top:auto; width:100%; padding:10px; border-radius:var(--r-btn); border:1.5px solid color-mix(in srgb, var(--accent) 45%, transparent); color:var(--accent); font-weight:700; font-size:13px; text-align:center; text-decoration:none; box-sizing:border-box;">Ver detalhes</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php endif; ?>

<!-- Modal Nova/Editar Carteira -->
<dialog id="walletModal" class="nvx-modal nvx-modal--wide wallets-modal bg-slate-900 backdrop:bg-slate-950/70 rounded-3xl p-6 max-w-2xl w-full border border-white/10">
        <div class="nvx-mhead">
            <div class="nvx-mhead-txt">
                <div id="walletModalTitle" class="nvx-mtitle">Nova carteira</div>
                <div class="nvx-msub">Agrupe contas em espécie.</div>
            </div>
            <button type="button" onclick="closeWalletModal()" aria-label="Fechar" class="nvx-mclose">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6 18 18M18 6 6 18"></path></svg>
            </button>
        </div>

        <form id="walletForm" method="POST" action="/actions/wallets/save.php" class="space-y-4">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" id="wallet_id" name="wallet_id" value="">
            
            <div>
                <label class="text-sm text-slate-300" for="wallet_icon">Ícone</label>
                <input type="text" id="wallet_icon" name="icon" maxlength="10" placeholder="💼" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="wallet_name">Nome</label>
                <input type="text" id="wallet_name" name="name" required maxlength="100" placeholder="Ex: Pessoal, Trabalho, Investimentos" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="wallet_description">Descrição (opcional)</label>
                <textarea id="wallet_description" name="description" rows="3" maxlength="500" placeholder="Descreva o propósito desta carteira..." class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none"></textarea>
            </div>
            
            <div>
                <label class="text-sm text-slate-300" for="wallet_color">Cor (hex)</label>
                <input type="text" id="wallet_color" name="color" maxlength="20" placeholder="#6366f1" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none">
            </div>
            
            <div>
                <label class="text-sm text-slate-300 mb-2 block">Contas</label>
                <div class="max-h-64 overflow-y-auto space-y-2 p-3 rounded-xl bg-slate-800/50 border border-white/5">
                    <?php foreach ($allAccounts as $account) : ?>
                        <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-slate-700/50 transition">
                            <input 
                                type="checkbox" 
                                name="account_ids[]" 
                                value="<?php echo (int) $account['id']; ?>"
                                class="w-5 h-5 rounded bg-slate-900 border-white/10"
                            >
                            <span class="text-xl"><?php echo sanitize($account['icon'] ?? '🏦'); ?></span>
                            <span class="flex-1 text-sm text-white"><?php echo sanitize($account['name']); ?></span>
                            <span class="text-xs text-slate-400 capitalize"><?php echo sanitize($account['type']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="nvx-mfoot pt-2">
                <button type="button" onclick="closeWalletModal()" class="nvx-mcancel">Cancelar</button>
                <button type="submit" class="nvx-mbtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    Salvar
                </button>
            </div>
        </form>
</dialog>

<script>
function openWalletModal() {
    document.getElementById('walletModal').showModal();
    document.getElementById('walletModalTitle').textContent = 'Nova Carteira';
    document.getElementById('walletForm').reset();
    document.getElementById('wallet_id').value = '';
    // Desmarca todas as contas
    document.querySelectorAll('input[name="account_ids[]"]').forEach(cb => cb.checked = false);
}

function closeWalletModal() {
    document.getElementById('walletModal').close();
}

function editWallet(wallet) {
    document.getElementById('walletModal').showModal();
    document.getElementById('walletModalTitle').textContent = 'Editar Carteira';
    document.getElementById('wallet_id').value = wallet.id;
    document.getElementById('wallet_icon').value = wallet.icon || '';
    document.getElementById('wallet_name').value = wallet.name;
    document.getElementById('wallet_description').value = wallet.description || '';
    document.getElementById('wallet_color').value = wallet.color || '';
    
    // Busca contas da carteira e marca
    fetch(`/actions/wallets/get-accounts.php?wallet_id=${wallet.id}`)
        .then(response => response.json())
        .then(data => {
            document.querySelectorAll('input[name="account_ids[]"]').forEach(cb => {
                cb.checked = data.account_ids && data.account_ids.includes(parseInt(cb.value));
            });
        })
        .catch(err => console.error('Erro ao carregar contas:', err));
}

// Fecha modal ao clicar fora
document.getElementById('walletModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeWalletModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

