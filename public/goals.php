<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

// Tenta usar 'title', se não existir usa 'name' (compatibilidade)
try {
    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM goals LIKE 'title'");
    $hasTitleColumn = $checkColumnStmt->rowCount() > 0;
    $titleColumn = $hasTitleColumn ? 'title' : 'name';
} catch (Exception $e) {
    $titleColumn = 'name'; // Fallback
}

$stmt = $pdo->prepare("SELECT *, {$titleColumn} as title FROM goals WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => $userId]);
$goals = $stmt->fetchAll();

$title = 'Metas | NovixFin';
$pageStyles = ['styles/goals.css'];

include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

?>

<section class="space-y-6 goals-screen">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink);">Suas metas financeiras</span>
        <span style="font-size:12.5px; color:var(--muted);"><?php echo count($goals); ?> <?php echo count($goals) === 1 ? 'meta' : 'metas'; ?></span>
        <button type="button" data-open-modal style="margin-left:auto; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:13.5px; padding:10px 18px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Nova meta</button>
    </div>

    <?php if ($success) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--pos) 11%, transparent); border:1px solid color-mix(in srgb, var(--pos) 30%, transparent); color:var(--pos); font-size:13px;"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--neg) 11%, transparent); border:1px solid color-mix(in srgb, var(--neg) 30%, transparent); color:var(--neg); font-size:13px;"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (empty($goals)) : ?>
        <div style="background:var(--surface); border:1px dashed var(--line); border-radius:calc(var(--r) + 10px); padding:56px 32px; text-align:center;">
            <span style="width:58px; height:58px; margin:0 auto; display:grid; place-items:center; border-radius:50%; background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--accent);"><svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="5"></circle><circle cx="12" cy="12" r="1.2" fill="currentColor"></circle></svg></span>
            <div style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink); margin-top:16px;">Nenhuma meta ainda</div>
            <div style="font-size:13.5px; color:var(--muted); margin-top:8px; line-height:1.6; max-width:460px; margin-left:auto; margin-right:auto;">Uma meta é um alvo para alcançar &mdash; um saldo, uma receita. Um limite é um teto para não passar. Crie a primeira e acompanhe o progresso aqui.</div>
            <button type="button" data-open-modal style="margin-top:22px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14px; padding:12px 24px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Criar primeira meta</button>
        </div>
    <?php else : ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px;">
        <?php foreach ($goals as $goal) :
            $target = (float) ($goal['target_amount'] ?? 0);
            $current = (float) ($goal['current_amount'] ?? 0);
            $rawPct = $target > 0 ? ($current / $target) * 100 : 0;
            $barW = max(0, min(100, $rawPct));
            $goalType = $goal['type'] ?? 'saving';
            $typeLabels = [
                'saving' => 'Economia', 'income_target' => 'Meta de receita',
                'balance_target' => 'Meta de saldo', 'expense_limit' => 'Limite de despesas',
                'spending_limit' => 'Limite de gastos',
            ];
            $typeLabel = $typeLabels[$goalType] ?? 'Meta';
            $isLimit = in_array($goalType, ['expense_limit', 'spending_limit'], true);
            $kindLabel = $isLimit ? 'Limite' : 'Meta';
            $kindColor = $isLimit ? 'var(--gold)' : 'var(--accent)';
            if ($isLimit) {
                if ($rawPct >= 100) { $stLabel = 'Estourado'; $stColor = 'var(--neg)'; $barColor = 'var(--neg)'; }
                elseif ($rawPct >= 80) { $stLabel = 'Perto do limite'; $stColor = 'var(--gold)'; $barColor = 'var(--gold)'; }
                else { $stLabel = 'Dentro do limite'; $stColor = 'var(--pos)'; $barColor = 'var(--gold)'; }
            } else {
                if ($rawPct >= 100) { $stLabel = 'Concluída'; $stColor = 'var(--pos)'; $barColor = 'var(--pos)'; }
                else { $stLabel = 'Em progresso'; $stColor = 'var(--accent)'; $barColor = 'var(--accent)'; }
            }
            $remaining = $target - $current;
            $note = $isLimit
                ? ($remaining >= 0 ? 'restam ' . formatCurrency($remaining, 'BRL') . ' do limite' : 'passou ' . formatCurrency(abs($remaining), 'BRL'))
                : ($remaining > 0 ? 'faltam ' . formatCurrency($remaining, 'BRL') : 'alvo alcançado');

            $accountName = null;
            if (!empty($goal['account_id'])) {
                $accountStmt = $pdo->prepare('SELECT name, icon FROM accounts WHERE id = :id');
                $accountStmt->execute(['id' => $goal['account_id']]);
                $account = $accountStmt->fetch();
                if ($account) {
                    $accountName = trim(($account['icon'] ?? '') . ' ' . $account['name']);
                }
            }
            $deadlineF = !empty($goal['deadline']) ? date('d/m/Y', strtotime($goal['deadline'])) : 'sem prazo';
            $autoLabel = (isset($goal['auto_update']) ? (bool) $goal['auto_update'] : true) ? 'Auto' : 'Manual';
        ?>
        <div class="card-hover" style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:19px 20px; display:flex; flex-direction:column; gap:12px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.16em; padding:3px 9px; border-radius:999px; font-weight:700; background:color-mix(in srgb, <?php echo $kindColor; ?> 13%, transparent); color:<?php echo $kindColor; ?>;"><?php echo $kindLabel; ?></span>
                <span style="font-size:11.5px; color:var(--muted);"><?php echo sanitize($typeLabel); ?></span>
                <span style="margin-left:auto; display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; background:color-mix(in srgb, <?php echo $stColor; ?> 13%, transparent); color:<?php echo $stColor; ?>;"><span style="width:5px; height:5px; border-radius:50%; background:<?php echo $stColor; ?>;"></span><?php echo $stLabel; ?></span>
            </div>
            <div style="display:flex; align-items:flex-start; gap:8px;">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:16px; font-weight:800; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($goal['title']); ?></div>
                    <div style="font-size:12.5px; color:var(--muted); margin-top:4px;"><span style="font-family:var(--f-num); font-size:15px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--ink);" data-balance><?php echo formatCurrency($current, 'BRL'); ?></span> de <span data-balance><?php echo formatCurrency($target, 'BRL'); ?></span></div>
                </div>
                <button type="button" data-edit-goal="<?php echo (int) $goal['id']; ?>" title="Editar meta" aria-label="Editar meta" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"></path></svg></button>
                <form method="POST" action="/actions/goals/delete.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta meta?')">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $goal['id']; ?>">
                    <button type="submit" title="Excluir meta" aria-label="Excluir meta" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg></button>
            </div>
            <div style="height:8px; border-radius:999px; background:var(--line-soft); overflow:hidden;"><span style="display:block; height:100%; border-radius:999px; width:<?php echo $barW; ?>%; background:<?php echo $barColor; ?>; transition:width .4s ease;"></span></div>
            <div style="display:flex; align-items:baseline; gap:7px; font-size:12px; color:var(--muted);">
                <span style="font-family:var(--f-m); font-weight:700; color:<?php echo $stColor; ?>;"><?php echo number_format($rawPct, 0); ?>%</span>
                <span><?php echo $note; ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:8px; font-size:11.5px; color:var(--muted); flex-wrap:wrap; padding-top:10px; border-top:1px solid var(--line-soft);">
                <span style="display:inline-flex; align-items:center; gap:5px;"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2"></rect><path d="M3 9h18M8 2.5v4M16 2.5v4"></path></svg><?php echo $deadlineF; ?></span>
                <?php if ($accountName) : ?><span>&middot;</span><span style="min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($accountName); ?></span><?php endif; ?>
                <span style="margin-left:auto; font-family:var(--f-m); font-size:10px; letter-spacing:.1em; text-transform:uppercase; padding:3px 8px; border-radius:999px; border:1px solid var(--line-soft);"><?php echo $autoLabel; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<dialog id="modal-goal" class="nvx-modal goals-modal bg-slate-900/90 backdrop:bg-slate-950/70 rounded-3xl border border-white/10 max-w-xl w-full p-6">
    <form method="POST" action="/actions/goals/save.php" class="space-y-4">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="id" value="">
        <div>
            <label class="text-sm text-slate-300" for="goal-title">Título</label>
            <input type="text" id="goal-title" name="title" required minlength="3" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-slate-300" for="goal-target">Valor alvo (R$)</label>
                <input type="number" id="goal-target" name="target_amount" min="0.01" step="0.01" required class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
            </div>
            <div>
                <label class="text-sm text-slate-300" for="goal-current">Valor atual (R$)</label>
                <input type="number" id="goal-current" name="current_amount" min="0" step="0.01" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
            </div>
        </div>
        <div>
            <label class="text-sm text-slate-300" for="goal-account">Conta (opcional)</label>
            <select id="goal-account" name="account_id" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
                <option value="">Todas as contas</option>
                <?php
                $accountsStmt = $pdo->prepare('SELECT id, name, icon FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC');
                $accountsStmt->execute(['user_id' => $userId]);
                foreach ($accountsStmt->fetchAll() as $account) : ?>
                    <option value="<?php echo (int) $account['id']; ?>">
                        <?php echo sanitize(($account['icon'] ?? '🏦') . ' ' . $account['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500 mt-1">Deixe em branco para meta global, ou selecione uma conta específica</p>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-slate-300" for="goal-deadline">Prazo</label>
                <input type="date" id="goal-deadline" name="deadline" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
            </div>
            <div>
                <label class="text-sm text-slate-300" for="goal-type">Tipo de Meta</label>
                <select id="goal-type" name="type" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-sm">
                    <option value="saving">Economia (Poupar valor)</option>
                    <option value="income_target">Meta de Receita</option>
                    <option value="balance_target">Meta de Saldo</option>
                    <option value="expense_limit">Limite de Despesas</option>
                    <option value="spending_limit">Limite de Gastos</option>
                </select>
            </div>
        </div>
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="goal-auto-update" name="auto_update" value="1" checked class="w-4 h-4 rounded bg-slate-900 border-white/10 text-violet-500 focus:ring-violet-500 focus:ring-offset-slate-900">
                <span class="text-sm text-slate-300">Atualizar automaticamente com base em transações</span>
            </label>
            <p class="text-xs text-slate-500 mt-1">Se marcado, o progresso será atualizado automaticamente conforme você registra transações</p>
        </div>
        <div class="flex items-center justify-end gap-3">
            <button type="button" data-close-modal class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 transition text-sm">Cancelar</button>
            <button type="submit" class="px-4 py-2 rounded-lg bg-violet-500 hover:bg-violet-400 transition text-white text-sm">Salvar</button>
        </div>
    </form>
</dialog>

<?php
$pageScripts = ['scripts/goals.js'];
?>

<script>
    const goalsData = <?php echo json_encode(array_map(static function (array $goal): array {
        return [
            'id' => (int) $goal['id'],
            'title' => $goal['title'],
            'target_amount' => (float) $goal['target_amount'],
            'current_amount' => (float) $goal['current_amount'],
            'deadline' => $goal['deadline'],
            'type' => $goal['type'],
            'account_id' => isset($goal['account_id']) ? (int) $goal['account_id'] : null,
            'auto_update' => isset($goal['auto_update']) ? (bool) $goal['auto_update'] : true,
        ];
    }, $goals), JSON_THROW_ON_ERROR); ?>;
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

