<?php
declare(strict_types=1);
/** HISTÓRICO: cria uma versão (marco). Nunca a cada tecla — ver sheet.js. */
require_once __DIR__ . '/_bootstrap.php';
sheets_require_post();

$content = sheets_sanitize_content((string) ($_POST['content'] ?? ''));
if ($content === null) {
    sheets_json(['ok' => false, 'error' => 'Conteúdo inválido.'], 422);
}
$label = trim((string) ($_POST['label'] ?? ''));
$label = $label !== '' ? mb_substr($label, 0, 160) : 'Marco salvo';

$pdo = \Novix\Database\Connection::get();
$userId = (int) (currentUserId() ?? 0);
$sheet = sheets_get_or_create($pdo, $userId);
$sheetId = (int) $sheet['id'];

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE sheets SET content = :c WHERE id = :id AND user_id = :u')
        ->execute(['c' => $content, 'id' => $sheetId, 'u' => $userId]);

    $pdo->prepare('INSERT INTO sheet_versions (sheet_id, label, content) VALUES (:s, :l, :c)')
        ->execute(['s' => $sheetId, 'l' => $label, 'c' => $content]);

    // Poda: mantém os 50 mais recentes deste sheet.
    // `created_at` é TIMESTAMP (1 segundo): marcos criados no mesmo segundo
    // EMPATAM, e sem desempate fica indefinido quem a poda mantém. `id` é
    // AUTO_INCREMENT, então desempata por ordem real de criação.
    $old = $pdo->prepare('SELECT id FROM sheet_versions WHERE sheet_id = :s ORDER BY created_at DESC, id DESC LIMIT 500 OFFSET 50');
    $old->execute(['s' => $sheetId]);
    $ids = $old->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM sheet_versions WHERE sheet_id = ? AND id IN ($in)")
            ->execute(array_merge([$sheetId], $ids));
    }
    $pdo->commit();
} catch (Throwable $e) {
    try { $pdo->rollBack(); } catch (Throwable $rb) {}
    error_log('sheets/snapshot: ' . $e->getMessage());
    sheets_json(['ok' => false, 'error' => 'Falha ao criar a versão.'], 500);
}

$vstmt = $pdo->prepare('SELECT id, label, created_at FROM sheet_versions WHERE sheet_id = :s ORDER BY created_at DESC, id DESC LIMIT 50');
$vstmt->execute(['s' => $sheetId]);
sheets_json(['ok' => true, 'versions' => $vstmt->fetchAll(PDO::FETCH_ASSOC), 'saved_at' => date('H:i')]);
