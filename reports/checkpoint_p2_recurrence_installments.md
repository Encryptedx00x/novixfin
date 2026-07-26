# NovixFin v3.1 - P2 Recurrence Installments

Date: 2026-07-04

## Task
- Implement recurring transactions that end after a fixed quantity of installments.
- Keep parent transaction as installment 1/N.
- Generate children as installments 2/N through N/N.
- Preserve existing end-date and no-limit recurrence behavior.

## Files Changed
- `migrations/20240101000006_add_recurrence_installments_v3.php`
- `src/Repositories/TransactionRepository.php`
- `src/Services/RecurringService.php`
- `actions/transactions/create.php`
- `actions/transactions/update.php`
- `public/dashboard.php`
- `public/history.php`
- `public/scripts/dashboard.js`
- `public/scripts/history.js`
- `tests/probe_recurrence_installments.py`
- `tests/recurrence_installments.php`
- `reports/checkpoint_p2_recurrence_installments.md`
- `state/progress.md`
- `state/context.md`

## Schema Changes
- Added nullable fields:
  - `transactions.parent_transaction_id`
  - `transactions.installment_index`
  - `transactions.recurrence_total_occurrences`
  - `recurring_generated.installment_index`
- Added indexes:
  - `idx_transactions_parent_transaction_id`
  - `idx_transactions_installment_parent`
  - `idx_transactions_recurrence_total`
  - `idx_recurring_generated_installment`
- Backfilled generated child rows with `transactions.parent_transaction_id` from `recurring_generated`.
- No destructive changes and no new constraints.

## Counting Rule
- Parent recurring transaction counts as installment `1/N`.
- Generated child transactions count as `2/N` through `N/N`.
- `RecurringService` stops when `1 + COUNT(recurring_generated rows for parent) >= recurrence_total_occurrences`.

## Summary
- Create/update actions now parse `recurrence_limit_mode` as `never`, `date`, or `count`.
- Count mode requires `recurrence_total_occurrences >= 2` and clears `recurrence_end_date`.
- Date mode requires `recurrence_end_date` and clears `recurrence_total_occurrences`.
- Never mode clears both limit fields.
- Update rejects lowering a parent total below consumed occurrences.
- `TransactionRepository` persists and lists installment metadata when columns exist.
- `RecurringService` assigns child `parent_transaction_id`, `installment_index`, and `recurrence_total_occurrences`.
- Dashboard and history create/edit flows expose the new ending UI and populate edit state correctly.
- Transaction rows with installment metadata show a small `N/Total` label.

## Post-pause Verification / Hardening (2026-07-06)
- Reviewed the migration for idempotency and MariaDB-compatible guarded `ALTER TABLE` / index checks.
- Reviewed `RecurringService` count handling for the parent-as-1/N rule and no N+1 generation.
- Found and fixed one P2 metadata preservation issue: editing an already generated child transaction could clear `installment_index` and `recurrence_total_occurrences` because the shared repository update path wrote nullable installment fields unconditionally.
- `actions/transactions/update.php` now detects generated child rows through `transactions.parent_transaction_id` and asks the repository to preserve installment metadata for those rows.
- `TransactionRepository::update()` now accepts a scoped `$updateInstallmentFields` flag; normal parent recurrence edits still update count/index fields.
- Static recurrence probes were extended to cover this preservation guard.

## Hetzner Hotfix Verification / Hardening (2026-07-08)
- Report: `reports/checkpoint_hotfix_p2_migration_parser_overflow.md`.
- Persisted the migration parser/comment fix after Hetzner fresh migration failed on semicolons inside `--` comments.
- `scripts/migrate.php::splitSqlStatements()` now strips `--` line comments before splitting on semicolons.
- Existing SQL migration line comments were normalized to remove semicolons.
- Added `tests/probe_migration_parser.py`.
- Updated `tests/recurrence_overflow.php` so the Jan 31 monthly chain simulation carries the parent day-of-month anchor, matching the production `generateForParent()` path for empty `recurrence_day`.
- `src/Services/RecurringService.php` was not changed in this hotfix; the runtime path already passes the parent anchor into `nextOccurrence()`.

## Validation Performed
- `python -m pytest tests\probe_recurrence_installments.py` -> 6 passed.
- `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 10 passed.
- `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py tests\probe_recurrence_installments.py` -> 16 passed.
- `node --check public\scripts\dashboard.js` -> passed.
- `node --check public\scripts\history.js` -> passed.
- `node --check public\scripts\balance-visibility.js` -> passed.
- `node --check public\scripts\main.js` -> passed.
- `node --check public\scripts\kanban.js` -> passed.
- `node --check public\scripts\forecast-chart.js` -> passed.
- PHP 7.4 blocker static scan -> no matches.
- `php --version` -> unavailable locally.
- Post-pause rerun:
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\history.js` -> passed.
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\main.js` -> passed.
  - `node --check public\scripts\kanban.js` -> passed.
  - `node --check public\scripts\forecast-chart.js` -> passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 10 passed.
  - `python -m pytest tests\probe_recurrence_installments.py` -> 6 passed.
  - PHP 7.4 blocker static scan -> no matches.
  - `php --version` -> unavailable locally.
- Hetzner hotfix local validation:
  - `python -m pytest tests\probe_migration_parser.py` -> 2 passed.
  - `python -m pytest tests\probe_recurrence_installments.py` -> 7 passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py tests\probe_recurrence_installments.py tests\probe_migration_parser.py` -> 19 passed.
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\history.js` -> passed.
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\main.js` -> passed.
  - PHP 7.4 blocker static scan -> no matches.
  - `php --version` -> unavailable locally.

## Hostinger Validation Required
```bash
php scripts/migrate.php up
php -l actions/transactions/create.php
php -l actions/transactions/update.php
php -l public/dashboard.php
php -l public/history.php
php -l src/Services/RecurringService.php
php -l src/Repositories/TransactionRepository.php
php -l migrations/20240101000006_add_recurrence_installments_v3.php
php -l tests/recurrence_installments.php
php tests/smoke.php
php tests/recurrence_overflow.php
php tests/recurrence_installments.php
```

Optional cron check:

```bash
php scripts/cron.php recurring
```

## Risks
- Local PHP lint could not run, so Hostinger `php -l` remains required.
- The new PHP harness is static because local/test DB setup is unavailable; production cron behavior still needs Hostinger validation after migration.
- Editing generated child rows still uses the existing transaction edit path; this batch now preserves existing child installment metadata, but it does not add child-specific edit UX.
- Existing generated rows are linked to parents but old rows do not receive invented installment indexes.

## Confidence
88% until Hostinger migration, PHP lint, smoke, recurrence overflow, and recurrence installment harness are run.
