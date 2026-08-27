# Migration order (NovixFin v3)

> Created in Phase 2. This document explains how the v3 migration system
> works and how to apply it on a fresh install vs. an existing production DB.

## TL;DR

- Migrations live in `/migrations/` and are applied with `php scripts/migrate.php up`.
- The runner is **CLI-only** (refuses via HTTP — 403 Forbidden).
- Each migration is tracked in a new `migrations` table; already-applied ones
  are skipped.
- Three migrations ship in v3 Phase 2:
  1. `20240101000000_baseline_v3.sql` — `CREATE TABLE IF NOT EXISTS` for the
     full canonical schema. Safe on fresh (creates everything) and on existing
     (no-op for tables that already exist).
  2. `20240101000001_reconcile_v3.php` — guarded `ALTER`s that bring an
     existing/prod DB to the canonical shape. Uses `information_schema`
     probes so it never re-runs an ALTER on a column that already exists.
     **Idempotent**, safe to re-run.
  3. `20240101000002_seed_categories_v3.sql` — inserts the canonical default
     categories (income + expense global set). Each `INSERT` is guarded by a
     `WHERE NOT EXISTS` subquery so it is idempotent without relying on a
     uniqueness constraint.

## Apply steps

### Existindo banco em produção (Hostinger) — RECOMENDADO

1. **Faça um backup do banco de dados** via phpMyAdmin → Exportar (full SQL).
   Guarde o `.sql` em local seguro.
2. No terminal do servidor (ou via cron/SSH se o plano Hostinger permitir):
   ```sh
   cd /home/u545165688/<raiz_do_app>
   php scripts/migrate.php status
   php scripts/migrate.php up
   php scripts/migrate.php status   # deve mostrar tudo aplicado
   ```
3. Se a versão do MySQL do Hostinger for < 8.0.29, a chamada
   `ADD COLUMN IF NOT EXISTS` usada em migrations .sql pode falhar. A migration
   `reconcile_v3.php`, porém, **não usa** essa sintaxe — ela faz o guard via
   PHP + `information_schema`, então funciona em MySQL 5.7/8.0/MariaDB sem
   restrição. Cada ALTER só roda se a coluna realmente faltar.

### Instalação nova (fresh install)

1. Crie um banco vazio.
2. Defina `.env` com as credenciais do novo banco.
3. Rode:
   ```sh
   php scripts/migrate.php up
   ```
   A `baseline_v3.sql` cria todas as tabelas, a `reconcile_v3.php` não precisa
   ALTERar nada (tudo já nasce no formato canônico — cada probe retorna "já
   existe" e pula), e a `seed_categories_v3.sql` popula as categorias padrão.

## What the reconcile migration changes (decisions baked in)

| Element | v2 (live state) | v3 (canonical) | Mechanism |
|---|---|---|---|
| `categories.user_id` | runtime `ALTER` only on demand | adds the column if missing | guarded ALTER; stays **nullable** so legacy global categories survive |
| `goals.title` | legacy `name` only | `title` is canonical; `name` copied to `title`, legacy `name` kept for now | guarded ADD `title`; backfill `title = name`; `name` NOT dropped in Phase 2 (Phase 3 sanitises reads) |
| `goals.type` enum | `savings`/`expense_limit`/`debt_payoff` (v1) OR mixed (v2 staged) | `saving`/`spending_limit`/`income_target`/`balance_target`/`expense_limit` | map `savings`→`saving`, `debt_payoff`→`expense_limit`; MODIFY enum |
| `transactions.currency` | nullable in some installs; default BRL | `NOT NULL DEFAULT 'BRL'` (ENUM) | backfill NULLs to BRL first, then MODIFY NOT NULL |
| `credit_card_bills.currency` | VARCHAR(3) | ENUM('BRL','USD','EUR') DEFAULT 'BRL' | backfill bad values to BRL first, then MODIFY |
| `accounts.balance` vs `initial_balance` | diverged between `add_accounts.sql` (balance) and the archived `update-accounts.sql` (initial_balance) | `balance` is canonical | if `balance` missing AND `initial_balance` present → RENAME COLUMN |
| indexes/FKs | inconsistent across installs | all canonical indexes added if missing | guarded `ADD INDEX` |
| FKs for `goals.account_id` / `goals.goal_category_id` | none (just nullable ints) | **left as-is** in Phase 2 (adding FKs against existing data risks orphan-row failures); a later migration can add them after data audit |

## What the reconcile migration intentionally DOES NOT do (deferred)

- **DROP `goals.name`**: kept during v3 to avoid breaking code that reads
  `name`. Phase 3 sanitises those reads; the DROP happens after that in a
  later migration.
- **`categories.user_id` NOT NULL**: still nullable so legacy global
  categories survive. A future migration will backfill orphan globals to a
  designated system user and then tighten the column.
- **Remove runtime DDL from action handlers**: the action handlers still
  have `CREATE TABLE IF NOT EXISTS wallets/wallet_accounts/
  credit_card_transaction_settings` and `ALTER TABLE categories ADD user_id`.
  Once migrations have been applied, those statements become harmless
  no-ops. Removing the redundant runtime DDL is scheduled for Phase 3 cleanup.

## Migration 4 (validation-W2 patch): `20240101000004_add_login_attempts_v3.sql`

Adds the `login_attempts` table backing the server-side login/register
throttle (`includes/throttle.php`). It can be applied independently; no
foreign keys to other tables. If missing, the throttle functions
fail-open (login proceeds, with `error_log`) — safe deploy-order behaviour.

Columns: `id INT PK`, `identifier CHAR(64)` (sha256 hex), `ip VARCHAR(45)`
(audit), `attempted_at TIMESTAMP`. Indexes: `(identifier, attempted_at)`
for the count query, `(ip, attempted_at)` for IP-side audit, and
`(attempted_at)` for the 24h GC done by `is_throttled()`.

## Old snapshot migrations (`database/*.sql`)

The historical migration files in `/database/` (schema.sql + ~12 ALTER scripts
left in place during v3) are kept as **read-only historical reference**. They
are NOT what the runner consumes; do not rely on them. The v3 canonical view
lives only in `/migrations/`.

## Rollback

v3 does **not** ship automated down-migrations. To roll back Phase 2:

1. Restore the DB dump you took before applying (phpMyAdmin → Importar).
2. Optionally `TRUNCATE TABLE migrations;` to re-run from scratch.
3. The Phase 2 code (just `scripts/migrate.php` + the migration files) doesn't
   touch the application source — no code rollback needed.