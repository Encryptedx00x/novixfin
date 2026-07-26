# Checkpoint — Phase 3 Batch B (remove runtime DDL)

**Timestamp**: 2026-06-22
**Phase**: 3 (Batch B)
**Confidence**: 92%

## Files modified
Six files; in each, the runtime `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE` block was replaced with a comment explaining the schema now lives in `/migrations/`.

1. `actions/wallets/save.php` — removed the `SHOW TABLES` + `CREATE TABLE wallets/wallet_accounts` block (the runtime version omitted FKs).
2. `public/wallets.php` — removed the same runtime CREATE-TABLE block (also FK-less in this version).
3. `actions/credit-cards/pay-bill.php` — removed `CREATE TABLE IF NOT EXISTS credit_card_transaction_settings`.
4. `actions/credit-cards/save-transaction-settings.php` — same removal.
5. `actions/categories/save.php` — removed `ALTER TABLE categories ADD COLUMN user_id` (and the inline `ADD INDEX idx_user_id`). The `SHOW COLUMNS` probe that **gates** the INSERT behaviour (i.e. still picks the user_id-aware branch) was kept — it's scheduled for Batch C removal once the canonical schema is guaranteed.
6. `public/categories.php` — same removal; same probe-keep.

## Why keeping the probes (for now)
Removing the probe means the code unconditionally reads/writes `categories.user_id`. That is safe ONLY once the migration has run on prod. To avoid a chicken-and-egg break (app crashes if the user deploys v3 code before running `migrate.php up`), the probe stays. It's a per-request `information_schema.columns` lookup — cheap — and the defensive codepath it gates still produces correct results regardless of schema state. Batch C will remove the probes once we've also cot-tags the deploy requirement (run migrations) into the app boot path. (That latter bit is a design choice deferred to Batch C.)

## Bugs fixed
- Two divergent `wallets`/`wallet_accounts` table definitions fighting the canonical FK-bearing one are now history — the migration is the single source of truth.
- Same for `credit_card_transaction_settings`.

## Risks introduced
- **Medium**: any deployment that ships v3 code WITHOUT running `php scripts/migrate.php up` first will fail on the first `wallets.save` / `credit-card.pay-bill` / `save-transaction-settings` action because the tables may not exist (depending on prior v2 state — if v2 already created them at runtime, they exist; if not, ERROR). Mitigation: deploy docs already mandate running migrations as the FIRST post-deploy step (`docs/SETUP.md` + `docs/migration-order.md`). The probe-keep for `categories.user_id` partially shields that module but not wallets/credit-card-settings.
- **Low**: action handlers preserve all behaviour except the (silently-idempotent) table-creation prelude — they are unchanged otherwise.

## Rollback strategy
- Restore any of the 6 files from /backups/phase3b/.

## Validation performed
- No PHP locally. Brace/paren balance verified for all six files (all balanced).
- The probes kept were NOT removed — only the DDL was. (Diff confirmed against original content.)

## Pending (Phase 3 remaining batches)
- Batch C: remove runtime `SHOW COLUMNS` / `SHOW TABLES` probes (after confirming deploy approach for migration-guarantee).
- Batch D: actions/transactions/update.php — add category-type validation parity with create.php.
- Batch E: actions/credit-cards/revert-payment.php — actually revert auto-generated expense/limit transactions.
- Batch F: PDO transactions in multi-statement mutations.

## Phase 3 Batch B status: COMPLETE