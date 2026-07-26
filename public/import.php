<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\TransactionRepository;
use Novix\Services\GamificationService;

/**
 * NovixFin v3 — Phase 7: CSV re-import tool (round-trip with public/export.php).
 *
 * Two-stage flow:
 *   1) GET  → renders the upload form.
 *   2) POST ?action=preview (multipart file) → parses the CSV in-memory and
 *      shows a preview table; stashes parsed rows into $_SESSION so the
 *      user can review before commit. NO database writes at this stage.
 *   3) POST ?action=commit  (with CSRF + the session-preview confirmation) →
 *      inserts each valid transaction row inside a single DB transaction;
 *      on any failure, rolls back fully and reports. Awards gamification
 *      points per successfully-committed effective row.
 *
 * Accepts the same CSV format written by export.php (the "# SECTION:
 * transactions" prefix is tolerated/optional; the first non-comment row is
 * treated as the header). Required columns for transaction import:
 *   date, type, amount, currency, description, category_id, account_id
 * Optional columns: is_effective, recurring, recurrence_type.
 */

$userId = currentUserId() ?? 0;
if ($userId <= 0) {
    http_response_code(401);
    exit('Unauthorized.');
}

$title = 'Importar transações | NovixFin';
$pageStyles = ['styles/io.css'];
include __DIR__ . '/../includes/layout/header.php';

$success = flash('success');
$error = flash('error');

$preview = $_SESSION['import_preview'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['_token'] ?? '');
}

if ($action === 'preview' && isset($_FILES['csv_file'])) {
    // Stage 2: parse upload → stash preview in session.
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Falha no upload: código ' . $file['error']);
        redirect('/public/import.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Não foi possível ler o arquivo enviado.');
        redirect('/public/import.php');
    }

    $parsed = [];
    $header = null;
    while (($row = fgetcsv($handle)) !== false) {
        if (empty($row)) continue;
        $first = $row[0] ?? '';
        // Skip comment / section / blank rows.
        if ($first === '' || str_starts_with((string) $first, '#')) continue;

        if ($header === null) {
            $header = $row;
            continue;
        }
        if (count($row) !== count($header)) continue;
        $parsed[] = array_combine($header, $row);
    }
    fclose($handle);

    if ($header === null || empty($parsed)) {
        flash('error', 'CSV vazio ou sem cabeçalho reconhecido.');
        redirect('/public/import.php');
    }

    // Validate required columns.
    $required = ['date', 'type', 'amount', 'category_id', 'account_id'];
    $missing = array_diff($required, $header);
    if (!empty($missing)) {
        flash('error', 'CSV sem colunas obrigatórias: ' . implode(', ', $missing));
        redirect('/public/import.php');
    }

    $_SESSION['import_preview'] = ['header' => $header, 'rows' => $parsed];
    $preview = $_SESSION['import_preview'];
}

if ($action === 'commit' && isset($_POST['confirm']) && $_POST['confirm'] === '1') {
    // Stage 3: commit the previously-previewed rows.
    if (!isset($preview['rows']) || empty($preview['rows'])) {
        flash('error', 'Nenhum dado pré-visualizado para importar. Envie um CSV primeiro.');
        redirect('/public/import.php');
    }

    $pdo = Connection::get();
    $repo = new TransactionRepository($pdo);
    $imported = 0;
    $errors = [];

    // Validate the foreign keys the user is about to mutate against before
    // opening the transaction so we don't fail mid-batch for an obvious one.
    $accountCheck = $pdo->prepare('SELECT 1 FROM accounts WHERE id = :id AND user_id = :uid LIMIT 1');
    $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id AND (user_id = :uid OR user_id IS NULL) LIMIT 1');

    // Whole batch commits atomically.
    $pdo->beginTransaction();
    try {
        $gamification = new GamificationService($pdo);
        foreach ($preview['rows'] as $i => $row) {
            $date = trim((string) ($row['date'] ?? ''));
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            $amount = (float) ($row['amount'] ?? 0);
            $currency = (string) ($row['currency'] ?? 'BRL');
            $description = (string) ($row['description'] ?? '');
            $accountId = (int) ($row['account_id'] ?? 0);
            $categoryId = (int) ($row['category_id'] ?? 0);
            $isEffective = ($row['is_effective'] ?? '1') === '1' || $row['is_effective'] === 'true';

            if (!in_array($type, ['income', 'expense'], true)) {
                $errors[] = 'Linha ' . ($i + 2) . ': tipo inválido "' . sanitize($type) . '"';
                continue;
            }
            if ($amount <= 0) {
                $errors[] = 'Linha ' . ($i + 2) . ': valor deve ser positivo';
                continue;
            }
            $accountCheck->execute([':id' => $accountId, ':uid' => $userId]);
            $categoryCheck->execute([':id' => $categoryId, ':uid' => $userId]);
            if (!$accountCheck->fetchColumn()) { $errors[] = 'Linha ' . ($i + 2) . ': conta ' . $accountId . ' inválida'; continue; }
            if (!$categoryCheck->fetchColumn()) { $errors[] = 'Linha ' . ($i + 2) . ': categoria ' . $categoryId . ' inválida'; continue; }

            try {
                $dateObj = new DateTimeImmutable($date);
            } catch (Throwable $e) { $errors[] = 'Linha ' . ($i + 2) . ': data inválida "' . sanitize($date) . '"'; continue; }

            try {
                $repo->create($userId, $accountId, $categoryId, $type, $amount, $description, $dateObj, $currency, $isEffective);
                $gamification->award($userId, $isEffective ? 'tx_created_effective' : 'tx_created_pending');
                $imported++;
            } catch (Throwable $e) {
                $errors[] = 'Linha ' . ($i + 2) . ': ' . $e->getMessage();
            }
        }

        if (!empty($errors) && $imported === 0) {
            $pdo->rollBack();
            flash('error', 'Nenhuma linha importada. Erros: ' . implode(' | ', array_slice($errors, 0, 5)));
            unset($_SESSION['import_preview']);
            redirect('/public/import.php');
        }

        $pdo->commit();
        unset($_SESSION['import_preview']);
        flash('success', sprintf('%d transações importadas.', $imported) . ($errors ? (' Erros: ' . implode(' | ', array_slice($errors, 0, 5))) : ''));
        redirect('/public/history.php');
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $rb) {}
        flash('error', 'Falha ao importar: ' . $e->getMessage());
        redirect('/public/import.php');
    }
}
?>

<section class="space-y-6 io-screen">
    <div>
        <span style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink);">Import / Export</span>
        <p style="font-size:13px; color:var(--muted); margin-top:4px;">Traga um extrato em CSV com pré-visualização, ou leve seus dados para backup e migração.</p>
    </div>

    <?php if ($success) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--pos) 11%, transparent); border:1px solid color-mix(in srgb, var(--pos) 30%, transparent); color:var(--pos); font-size:13px;"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--neg) 11%, transparent); border:1px solid color-mix(in srgb, var(--neg) 30%, transparent); color:var(--neg); font-size:13px;"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (isset($preview['rows']) && !empty($preview['rows'])) : ?>
        <!-- ESTADO: PRÉ-VISUALIZAÇÃO (nada gravado ainda; overflow-x preservado) -->
        <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); overflow:hidden;">
            <div style="display:flex; align-items:center; gap:12px; padding:17px 22px; border-bottom:1px solid var(--line-soft); flex-wrap:wrap;">
                <div style="min-width:0;">
                    <div style="font-size:15.5px; font-weight:800; color:var(--ink);">Pré-visualização &mdash; nada foi gravado ainda</div>
                    <div style="font-size:12.5px; color:var(--muted); margin-top:2px;"><?php echo count($preview['rows']); ?> linhas no arquivo</div>
                </div>
                <span style="margin-left:auto; display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:700; background:color-mix(in srgb, var(--pos) 11%, transparent); color:var(--pos);"><?php echo count($preview['rows']); ?> prontas</span>
            </div>
            <div class="io-prevwrap">
                <div class="io-prevmin">
                    <div style="display:flex; gap:0; padding:11px 22px; border-bottom:1px solid var(--line-soft); font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">
                        <?php foreach ($preview['header'] as $h) : ?>
                            <span style="flex:1; min-width:90px; padding-right:12px;"><?php echo sanitize((string) $h); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach (array_slice($preview['rows'], 0, 20) as $row) : ?>
                        <div style="display:flex; gap:0; padding:11px 22px; border-bottom:1px solid var(--line-soft); align-items:center;">
                            <?php foreach ($preview['header'] as $h) : ?>
                                <span style="flex:1; min-width:90px; padding-right:12px; font-size:12.5px; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize((string) ($row[$h] ?? '')); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px; padding:15px 22px; flex-wrap:wrap;">
                <span style="font-size:12.5px; color:var(--muted); min-width:0;">Confira as linhas acima. A gravação só acontece ao confirmar.</span>
                <form method="POST" action="/public/import.php" style="margin-left:auto; display:flex; gap:10px;">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="commit">
                    <input type="hidden" name="confirm" value="1">
                    <a href="/public/import.php" style="padding:11px 18px; border-radius:var(--r-btn); color:var(--muted); font-weight:600; font-size:13.5px; border:1px solid var(--line-soft); text-decoration:none;">Cancelar</a>
                    <button type="submit" style="padding:11px 22px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:13.5px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">Confirmar importação</button>
                </form>
            </div>
        </div>
    <?php else : ?>
        <?php
        // Referência de IDs (dados reais) — ajuda a montar o CSV. Read-only.
        $ioAccounts = [];
        $ioCategories = [];
        try {
            $ioPdo = Connection::get();
            $s = $ioPdo->prepare('SELECT id, name FROM accounts WHERE user_id = :u AND is_active = 1 ORDER BY name ASC');
            $s->execute(['u' => $userId]);
            $ioAccounts = $s->fetchAll(PDO::FETCH_ASSOC);
            $s = $ioPdo->prepare('SELECT id, name FROM categories WHERE (user_id = :u OR user_id IS NULL) ORDER BY name ASC');
            $s->execute(['u' => $userId]);
            $ioCategories = $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // segue sem a referência
        }
        $ioReqCols = ['date', 'type', 'amount', 'category_id', 'account_id'];
        ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(380px,1fr)); gap:16px; align-items:start;">
            <!-- IMPORTAR -->
            <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px 24px; display:flex; flex-direction:column; gap:15px;">
                <div style="display:flex; align-items:center; gap:11px;">
                    <span style="width:38px; height:38px; flex:none; display:grid; place-items:center; border-radius:11px; background:color-mix(in srgb, var(--accent) 11%, transparent); color:var(--accent);"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V5 M8 9l4-4 4 4 M4 19h16"></path></svg></span>
                    <div><div style="font-size:16px; font-weight:800; color:var(--ink);">Importar transações</div><div style="font-size:12px; color:var(--muted); margin-top:1px;">CSV no formato do NovixFin &mdash; com pré-visualização antes de gravar.</div></div>
                </div>
                <form method="POST" action="/public/import.php?action=preview" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:14px;">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="preview">
                    <label class="io-drop" style="display:block; border:1.5px dashed var(--line); border-radius:var(--r-chip); padding:22px 20px; text-align:center; cursor:pointer;">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="var(--muted)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto;"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><path d="M14 3v6h6"></path></svg>
                        <div style="font-size:13.5px; font-weight:700; color:var(--ink); margin-top:9px;">Escolha o arquivo CSV</div>
                        <div style="font-size:12px; color:var(--muted); margin-top:3px;">O arquivo é lido e pré-visualizado &mdash; nada é gravado ainda.</div>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required style="margin-top:12px;">
                    </label>
                    <button type="submit" style="width:100%; padding:12px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">Enviar e pré-visualizar</button>
                </form>
                <div style="display:flex; flex-direction:column; gap:7px;">
                    <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Colunas obrigatórias</span>
                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                        <?php foreach ($ioReqCols as $rc) : ?>
                            <span style="font-family:var(--f-m); font-size:11.5px; font-weight:600; color:var(--ink); padding:4px 10px; border-radius:999px; border:1px solid var(--line-soft); background:var(--bg);"><?php echo $rc; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <span style="font-size:11.5px; color:var(--muted);">Opcionais: currency, description, is_effective, recurring, recurrence_type.</span>
                </div>
                <?php if (!empty($ioAccounts) || !empty($ioCategories)) : ?>
                <div style="display:flex; flex-direction:column; gap:7px;">
                    <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Seus IDs de referência</span>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div style="border:1px solid var(--line-soft); border-radius:var(--r-chip); padding:10px 12px;">
                            <div style="font-size:11px; font-weight:700; color:var(--muted); margin-bottom:6px;">Contas</div>
                            <?php foreach ($ioAccounts as $ai) : ?>
                                <div style="display:flex; justify-content:space-between; gap:8px; font-size:12px; padding:2px 0;"><span style="color:var(--ink); min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($ai['name']); ?></span><span style="font-family:var(--f-m); font-weight:700; color:var(--accent); flex:none;"><?php echo (int) $ai['id']; ?></span></div>
                            <?php endforeach; ?>
                        </div>
                        <div style="border:1px solid var(--line-soft); border-radius:var(--r-chip); padding:10px 12px;">
                            <div style="font-size:11px; font-weight:700; color:var(--muted); margin-bottom:6px;">Categorias</div>
                            <?php foreach ($ioCategories as $ci) : ?>
                                <div style="display:flex; justify-content:space-between; gap:8px; font-size:12px; padding:2px 0;"><span style="color:var(--ink); min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo sanitize($ci['name']); ?></span><span style="font-family:var(--f-m); font-weight:700; color:var(--accent); flex:none;"><?php echo (int) $ci['id']; ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- EXPORTAR (opções -> export.php, que faz o download) -->
            <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px 24px; display:flex; flex-direction:column; gap:15px;">
                <div style="display:flex; align-items:center; gap:11px;">
                    <span style="width:38px; height:38px; flex:none; display:grid; place-items:center; border-radius:11px; background:color-mix(in srgb, var(--accent-2) 11%, transparent); color:var(--accent-2);"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v12 M8 15l4 4 4-4 M4 5h16"></path></svg></span>
                    <div><div style="font-size:16px; font-weight:800; color:var(--ink);">Exportar seus dados</div><div style="font-size:12px; color:var(--muted); margin-top:1px;">Backup ou migração &mdash; os dados são seus.</div></div>
                </div>
                <form method="GET" action="/public/export.php" style="display:flex; flex-direction:column; gap:14px;">
                    <label style="display:flex; flex-direction:column; gap:5px;">
                        <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Formato</span>
                        <select name="format" style="width:100%; padding:10px 12px;">
                            <option value="csv">CSV (reimportável aqui mesmo)</option>
                            <option value="json">JSON (backup completo)</option>
                        </select>
                    </label>
                    <label style="display:flex; flex-direction:column; gap:5px;">
                        <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">O que exportar</span>
                        <select name="scope" style="width:100%; padding:10px 12px;">
                            <option value="transactions">Transações</option>
                            <option value="accounts">Contas</option>
                            <option value="goals">Metas</option>
                            <option value="all">Tudo (em seções)</option>
                        </select>
                    </label>
                    <button type="submit" style="width:100%; padding:13px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14.5px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">Baixar exportação</button>
                </form>
                <div style="font-size:11.5px; color:var(--muted); line-height:1.55;">O CSV de transações é reimportável aqui mesmo &mdash; ida e volta sem perda. No escopo "Tudo", o arquivo sai em seções (transações, contas, metas).</div>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>