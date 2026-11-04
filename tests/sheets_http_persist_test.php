<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/http_client.php';
$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
if (!preg_match('/(^|[_-])(test|app)$/i', (string) $db['database']) || http_base() === null
    || !in_array(parse_url(http_base(), PHP_URL_HOST), ['127.0.0.1', 'localhost', '::1'], true)) {
    t_skip('requer banco descartável e HTTP_BASE local');
}
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$email = 'sheets-http+' . bin2hex(random_bytes(6)) . '@novixfin.test';
$pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)')->execute(['Teste', $email, password_hash('secret-test-only', PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();
try {
    $session = http_new_session();
    if (!http_login($email, 'secret-test-only', $session)) { throw new RuntimeException('Login HTTP falhou'); }
    $first = http_request('GET', '/actions/sheets/load.php', [], $session);
    $initial = json_decode($first['body'], true);
    t_same('planilha criada por load HTTP', $first['status'], 200);
    t_ok('load inicial respondeu ok', ($initial['ok'] ?? false) === true);
    $payloads = [
        ['tabs' => [['name' => 'Página 1', 'cells' => ['A1' => 'primeira']]]],
        ['tabs' => [['name' => 'Página 1', 'cells' => ['A1' => 'ação & café 🐱', 'B2' => '=SUM(A1:A3)', 'C3' => '']], ['name' => 'Outra aba', 'cells' => ['A1' => '123,45']]]],
    ];
    foreach ($payloads as $i => $content) {
        $response = http_post('/actions/sheets/save.php', ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)], $session);
        t_same('HTTP save ' . $i, $response['status'], 200);
        t_ok('JSON save ' . $i, (json_decode($response['body'], true)['ok'] ?? false) === true);
        $query = $pdo->prepare('SELECT content FROM sheets WHERE user_id = ?');
        $query->execute([$userId]);
        t_same('banco save ' . $i, json_decode((string) $query->fetchColumn(), true), $content);
        $load = http_request('GET', '/actions/sheets/load.php', [], $session);
        t_same('HTTP reload ' . $i, $load['status'], 200);
        t_same('conteúdo reload ' . $i, json_decode($load['body'], true)['content'] ?? null, $content);
    }
    $bad = http_post('/actions/sheets/save.php', ['_token' => 'invalido', 'content' => '{}'], $session);
    t_same('CSRF inválido rejeitado', $bad['status'], 419);
    $invalid = http_post('/actions/sheets/save.php', ['content' => '{"tabs":[]}'], $session);
    t_same('conteúdo inválido rejeitado', $invalid['status'], 422);
    $anonymous = http_new_session();
    $denied = http_request('POST', '/actions/sheets/save.php', ['content' => '{}'], $anonymous);
    t_ok('usuário anônimo não salva', $denied['status'] >= 300 && $denied['status'] < 400);
    $load = http_request('GET', '/actions/sheets/load.php', [], $session);
    t_same('rejeições não alteram dados', json_decode($load['body'], true)['content'] ?? null, $payloads[1]);
    $revision = json_decode($load['body'], true)['revision'];
    $snapshot = http_post('/actions/sheets/snapshot.php', ['_revision' => $revision, 'content' => json_encode($payloads[1])], $session);
    t_same('snapshot com versão atual', $snapshot['status'], 200);
    $versionId = json_decode($snapshot['body'], true)['versions'][0]['id'];
    $fresh = $payloads[1];
    $fresh['tabs'][0]['cells']['A1'] = 'nova versão';
    $saved = http_post('/actions/sheets/save.php', ['_revision' => $revision, 'content' => json_encode($fresh)], $session);
    t_same('salva com versão atual', $saved['status'], 200);
    foreach (['save.php' => ['content' => json_encode($payloads[0])], 'snapshot.php' => ['content' => json_encode($payloads[0])], 'restore.php' => ['version_id' => $versionId]] as $handler => $args) {
        $stale = http_post('/actions/sheets/' . $handler, array_merge($args, ['_revision' => $revision]), $session);
        t_same($handler . ' rejeita versão antiga', $stale['status'], 409);
    }
    $load = http_request('GET', '/actions/sheets/load.php', [], $session);
    t_same('conflitos preservam versão recente', json_decode($load['body'], true)['content'] ?? null, $fresh);
} finally {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}
t_finish();
