# NovixFin v3.1 - Hotfix P2 Migration Parser + Recurrence Overflow Harness

Date: 2026-07-08

## Task
- Persist the migration SQL parser/comment fix in source after Hetzner migration validation exposed semicolons inside `--` comments.
- Make `tests/recurrence_overflow.php` pass again before allowing recurring cron.
- Do not implement P3, paid/received workflow, notes, deployment config changes, credential changes, or cron behavior changes.

## Files Changed
- `scripts/migrate.php`
- `migrations/20240101000000_baseline_v3.sql`
- `migrations/20240101000002_seed_categories_v3.sql`
- `migrations/20240101000003_add_recurring_generated_v3.sql`
- `migrations/20240101000005_add_hide_balances_v3.sql`
- `tests/recurrence_overflow.php`
- `tests/probe_recurrence_installments.py`
- `tests/probe_migration_parser.py`
- `reports/checkpoint_p2_recurrence_installments.md`
- `state/progress.md`
- `state/context.md`
- `AGENTS.md`

## Migration Parser Root Cause
- `scripts/migrate.php::splitSqlStatements()` split statements on every semicolon outside single-quoted strings.
- It did not ignore SQL `--` line comments.
- A comment like `-- categories (per-user since v2; global ones carry user_id NULL)` was split at the comment semicolon, causing a comment fragment to be executed as SQL on fresh migration runs.

## Migration Parser Fix
- Added `stripSqlLineComments()` in `scripts/migrate.php`.
- `splitSqlStatements()` now strips `--` line comments before semicolon splitting.
- The stripper is single-quote aware and preserves newlines while removing comments.
- Existing SQL migration comments were also normalized to remove semicolons from `--` comment lines.
- Added `tests/probe_migration_parser.py` to assert the parser keeps the comment-stripping guard and that `.sql` migration line comments do not contain semicolons.

## Recurrence Overflow Root Cause
- The runtime recurrence path already computes the parent day-of-month anchor in `RecurringService::generateForParent()`.
- The failing `tests/recurrence_overflow.php` chain simulation called private `nextOccurrence()` with `recurrenceDay = 0` on every loop step, so it did not carry the parent anchor that production generation carries.
- That made the harness simulate the legacy ratchet-down fallback instead of the actual no-explicit-`recurrence_day` production path.

## Recurrence Overflow Fix
- Updated the Jan 31 monthly chain simulation to compute `$anchorDay` from the parent cursor before the loop and pass it to `nextOccurrence()` for each step.
- P2 installment behavior was not changed.
- `src/Services/RecurringService.php` was not changed because the production path already preserves the parent anchor.
- Added a static assertion in `tests/probe_recurrence_installments.py` so the overflow harness keeps using the parent-day anchor in that chain.

## Validation Performed Locally
- `python -m pytest tests\probe_migration_parser.py` -> 2 passed.
- `python -m pytest tests\probe_recurrence_installments.py` -> 7 passed.
- `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py tests\probe_recurrence_installments.py tests\probe_migration_parser.py` -> 19 passed.
- `node --check public\scripts\dashboard.js` -> passed.
- `node --check public\scripts\history.js` -> passed.
- `node --check public\scripts\balance-visibility.js` -> passed.
- `node --check public\scripts\main.js` -> passed.
- PHP 7.4 blocker static scan -> no matches.
- `php --version` -> unavailable locally.

## Hetzner Validation Required
```bash
docker compose exec -w /srv/sites/novixfin php php -l scripts/migrate.php
docker compose exec -w /srv/sites/novixfin php php -l src/Services/RecurringService.php
docker compose exec -w /srv/sites/novixfin php php -l tests/recurrence_overflow.php
docker compose exec -w /srv/sites/novixfin php php -l tests/recurrence_installments.php

docker compose exec -w /srv/sites/novixfin php php tests/recurrence_overflow.php
docker compose exec -w /srv/sites/novixfin php php tests/recurrence_installments.php
docker compose exec -w /srv/sites/novixfin php php tests/smoke.php
```

Do not run recurring cron until all three PHP tests above pass.

## Risks
- Local PHP lint and PHP harnesses could not run because this machine has no PHP binary.
- The SQL parser fix is static/probe-validated locally; final confirmation must happen in the Hetzner PHP container.
- The overflow runtime path was not changed; the fix aligns the test harness with the existing production anchor-day path.

## Confidence
90% after local static validation; final confidence depends on Hetzner PHP lint and the three PHP tests.
