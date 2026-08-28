<?php

declare(strict_types=1);

namespace Novix\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Novix\Repositories\CreditCardBillRepository;
use Novix\Repositories\TransactionRepository;
use PDO;
use PDOException;
use Throwable;

/**
 * NovixFin v3 — Phase 5: recurring transaction engine.
 *
 * Pre-v3 the schema stored `is_recurring`, `recurrence_type`,
 * `recurrence_day`, `recurrence_end_date` on `transactions` but NOTHING
 * actually generated the future occurrences — the "Fixa" badge was purely
 * decorative. This service materialises them idempotently, driven by the
 * cron dispatcher `scripts/cron.php`.
 *
 * Idempotency contract:
 *   The `recurring_generated` table (migration
 *   20240101000003_add_recurring_generated_v3.sql) records every (parent,
 *   occurrence_date) pair that has already been materialised. The unique
 *   constraint guarantees re-runs of the engine never duplicate rows.
 *
 * Limits / safety:
 *   - At most 365 occurrences are generated per parent per invocation (caps
 *     pathologically early start_dates from saturating a single cron run).
 *   - Each occurrence is wrapped in a per-parent PDO transaction so a
 *     mid-batch failure rolls back the in-flight generation; already-applied
 *     occurrences from earlier parents remain.
 *   - Children are created via `TransactionRepository::create` (which itself
 *     now wraps INSERT + balance UPDATE in a transaction — Phase 3 Batch F).
 *
 * Scope of MVP:
 *   - Credit-card-recurring parents auto-link each child to the appropriate
 *     monthly `credit_card_bills` row by occurrence month.
 *   - Non-credit-card parents pass `bill_id = null`.
 *
 * @final
 */
final class RecurringService
{
    private PDO $connection;

    private const MAX_ITERATIONS_PER_PARENT = 365;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Materialise every recurring occurrence due on or before $upTo.
     *
     * @param DateTimeImmutable|null $upTo Defaults to "today" (00:00 of the
     *     server's local time). Pass an explicit date to backfill backwards
     *     (e.g. after the cron was off for a week) or test into the future.
     * @return array{generated: int, parents_scanned: int, errors: int, users_touched: int[]}  a
     *     small summary for logging / cron output. `users_touched` lists every user
     *     id whose recurrence generated at least one new transaction this run
     *     (used by the cron dispatcher + post-run goal recalc).
     */
    public function generateDue(?DateTimeImmutable $upTo = null): array
    {
        $upTo ??= new DateTimeImmutable('today 00:00:00');

        $summary = ['generated' => 0, 'parents_scanned' => 0, 'errors' => 0, 'users_touched' => []];

        $recurrenceTotalField = $this->transactionColumnExists('recurrence_total_occurrences')
            ? ', recurrence_total_occurrences'
            : ', NULL AS recurrence_total_occurrences';

        // Fetch all recurring templates. is_recurring=1, recurrence_type != none,
        // and either unbounded or not yet past their recurrence_end_date.
        $stmt = $this->connection->prepare(
            'SELECT id, user_id, account_id, category_id, type, amount, currency,
                   description, transaction_date, recurrence_type, recurrence_day,
                   recurrence_end_date' . $recurrenceTotalField . '
              FROM transactions
             WHERE is_recurring = 1
               AND recurrence_type IS NOT NULL
               AND recurrence_type <> \'none\'
               AND (recurrence_end_date IS NULL OR recurrence_end_date >= CURDATE())'
        );
        $stmt->execute();
        $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transactionRepository = new TransactionRepository($this->connection);

        foreach ($parents as $parent) {
            $summary['parents_scanned']++;
            $parent['id'] = (int) $parent['id'];
            $parent['user_id'] = (int) $parent['user_id'];
            $parent['account_id'] = $parent['account_id'] === null ? null : (int) $parent['account_id'];

            if ($parent['account_id'] === null) {
                // Recurring cannot target a NULL account (TransactionRepository::create
                // silently UPDATE-accounts-update would be a no-op, but the row
                // would also link to no account → reports broken). Skip with audit log.
                $summary['errors']++;
                error_log('RecurringService: parent tx ' . $parent['id'] . ' skipped (account_id NULL).');
                continue;
            }

            try {
                $gen = $this->generateForParent($parent, $upTo, $transactionRepository);
                $summary['generated'] += $gen;
                if ($gen > 0) {
                    $summary['users_touched'][$parent['user_id']] = true;
                }
            } catch (Throwable $e) {
                $summary['errors']++;
                error_log('RecurringService: parent tx ' . $parent['id'] . ' generation failed: ' . $e->getMessage());
            }
        }

        // v3 validation fix: RecurringService materialises child transactions
        // WITHOUT calling updateGoalsFromTransaction (that incremental updater
        // requires per-tx call). To keep goal progress consistent with the
        // newly-created rows, we recalculate ALL auto_update goals for every
        // user whose recurrence generated at least one new transaction this run.
        foreach (array_keys($summary['users_touched']) as $touchedUserId) {
            try {
                if (function_exists('\recalculateAllGoals')) {
                    \recalculateAllGoals((int) $touchedUserId);
                }
            } catch (Throwable $goalErr) {
                error_log('RecurringService: recalculateAllGoals failed for user ' . $touchedUserId . ': ' . $goalErr->getMessage());
            }
        }
        $summary['users_touched'] = array_keys($summary['users_touched']);

        return $summary;
    }

    /**
     * Generate all occurrences for a single recurring parent.
     */
    private function generateForParent(array $parent, DateTimeImmutable $upTo, TransactionRepository $repo): int
    {
        $generated = 0;
        $cursor = new DateTimeImmutable($parent['transaction_date']);
        $recurrenceType = $parent['recurrence_type'] ?? 'monthly';
        $recurrenceEnd = $parent['recurrence_end_date'] !== null
            ? new DateTimeImmutable($parent['recurrence_end_date'])
            : null;
        $totalOccurrences = $parent['recurrence_total_occurrences'] !== null
            ? (int) $parent['recurrence_total_occurrences']
            : null;
        if ($totalOccurrences !== null && $totalOccurrences < 2) {
            $totalOccurrences = null;
        }
        $consumedOccurrences = 1 + $this->generatedCountForParent($parent['id']);
        // v3 fix: carry the PARENT's original day-of-month as the recurrence
        // anchor when the user did not set an explicit `recurrence_day`. This
        // prevents day-ratchet-down caused by clamp-then-recur on short
        // months (Jan 31 → Feb 29 clamped; if we then read Feb 29's `j` as
        // the new source day, every subsequent month would use 29 instead of
        // the original 31 → May would land on the 29th instead of the 31st).
        // The parent's `transaction_date` day-of-month is the deterministic
        // anchor the user expressed when they created the recurring tx.
        $parentAnchorDay = (int) $cursor->format('j');
        $explicitDay = (int) ($parent['recurrence_day'] ?? 0);
        // If the user gave a valid 1..31 explicit day, use it; otherwise the
        // anchor is the parent's own day-of-month (legacy "same-day" mode).
        $anchorDay = ($explicitDay > 0 && $explicitDay <= 31) ? $explicitDay : $parentAnchorDay;

        for ($i = 0; $i < self::MAX_ITERATIONS_PER_PARENT; $i++) {
            if ($totalOccurrences !== null && $consumedOccurrences >= $totalOccurrences) {
                break;
            }

            $cursor = $this->nextOccurrence($cursor, $recurrenceType, $anchorDay);
            if ($cursor === null) {
                break; // unknown recurrence type — abort this parent safely
            }
            if ($cursor > $upTo) {
                break; // future period reached — nothing more to materialise
            }
            if ($recurrenceEnd !== null && $cursor > $recurrenceEnd) {
                break; // past user-defined end date
            }

            // Idempotency check (also enforced by the unique constraint, but
            // the explicit check avoids the exception+rollback path on the
            // common already-generated case).
            if ($this->alreadyGenerated($parent['id'], $cursor)) {
                continue;
            }

            // Resolve the credit-card bill linkage if the parent sits on a
            // credit-card account, so the child appears on the right month.
            $childBillId = null;
            if ($this->accountIsCreditCard($parent['account_id'], $parent['user_id'])) {
                try {
                    // Task B2: a parcela/ocorrência segue o mesmo ciclo de
                    // fechamento das compras avulsas. Antes usava o mês do
                    // calendário, então uma parcela gerada depois do fechamento
                    // caía numa fatura já fechada.
                    $billRepo = new CreditCardBillRepository($this->connection);
                    $bill = $billRepo->findOrCreateBill(
                        $parent['account_id'],
                        $parent['user_id'],
                        $billRepo->billingMonthForAccount(
                            (int) $parent['account_id'],
                            (int) $parent['user_id'],
                            $cursor
                        )
                    );
                    $childBillId = (int) $bill['id'];
                } catch (Throwable $e) {
                    error_log('RecurringService: bill-month resolution failed for parent ' . $parent['id'] . ' at ' . $cursor->format('Y-m-d') . ': ' . $e->getMessage());
                    // Continue with null billId; the transaction will still be created.
                }
            }

            $today = new DateTimeImmutable('today 00:00:00');
            $isEffective = $cursor <= $today;
            $effectiveDate = $isEffective ? null : $cursor;
            $nextInstallmentIndex = null;
            if ($totalOccurrences !== null) {
                $nextInstallmentIndex = $consumedOccurrences + 1;
            }

            // Wrap each occurrence generation in its own transaction so a
            // failure mid-occurrence rolls back that single (transation +
            // recurring_generated) pair without leaving the parent half-done.
            $startedTx = false;
            if (!$this->connection->inTransaction()) {
                $this->connection->beginTransaction();
                $startedTx = true;
            }

            try {
                $childId = $repo->create(
                    $parent['user_id'],
                    $parent['account_id'],
                    (int) $parent['category_id'],
                    (string) $parent['type'],
                    (float) $parent['amount'],
                    (string) ($parent['description'] ?? ''),
                    $cursor,
                    (string) ($parent['currency'] ?? 'BRL'),
                    $isEffective,
                    false, // child is NOT itself recurring — only the parent is
                    $childBillId,
                    $effectiveDate,
                    null, // recurrenceType: child has none
                    null, // recurrenceDay
                    null, // recurrenceEndDate
                    $parent['id'],
                    $nextInstallmentIndex,
                    $totalOccurrences
                );

                $generatedParams = [
                    ':parent' => $parent['id'],
                    ':occurrence' => $cursor->format('Y-m-d'),
                    ':child' => $childId,
                ];
                if ($this->recurringGeneratedHasInstallmentIndex()) {
                    $stmt = $this->connection->prepare(
                        'INSERT INTO recurring_generated (parent_transaction_id, occurrence_date, child_transaction_id, installment_index)
                         VALUES (:parent, :occurrence, :child, :installment_index)'
                    );
                    $generatedParams[':installment_index'] = $nextInstallmentIndex;
                } else {
                    $stmt = $this->connection->prepare(
                        'INSERT INTO recurring_generated (parent_transaction_id, occurrence_date, child_transaction_id)
                         VALUES (:parent, :occurrence, :child)'
                    );
                }
                try {
                    $stmt->execute($generatedParams);
                } catch (PDOException $dup) {
                    // Lost the race (another cron row beat us) — TransactionRepository::create
                    // already committed its INSERT + balance update. Rollback the duplicate
                    // INSERT side-effect and continue; the consecutive unique-key reject
                    // actually means SOMETHING ELSE inserted the same cursor already, so the
                    // child we just created is now orphaned. Best-effort clean up: delete the
                    // just-created child to preserve idempotency.
                    $del = $this->connection->prepare('DELETE FROM transactions WHERE id = :id');
                    $del->execute([':id' => $childId]);
                    error_log('RecurringService: dedup race for parent ' . $parent['id'] . ' at ' . $cursor->format('Y-m-d') . ' — cleaned up child ' . $childId);
                    throw $dup;
                }

                if ($startedTx) {
                    $this->connection->commit();
                    $startedTx = false;
                }

                $generated++;
                if ($totalOccurrences !== null) {
                    $consumedOccurrences++;
                }
            } catch (Throwable $e) {
                if ($startedTx) {
                    try { $this->connection->rollBack(); } catch (Throwable $rb) { /* ignore */ }
                    $startedTx = false;
                }
                throw $e;
            }
        }

        return $generated;
    }

    /**
     * Compute the next occurrence date after `$current` using the recurrence
     * type. Returns null if the recurrence type is unsupported (handled
     * upstream as a safe bail-out).
     *
     * v3 fix (monthly/yearly overflow): pre-v3 used a bare `$current->modify
     * ('+1 month')` which triggers PHP's well-known day-overflow quirk
     * (Jan 31 → Mar 3, Mar 31 → May 1, Feb 29 leap → Mar 1 next non-leap
     * year). This patch implements deterministic clamping: if the target
     * month has fewer days than the source day-of-month, the occurrence
     * lands on the LAST valid day of the target month. Behaviour is now
     * stable across all months and across leap-year boundaries for both
     * `monthly` and `yearly` recurrences.
     *
     * If `recurrence_day` is provided (> 0) for a `monthly` recurrence, the
     * user asked to be charged on a specific day-of-month (e.g. "31st of
     * every month"); the same clamp applies so the 31st becomes the 30th in
     * April, the 28th/29th in February, etc.
     */
    private function nextOccurrence(DateTimeImmutable $current, string $recurrenceType, int $recurrenceDay): ?DateTimeImmutable
    {
        switch ($recurrenceType) {
            case 'daily':
                return $current->modify('+1 day');

            case 'weekly':
                // Honour recurrence_day (0-6, Sun=0) — advance to the next
                // matching weekday OR fall back to +1 week if recurrence_day
                // is out of range.
                if ($recurrenceDay < 0 || $recurrenceDay > 6) {
                    return $current->modify('+1 week');
                }
                $next = $current->modify('tomorrow');
                while ((int) $next->format('w') !== $recurrenceDay) {
                    $next = $next->modify('+1 day');
                }
                return $next;

            case 'monthly':
            case 'same_day':
                // `recurrenceDay` is the anchor — generateForParent passes
                // either the user's explicit `recurrence_day` OR the parent's
                // original transaction_date day-of-month. This prevents the
                // day-ratchet-down bug where clamping Feb 29 would propagate
                // 29 as the new source day for every subsequent month. If
                // `recurrenceDay` is missing (<= 0, e.g. because the caller
                // didn't pre-resolve the anchor), fall back to the current
                // cursor's day-of-month (legacy same-day mode — preserves
                // backward compat with standalone callers/tests).
                $sourceDay = ($recurrenceDay > 0 && $recurrenceDay <= 31)
                    ? $recurrenceDay
                    : (int) $current->format('j');
                // Same-month first: if the requested day-of-month is later
                // than the current day-of-month AND it still fits this month,
                // return the same-month date (the first occurrence after a
                // parent created on, say, Mar 1 with recurrence_day=31 is
                // Mar 31 — not Apr 30). Otherwise advance to next month and
                // clamp.
                $currentDay = (int) $current->format('j');
                $firstOfMonth = $current->modify('first day of this month 00:00:00');
                $lastOfCurrentMonth = $firstOfMonth->modify('last day of this month 00:00:00');
                $maxCurrentDay = (int) $lastOfCurrentMonth->format('j');
                $clampedSameMonthDay = max(1, min($sourceDay, $maxCurrentDay));
                if ($clampedSameMonthDay > $currentDay) {
                    return new DateTimeImmutable(
                        sprintf('%04d-%02d-%02d 00:00:00',
                            (int) $current->format('Y'),
                            (int) $current->format('n'),
                            $clampedSameMonthDay
                        )
                    );
                }
                return $this->advanceAndClamp($current, 1, $sourceDay);

            case 'yearly':
                // v3 fix: prefer `recurrenceDay` (the anchor) so Feb 29
                // leap-day recovers on subsequent leap target years instead
                // of being permanently demoted to Feb 28 by ratchet-down.
                // Fallback to the current day-of-month for standalone callers
                // that didn't pre-resolve the anchor.
                $sourceDay = ($recurrenceDay > 0 && $recurrenceDay <= 31)
                    ? $recurrenceDay
                    : (int) $current->format('j');
                // Same-year-first: matches the monthly semantic — if the
                // anchor day is later than the current day AND still fits
                // the current month, return the same-year date. Sensible for
                // "yearly on Dec 31" started Jan 1 2024 → first occurrence
                // Dec 31 2024 (not Dec 31 2025).
                $currentDay = (int) $current->format('j');
                $firstOfMonth = $current->modify('first day of this month 00:00:00');
                $lastOfCurrentMonth = $firstOfMonth->modify('last day of this month 00:00:00');
                $maxCurrentDay = (int) $lastOfCurrentMonth->format('j');
                $clampedSameMonthDay = max(1, min($sourceDay, $maxCurrentDay));
                if ($clampedSameMonthDay > $currentDay) {
                    return new DateTimeImmutable(
                        sprintf('%04d-%02d-%02d 00:00:00',
                            (int) $current->format('Y'),
                            (int) $current->format('n'),
                            $clampedSameMonthDay
                        )
                    );
                }
                return $this->advanceAndClamp($current, 12, $sourceDay);

            default:
                return null;
        }
    }

    /**
     * Advance `$current` by `$monthsAhead` months and return a date in the
     * target month clamped to `$sourceDay`. If `$sourceDay` exceeds the
     * target month's length, the result falls back to the target month's
     * last valid day (deterministic, no PHP "+1 month" overflow).
     *
     * Implementation rationale: PHP's `DateTimeImmutable::modify('+1 month')`
     * resolves overflow by cascading into subsequent months (Jan 31 → Mar 3),
     * which is NOT what users expect from a "charge on the 31th" schema.
     * We sidestep by constructing the target date explicitly from year +
     * target-month-number + clamped day. Use `modify("last day of <month-
     * name> <year>")` to read the target month's length robustly across
     * leap years without manual length tables.
     *
     * @param int $monthsAhead 1 for monthly recurrence, 12 for yearly.
     * @param int $sourceDay    1..31 (or any positive int — clamped anyway).
     */
    private function advanceAndClamp(DateTimeImmutable $current, int $monthsAhead, int $sourceDay): DateTimeImmutable
    {
        // Compose the target month: start from the first day of the current
        // month, then add the requested number of months. Working from
        // day-1 avoids the very overflow we are circling around.
        $firstOfMonth = $current->modify('first day of this month 00:00:00');
        $firstOfTargetMonth = $firstOfMonth->modify('+' . $monthsAhead . ' months');

        $targetYear  = (int) $firstOfTargetMonth->format('Y');
        $targetMonth = (int) $firstOfTargetMonth->format('n');
        $lastOfTarget = $firstOfTargetMonth->modify('last day of this month 00:00:00');
        $maxDay = (int) $lastOfTarget->format('j');

        // Clamp + guard against invalid/negative inputs.
        $clampedDay = max(1, min($sourceDay, $maxDay));

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $targetYear, $targetMonth, $clampedDay)
        );
    }

    private function transactionColumnExists(string $column): bool
    {
        try {
            $quotedColumn = $this->connection->quote($column);
            if ($quotedColumn === false) {
                return false;
            }
            $stmt = $this->connection->query("SHOW COLUMNS FROM transactions LIKE {$quotedColumn}");
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function generatedCountForParent(int $parentId): int
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM recurring_generated WHERE parent_transaction_id = :parent'
        );
        $stmt->execute([':parent' => $parentId]);
        return (int) $stmt->fetchColumn();
    }

    private function recurringGeneratedHasInstallmentIndex(): bool
    {
        try {
            $stmt = $this->connection->query("SHOW COLUMNS FROM recurring_generated LIKE 'installment_index'");
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function alreadyGenerated(int $parentId, DateTimeImmutable $date): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1 FROM recurring_generated
              WHERE parent_transaction_id = :p AND occurrence_date = :d
              LIMIT 1'
        );
        $stmt->execute([':p' => $parentId, ':d' => $date->format('Y-m-d')]);
        return (bool) $stmt->fetchColumn();
    }

    private function accountIsCreditCard(int $accountId, int $userId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT type FROM accounts WHERE id = :id AND user_id = :uid LIMIT 1'
        );
        $stmt->execute([':id' => $accountId, ':uid' => $userId]);
        $type = $stmt->fetchColumn();
        return $type === 'credit';
    }
}
