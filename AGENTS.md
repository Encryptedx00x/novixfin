# AGENTS.md

Permanent operating manual for Codex sessions and subagents working on NovixFin.

Read this file first in every future session. Then read `state/progress.md`,
`state/context.md`, the newest files under `reports/`, and the current file
diff before editing anything.

## Project Overview

NovixFin is a production-running personal finance and fintech application built
with plain PHP, MySQL/MariaDB, HTML, CSS, and JavaScript.

The architecture is intentionally plain and dependency-light. It began as
shared-hosting compatible and, although production is now a Docker VPS (see
"Production Environment Facts"), the constraints below still hold:

- no Laravel
- no Symfony
- no React/Vue rewrite
- no SPA rewrite
- no framework migration
- no unnecessary architecture refactor

Preserve the current PHP architecture. Prefer small, local, production-safe
changes over broad rewrites.

Core domains include auth, accounts and wallets, income and expense tracking,
transfers, credit cards, recurring transactions, savings goals, dashboard
insights, kanban visualization, currency conversion, CSV import/export, and
partial gamification.

## Source Of Truth Paths

Primary project and source of truth:

`C:\Users\Encryptedx\Desktop\NovixFin`

Historical reference only:

`D:[ Importante ][ Serviços e ferramentas ]\Novix Fin v2`

Use the historical directory only when comparison is necessary. Do not edit it.
Do not treat it as the active project.

## Production Environment Facts

Updated 2026-07-23. The project migrated OFF Hostinger shared hosting.

- Production host: **VPS with Docker + Caddy + MariaDB**.
- **PHP 8.3.32** in production (web and CLI).
- Database: MariaDB 11.8.x.
- PHP is available on the server, but local PHP may be unavailable on the dev
  machine — see "Validation Rules".

### Language target

- **New code targets PHP 8.1+.** `match`, `str_contains`/`str_starts_with`,
  the nullsafe operator `?->`, union types, constructor property promotion,
  named arguments, readonly properties and enums are all allowed.
- **Do NOT retrofit existing PHP 7.4-era code.** Most of the codebase was
  written against 7.4 and runs fine on 8.3. Rewriting it to modern syntax is
  churn with regression risk and no user-visible benefit. Modernize a file
  only when you are already changing it for a real reason, and keep the diff
  scoped to that reason.
- All `catch` blocks must still include a variable (`catch (Throwable $e)`).
  This was a 7.4 requirement but remains the house style — variableless catch
  makes errors harder to log.

### Deployment layout and the `actions/` routing trap

- Docroot in production is `/srv/sites/novixfin/public`.
- **`actions/` lives OUTSIDE the docroot.** It is therefore NOT reachable by
  default: it requires the `@actions` route in the host Caddyfile at
  `/srv/stack/Caddyfile`.
- **Failure mode to recognise:** without that route, every `POST /actions/*`
  falls through to the SPA/landing fallback and returns **the landing page with
  HTTP 200**. Nothing errors. The form appears to submit and silently does
  nothing — no 404, no 500, no PHP log entry. If a form "does nothing" in
  production while working locally, check the `@actions` route FIRST.
- Keep this in mind when adding any new endpoint under `actions/`.

### MariaDB

- MariaDB 11.8 supports `ON DUPLICATE KEY UPDATE col = VALUES(col)`.
- MariaDB 11.8 does not support MySQL-style
  `INSERT ... AS alias ON DUPLICATE KEY UPDATE alias.col`.

Do not regress the MariaDB compatibility fix in
`src/Repositories/CurrencySettingsRepository.php`.

## Current Project State

- NovixFin v3 is deployed and production-running.
- The v3 validation score before v3.1 work was around 90/100.
- Batch 1 is completed.
- Batch 2 Tasks 4 through 6 are completed according to the current checkpoint:
  sidebar polish, hide/show balances, and inline category creation.
- Remaining future work starts after Batch 2.

Local PHP has previously been unavailable in this workspace, so PHP lint and
server smoke checks may need to run on the VPS (or any PHP 8.3 environment).

## Critical Invariants

Never regress these behaviors:

- balance calculations
- transaction atomicity
- credit card pay/revert logic
- recurring transaction generation
- monthly recurrence anchor-day behavior
- authentication and session behavior
- CSRF protection
- migration idempotency

Recurring invariant:

`Jan 31 -> Feb 28/29 -> Mar 31` recovery must remain preserved.

Do not touch `src/Services/RecurringService.php` unless the task truly requires
it. If it must be changed, first write down the exact bug, the reproduction
case, and how the anchor-day invariant will be validated.

Financial operations must stay ACID-safe. Inserts, updates, deletes, credit card
payments, bill reversions, recurring generation, and balance mutations must not
leave account balances, bills, or transaction rows inconsistent after a partial
failure.

Global categories can exist with `categories.user_id IS NULL`. Transaction and
dashboard category queries should keep supporting both user-owned categories and
global default categories when the schema has `categories.user_id`.

Balance privacy is DB-backed. `users.hide_balances` is the source of truth for
whether balances are hidden by default after login. The layout masks balances on
first paint by rendering `body.hide-balances` and
`data-hide-balances-default="true|false"`.

## Critical Files

- `includes/env.php`
  Dependency-free `.env` loader. Keeps sensitive configuration out of committed
  PHP files and exposes values through `env()`, `getenv()`, `$_ENV`, and
  `$_SERVER`.

- `config/config.php`
  Environment-driven app and database config. Do not hardcode production
  credentials here.

- `includes/auth.php`
  Authentication helpers for finding, creating, authenticating, and logging out
  users. Session payload includes theme and `hide_balances`; preserve that
  behavior.

- `includes/throttle.php`
  DB-backed login and registration throttle using `login_attempts`. It is
  intentionally fail-open on DB errors to avoid locking all users out.

- `includes/layout/header.php`
  Shared HTML shell and sidebar. Applies theme, exposes `window.NOVIX_CSRF_TOKEN`,
  renders the server-side hide-balances body class, and contains the polished
  sidebar/user/logout layout.

- `includes/layout/footer.php`
  Shared script footer. Loads Chart.js, common scripts, global
  `public/scripts/balance-visibility.js`, and page-specific scripts.

- `src/Repositories/TransactionRepository.php`
  Core transaction repository. Handles create, update, delete, totals, account
  balance mutation, recurrence fields, and effective transaction state. Treat as
  financial core.

- `src/Services/RecurringService.php`
  Recurring transaction engine used by cron. Preserves idempotency through
  `recurring_generated`, links credit-card recurring children to bills, and
  contains the monthly/yearly clamp and anchor-day logic.

- `src/Repositories/CurrencySettingsRepository.php`
  Currency settings persistence. Must keep MariaDB-compatible
  `VALUES(col)` upsert syntax.

- `actions/transactions/create.php`
  Transaction creation action. Auth and CSRF protected. Must preserve category
  scope, recurrence fields, effective state, and balance behavior.

- `actions/transactions/update.php`
  Transaction update action. Auth and CSRF protected. Must preserve recurrence
  editing fields and balance adjustment behavior.

- `actions/credit-cards/pay-bill.php`
  Credit card bill payment flow. Treat as financial core; preserve atomic bill
  payment behavior and generated helper transactions.

- `actions/credit-cards/revert-payment.php`
  Credit card payment revert flow. Treat as financial core; preserve atomic
  reversal behavior.

- `actions/profile/update-balance-visibility.php`
  Persists the hide-balances privacy preference to `users.hide_balances` and the
  active session. Supports AJAX JSON responses and normal redirects.

- `public/dashboard.php`
  Main dashboard page. It is large and mixes data preparation, filters, charts,
  transaction forms, modals, inline category creation data, and page script
  wiring. Do not rewrite it broadly.

- `public/scripts/dashboard.js`
  Dashboard behavior, including charts, edit modal population, recurrence UI,
  and inline category creation.

- `public/scripts/balance-visibility.js`
  Global hide/show balance toggle. Coordinates `body.hide-balances`, the eye
  button, and AJAX persistence.

- `public/scripts/kanban.js`
  Kanban visualization. Must preserve recurring/fixed item detection using
  `has_recurring` before name heuristics.

- `scripts/migrate.php`
  CLI-only migration runner. Runs ordered `.sql` and `.php` migrations and
  records applied files in the `migrations` table.

- `scripts/cron.php`
  CLI-only cron dispatcher for recurring generation and auto-effective
  transactions. Scheduled on the VPS.

## Migration Rules

- Use idempotent migrations.
- Assume MariaDB 11.8 compatibility.
- Do not use MySQL-only syntax if MariaDB rejects it.
- Do not use `INSERT ... AS alias ON DUPLICATE KEY UPDATE alias.col`.
- Prefer `ON DUPLICATE KEY UPDATE col = VALUES(col)` for MariaDB-compatible
  upserts.
- Never run destructive schema changes without explicit user approval.
- Never drop or truncate production data without explicit user approval.
- Keep migrations ordered by filename and compatible with `scripts/migrate.php`.
- Use guarded PHP migrations when SQL needs schema introspection.
- Avoid semicolons in SQL `--` line comments. The migration runner strips line
  comments before statement splitting, and tests enforce comment-safe
  migrations.
- Back up production before applying schema changes.

Server migration command:

```bash
php scripts/migrate.php up
```

Status command:

```bash
php scripts/migrate.php status
```

## Work Process

Every future agent must:

1. Read `AGENTS.md`.
2. Read `state/progress.md` and `state/context.md`.
3. Inspect the latest checkpoint and validation files under `reports/`.
4. Inspect the current file diff before editing.
5. Determine whether the workspace contains partial work from another tool.
6. Read every file before changing it.
7. Use minimal diffs and preserve local style.
8. Avoid touching financial core files unless the task requires it.
9. Create a checkpoint after every meaningful batch or every 3 to 5 changed
   files.
10. Update `/state` and `/reports` after meaningful work.

Checkpoint reports belong in `reports/` and should include:

- task
- files changed
- summary
- risks
- validation performed
- remaining work
- confidence percentage

Do not modify unrelated files. Do not revert user or tool changes unless the
user explicitly asks for that operation.

## Subagent Orchestration Rules

Codex may use subagents only when the active session policy permits delegation.
Subagents are read-only by default.

Recommended read-only subagent audits:

- Balance privacy audit
- UI/CSS regression audit
- Category/transaction form audit
- Recurring regression audit
- SQL/migration compatibility audit

Rules:

- Subagents investigate and report only unless explicitly authorized otherwise.
- The main agent edits code.
- The main agent consolidates subagent reports.
- No subagent may alter financial core files unless explicitly authorized.
- Financial core files include transaction repositories, recurring services,
  credit-card pay/revert actions, migration runner behavior, and balance
  mutation paths.

## Validation Rules

If local PHP is unavailable:

- use static validation
- use `node --check` for changed JavaScript files
- use Python probes where applicable
- provide server-side PHP lint and smoke commands for the user or server

Server-side checks:

```bash
php scripts/migrate.php up
php tests/smoke.php
php -l <changed php files>
```

Useful targeted checks:

```bash
php scripts/migrate.php status
php scripts/cron.php recurring
php scripts/cron.php auto-effective
node --check public/scripts/dashboard.js
node --check public/scripts/balance-visibility.js
node --check public/scripts/kanban.js
```

For recurring changes, validate the anchor-day case explicitly:

`Jan 31 -> Feb 28/29 -> Mar 31`

For SQL changes, validate on MariaDB-compatible syntax, not only MySQL syntax.

## Forbidden Changes Without Explicit Approval

- Framework migration.
- SPA rewrite.
- Laravel, Symfony, React, or Vue rewrite.
- Destructive database operations.
- Broad dashboard rewrite.
- Changes to recurring anchor-day behavior.
- Changes to balance mutation logic without tests or a written validation plan.
- Reverting MariaDB `VALUES(col)` compatibility.
- Moving secrets back into committed config files.

## Practical Notes

- Prefer existing helpers and repositories over new abstractions.
- Keep auth and CSRF checks on every action endpoint.
- Keep action endpoints shared-hosting friendly and dependency-free.
- When adding UI features, preserve the existing PHP-rendered pages and global
  script pattern.
- When masking monetary values, mark values with `data-balance` and let the
  global balance visibility script and CSS handle display.
- When adding categories from transaction forms, update the select immediately
  and keep existing category CRUD behavior intact.
