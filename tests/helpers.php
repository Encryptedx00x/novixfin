<?php

declare(strict_types=1);

/**
 * NovixFin — helpers de teste: banco ISOLADO, transação com rollback e
 * factories mínimas. Sem Composer / PHPUnit.
 *
 * ------------------------------------------------------------------------
 * SEGURANÇA: este arquivo NUNCA pode escrever no banco da aplicação.
 * ------------------------------------------------------------------------
 * O banco de teste é opt-in EXPLÍCITO, por variáveis de ambiente próprias.
 * Sem elas, os testes de banco não rodam — eles se marcam como SKIP em vez de
 * cair no banco configurado em config/config.php (que, neste projeto, aponta
 * para PRODUÇÃO). O guard abaixo recusa qualquer configuração em que o nome do
 * banco de teste coincida com o da aplicação.
 *
 * Como habilitar (shell, antes de rodar a suíte):
 *
 *   export TEST_DB_HOST=127.0.0.1
 *   export TEST_DB_PORT=3306
 *   export TEST_DB_DATABASE=novixfin_test     # precisa ser != do banco do app
 *   export TEST_DB_USERNAME=root
 *   export TEST_DB_PASSWORD=
 *
 * O schema do banco de teste deve vir das migrações do próprio repo:
 *
 *   DB_DATABASE=novixfin_test php scripts/migrate.php up
 *
 * Toda escrita acontece dentro de uma transação revertida ao fim
 * (test_with_rollback), então o banco de teste também não acumula lixo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

// ============================================================ asserções

$GLOBALS['t_failures'] = 0;
$GLOBALS['t_checks'] = 0;

/** Verdadeiro/falso. */
function t_ok(string $label, bool $ok): void
{
    $GLOBALS['t_checks']++;
    if ($ok) {
        printf("  ok    %s\n", $label);
        return;
    }
    $GLOBALS['t_failures']++;
    printf("  FAIL  %s\n", $label);
}

/** Igualdade estrita (não-monetária). */
function t_same(string $label, mixed $got, mixed $want): void
{
    $GLOBALS['t_checks']++;
    if ($got === $want) {
        printf("  ok    %s\n", $label);
        return;
    }
    $GLOBALS['t_failures']++;
    printf(
        "  FAIL  %s\n          esperado: %s\n          obtido:   %s\n",
        $label,
        var_export($want, true),
        var_export($got, true)
    );
}

/**
 * Comparação MONETÁRIA — exata ao centavo.
 *
 * Regra da fila de tarefas: valor financeiro que não bate ao centavo é FALHA.
 * Comparamos em centavos inteiros para não depender de igualdade de float.
 */
function t_money(string $label, float $got, float $want): void
{
    $GLOBALS['t_checks']++;
    $gotCents = (int) round($got * 100);
    $wantCents = (int) round($want * 100);

    if ($gotCents === $wantCents) {
        printf("  ok    %-52s %12s\n", $label, number_format($got, 2, ',', '.'));
        return;
    }
    $GLOBALS['t_failures']++;
    printf(
        "  FAIL  %-52s esperado %12s, obtido %12s\n",
        $label,
        number_format($want, 2, ',', '.'),
        number_format($got, 2, ',', '.')
    );
}

/** Fecha o teste com o código de saída que o run.php espera. */
function t_finish(): void
{
    $failures = (int) $GLOBALS['t_failures'];
    $checks = (int) $GLOBALS['t_checks'];

    echo "\n";
    if ($failures > 0) {
        printf("FALHOU: %d de %d verificações.\n", $failures, $checks);
        exit(1);
    }
    printf("OK: %d verificações passaram.\n", $checks);
    exit(0);
}

/** Encerra como SKIP (exit 0) — usado quando não há banco de teste. */
function t_skip(string $reason): void
{
    printf("  SKIP  %s\n", $reason);
    echo "\nIgnorado (sem banco de teste configurado). Ver tests/helpers.php.\n";
    exit(0);
}

// ============================================================ banco isolado

/**
 * Configuração do banco de TESTE, ou null se não houver opt-in.
 *
 * @return array{host:string,port:int,database:string,username:string,password:string}|null
 */
function test_db_config(): ?array
{
    $database = getenv('TEST_DB_DATABASE');
    if ($database === false || trim((string) $database) === '') {
        return null;
    }
    $database = trim((string) $database);

    // --- Guard 1: nunca coincidir com o banco da aplicação. ---
    $appDatabase = null;
    $configFile = __DIR__ . '/../config/config.php';
    if (is_file($configFile)) {
        try {
            $appConfig = require $configFile;
            $appDatabase = $appConfig['db']['database'] ?? null;
        } catch (Throwable $e) {
            $appDatabase = null;
        }
    }

    if (is_string($appDatabase) && strcasecmp($appDatabase, $database) === 0) {
        fwrite(STDERR,
            "RECUSADO: TEST_DB_DATABASE ('{$database}') é o MESMO banco da aplicação.\n" .
            "Os testes escrevem dados; aponte para um banco de teste separado.\n");
        exit(1);
    }

    // --- Guard 2: nome precisa se identificar como teste. ---
    if (!preg_match('/(^|[_-])test/i', $database)) {
        fwrite(STDERR,
            "RECUSADO: TEST_DB_DATABASE ('{$database}') não parece um banco de teste.\n" .
            "Use um nome contendo 'test' (ex.: novixfin_test) — proteção contra apontar para produção.\n");
        exit(1);
    }

    $port = getenv('TEST_DB_PORT');
    $username = getenv('TEST_DB_USERNAME');
    $password = getenv('TEST_DB_PASSWORD');
    $host = getenv('TEST_DB_HOST');

    return [
        'host' => is_string($host) && $host !== '' ? $host : '127.0.0.1',
        'port' => is_string($port) && $port !== '' ? (int) $port : 3306,
        'database' => $database,
        'username' => is_string($username) && $username !== '' ? $username : 'root',
        'password' => is_string($password) ? $password : '',
    ];
}

/** PDO do banco de teste (singleton). Chame só depois de test_db_available(). */
function test_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = test_db_config();
    if ($config === null) {
        throw new RuntimeException('Banco de teste não configurado (TEST_DB_DATABASE).');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/** Há banco de teste utilizável? */
function test_db_available(): bool
{
    if (test_db_config() === null) {
        return false;
    }
    try {
        test_pdo();
        return true;
    } catch (Throwable $e) {
        fwrite(STDERR, "Banco de teste inacessível: " . $e->getMessage() . "\n");
        return false;
    }
}

/**
 * Roda $body dentro de uma transação e SEMPRE reverte.
 * Nenhum teste deixa resíduo no banco de teste.
 */
function test_with_rollback(callable $body): void
{
    $pdo = test_pdo();
    $pdo->beginTransaction();
    try {
        $body($pdo);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// ============================================================ factories
//
// Mínimas de propósito: só as colunas NOT NULL sem default, mais o que o teste
// precisa controlar. Cada uma devolve o id inserido.

function make_user(PDO $pdo, string $email = null): int
{
    $email ??= 'test+' . bin2hex(random_bytes(6)) . '@novixfin.test';
    // Coluna é `password` (ver migrations/20240101000000_baseline_v3.sql), não `password_hash`.
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password) VALUES (:n, :e, :p)'
    );
    $stmt->execute([
        'n' => 'Teste',
        'e' => $email,
        'p' => password_hash('secret-test-only', PASSWORD_BCRYPT),
    ]);
    return (int) $pdo->lastInsertId();
}

function make_account(
    PDO $pdo,
    int $userId,
    string $name = 'Conta Teste',
    string $type = 'bank',
    float $balance = 0.0
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO accounts (user_id, name, type, balance) VALUES (:u, :n, :t, :b)'
    );
    $stmt->execute(['u' => $userId, 'n' => $name, 't' => $type, 'b' => $balance]);
    return (int) $pdo->lastInsertId();
}

/** Cartão = accounts.type = 'credit' (não há tabela cards neste modelo). */
function make_card(
    PDO $pdo,
    int $userId,
    string $name = 'Cartão Teste',
    ?int $closingDay = 5,
    ?int $dueDay = 15,
    ?float $creditLimit = 10000.00
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO accounts (user_id, name, type, balance, closing_day, due_day, credit_limit)
         VALUES (:u, :n, "credit", 0, :cd, :dd, :cl)'
    );
    $stmt->execute([
        'u' => $userId,
        'n' => $name,
        'cd' => $closingDay,
        'dd' => $dueDay,
        'cl' => $creditLimit,
    ]);
    return (int) $pdo->lastInsertId();
}

function make_category(PDO $pdo, int $userId, string $type = 'expense', string $name = 'Categoria Teste'): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO categories (user_id, name, type) VALUES (:u, :n, :t)'
    );
    $stmt->execute(['u' => $userId, 'n' => $name, 't' => $type]);
    return (int) $pdo->lastInsertId();
}

function make_bill(
    PDO $pdo,
    int $accountId,
    int $userId,
    string $billingMonth,
    string $closingDate,
    string $dueDate
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO credit_card_bills (account_id, user_id, billing_month, closing_date, due_date)
         VALUES (:a, :u, :bm, :cd, :dd)'
    );
    $stmt->execute([
        'a' => $accountId,
        'u' => $userId,
        'bm' => $billingMonth,
        'cd' => $closingDate,
        'dd' => $dueDate,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Insere uma transação DIRETO no banco (sem passar pelo repositório), para
 * montar cenário. Quando o teste for sobre a mutação de saldo, use o
 * TransactionRepository — o objetivo aqui é só preparar estado.
 */
function make_transaction(
    PDO $pdo,
    int $userId,
    int $accountId,
    int $categoryId,
    string $type,
    float $amount,
    string $date,
    bool $isEffective = true,
    ?int $billId = null
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO transactions
            (user_id, account_id, bill_id, category_id, type, amount, transaction_date, is_effective)
         VALUES (:u, :a, :b, :c, :t, :amt, :d, :e)'
    );
    $stmt->execute([
        'u' => $userId,
        'a' => $accountId,
        'b' => $billId,
        'c' => $categoryId,
        't' => $type,
        'amt' => $amount,
        'd' => $date,
        'e' => $isEffective ? 1 : 0,
    ]);
    return (int) $pdo->lastInsertId();
}

/** Saldo atual de uma conta, lido do banco. */
function account_balance(PDO $pdo, int $accountId): float
{
    $stmt = $pdo->prepare('SELECT balance FROM accounts WHERE id = :id');
    $stmt->execute(['id' => $accountId]);
    return (float) $stmt->fetchColumn();
}
