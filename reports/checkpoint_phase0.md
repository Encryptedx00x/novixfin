# Checkpoint — Phase 0 (Security hardening & dead-code removal)

**Timestamp**: 2026-06-22 (build session start)
**Phase**: 0
**Confidence**: 96%

## Files modified (edits)
1. `public/dashboard.php`
   - Removed `error_reporting(E_ALL); ini_set('display_errors','1');`
   - Added `require_once __DIR__ . '/../includes/errors.php';`
   - **Bug fixed**: PHP errors no longer leaked to authenticated dashboard users in production.
2. `actions/transactions/update.php`
   - Removed debug `ini_set('display_errors','1')` + `error_reporting(E_ALL);`
   - Added `require_once __DIR__ . '/../../includes/errors.php';`
3. `actions/transactions/auto-effective.php`
   - Added CLI-only guard: `if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }`
   - Added `require errors.php` before bootstrap
   - **Bug fixed**: anonymous users can no longer trigger mass transaction effective-dating via HTTP.
4. `actions/credit-cards/save-transaction-settings.php`
   - Removed 4 bare `error_log` debug calls (the diagnostic dump of state transitions on every request).

## Files created
1. `includes/errors.php` — centralized error reporting. Reads `APP_ENV` via `getenv` (forward-compatible with Phase 1 env loader). Production-safe default (display_errors=0, log_errors=1).

## Files archived (moved to /archive/phase0)
- test_db.php
- create.php (root — broken duplicate; `require __DIR__.'/../../includes'` was fatal from project root)
- public/check-accounts.php (debug diagnostic leaking account data + non-parameterized cleanуда SQL)
- public/install-accounts.php (installer with NO auth + `?force=yes` raw-SQL override)
- actions/login.php, actions/register.php (5-line stub redirects; real flows in public/login.php / public/register.php)
- actions/logout.php (duplicate of public/logout.php)
- database/update-accounts.sql (divergent accounts installer; superseded by add_accounts.sql)
- database/fix_categories.sql (destructive: `DELETE FROM categories;` + reset AUTO_INCREMENT)
- database/add_effective_date.sql (subsumed by improve_recurring_transactions.sql)

## Directories archived (moved to /archive/phase0/snapshots)
- v1.7 (antes de mudar design completamente)/
- v2 (prototipo 1)/
- v2.1 (com sidebar correta)/

## Backups retained in /backups/phase0
.htaccess, docs/ROADMAP.md, docs/SETUP.md, config/auth.php, config/config.php, includes/bootstrap.php, includes/helpers.php, public/dashboard.php, actions/transactions/{update,auto-effective}.php, actions/credit-cards/save-transaction-settings.php, src/Database/Connection.php

## Bugs fixed
- Production error leakage on `public/dashboard.php` and `actions/transactions/update.php`.
- Unauthenticated mass-mutation via `actions/transactions/auto-effective.php` over HTTP.
- Diagnostic/stray debug logging on every credit-card settings request.

## Risks introduced
- **Low**: removing `display_errors=1` means dev users no longer see verbose errors inline. Mitigated by `includes/errors.php` which re-enables them when `APP_ENV=local` (Phase 1 will set this via .env). Currently `APP_ENV` is unset → defaults to production-off.
- **Low**: archived files no longer web-accessible. Verified no live route references them (`/actions/login|register|logout\.php` not used by any form; forms POST to `/public/login.php` etc. — confirmed via search).

## Rollback strategy
- To revert Phase 0: restore the 4 edited files from `/backups/phase0/`, delete `includes/errors.php`, and `Move-Item` each archived file/directory back from `/archive/phase0/`.

## Validation performed
- No PHP binary on dev machine; cannot run `php -l`. Mitigation: surgical edits via exact-string replacement + manual brace/paren review.
- Searched active PHP tree for stray `display_errors='1'` and bare `error_log` debug blocks → none remain.
- Searched active PHP tree for references to archived files → only matches were inside backups/state/snapshots (now archived).
- Confirmed `.htaccess` does not reference any archived file.

## Pending (carry into later phases)
- `.htaccess:10` whitelist still missing `accounts|categories|credit-cards|currency-settings|wallets` → Phase 3.
- `redirect()` helper whitelist still incomplete (`includes/helpers.php:16`) → Phase 3.
- Runtime `CREATE TABLE`/`ALTER TABLE` DDL still present in actions (categories, wallets, credit-cards) → removed in Phase 2 once migration runner is canonical.
- Hardcoded production credentials still in `config/config.php:8-13` and `docs/SETUP.md:27` → Phase 1 (env loader) + Phase 1 final step (rotate password).

## Phase 0 status: COMPLETE