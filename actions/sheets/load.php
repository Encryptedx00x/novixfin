<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pdo = \Novix\Database\Connection::get();
$userId = (int) (currentUserId() ?? 0);
$sheet = sheets_get_or_create($pdo, $userId);

$vstmt = $pdo->prepare('SELECT v.id, v.label, v.created_at FROM sheet_versions v
    INNER JOIN sheets s ON s.id = v.sheet_id AND s.user_id = :u
    WHERE v.sheet_id = :sid ORDER BY v.created_at DESC LIMIT 50');
$vstmt->execute(['u' => $userId, 'sid' => (int) $sheet['id']]);

sheets_json([
    'ok' => true,
    'id' => (int) $sheet['id'],
    'content' => json_decode((string) ($sheet['content'] ?: '{"tabs":[]}'), true),
    'updated_at' => $sheet['updated_at'],
    'versions' => $vstmt->fetchAll(PDO::FETCH_ASSOC),
]);
