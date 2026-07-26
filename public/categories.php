<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

$pdo = Connection::get();
$userId = currentUserId() ?? 0;

// v3: `categories.user_id` is added by the canonical migration
// (migrations/20240101000001_reconcile_v3.php). The defensive ALTER-TABLE
// that lived here is removed so the migration owns the column definition.
//
// The probe for `user_id` existence is scheduled for removal in Phase 3
// Batch C (once the migration is confirmed applied on prod).

// Busca categorias do usuário, separadas por tipo
// Verifica se user_id existe na tabela antes de usar
$hasUserIdColumn = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
    $hasUserIdColumn = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Ignora erro
}

if ($hasUserIdColumn) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id = :user_id ORDER BY type ASC, name ASC');
    $stmt->execute(['user_id' => $userId]);
} else {
    // Se não tem user_id, mostra todas (compatibilidade com dados antigos)
    $stmt = $pdo->query('SELECT * FROM categories ORDER BY type ASC, name ASC');
}
$allCategories = $stmt->fetchAll();

// Separa por tipo
$incomeCategories = array_filter($allCategories, fn($cat) => $cat['type'] === 'income');
$expenseCategories = array_filter($allCategories, fn($cat) => $cat['type'] === 'expense');

$title = 'Categorias | NovixFin';
$pageStyles = ['styles/categories.css'];

include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

?>

<section class="space-y-6 cat-screen">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink);">Suas categorias</span>
        <span style="font-size:12.5px; color:var(--muted);"><?php echo count($allCategories); ?> <?php echo count($allCategories) === 1 ? 'categoria' : 'categorias'; ?></span>
        <button type="button" onclick="openModal()" style="margin-left:auto; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:13.5px; padding:10px 18px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Nova categoria</button>
    </div>

    <?php if ($success) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--pos) 11%, transparent); border:1px solid color-mix(in srgb, var(--pos) 30%, transparent); color:var(--pos); font-size:13px;"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--neg) 11%, transparent); border:1px solid color-mix(in srgb, var(--neg) 30%, transparent); color:var(--neg); font-size:13px;"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (empty($allCategories)) : ?>
        <div style="background:var(--surface); border:1px dashed var(--line); border-radius:calc(var(--r) + 10px); padding:56px 32px; text-align:center;">
            <span style="width:58px; height:58px; margin:0 auto; display:grid; place-items:center; border-radius:14px; background:color-mix(in srgb, var(--accent) 10%, transparent);"><span style="width:26px; height:26px; display:grid; place-items:center; transform:rotate(45deg); border-radius:6px; background:color-mix(in srgb, var(--accent) 18%, transparent);"><span style="transform:rotate(-45deg); font-size:13px; font-weight:800; color:var(--accent);">C</span></span></span>
            <div style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink); margin-top:16px;">Nenhuma categoria ainda</div>
            <div style="font-size:13.5px; color:var(--muted); margin-top:8px; line-height:1.6; max-width:460px; margin-left:auto; margin-right:auto;">Categorias dão identidade a cada lançamento do extrato e alimentam o kanban de classificação de gastos do Dashboard. Crie a primeira.</div>
            <button type="button" onclick="openModal()" style="margin-top:22px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14px; padding:12px 24px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">+ Criar primeira categoria</button>
        </div>
    <?php else : ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:16px; align-items:start;">
        <?php
        $catColumns = [
            ['label' => 'Receitas', 'color' => 'var(--pos)', 'list' => $incomeCategories],
            ['label' => 'Despesas', 'color' => 'var(--neg)', 'list' => $expenseCategories],
        ];
        foreach ($catColumns as $col) :
        ?>
        <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); overflow:hidden;">
            <div style="display:flex; align-items:baseline; gap:9px; padding:15px 20px 12px; border-bottom:1px solid var(--line-soft);">
                <span style="font-size:13.5px; font-weight:800; color:<?php echo $col['color']; ?>;"><?php echo $col['label']; ?></span>
                <span style="font-family:var(--f-m); font-size:11.5px; color:var(--muted);"><?php echo count($col['list']); ?></span>
            </div>
            <?php if (empty($col['list'])) : ?>
                <div style="padding:22px 20px; font-size:13px; color:var(--muted);">Nenhuma categoria de <?php echo strtolower($col['label']); ?> ainda.</div>
            <?php else : ?>
                <?php foreach ($col['list'] as $category) :
                    $initial = ($category['name'] ?? '') !== '' ? strtoupper(substr($category['name'], 0, 1)) : '.';
                ?>
                <div style="display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid var(--line-soft);">
                    <span style="width:30px; height:30px; flex:none; display:grid; place-items:center; transform:rotate(45deg); border-radius:6px; background:color-mix(in srgb, <?php echo $col['color']; ?> 16%, transparent);"><span style="transform:rotate(-45deg); font-size:11.5px; font-weight:800; color:<?php echo $col['color']; ?>;"><?php echo sanitize($initial); ?></span></span>
                    <span style="flex:1; min-width:0; font-size:14px; font-weight:700; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($category['name']); ?></span>
                    <form method="POST" action="/actions/categories/delete.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja remover esta categoria?')">
                        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                        <button type="submit" title="Excluir categoria" aria-label="Excluir categoria" style="width:30px; height:30px; flex:none; display:grid; place-items:center; border-radius:var(--r-chip); color:var(--muted); border:none; background:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v5 M14 11v5"></path></svg></button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- Modal Nova Categoria -->
<dialog id="categoryModal" class="cat-modal bg-slate-900 backdrop:bg-slate-950/70 rounded-3xl p-6 max-w-md w-full border border-white/10">
        <h2 class="text-2xl font-semibold text-white mb-6">Nova Categoria</h2>
        
        <form method="POST" action="/actions/categories/save.php" class="space-y-4">
            <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
            
            <div>
                <label class="text-sm text-slate-300" for="name">Nome da Categoria</label>
                <input type="text" id="name" name="name" required maxlength="100" placeholder="Ex: Streaming, Pets, Academia" class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
            </div>
            <div>
                <label class="text-sm text-slate-300" for="type">Tipo</label>
                <select id="type" name="type" required class="w-full mt-1 px-4 py-2 rounded-xl bg-slate-900 border border-white/10 focus:border-violet-500 focus:outline-none text-white">
                    <option value="income">Receita</option>
                    <option value="expense">Despesa</option>
                </select>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 transition text-white">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-violet-500 hover:bg-violet-400 transition text-white font-medium">Adicionar</button>
            </div>
        </form>
</dialog>

<script>
function openModal() {
    document.getElementById('categoryModal').showModal();
}

function closeModal() {
    document.getElementById('categoryModal').close();
    document.getElementById('name').value = '';
}

// Fecha modal ao clicar fora
document.getElementById('categoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

