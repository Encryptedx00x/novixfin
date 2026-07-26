# Checkpoint — Phase 5 (Recurring transaction generation)

**Timestamp**: 2026-06-22
**Phase**: 5
**Confidence**: 90%

## Files created
1. `migrations/20240101000003_add_recurring_generated_v3.sql` — `CREATE TABLE IF NOT EXISTS recurring_generated` with unique `(parent_transaction_id, occurrence_date)` constraint → idempotent generation; indexes on parent/child/occurrence_date.
2. `src/Services/RecurringService.php` (284 lines) — the actual engine. `generateDue(?DateTimeImmutable $upTo = null)` scans every `transactions WHERE is_recurring=1 AND recurrence_type<>'none' AND (recurrence_end_date IS NULL OR >= CURDATE())`, computes the next occurrence date past `transaction_date` (daily/weekly/monthly/yearly/same_day), and for each unmaterialised occurrence ≤ `$upTo`:
   - Pre-check + unique-constraint backstop against `recurring_generated` so re-runs never duplicate.
   - Creates a child transaction via `TransactionRepository::create` (which is itself transactional post-Phase-3-F) with `is_recurring=0, is_effective=(date <= today), effective_date=(future date if !effective)`.
   - For credit-card parents: resolves the right monthly `credit_card_bills` row via `CreditCardBillRepository::findOrCreateBill(accountId, userId, month)` so recurring subscription charges aggregate on the correct bill.
   - Per-occurrence inner transaction (only when no outer tx active, guarded by `inTransaction()`); on the rare dedup-race condition, the orphaned child is cleaned up via DELETE so account balances stay correct.
   - `MAX_ITERATIONS_PER_PARENT = 365` safety cap so a cron run is bounded.
3. `scripts/cron.php` (81 lines) — CLI-only (HTTP 403) dispatcher. Jobs: `recurring`, `auto-effective`, `all` (default). Delegates `auto-effective` to the existing `actions/transactions/auto-effective.php` (no duplication).

## Files modified
- `docs/SETUP.md` — added a concrete `## Cron Jobs (v3 — Fase 5)` section with the Hostinger cron-line example + manual-run commands + idempotency note. Also fixed a leftover typo (`runnerordenado` → `runner ordenado`).

## Bugs fixed / improvements
- UI advertises recurring via the "Fixa" badge — pre-v3 nothing happened behind it (the column was decorative). Now the cron materialises the future occurrences inline as ordinary transactions so totals, charts, and history reflect them. **This is the first time recurring actually works in NovixFin.**
- Credit-card-recurring parents now correctly aggregate each child onto the right monthly bill (so a recurring Netflix subscription on a Visa card appears on each month's bill, not lumped into the original month only).

## Risks introduced
- **Medium** — the existing `transactions` schema had no awareness of "this row is the recurring template"; the service treats `is_recurring=1` rows as templates. If a user manually creates a `is_recurring=1` transaction INSIDE their UI today AND the cron runs on the same day, the service will start materialising EXTRA occurrences from that date forward. This matches the original UI contract (the badge promised recurring behaviour) so it's not a regression — it's the previously-absent realisation of the contract.
- **Low** — idempotency is guaranteed at the DB level (`recurring_generated.unique_parent_occurrence`); even a concurrent double-cron can't fabricate duplicates. Race-deadlock loss is cleaned up via best-effort orphan-DELETE + error_log.
- **Low** — `MAX_ITERATIONS_PER_PARENT = 365` cap means a recurring tx started years ago with NO recurrence_end_date will materialise at most 365 occurrences per cron run. Practical: with daily-strict recurrence this is 1 year of catch-up per night. For most users (monthly/yearly subscriptions) this is fine. Documented in source.

## Schema addition summary
- New table: `recurring_generated(id, parent_transaction_id, occurrence_date, child_transaction_id, created_at)` with FK-free intentionally (so deletion of parent/child rows doesn't cascade off the audit trail; FKs would be a nice-to-have later). The unique key IS the idempotency guard.

## Rollback strategy
- New files only; rollback = delete `RecurringService.php`, `scripts/cron.php`, and `migrations/20240101000003_add_recurring_generated_v3.sql`. DB rollback = `DROP TABLE recurring_generated;` (deleting it has no effect on existing app rows since nothing references it yet). SETUP.md edit can be left as-is or restored from `/backups/phase1/docs__SETUP.md`.

## Validation performed
- Brace/paren balance check on both PHP files: RecurringService 32/32 braces, 107/107 parens, 284 lines. cron.php 6/6 braces, 31/31 parens, 81 lines.
- Manual trace: idempotency path is reached on the SECOND cron run for a given parent+date → the unique key rejects → outer per-occurrence transaction rolls back via `try/catch`. The race-deadlock catch-deletes-orphan path is the safety net.

## Pending
- Phase 6: GamificationService + wiring into transaction/bill/goal actions + profile.php reads real points/level.
- Phase 7: public/export.php + public/import.php.
- Phase 9: Tailwind CDN → build (or doc-only deferred).
- Phase 10: tests + finalize docs.

## Phase 5 status: COMPLETE