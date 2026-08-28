<?php

declare(strict_types=1);

/**
 * NovixFin — runner da suíte de testes. Sem Composer / PHPUnit.
 *
 *   php tests/run.php            # roda tudo
 *   php tests/run.php --only=bill    # só os arquivos cujo nome casa com "bill"
 *
 * Contrato de um teste: é um script PHP CLI autônomo que sai com código 0
 * quando passa e ≠0 quando falha — exatamente o que os testes que já existem
 * no repo (smoke.php, forecast_balance.php, recurrence_overflow.php…) fazem.
 * O runner não impõe framework: ele só descobre, executa em processo separado
 * e agrega os códigos de saída.
 *
 * Descoberta:
 *   1. tests/*_test.php            — convenção nova (testes deste ciclo)
 *   2. LEGACY_TESTS abaixo          — os standalone que já existiam
 *
 * Cada teste roda em seu PRÓPRIO processo. Assim um fatal error num arquivo
 * não derruba a suíte inteira, e nenhum teste enxerga estado global de outro.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$testsDir = __DIR__;

/** Testes standalone que já existiam antes desta convenção. */
const LEGACY_TESTS = [
    'smoke.php',
    'forecast_balance.php',
    'recurrence_overflow.php',
    'recurrence_installments.php',
    'history_listing.php',
];

// ---------------------------------------------------------------- argumentos
$filter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $filter = substr($arg, strlen('--only='));
    }
}

// ---------------------------------------------------------------- descoberta
$files = glob($testsDir . '/*_test.php') ?: [];
sort($files);

foreach (LEGACY_TESTS as $legacy) {
    $path = $testsDir . '/' . $legacy;
    if (is_file($path)) {
        $files[] = $path;
    }
}

if ($filter !== null && $filter !== '') {
    $files = array_values(array_filter($files, static function (string $f) use ($filter): bool {
        return str_contains(basename($f), $filter);
    }));
}

if ($files === []) {
    echo "Nenhum teste encontrado" . ($filter !== null ? " para o filtro '{$filter}'" : '') . ".\n";
    exit(1);
}

// ---------------------------------------------------------------- execução
$php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$passed = 0;
$failed = 0;
$failedNames = [];

echo "NovixFin — suíte de testes (" . count($files) . " arquivo(s))\n";
echo str_repeat('=', 64) . "\n\n";

foreach ($files as $file) {
    $name = basename($file);
    echo "-- {$name}\n";

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($file);
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);

    foreach ($output as $line) {
        echo '   ' . $line . "\n";
    }

    if ($exitCode === 0) {
        $passed++;
        echo "   \033[32mPASSOU\033[0m\n\n";
    } else {
        $failed++;
        $failedNames[] = $name;
        echo "   \033[31mFALHOU\033[0m (exit {$exitCode})\n\n";
    }
}

// ---------------------------------------------------------------- resumo
echo str_repeat('=', 64) . "\n";
printf("Arquivos: %d passaram, %d falharam.\n", $passed, $failed);

if ($failed > 0) {
    echo "Falharam: " . implode(', ', $failedNames) . "\n";
    exit(1);
}

echo "0 falhas.\n";
exit(0);
