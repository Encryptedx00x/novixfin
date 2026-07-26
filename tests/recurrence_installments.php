<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$failures = 0;

$read = static function (string $path) use ($root): string {
    $fullPath = $root . '/' . $path;
    $contents = file_get_contents($fullPath);
    if ($contents === false) {
        return '';
    }
    return $contents;
};

$pass = static function (string $label, bool $ok) use (&$failures): void {
    if ($ok) {
        echo "OK {$label}\n";
    } else {
        echo "FAIL {$label}\n";
        $failures++;
    }
};

echo "Recurrence installments static smoke\n";

$migration = $read('migrations/20240101000006_add_recurrence_installments_v3.php');
$pass('migration exists', $migration !== '');
$pass('migration adds parent/index/total columns',
    strpos($migration, 'parent_transaction_id') !== false
    && strpos($migration, 'installment_index') !== false
    && strpos($migration, 'recurrence_total_occurrences') !== false
);
$pass('migration adds required indexes',
    strpos($migration, 'idx_transactions_parent_transaction_id') !== false
    && strpos($migration, 'idx_transactions_installment_parent') !== false
    && strpos($migration, 'idx_transactions_recurrence_total') !== false
    && strpos($migration, 'idx_recurring_generated_installment') !== false
);
$pass('migration backfills child parent links',
    strpos($migration, 'UPDATE `transactions` t') !== false
    && strpos($migration, 'recurring_generated') !== false
    && strpos($migration, 'child_transaction_id') !== false
);

$repo = $read('src/Repositories/TransactionRepository.php');
$pass('repository accepts installment metadata',
    strpos($repo, '?int $parentTransactionId = null') !== false
    && strpos($repo, '?int $installmentIndex = null') !== false
    && strpos($repo, '?int $recurrenceTotalOccurrences = null') !== false
);
$pass('repository can preserve generated child installment metadata',
    strpos($repo, 'bool $updateInstallmentFields = true') !== false
    && strpos($repo, '$updateInstallmentFields && isset($columns[\'installment_index\'])') !== false
);
$pass('repository lists installment metadata',
    strpos($repo, 'NULL as parent_transaction_id') !== false
    && strpos($repo, 'NULL as installment_index') !== false
    && strpos($repo, 'NULL as recurrence_total_occurrences') !== false
);

$create = $read('actions/transactions/create.php');
$update = $read('actions/transactions/update.php');
$pass('create parses recurrence limit modes',
    strpos($create, 'recurrence_limit_mode') !== false
    && strpos($create, 'recurrence_total_occurrences') !== false
    && strpos($create, "['never', 'date', 'count']") !== false
);
$pass('update rejects totals below consumed occurrences',
    strpos($update, 'COUNT(*) FROM recurring_generated') !== false
    && strpos($update, '$consumedOccurrences') !== false
    && strpos($update, '$recurrenceTotalOccurrences < $consumedOccurrences') !== false
);
$pass('update preserves generated child installment metadata',
    strpos($update, 'parent_transaction_id FROM transactions') !== false
    && strpos($update, '$preserveInstallmentFields') !== false
    && strpos($update, '!$preserveInstallmentFields') !== false
);

$service = $read('src/Services/RecurringService.php');
$pass('service stops at recurrence total',
    strpos($service, 'recurrence_total_occurrences') !== false
    && strpos($service, 'generatedCountForParent') !== false
    && strpos($service, '$consumedOccurrences >= $totalOccurrences') !== false
);
$pass('service assigns child installment metadata',
    strpos($service, '$nextInstallmentIndex = $consumedOccurrences + 1') !== false
    && strpos($service, 'parent_transaction_id') !== false
    && strpos($service, 'installment_index') !== false
);

$dashboard = $read('public/dashboard.php');
$history = $read('public/history.php');
$dashboardJs = $read('public/scripts/dashboard.js');
$historyJs = $read('public/scripts/history.js');
$pass('dashboard and history expose recurrence limit UI',
    strpos($dashboard, 'recurrence_limit_mode') !== false
    && strpos($dashboard, 'recurrence_total_occurrences') !== false
    && strpos($history, 'recurrence_limit_mode') !== false
    && strpos($history, 'recurrence_total_occurrences') !== false
);
$pass('dashboard and history JS toggle recurrence limit fields',
    strpos($dashboardJs, 'toggleRecurrenceLimitFieldsEditDashboard') !== false
    && strpos($historyJs, 'toggleRecurrenceLimitFieldsEdit') !== false
);

$activeRoots = ['public', 'actions', 'includes', 'src', 'scripts', 'tests', 'migrations'];
$offenders = [];
foreach ($activeRoots as $activeRoot) {
    $dir = $root . '/' . $activeRoot;
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $text = file_get_contents($file->getPathname());
        if ($text === false) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (preg_match('/catch\s*\(\s*\\\\?(?:Exception|Throwable|PDOException)\s*\)/', $text)) {
            $offenders[] = $relative . ': variableless catch';
        }
        if (preg_match('/function\s+__construct\s*\([^)]*\b(?:private|protected|public)\s+/', $text)) {
            $offenders[] = $relative . ': constructor property promotion';
        }
        if (preg_match('/(?::\s*mixed\b|\bmixed\s+\$)/', $text)) {
            $offenders[] = $relative . ': ' . 'mixed-type';
        }
        $nullsafeToken = '?' . '->';
        if (strpos($text, $nullsafeToken) !== false) {
            $offenders[] = $relative . ': nullsafe operator';
        }
        if (preg_match('/\bmatch\s*\(/', $text)) {
            $offenders[] = $relative . ': match expression';
        }
        $php8StringContains = 'str_' . 'contains(';
        if (strpos($text, $php8StringContains) !== false) {
            $offenders[] = $relative . ': str_' . 'contains';
        }
    }
}
$pass('active PHP remains PHP 7.4 compatible by static scan', count($offenders) === 0);
foreach ($offenders as $offender) {
    echo "  {$offender}\n";
}

echo "Done\n";
echo "Failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
