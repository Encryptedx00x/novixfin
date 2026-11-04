<?php
declare(strict_types=1);
/** AUTOSAVE: sobrescreve o estado vivo. NÃO gera histórico. */
require_once __DIR__ . '/_bootstrap.php';
sheets_require_post();

$content = sheets_sanitize_content((string) ($_POST['content'] ?? ''));
if ($content === null) {
    sheets_json(['ok' => false, 'error' => 'Conteúdo inválido.'], 422);
}

$pdo = \Novix\Database\Connection::get();
$userId = (int) (currentUserId() ?? 0);
$sheet = sheets_get_or_create($pdo, $userId);

$pdo->beginTransaction();
try {
    sheets_lock_revision($pdo, (int) $sheet['id'], $userId);
    $upd = $pdo->prepare('UPDATE sheets SET content = :c WHERE id = :id AND user_id = :u');
    $upd->execute(['c' => $content, 'id' => (int) $sheet['id'], 'u' => $userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('sheets/save: ' . $e->getMessage());
    sheets_json(['ok' => false, 'error' => 'Falha ao salvar.'], 500);
}

sheets_json(['ok' => true, 'id' => (int) $sheet['id'], 'revision' => hash('sha256', $content), 'saved_at' => date('H:i')]);
