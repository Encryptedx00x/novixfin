<?php

declare(strict_types=1);

/**
 * Task A2 — múltiplas abas.
 *
 * CONTRATO SOB TESTE: o content é um array de abas; save/load preserva as 3
 * abas, seus NOMES e seus CONTEÚDOS, sem misturar células entre abas e sem
 * reordenar. Fórmulas continuam gravadas como fórmula, inclusive as que
 * referenciam outra aba ('Página 2'!A1) — é o nome da aba que as sustenta,
 * por isso renomear/excluir são operações sensíveis.
 *
 * Exercita os handlers REAIS em subprocesso, como o A1.
 */

require_once __DIR__ . '/helpers.php';

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

if (!in_array('sheets', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), true)) {
    t_skip('tabela sheets ausente — rode as migrações no banco de teste');
}

$stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (:n, :e, :p)');
$stmt->execute([
    'n' => 'Tabs Teste',
    'e' => 'tabs+' . bin2hex(random_bytes(6)) . '@novixfin.test',
    'p' => password_hash('x', PASSWORD_BCRYPT),
]);
$userId = (int) $pdo->lastInsertId();

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

    $tmp = tempnam(sys_get_temp_dir(), 'tabs_') . '.php';
    file_put_contents($tmp, $bootstrap);
    $output = [];
    $exit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $output, $exit);
    @unlink($tmp);

    $decoded = json_decode(implode("\n", $output), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Resposta não-JSON de {$handler}:\n" . implode("\n", $output) . "\n");
        return ['__nao_json' => true];
    }
    if (isset($decoded['revision'])) { $revision = $decoded['revision']; }
    return $decoded;
};
$callHandler('load.php', []);

// ----------------------------------------------------- documento com 3 abas
$crossRef = "='Receitas'!B2";
$content = json_encode([
    'tabs' => [
        ['name' => 'Resumo',   'cells' => ['A1' => 'Total geral', 'B1' => '=SUM(A2:A9)', 'C1' => $crossRef]],
        ['name' => 'Receitas', 'cells' => ['A1' => 'Salário', 'B2' => '7500.55']],
        ['name' => 'Despesas', 'cells' => ['A1' => 'Aluguel', 'B1' => '-1200', 'A2' => 'Mercado']],
    ],
], JSON_UNESCAPED_UNICODE);

$saved = $callHandler('save.php', ['_token' => 'test-token', 'content' => $content]);
t_ok('save.php aceitou documento de 3 abas', ($saved['ok'] ?? false) === true);

$loaded = $callHandler('load.php', []);
t_ok('load.php respondeu ok', ($loaded['ok'] ?? false) === true);

$tabs = $loaded['content']['tabs'] ?? [];
t_same('as 3 abas voltaram', count($tabs), 3);

// Nomes, na ordem.
t_same('nome da aba 1', $tabs[0]['name'] ?? null, 'Resumo');
t_same('nome da aba 2', $tabs[1]['name'] ?? null, 'Receitas');
t_same('nome da aba 3', $tabs[2]['name'] ?? null, 'Despesas');

// Conteúdos, sem vazar de uma aba para outra.
t_same('aba 1 mantém sua fórmula', $tabs[0]['cells']['B1'] ?? null, '=SUM(A2:A9)');
t_same('aba 1 mantém a referência ENTRE abas', $tabs[0]['cells']['C1'] ?? null, $crossRef);
t_same('aba 2 mantém seu valor', $tabs[1]['cells']['B2'] ?? null, '7500.55');
t_same('aba 3 mantém texto', $tabs[2]['cells']['A2'] ?? null, 'Mercado');
t_same('aba 3 mantém negativo', $tabs[2]['cells']['B1'] ?? null, '-1200');

// Isolamento: a célula de uma aba não pode aparecer em outra.
t_ok('aba 2 não herdou A1 da aba 1', ($tabs[1]['cells']['A1'] ?? null) === 'Salário');
t_ok('aba 3 não herdou B2 da aba 2', !isset($tabs[2]['cells']['B2']));

// ------------------------------------------------- renomear e excluir (via save)
// O front renomeia/exclui em memória e persiste o documento inteiro; o
// servidor só precisa aceitar o novo formato. Simulamos exatamente isso.
$renamed = json_decode($content, true);
$renamed['tabs'][1]['name'] = 'Entradas 2026';   // renomeia
array_splice($renamed['tabs'], 2, 1);            // exclui a 3ª
$saved2 = $callHandler('save.php', [
    '_token' => 'test-token',
    'content' => json_encode($renamed, JSON_UNESCAPED_UNICODE),
]);
t_ok('save.php aceitou renomear + excluir', ($saved2['ok'] ?? false) === true);

$loaded2 = $callHandler('load.php', []);
$tabs2 = $loaded2['content']['tabs'] ?? [];
t_same('restaram 2 abas', count($tabs2), 2);
t_same('renome persistido', $tabs2[1]['name'] ?? null, 'Entradas 2026');
t_ok('aba excluída não voltou', !isset($tabs2[2]));
t_same('conteúdo da aba renomeada intacto', $tabs2[1]['cells']['B2'] ?? null, '7500.55');

// ------------------------------------------------------------------ limpeza
$pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
t_same(
    'limpeza: sheets removidos por cascade',
    (int) $pdo->query('SELECT COUNT(*) FROM sheets WHERE user_id = ' . $userId)->fetchColumn(),
    0
);

t_finish();
