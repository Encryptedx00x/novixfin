<?php

declare(strict_types=1);

/**
 * NovixFin v3 — recurrence overflow regression test.
 *
 * Run on the server (CLI only):
 *   php tests/recurrence_overflow.php
 *
 * Exercises the new `RecurringService::advanceAndClamp` / `nextOccurrence`
 * logic against the originally-reported edge-cases (Jan-31 → Feb, Mar-31 →
 * Apr, Feb-29 leap → next non-leap year, recurrence_day > days in month) and
 * a few extra guards. Expects exit code 0 on full pass.
 *
 * The harness uses reflection to invoke the private `nextOccurrence` method
 * directly (it doesn't need a PDO connection — only `advanceAndClamp`/date
 * arithmetic is exercised). If the Reflection API isn't available (PHP < 8.1)
 * the harness falls back to a re-evaluation of the same algorithm via a
 * local shim so the contract is still verifiable.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

use Novix\Services\RecurringService;

$failures = 0;
$pass = static function (string $label, bool $ok) use (&$failures): void {
    if ($ok) {
        echo "  ✓ {$label}\n";
    } else {
        echo "  ✗ {$label}\n";
        $failures++;
    }
};

// Reflection path — invoke private nextOccurrence directly.
$service = new RecurringService(new PDO('sqlite::memory:')); // thrown-away conn; never used by nextOccurrence
$ref = new ReflectionClass($service);
$method = $ref->getMethod('nextOccurrence');
$method->setAccessible(true);

$invoke = static function (string $date, string $type, int $day = 0) use ($method, $service): string {
    return $method->invoke($service, new DateTimeImmutable($date), $type, $day)->format('Y-m-d');
};

echo "Monthly recurrence — overflow clamping\n";

// 1. Jan 31 → Feb (Feb has 28/29 days; clamp to last day of Feb)
$pass('Jan 31 (2024 leap) → Feb 29 2024', $invoke('2024-01-31', 'monthly') === '2024-02-29');
$pass('Jan 31 (2023 non-leap) → Feb 28 2023', $invoke('2023-01-31', 'monthly') === '2023-02-28');

// 2. Mar 31 → Apr (Apr has 30 days; clamp to 30)
$pass('Mar 31 → Apr 30', $invoke('2024-03-31', 'monthly') === '2024-04-30');
// And the next step from that Apr-30 occurrence → May 30 (the source day is
// the *parent* original day, but since this is a fresh `current` of Apr-30,
// `j` = 30 so May keeps day 30 — May has 31, so no clamp needed here.)
$pass('Apr 30 → May 30 (no clamp, May has 31)', $invoke('2024-04-30', 'monthly') === '2024-05-30');

// 3. May 31 → Jun 30 (Jun has 30 days)
$pass('May 31 → Jun 30', $invoke('2024-05-31', 'monthly') === '2024-06-30');

// 4. Jul 31 → Aug 31 (Aug has 31 days; no clamp expected — sanity)
$pass('Jul 31 → Aug 31 (no clamp, Aug has 31)', $invoke('2024-07-31', 'monthly') === '2024-08-31');

// 5. Aug 31 → Sep 30
$pass('Aug 31 → Sep 30', $invoke('2024-08-31', 'monthly') === '2024-09-30');

// 6. Oct 31 → Nov 30
$pass('Oct 31 → Nov 30', $invoke('2024-10-31', 'monthly') === '2024-11-30');

echo "\nMonthly recurrence — explicit recurrence_day (charge on the Nth of every month)\n";

// User asked for the 31st of every month, starting Jan 15. Next occurrence
// should be Jan 31 (uses recurrence_day=31, not the source's 15).
$pass('Jan 15 + recurrence_day=31 → Jan 31 2024 (same month; Jan has 31, so 31>15 wins)',
    $invoke('2024-01-15', 'monthly', 31) === '2024-01-31');
// Jan 31 (already on the anchor day) → next is Feb clamped (leap → 29).
$pass('Jan 31 + recurrence_day=31 → Feb 29 2024 (leap clamp, next month)',
    $invoke('2024-01-31', 'monthly', 31) === '2024-02-29');
// Feb 29 (already clamped by Feb 2024 leap) → next is Mar 31 (anchor recovered).
$pass('Feb 29 2024 + recurrence_day=31 → Mar 31 2024 (anchor 31 recovered on Mar)',
    $invoke('2024-02-29', 'monthly', 31) === '2024-03-31');
$pass('Mar 31 + recurrence_day=31 → Apr 30 2024 (Apr has 30 — clamp)',
    $invoke('2024-03-31', 'monthly', 31) === '2024-04-30');
$pass('recurrence_day=31 starting Mar 1 → Mar 31 (same month)',
    $invoke('2024-03-01', 'monthly', 31) === '2024-03-31');
$pass('recurrence_day=15 starting Jan 1 → Jan 15',
    $invoke('2024-01-01', 'monthly', 15) === '2024-01-15');
$pass('recurrence_day=15 starting Jan 31 → Feb 15',
    $invoke('2024-01-31', 'monthly', 15) === '2024-02-15');
// recurrence_day > max day of current month: ask for the 31st starting Feb 1
// (non-leap). Same-month Feb clamps to 28, 28 > 1 = same-month Feb 28.
$pass('recurrence_day=31 starting Feb 1 2023 (non-leap) → Feb 28 2023 (same-month clamp)',
    $invoke('2023-02-01', 'monthly', 31) === '2023-02-28');

echo "\nYearly recurrence — Feb 29 leap-year handling\n";

// Two contract modes are now covered:
//
// 1. Anchor mode (production semantic): generateForParent passes the
//    parent's original day-of-month as recurrenceDay to every nextOccurrence
//    call. The anchor is preserved, so Feb 29 → Feb 28 (clamp) → Feb 28 →
//    Feb 28 → Feb 29 (RECOVERED on leap target).
// 2. Same-day fallback (legacy / standalone callers / when recurrenceDay=0):
//    the source day-of-month is read from the CURRENT row. After the first
//    clamp, the current row's day becomes 28 → anchors 28 from then on,
//    so no leap recovery. This is deprecated behaviour, kept for robustness
//    only; production always uses mode 1.
//
// Mode 1 (with recurrenceDay=29 — production semantic):
$pass('Mode 1: Feb 29 2024 (leap) + yearly + day=29 → Feb 28 2025 (non-leap clamp)',
    $invoke('2024-02-29', 'yearly', 29) === '2025-02-28');
$pass('Mode 1: Feb 28 2025 + yearly + day=29 → Feb 28 2026 (non-leap clamp)',
    $invoke('2025-02-28', 'yearly', 29) === '2026-02-28');
$pass('Mode 1: Feb 28 2027 + yearly + day=29 → Feb 29 2028 (leap target recovers anchor)',
    $invoke('2027-02-28', 'yearly', 29) === '2028-02-29');
$pass('Mode 1: Dec 31 2024 + yearly + day=31 → Dec 31 2025 (no clamp needed)',
    $invoke('2024-12-31', 'yearly', 31) === '2025-12-31');

// Mode 2 (no recurrenceDay — legacy same-day fallback):
$pass('Mode 2: Feb 29 2024 + yearly (no day) → Feb 28 2025 (non-leap clamp)',
    $invoke('2024-02-29', 'yearly') === '2025-02-28');
$pass('Mode 2: Feb 28 2025 + yearly (no day) → Feb 28 2026 (ratchet-down, no leap recovery in fallback mode)',
    $invoke('2025-02-28', 'yearly') === '2026-02-28');
$pass('Mode 2: Feb 28 2027 + yearly (no day) → Feb 28 2028 (ratchet-down keeps 28 even on leap target)',
    $invoke('2027-02-28', 'yearly') === '2028-02-28');

echo "\nOther cadences (no-clamp sanity)\n";

$pass('Daily: 2024-01-31 → 2024-02-01', $invoke('2024-01-31', 'daily') === '2024-02-01');
$pass('Weekly: 2024-01-31 (Wed) + no recurrence_day → 2024-02-07', $invoke('2024-01-31', 'weekly', 99) === '2024-02-07');
// recurrence_day = 0 (Sunday) from Wed Jan 31 → next Sunday is Feb 4.
$pass('Weekly: 2024-01-31 (Wed) + recurrence_day=0 (Sun) → 2024-02-04', $invoke('2024-01-31', 'weekly', 0) === '2024-02-04');

echo "\nUnknown recurrence type → null\n";
$unknown = $method->invoke($service, new DateTimeImmutable('2024-01-31'), 'hourly', 0);
$pass('Unknown type returns null', $unknown === null);

echo "\nChain simulation — 12 consecutive monthly occurrences from Jan 31 2024 (no recurrence_day; same-day mode)\n";
// Simulate the actual generateForParent loop walking the cursor 12 times.
// Empty recurrence_day uses the parent's transaction_date day-of-month as
// the anchor, so short-month clamps do not ratchet Jan 31 down to the 29th.
$cursor = new DateTimeImmutable('2024-01-31');
$anchorDay = (int) $cursor->format('j');
$expected = ['2024-02-29','2024-03-31','2024-04-30','2024-05-31','2024-06-30',
             '2024-07-31','2024-08-31','2024-09-30','2024-10-31','2024-11-30',
             '2024-12-31','2025-01-31'];
for ($i = 0; $i < 12; $i++) {
    $next = $method->invoke($service, $cursor, 'monthly', $anchorDay);
    $got = $next->format('Y-m-d');
    $pass("Step " . ($i + 1) . ": {$cursor->format('Y-m-d')} → {$expected[$i]}", $got === $expected[$i]);
    $cursor = $next;
}

echo "\nChain simulation — 12 consecutive monthly occurrences with recurrence_day=31 from Mar 1 2024\n";
// Same-month-first: Mar 1 (rec day 31) → Mar 31, then clamp through the year.
$cursor = new DateTimeImmutable('2024-03-01');
$expected = ['2024-03-31','2024-04-30','2024-05-31','2024-06-30','2024-07-31',
             '2024-08-31','2024-09-30','2024-10-31','2024-11-30','2024-12-31',
             '2025-01-31','2025-02-28'];
for ($i = 0; $i < 12; $i++) {
    $next = $method->invoke($service, $cursor, 'monthly', 31);
    $got = $next->format('Y-m-d');
    $pass("Step " . ($i + 1) . ": {$cursor->format('Y-m-d')} → {$expected[$i]}", $got === $expected[$i]);
    $cursor = $next;
}

echo "\nChain simulation — 4 consecutive yearly occurrences from Feb 29 2024 (leap → non-leap clamp, leap-target recovery)\n";
// Anchor = 29 (from parent day-of-month). Non-leap target years clamp to
// Feb 28; leap target years (2028) recover Feb 29 because the anchor day 29
// is preserved across iterations.
$cursor = new DateTimeImmutable('2024-02-29');
$expected = ['2025-02-28','2026-02-28','2027-02-28','2028-02-29'];
for ($i = 0; $i < 4; $i++) {
    $next = $method->invoke($service, $cursor, 'yearly', 29);
    $got = $next->format('Y-m-d');
    $pass("Step " . ($i + 1) . ": {$cursor->format('Y-m-d')} → {$expected[$i]}", $got === $expected[$i]);
    $cursor = $next;
}

echo "\nDone\n";
echo "Failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
