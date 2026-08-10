<?php

declare(strict_types=1);

/**
 * Task A4 — histórico de versões do Modo Planilha.
 *
 * CONTRATO SOB TESTE:
 *   1. snapshot.php cria uma versão (MARCO) e atualiza o estado vivo;
 *   2. restore.php devolve o content EXATO daquela versão;
 *   3. a poda mantém no máximo 50 versões por sheet — e as que sobram são
 *      as MAIS NOVAS.
 *
 * O item 3 é o perigoso: `sheet_versions.created_at` é TIMESTAMP (granularidade
 * de 1 segundo). Snapshots criados dentro do mesmo segundo EMPATAM, e um
 * ORDER BY só por created_at deixa indefinido quem sobrevive. Por isso o teste
 * cria as versões rapidamente de propósito — é o pior caso real.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/http_client.php';

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];

if (!preg_match('/(^|[_-])(test|app)$/i', (string) $db['database'])) {
    t_skip('banco da aplicação (' . $db['database'] . ') não é descartável — pulando');
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    t_skip('banco da aplicação inacessível: ' . $e->getMessage());
}

if (!in_array('sheet_versions', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), true)) {
    t_skip('tabela sheet_versions ausente — rode as migrações no banco de teste');
}

if (http_base() === null) {
    t_skip('HTTP_BASE não configurada — restore.php exige requisição HTTP real');
}

// Usuário com senha conhecida: o login é feito de verdade, via POST.
$email = 'hist+' . bin2hex(random_bytes(6)) . '@novixfin.test';
$senha = 'SenhaTeste123!';
$stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (:n, :e, :p)');
$stmt->execute(['n' => 'Hist Teste', 'e' => $email, 'p' => password_hash($senha, PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();

$session = http_new_session();
if (!http_login($email, $senha, $session)) {
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    t_skip('não foi possível autenticar no servidor de teste');
}

/**
 * Chama um handler de sheets por HTTP REAL. É o que permite testar
 * restore.php, que lê `version_id` com filter_input(INPUT_POST, ...) — e essa
 * função ignora a superglobal $_POST, lendo o corpo verdadeiro da requisição.
 */
$callHandler = static function (string $handler, array $post) use (&$session): array {
    $path = '/actions/sheets/' . $handler;
    $res = ($post === [])
        ? http_get_json($path, $session)
        : http_post_json($path, $post, $session);
    return $res ?? ['__nao_json' => true];
};

$mkContent = static function (string $marker): string {
    return json_encode([
        'tabs' => [['name' => 'Página 1', 'cells' => ['A1' => $marker, 'A2' => '=SUM(B1:B9)']]],
    ], JSON_UNESCAPED_UNICODE);
};

// ============================================ 1. snapshot cria versão
$v1 = $mkContent('versao-1');
$res = $callHandler('snapshot.php', ['content' => $v1, 'label' => 'Marco 1']);
t_ok('snapshot.php criou a versão', ($res['ok'] ?? false) === true);
t_same('snapshot devolve 1 versão na lista', count($res['versions'] ?? []), 1);

$sheetId = (int) $pdo->query('SELECT id FROM sheets WHERE user_id = ' . $userId . ' ORDER BY id ASC LIMIT 1')->fetchColumn();
t_ok('sheet do usuário existe', $sheetId > 0);

$firstVersionId = (int) $pdo->query('SELECT id FROM sheet_versions WHERE sheet_id = ' . $sheetId . ' ORDER BY id ASC LIMIT 1')->fetchColumn();

// ============================================ 2. restore devolve o content exato
// Sobrescreve o estado vivo com outro conteúdo…
$callHandler('save.php', ['content' => $mkContent('estado-vivo-alterado')]);
$live = $pdo->query('SELECT content FROM sheets WHERE id = ' . $sheetId)->fetchColumn();
t_ok('estado vivo mudou antes do restore', strpos((string) $live, 'estado-vivo-alterado') !== false);

// …e restaura a versão 1.
$restored = $callHandler('restore.php', ['version_id' => $firstVersionId]);
t_ok('restore.php respondeu ok', ($restored['ok'] ?? false) === true);
t_same(
    'restore devolve o content EXATO da versão',
    $restored['content']['tabs'][0]['cells']['A1'] ?? null,
    'versao-1'
);
t_same(
    'restore preserva a fórmula da versão',
    $restored['content']['tabs'][0]['cells']['A2'] ?? null,
    '=SUM(B1:B9)'
);

$liveAfter = (string) $pdo->query('SELECT content FROM sheets WHERE id = ' . $sheetId)->fetchColumn();
t_ok('restore gravou de volta no estado vivo', strpos($liveAfter, 'versao-1') !== false);

// ============================================ 3. poda em 50
// Zera e recria 55 versões DEPRESSA, numeradas. É o pior caso: várias no
// mesmo segundo, empatando em created_at.
$pdo->prepare('DELETE FROM sheet_versions WHERE sheet_id = :s')->execute(['s' => $sheetId]);

for ($i = 1; $i <= 55; $i++) {
    $res = $callHandler('snapshot.php', [
        'content' => $mkContent('v' . $i),
        'label' => 'Marco ' . $i,
    ]);
    if (isset($res['__nao_json'])) {
        t_ok('snapshot ' . $i . ' respondeu JSON', false);
        break;
    }
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM sheet_versions WHERE sheet_id = ' . $sheetId)->fetchColumn();
t_same('poda mantém no máximo 50 versões', $total, 50);

// As sobreviventes têm de ser as MAIS NOVAS: Marco 6..55.
$labels = $pdo->query(
    'SELECT label FROM sheet_versions WHERE sheet_id = ' . $sheetId . ' ORDER BY id ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$numbers = array_map(static function (string $l): int {
    return (int) filter_var($l, FILTER_SANITIZE_NUMBER_INT);
}, $labels);
sort($numbers);

t_same('a versão mais antiga sobrevivente é a Marco 6', $numbers[0] ?? null, 6);
t_same('a mais nova sobrevivente é a Marco 55', $numbers[count($numbers) - 1] ?? null, 55);
t_ok('as 5 primeiras foram podadas', !in_array(1, $numbers, true) && !in_array(5, $numbers, true));

// ------------------------------------------------------------------ limpeza
$pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
t_same(
    'limpeza: versões removidas por cascade',
    (int) $pdo->query('SELECT COUNT(*) FROM sheet_versions WHERE sheet_id = ' . $sheetId)->fetchColumn(),
    0
);

t_finish();
