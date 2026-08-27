# Testing (v3)

> Phase 10. We have no Composer + PHPUnit set up locally, so v3 ships a
> dependency-free cold smoke test (`tests/smoke.php`) plus a script-level
> sanity check the user runs on the server. End-to-end tests + PHPUnit are
> on the short-term roadmap (see `docs/ROADMAP.md`).

## 1. Smoke test (no Composer, no DB required)

```sh
php tests/smoke.php
```

Asserts:
- `includes/env.php` loads and `env('UNKNOWN', 'fallback') === 'fallback'`.
- `env()` exposes real `putenv()` values.
- `Novix\Services\{InsightService, RecurringService, GamificationService}` autoload + their canonical methods (`forUser`, `generateDue`, `award`) exist.
- `config/config.php` exists and does NOT contain the leaked production password.
- `config/config.php` reads `env('DB_HOST', ...)` (i.e., the v3 env-loader contract).

Exit code 0 = pass; non-zero = at least one assertion failed.

## 2. Migration runner self-check

```sh
php scripts/migrate.php status
```

Lists each migration with `[x]` / `[ ]` applied marker. Use `up` to apply:

```sh
php scripts/migrate.php up
```

## 3. Manual smoke (post-deploy)

After running both:
1. `php scripts/cron.php recurring` → EXPECTED: prints counts `parents_scanned` ≥ 0 and `generated` ≥ 0 (zero for a fresh install with no recurring parents, or however many were due otherwise).
2. Open `/public/export.php?format=csv&scope=transactions` → EXPECTED: downloads `novixfin_transactions_YYYY-MM-DD_HHMMSS.csv`.
3. Open `/public/import.php` → EXPECTED: upload form renders; upload a CSV file (you can use the one just exported) → preview grid renders; click Confirmar → success flash + redirect to history with new rows visible.
4. Open `/public/profile.php` → EXPECTED: `nível` and `pontos` reflect actual values (post creating a couple of transactions: +10 per effective, +2 per pending).

## 4. End-to-end PHPUnit (roadmap)

Once Composer lands (deferred to short-term roadmap):
```sh
composer install
vendor/bin/phpunit
```
First tests should cover `RecurringService::nextOccurrence`, `GamificationService::award`, `InsightService::forUser`, and `TransactionRepository::create`'s transactional boundary (mock PDO or use a SQLite shim).