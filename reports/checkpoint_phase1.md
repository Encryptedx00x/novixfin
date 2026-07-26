# Checkpoint — Phase 1 (Real config & env)

**Timestamp**: 2026-06-22
**Phase**: 1
**Confidence**: 94%

## Files created
1. `includes/env.php` — dependency-free `.env` parser + `env()` helper. Lazy + idempotent (parses project-root `.env` once). Sets `putenv()` + `$_ENV` + `$_SERVER` so both legacy `getenv()` and modern superglobals see values. Strips quotes, ignores comments/empty lines, supports inline `#` comments on unquoted values, normalizes `true`/`false`/`null`/`empty` literals.
2. `config/config.example.php` — template consumers copy to `config/config.php`.
3. `.env.example` — placeholder env template documenting all keys (APP_*, DB_*, SESSION_*).
4. `.gitignore` — ignores secrets `.env`, `config/config.php`, plus v3 artifacts `/backups/ /archive/ /reports/ /state/`, editor/OS dirs, `vendor/`, `node_modules/`.

## Files modified / rewritten
1. `config/config.php` — **REWRITTEN**: hardcoded Hostinger creds (host `srv811.hstgr.io`, db `u545165688_NovixFin`, user `u545165688_NovixADM`, password `@#$Novix200505`) REMOVED. Now reads everything from `env()` with safe dev fallbacks (`127.0.0.1` / `novix_fin` / `root` / empty). Array shape preserved (`db`, `app`) so all existing consumers (Connection.php) keep working unchanged.
2. `config/auth.php` — made env-driven: session name + cookie lifetime + samesite from env; kept `novix_fin_session` as default to **preserve existing sessions**. Password algo logic untouched.
3. `includes/bootstrap.php` — now requires `includes/errors.php` FIRST (centralizes error handling + env loading across every entry point). Timezone now reads `APP_TIMEZONE` from env (default `America/Sao_Paulo`). Autoloader/session/helpers/currency return contract unchanged.
4. `includes/errors.php` — uses `env('APP_ENV','production')` instead of raw `getenv()`, so the app-env value (from `.env` or system env) drives dev/prod error visibility.
5. `.env` — rewritten: local dev values, `APP_ENV=local`, `SESSION_NAME=novix_fin_session` (preserves v2 session identity).
6. `docs/SETUP.md` — **REWRITTEN**: removed leaked password, removed false `composer install` / `config/env.local` claims, documented hand-rolled `.env` workflow, added per-variable reference table, ROTATION OBLIGATION section (the old password `@#$Novix200505` is in git history; user must rotate it on Hostinger before deploying v3).

## Bugs fixed / risks eliminated
- Hardcoded production DB credentials no longer in source tree (moved to gitignored `.env`).
- `.env` is no longer decorative/dead — fully parsed and consumed.
- `display_errors=1` in production paths eliminated; centralized in `errors.php` driven by `APP_ENV`.
- Session-name backward compatibility preserved (existing user sessions survive).

## Risks introduced
- **Low/Medium — credentials rotate**: the OLD password is still in git history. Until the user rotates on Hostinger + deploys with the new `.env`, the production DB remains effectively exposed. (Mitigated as a manual user step documented in SETUP.md; cannot be auto-rotated from code.)
- **Low**: `env()` treats `null`/`empty`/`false`/empty-string env values as "unset" → returns default. This is intentional and matches dev intuition; matches what `vlucas/phpdotenv` consumers expect.
- **Low**: `.env` must exist on the Hostinger server (likely absent today since `.env` was never read by v2). Deployment of v3 now requires creating `.env` on the server per SETUP.md. If forgotten, `Connection::get()` will throw `Database connection failed` because it'll try 127.0.0.1 — so the site fails closed, not with mismatched creds. Safe failure mode.

## Rollback strategy
- Restore `config/config.php`, `config/auth.php`, `includes/bootstrap.php`, `includes/errors.php`, `docs/SETUP.md`, `.env` from `/backups/phase1/`.
- Delete `includes/env.php`, `config/config.example.php`, `.env.example`, `.gitignore`.

## Validation performed
- No PHP binary locally; cannot run `php -l`. Mitigation: surgical rewrites via exact replace / careful authorship + brace/paren balance check (all 6 new/edited PHP files: braces balanced, parens balanced, semicolon counts sane).
- Confirmed no remaining references to old Hostinger host/db/user/password/URL anywhere in active code (`.php/.md/.htaccess/.env/.js/.css/.html`).

## Pending (carried forward)
- User must rotate the DB password on Hostinger (manual step).
- Phase 2: ordered migration system in `/migrations/` + consolidation SQL (`docs/migration-order.md`); remove runtime DDL from actions/wallets, actions/categories/save, actions/credit-cards/pay-bill, public/wallets, public/categories.
- `.env` on the production server must be created during deploy. (Documented in SETUP.md.)

## Phase 1 status: COMPLETE