# Validation Phase C — Static Validation Suite

**Mode**: static structural sweep + smoke test static-trace + migration dry-run mental check (no PHP binary locally, so cannot do `php -l` / cannot run `php scripts/migrate.php status`).

## C.1 PHP syntax validation (proxy)

`php -l` cannot run locally (no PHP on dev PATH). Substitute: brace/paren balance + open-PHP-tag check across all 65 active PHP files (PHP files inventoried in `state/active_php_files.txt`).

| Check | Method | Failures | Verdict |
|---|---|---|---|
| Open PHP tag in every executable file | first-bytes starts with `<?php` or `<?` | 1 false-positive (`includes/layout/footer.php` is a pure-HTML partial `include`d from PHP pages — correct PHP behaviour) | PASS |
| Brace balance per file `{` == `}` | raw char count over file bytes | 0 mismatch | PASS |
| Parens balance — total run | raw `(` vs `)` chars | 3 deltas — all confirmed cosmetic (PHP-doc comment markers + in-string parens) | PASS |
| Parens balance — code-only (excl. comments) | filter then count | 0 mismatch | PASS |

Detailed per-line audit of the 3 cosmetic deltas:
- `actions/credit-cards/revert-payment.php` −3 → from `// 1) ... // 2) ... // 3) ...` PHPDOC markers (PHP ignores comments).
- `public/import.php` −3 → from `*   1) ... 2) ... 3) ...` PHPDOC markers.
- `tests/smoke.php` +1 → from `'env(\'DB_HOST\''` substring literal in a `str_contains` assertion (PHP ignores string contents).

## C.2 Include-path verification

Static-resolved every `require_once|require|include_once|include __DIR__ . '<relative>'` statement across all 65 active files.

- Total `__DIR__`-relative includes scanned: 102.
- Resolved each by combining `__DIR__` with the relative string + `Path.GetFullPath` (PowerShell `Path.GetFullPath(Path.Combine(fileDir, rel))`) — emulating PHP's exact path resolution.
- Result: **0 broken** — every target file exists.

## C.3 Novix\ `use` reference verification

Static-scanned all `use Novix\<FQN>;` declarations in 65 active files. Resolved each to `<project>/src/<path>.php` per the PSR-4-ish autoload map in `includes/bootstrap.php` (`Novix\` → `src/`).

- Result: **0 broken** — every `Novix\Database\Connection`, `Novix\Repositories\*`, `Novix\Services\*` resolves to an existing file.

## C.4 Form-action / cross-file route verification

Scanned every `action=".../<file>.php[?...]"` HTML attribute. Resolved each `<file>.php` against the project root (handles /actions/ and /public/ prefixes).

- Result: **0 broken** — every form posts to an existing PHP file in `/actions/` or `/public/`.

## C.5 Route integrity (entry-point coverage)

Public / web-accessible pages: `public/index.php` (landing), `login`, `register`, `logout`, `dashboard`, `history`, `accounts`, `categories`, `credit-cards`, `currency-settings`, `profile`, `wallets`, `goals`, `export.php`, `import.php`. All `requireAuth()`-protected except `index`/`login`/`register`/`logout`.

Internal / mutation handlers: `actions/{login,logout,register}.php` removed (Phase 0); kept `actions/accounts/*`, `actions/categories/*`, `actions/credit-cards/*`, `actions/currency-settings/save.php`, `actions/goals/*`, `actions/profile/*`, `actions/transactions/*`, `actions/wallets/*`. All `requireAuth()`-protected except `actions/wallets/get-accounts.php` (GET JSON, auth-only).

CLI-only scripts: `scripts/migrate.php`, `scripts/cron.php`, `actions/transactions/auto-effective.php` (post-Phase-0), `tests/smoke.php` — all gated by `PHP_SAPI !== 'cli'` → 403 if hit via HTTP.

## C.6 Migration dry-run (idempotency inspection)

Static-inspected each migration for idempotency (re-runnable without error):

| Migration | Re-run safe? | Mechanism |
|---|---|---|
| `20240101000000_baseline_v3.sql` | YES | 10× `CREATE TABLE IF NOT EXISTS` + 3× `SET` statements, no ALTERs. |
| `20240101000001_reconcile_v3.php` | YES | every ALTER guarded by `information_schema.columns`/`.statistics` probe via `$columnExists` / `$indexExists` closures. |
| `20240101000002_seed_categories_v3.sql` | YES | every INSERT guarded by `INSERT ... SELECT ... WHERE NOT EXISTS (...)`. |
| `20240101000003_add_recurring_generated_v3.sql` | YES | `CREATE TABLE IF NOT EXISTS` only. |

All four migrations can be applied in sequence repeatedly without surfacing a duplicate-key / duplicate-column error. The `migrations` tracking table (created by `ensureMigrationsTable`) records each one's checksum + apply time so re-running the migration runner skips already-applied migrations.

## C.7 Migration runner syntax dry-trace

`scripts/migrate.php` static trace:

1. CLI guard kicks in (`PHP_SAPI !== 'cli'` → 403).
2. `require includes/errors.php` → loads env (`env.php`); HTTP_REQUEST_METHOD irrelevant in CLI.
3. `require includes/bootstrap.php` → registers `Novix\` autoload + session + helpers.
4. `$pdo = Connection::get();` → single PDO instance (reads config/config.php which reads env). Errors propagate as RuntimeException on failure.
5. `ensureMigrationsTable($pdo)` runs `CREATE TABLE IF NOT EXISTS migrations (...)` first → no errors even if DB is fresh (subsequent inserts into migrations table work honouring the new table).
6. For each migration file in `/migrations`:
   - `.sql` → `executeSqlFile()` calls `splitSqlStatements()` (single-quote-aware `;` splitter) + executes each via `$pdo->exec()`.
   - `.php` → `require $path` → returns a callable → invoked with `(PDO, callable)`.
   - On success, INSERT into `migrations (id, checksum)` table.
   - On failure, log + `exit(1)`.
7. Two commands: `up` (default; runs pending), `status` (lists applied/pending).

Static trace: **no path issues, no quoting issues, no logic bugs.**

## C.8 Smoke-test static dry-trace (`tests/smoke.php`)

Reading `tests/smoke.php` against expected behaviour:

| Assertion | Expected behaviour | Verdict |
|---|---|---|
| `load_env('/tmp/__nope_nonexistent_env_file__novixfin_smoke')` | Function sets `static $loaded=true`, returns early on `!is_file($path)`. Subsequent `env()` calls bypass further parsing. ✓ | PASS |
| `env('NON_EXISTENT_KEY', 'fallback-default')` returns `'fallback-default'` | `getenv()` false → $_ENV empty → $_SERVER empty → returns default. ✓ | PASS |
| `env('__totally_unset_key__')` returns null | same path, no default → null. ✓ | PASS |
| `putenv('NOVIXFIN_SMOKE_TEST=value-from-system-env')` then `env(...)` returns `'value-from-system-env'` | putenv sets process env; `getenv('...')` true; env() returns it. ✓ | PASS |
| `ReflectionClass('\Novix\Services\InsightService')` exists | the smoke.php registers a spl_autoload for `Novix\` → src/; class loads. Inspect `hasMethod('forUser')` → exists. `getReturnType()` is `array` (we declared `: array` via PHP `array` return type). ✓ | PASS |
| `ReflectionClass('\Novix\Services\RecurringService')` exists + has `generateDue` | same path. ✓ | PASS |
| `ReflectionClass('\Novix\Services\GamificationService')` exists + has `award` | same path. ✓ | PASS |
| `config/config.php` does NOT contain `'Novix200505'` substring | Confirm Phase 1 removed leaked password; verify via `str_contains`. ✓ | PASS |
| `config/config.php` reads `env('DB_HOST', ...)` | Phase 1 rewrite reads env. Verify `str_contains($contents, "env(\'DB_HOST\'")`. ✓ | PASS |

Total: **9 assertions, all expected to PASS.** Final `exit(0)` if all match.

## C.9 Validation phase C summary

- No PHP syntax issues detected (proxy via brace/paren/open-tag checks).
- Every `__DIR__`-relative include resolves to an existing file.
- Every `Novix\` `use` reference resolves to an existing src/ file.
- Every form-action / cross-file route references an existing PHP file.
- All 4 migrations are re-runnable (idempotent).
- Smoke test assertions statically pass under trace.

## Phase C status: COMPLETE — no errors fixed, all validation checks pass.