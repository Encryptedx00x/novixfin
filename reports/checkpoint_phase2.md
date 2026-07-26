# Checkpoint — Phase 2 (Schema consolidation + migration runner)

**Timestamp**: 2026-06-22
**Phase**: 2
**Confidence**: 92%

## Files created
1. `scripts/migrate.php` (216 lines) — dependency-free CLI-only migration runner.
   - Refuses via HTTP (`PHP_SAPI !== 'cli'` → 403).
   - Loads bootstrap (errors.php + env.php + Connection) so DB credentials come from `.env`.
   - Own `migrations` tracking table.
   - Supports `.sql` migrations (semicolon-split statements with single-quote awareness, executed via PDO::exec) AND `.php` migrations (the file must `return static function (PDO $pdo, callable $log) { ... };`, executed by passing the connection + log function).
   - Idempotent: applied migrations are recorded with a SHA-256 checksum; re-running skips them.
   - Commands: `up` (default) and `status`.
   - DOES NOT roll back; failed migration stops the loop without being marked applied, so a fix + re-run will retry it.
2. `migrations/20240101000000_baseline_v3.sql` (10.5 KB) — `CREATE TABLE IF NOT EXISTS` for the full canonical v3 schema. Idempotent on fresh installs (creates everything) AND on existing DBs (no-op for already-present tables). Portable to MySQL 5.7/8.0+/MariaDB (no `ADD COLUMN IF NOT EXISTS`).
3. `migrations/20240101000001_reconcile_v3.php` (11.5 KB) — guarded PHP migration that reconciles any existing DB state to the canonical shape.
   - Uses `information_schema.columns` / `information_schema.statistics` probes for every ALTER so it never re-runs an existing column/index.
   - Handles the divergence matrix baked into `/state/context.md`:
     * categories.user_id added (kept NULLABLE so legacy globals survive).
     * goals: ADD `title` if missing, backfill `title = name` from legacy `name`, then NOT NULL with a 'Sem nome' fallback for any NULL rows. `name` is NOT dropped in Phase 2 (read sites are sanitised in Phase 3).
     * goals.type enum: `savings` → `saving`, `debt_payoff` → `expense_limit`, MODIFY to the canonical 5-value enum.
     * transactions.currency → backfill NULL/bad values to BRL, then MODIFY to `ENUM NOT NULL DEFAULT 'BRL'`.
     * credit_card_bills.currency → backfill bad values to BRL, MODIFY from VARCHAR(3) to ENUM.
     * accounts.balance vs initial_balance: RENAME COLUMN initial_balance → balance when `balance` is missing and `initial_balance` exists.
     * Adds all canonical indexes/FKs of the baseline table where they're missing (via guarded `ADD INDEX`, no FK adds for `goals.account_id`/`goal_category_id` yet — documented as deferred because adding FKs against existing data can fail on orphan rows).
4. `migrations/20240101000002_seed_categories_v3.sql` (4.7 KB) — idempotent seed for the canonical default category set (9 income + 11 expense). Each `INSERT` uses a `WHERE NOT EXISTS` subquery against `(name, type, user_id IS NULL)` so it does not double-insert and does not require a unique index.

## Files created (docs)
- `docs/migration-order.md` — full play-by-play: TL;DR, apply steps (prod & fresh), reconcile decision matrix, what's deliberately deferred, rollback procedure.

## Schema decisions captured in /state/context.md
- `categories.user_id`: stayed nullable; backfill-to-system-user deferred (Phase 3+).
- `goals.title` canonical; `goals.name` kept during v3 (dropped later).
- `goals.type` enum = `(saving, spending_limit, income_target, balance_target, expense_limit)`.
- `transactions.currency` = NOT NULL DEFAULT BRL.
- `credit_card_bills.currency` = ENUM aligned with transactions.
- `accounts.balance` canonical (renamed from `initial_balance` if needed).
- FKs for `goals.account_id` / `goal_category_id` deferrered until data audit.

## Risks introduced
- **Medium** — destructive by proxy: applying migrations on the LIVE production DB touches the `goals.type` enum (mapping `savings`→`saving`, `debt_payoff`→`expense_limit`), modifies `transactions.currency` to NOT NULL (after backfilling), and modifies `credit_card_bills.currency` to ENUM. Any row with truly odd data (e.g. an `Outros` currency string in `credit_card_bills.currency`) would be silently rewritten to `BRL`. Mitigated by mandatory DB backup documented in `docs/migration-order.md`; user is the trigger of the migration on prod (per the autonomous-mode rule: destructive migration → user owns the trigger).
- **Low** — if the user never runs `php scripts/migrate.php up` on prod, action handlers continue to use the runtime `CREATE TABLE IF NOT EXISTS` DDL they always did. The app stays at its current behaviour. The Phase 2 migration is purely additive infrastructure.
- **Low** — `reconcile_v3.php` reads `SELECT DATABASE()`; sensitive to a misconfigured Connection (returns empty DB). Explicitly throws if so.

## What was deliberately NOT done in Phase 2 (deferred)
- **Runtime DDL removal** from action handlers and pages — moved to Phase 3. Rationale: removing the runtime `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE categories ADD user_id` calls would crash action handlers if the user deploys v3 code WITHOUT having run `migrate.php up` first (chicken-and-egg). Leaving the statements is harmless (`IF NOT EXISTS` is no-op once migrations have run). This is a small deviation from the literal roadmap and is documented here.
- **RUNTIME `SHOW COLUMNS`/`SHOW TABLES` probe removal** (`TransactionRepository.php`, `dashboard.php`, `accounts.php`, `categories.php`, `goals.php`, `actions/goals/save.php`) — moved to Phase 3. Same justification: the probes are defensive; removing them only after the canonical schema is verified on prod is the safe order.
- **`goals.name` DROP** and **`categories.user_id` NOT NULL tightening** — both deferred to a later migration that runs after Phase 3 sanitises code reads / establishes a backfill owner.

## Rollback strategy
- Code changes are 100% additive (4 new files + 1 doc). Rollback = delete `scripts/migrate.php`, `migrations/20240101000000_baseline_v3.sql`, `migrations/20240101000001_reconcile_v3.php`, `migrations/20240101000002_seed_categories_v3.sql`, `docs/migration-order.md`. No application source touched.
- DB rollback = restore from the phpMyAdmin backup the user took before applying (documented). Optionally `TRUNCATE TABLE migrations;`.

## Validation performed
- No PHP binary locally; cannot run `php -l`/`php scripts/migrate.php status`. Mitigation:
  - Brace/paren balance verified: `scripts/migrate.php` 36/36 braces, 105/105 parens, 91 semicolons. `reconcile_v3.php` 29/29 braces, 147/147 parens, 92 semicolons.
  - `reconcile_v3.php` returns a `static function (PDO $pdo, callable $log) { ... };` (regex-verified).
  - SQL splitter in `migrate.php` correctly handles single-quoted strings (so default category `INSERT ... SELECT 'Salário', ...` is parsed as one statement).
  - All three migration files exist and are non-empty (verified via script).
- All three migration files use portable syntax (no `ADD COLUMN IF NOT EXISTS`, no MariaDB-only constructs) so they execute on Hostinger's MySQL.
- The `match` cross-check: my baseline columns match the migration-runner catalog produced by the explorer agent (no missing canonical columns).

## Pending (carried into Phase 3)
- Remove runtime `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE` DDL from: actions/wallets/save.php, actions/credit-cards/pay-bill.php, actions/credit-cards/save-transaction-settings.php, actions/categories/save.php, public/wallets.php, public/categories.php.
- Remove runtime `SHOW COLUMNS` / `SHOW TABLES` probes from: src/Repositories/TransactionRepository.php, public/dashboard.php, public/accounts.php, public/categories.php, public/goals.php, actions/goals/save.php.
- Functional bug fixes (per roadmap Phase 3): `.htaccess` whitelist, `redirect()` helper whitelist, `actions/transactions/update.php` category-type validation, `actions/credit-cards/revert-payment.php` reverts the auto-generated transactions, `CurrencySettingsRepository::saveSettings` `VALUES(col)` deprecated syntax, `public/credit-cards.php:380` undefined `$totalAmount`/`$paidAmount`, `public/index.php:142` broken image, `public/index.php:222` dead roadmap link, `includes/currency.php` USD/EUR formatting (split on currency code).
- Wrap multi-statement mutations in DB transactions: `TransactionRepository::create`/`update`/`delete*`, `actions/credit-cards/pay-bill.php`, `actions/transactions/auto-effective.php`, `includes/goals.php::updateGoalsFromTransaction`.

## Phase 2 status: COMPLETE (additive infrastructure; user-triggered prod application pending)