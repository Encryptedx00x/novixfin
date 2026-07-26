<?php

declare(strict_types=1);

/**
 * NovixFin v3 — Phase 5: cron dispatcher.
 *
 * CLI-only safe entry point that runs maintenance jobs. Refuses via HTTP
 * (403) so anonymous users can't trigger bulk generation by hitting the
 * URL (matches the model of `actions/transactions/auto-effective.php`).
 *
 * Usage:
 *   php scripts/cron.php recurring       # generate due recurring occurrences
 *   php scripts/cron.php auto-effective  # flip non-effective tx with
 *                                        # effective_date <= today to effective
 *   php scripts/cron.php all             # run both, recurring first
 *   php scripts/cron.php                 # alias for "all"
 *
 * Hostinger cron (cPanel → Cron Jobs):
 *   0 2 * * * /usr/bin/php /home/u545165688/<raiz>/scripts/cron.php all >> <raiz>/logs/cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden: cron-only script.' . PHP_EOL;
    exit;
}

require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/bootstrap.php';
// v3 validation fix: recalculate goals helper used by the recurring + auto-
// effective cron jobs. Loading explicitly here so cron.php never silently
// skips goal recalc if the helper isn't pulled in by bootstrap.
require_once __DIR__ . '/../includes/goals.php';

use Novix\Database\Connection;
use Novix\Services\RecurringService;

$pdo = Connection::get();

$job = $argv[1] ?? 'all';

$banner = static function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

$runRecurring = static function () use ($pdo, $banner): void {
    $banner('RecurringService: begin');
    $service = new RecurringService($pdo);
    $summary = $service->generateDue();
    $banner(sprintf(
        'RecurringService: parents_scanned=%d generated=%d errors=%d',
        $summary['parents_scanned'],
        $summary['generated'],
        $summary['errors']
    ));
};

$runAutoEffective = static function () use ($banner): void {
    // Delegate to the existing script (it has the CLI-only guard + the
    // effective-dating logic + bill totals + goal recalcs).
    $banner('Auto-effective: begin');
    require __DIR__ . '/../actions/transactions/auto-effective.php';
    $banner('Auto-effective: done');
};

switch ($job) {
    case 'recurring':
        $runRecurring();
        break;
    case 'auto-effective':
        $runAutoEffective();
        break;
    case 'all':
        $runRecurring();
        $runAutoEffective();
        break;
    default:
        fwrite(STDERR, "Unknown job: {$job}\n");
        fwrite(STDERR, "Usage: php scripts/cron.php [recurring|auto-effective|all]\n");
        exit(2);
}

$banner('cron.php: done');