<?php

declare(strict_types=1);

/**
 * Task A1 — persistência do Modo Planilha.
 *
 * CONTRATO SOB TESTE: o que é gravado e devolvido é o que o usuário DIGITOU
 * (a FÓRMULA), nunca o resultado calculado. O cálculo é do cliente
 * (HyperFormula); o servidor é um armazém burro de texto.
 *
 * Exercita os handlers REAIS (actions/sheets/save.php e load.php) em
 * subprocessos, com sessão e CSRF montados como o navegador faria. Não
 * reimplementa a lógica: se save.php passar a gravar o resultado, isto falha.
 *
 * O banco usado é o da APLICAÇÃO (config/config.php), que neste ambiente de
 * teste aponta para um MariaDB descartável — os handlers resolvem a conexão
 * sozinhos e não aceitam injeção. O teste limpa o que cria.
 */

require_once __DIR__ . '/helpers.php';

// ------------------------------------------------------------------ conexão
$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];

// Trava de segurança: este teste ESCREVE no banco da aplicação, então recusa
// rodar se ele não se identificar como banco de teste descartável.
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

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('sheets', $tables, true)) {
    t_skip('tabela sheets ausente — rode as migrações no banco de teste');
}

// ------------------------------------------------------------------ usuário
$email = 'sheets+' . bin2hex(random_bytes(6)) . '@novixfin.test';
$stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (:n, :e, :p)');
$stmt->execute(['n' => 'Sheets Teste', 'e' => $email, 'p' => password_hash('x', PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();

/**
 * Roda um handler de actions/sheets em subprocesso, com sessão autenticada e
 * CSRF válido, e devolve o JSON decodificado.
 */
$revision = null;
$callHandler = static function (string $handler, array $post) use ($userId, &$revision): array {
    if ($post !== [] && $revision !== null) { $post['_revision'] = $revision; }
    $bootstrap = '<?php' . "\n"
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($post === [] ? 'GET' : 'POST', true) . ';'
        . '$_SERVER["HTTP_HOST"] = "localhost";'
        . '$_POST = ' . var_export($post, true) . ';'
        . 'session_start();'
        . '$_SESSION["user"] = ["id" => ' . $userId . '];'
        . '$_SESSION["csrf_token"] = "test-token";'
        . 'require ' . var_export(dirname(__DIR__) . '/actions/sheets/' . $handler, true) . ';';

    $tmp = tempnam(sys_get_temp_dir(), 'sheets_') . '.php';
    file_put_contents($tmp, $bootstrap);

    $output = [];
    $exit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $output, $exit);
    @unlink($tmp);

    $raw = implode("\n", $output);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        // Marcador próprio: uma resposta não-JSON é falha do harness/handler e
        // NÃO pode ser confundida com uma rejeição legítima (ok:false).
        fwrite(STDERR, "Resposta não-JSON de {$handler}:\n{$raw}\n");
        return ['__nao_json' => true, 'raw' => $raw];
    }
    if (isset($decoded['revision'])) { $revision = $decoded['revision']; }
    return $decoded;
};
$callHandler('load.php', []);

// ------------------------------------------------------- salvar com fórmula
// A célula A4 guarda a FÓRMULA. 10+20+30 = 60 — se em algum momento voltar
// "60" no lugar de "=SUM(A1:A3)", o servidor passou a gravar o resultado.
$formula = '=SUM(A1:A3)';
$content = json_encode([
    'tabs' => [[
        'name' => 'Página 1',
        'cells' => ['A1' => '10', 'A2' => '20', 'A3' => '30', 'A4' => $formula, 'B1' => 'texto livre'],
    ]],
], JSON_UNESCAPED_UNICODE);

$saved = $callHandler('save.php', ['_token' => 'test-token', 'content' => $content]);
t_ok('save.php aceitou o content', ($saved['ok'] ?? false) === true);

// ----------------------------------------------------------------- recarregar
$loaded = $callHandler('load.php', []);
t_ok('load.php respondeu ok', ($loaded['ok'] ?? false) === true);

$cells = $loaded['content']['tabs'][0]['cells'] ?? [];
t_same('A4 devolve a FÓRMULA, não o resultado', $cells['A4'] ?? null, $formula);
t_ok('A4 não virou o número calculado (60)', ($cells['A4'] ?? '') !== '60' && ($cells['A4'] ?? '') !== 60);
t_same('valores digitados preservados (A1)', $cells['A1'] ?? null, '10');
t_same('texto livre preservado (B1)', $cells['B1'] ?? null, 'texto livre');
t_same('nome da aba preservado', $loaded['content']['tabs'][0]['name'] ?? null, 'Página 1');

// A fórmula tem de estar literal na COLUNA do banco, não só na resposta.
$stmt = $pdo->prepare('SELECT content FROM sheets WHERE user_id = :u ORDER BY id ASC LIMIT 1');
$stmt->execute(['u' => $userId]);
$storedRaw = (string) $stmt->fetchColumn();
t_ok('a fórmula está literal na coluna sheets.content', strpos($storedRaw, '=SUM(A1:A3)') !== false);

// ------------------------------------------------------- rejeição de lixo
$bad = $callHandler('save.php', ['_token' => 'test-token', 'content' => '{"tabs":[]}']);
t_ok(
    'save.php rejeita content sem abas (com JSON de erro)',
    !isset($bad['__nao_json']) && ($bad['ok'] ?? true) === false
);

$badCsrf = $callHandler('save.php', ['_token' => 'errado', 'content' => $content]);
t_ok(
    'save.php rejeita CSRF inválido (com JSON de erro)',
    !isset($badCsrf['__nao_json']) && ($badCsrf['ok'] ?? true) === false
);

// ------------------------------------------------------------------ limpeza
$pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
$left = (int) $pdo->query('SELECT COUNT(*) FROM sheets WHERE user_id = ' . $userId)->fetchColumn();
t_same('limpeza: sheets do usuário removidos por cascade', $left, 0);

t_finish();
