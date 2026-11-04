<?php
declare(strict_types=1);
/** Restaura uma versão para o estado vivo (o join garante a posse). */
require_once __DIR__ . '/_bootstrap.php';
sheets_require_post();

$versionId = filter_input(INPUT_POST, 'version_id', FILTER_VALIDATE_INT);
if (!$versionId || $versionId <= 0) {
    sheets_json(['ok' => false, 'error' => 'Versão inválida.'], 422);
}

$pdo = \Novix\Database\Connection::get();
$userId = (int) (currentUserId() ?? 0);

$stmt = $pdo->prepare('SELECT v.content, v.sheet_id FROM sheet_versions v
    INNER JOIN sheets s ON s.id = v.sheet_id AND s.user_id = :u
    WHERE v.id = :vid LIMIT 1');
$stmt->execute(['u' => $userId, 'vid' => $versionId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    sheets_json(['ok' => false, 'error' => 'Versão não encontrada.'], 404);
}

$pdo->beginTransaction();
try {
    sheets_lock_revision($pdo, (int) $row['sheet_id'], $userId);
    $pdo->prepare('UPDATE sheets SET content = :c WHERE id = :id AND user_id = :u')
        ->execute(['c' => $row['content'], 'id' => (int) $row['sheet_id'], 'u' => $userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('sheets/restore: ' . $e->getMessage());
    sheets_json(['ok' => false, 'error' => 'Falha ao restaurar.'], 500);
}

sheets_json(['ok' => true, 'revision' => hash('sha256', (string) $row['content']), 'content' => json_decode((string) $row['content'], true)]);
