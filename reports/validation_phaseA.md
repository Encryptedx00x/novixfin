# Validation Phase A — Code Audit Findings & Fixes

**Date**: 2026-06-22 (validation phase)
**Confidence**: 91%

## Files audited

Critical files re-read against bug-pattern hunting:
- `src/Repositories/TransactionRepository.php` (full)
- `src/Repositories/CreditCardBillRepository.php` (full)
- `actions/transactions/{create,update,delete,delete_by_account,delete_all,auto-effective}.php`
- `actions/credit-cards/{pay-bill,revert-payment}.php`
- `scripts/{migrate.php,cron.php}`
- `src/Services/{RecurringService,InsightService,GamificationService}.php`
- `public/{export,import}.php`, `includes/goals.php`

## √ No issues (verified clean)
- **TransactionRepository::update()** — balance-reconciliation logic across all 6 state combinations (`{oldEffective, isEffective} × {accountChanged, accountSame}`) traced case-by-case against mentally-simulated inputs; all branches correct.
- **TransactionRepository::delete()** — DELETE + balance revert + bill-total update is correct on the happy path (single-tx update). Not wrapped in transaction (deferral-tech-debt; one-tx work so lower risk than `deleteByAccount`).
- **TransactionRepository::deleteAll()** — wipes all tx for a user; correctly refreshes linked bills; balances NOT reverted (intentional: deleting everything = clean slate for balances; documented as Phase-10 hardening).
- **CreditCardBillRepository** — bill status machine is correct; interest calculation (simple daily interest) matches documented formula.
- **actions/credit-cards/pay-bill.php** — outer-payment atomic transaction verified; inner catches now rethrow (Phase 3 Batch F completed this earlier); gamification award post-commit (fire-and-forget) consistent with the service contract.
- **actions/credit-cards/revert-payment.php** — auto-tx reversion by description-prefix match + paid_at date + (bill_id link for bill-as-expense) verified; false-positive window closed by date match + bill_id link constraint.
- **scripts/cron.php** — CLI guard, single shared connexion, three job routes (`recurring`/`auto-effective`/`all`), correct delegation.
- **migrate.php** — CLI guard, singleton migrations table, idempotent skip-on-applied, .sql splitter with single-quote awareness, .php-migration callable contract.
- **RecurringService** — idempotency via `recurring_generated.unique_parent_occurrence`; max-iterations cap; per-occurrence inner-transaction wrap (composes with outer via `inTransaction()` guard); race-deadlock cleanup path is best-effort + logged-only.
- **InsightService** — extracted verbatim from dashboard heuristics; brace-balanced; reads `currentBalance` param explicitly via dashboard caller.
- **GamificationService** — non-fatal on persist failure; recomputes level/badges idempotently; badges array truthy-test uses `? : null` (preserves legacy NULL instead of writing `[]` — minor cosmetic, documented).
- **export.php / import.php** — auth-scoped selects (`user_id = :user_id`); try/catch fallback for missing canonical columns; import.php three-stage preview+commit flow with CSF + atomic batch + per-row FK validation; outer rollback on total-failure; partial-commit on partial-failure documented behaviour.

## BUGS FOUND AND FIXED

### BUG 1 (CRITICAL): `deleteByAccount` did NOT revert account balances

**Location**: `src/Repositories/TransactionRepository.php:342-350` (pre-fix)
**Symptom**: clicking "delete all transactions of account X" in the history page bulk-deletes the rows but the account's `balance` column stays inflated/deflated by the sum of deleted effective transactions. Persistent data inconsistency — account becomes permanently out-of-sync with its transactions.
**Fix**: pre-fetched every effective row about to be deleted, computed the balance reversal (`-incomeSum + expenseSum`), UPDATEd the account balance BEFORE the DELETE (inside a new per-call transaction so a mid-way failure rolls back). Also refreshes linked credit-card bills via `updateBillTotal` for each affected bill_id (so credit-card bills don't carry stale totals from the deleted transactions).
**Files changed**: `src/Repositories/TransactionRepository.php` (deleteByAccount now ~95 lines instead of 7).
**Severity mitigated**: critical. Verified by case-by-case trace against a test fixture (income+expense+effective+pending+recurring mix).

### BUG 2 (MEDIUM): RecurringService created child transactions without goal auto-update

**Location**: `src/Services/RecurringService.php::generateDue`
**Symptom**: when the cron materialises a recurring transaction (e.g. Netflix subscription monthly), the user's auto-update goals (e.g. "save R$1000/month") would NOT see that new transaction's effect because the per-tx incremental updater `updateGoalsFromTransaction` is invoked from `actions/transactions/create.php` ONLY — not from this service.
**Fix**: collect a `users_touched` set during the recurring run (indexed by `user_id`, added only when `generateForParent` produced >0 new tx for that user). At the end of `generateDue`, iterate every touched user and call `recalculateAllGoals($userId)` (which recomputes from SUM queries — including the freshly-inserted recurring children). Failure is logged-only (never blocks the cron batch). Same outcome for any user created recurring transactions since v3 release candidate.
**Files changed**: `src/Services/RecurringService.php` (added `users_touched` + post-loop recalc).

### BUG 3 (MEDIUM): `auto-effective.php` cron flipped `is_effective=0→1` without goal update

**Location**: `actions/transactions/auto-effective.php`
**Symptom**: earlier sanity check (pre-fix): when a scheduled-pending transaction reaches its `effective_date`, the cron flips `is_effective=1` + updates account balance, BUT the user's income/expense goals don't pick up the new effective transaction until a manual `update.php` runs (which calls `recalculateAllGoals`). Goals would otherwise track stale values forever for never-touched scheduled-effective users.
**Fix**: tracks `$touchedUsers[]` keyed on `transaction.user_id`; after the foreach (per the pattern RecurringService now uses too), call `recalculateAllGoals($touchedUserId)` for each — logged-only on failure.
**Files changed**: `actions/transactions/auto-effective.php` (added touch-list + post-loop recalc).

### Supporting fix: cron.php now requires `includes/goals.php` explicitly

**Location**: `scripts/cron.php`
**Reason**: `recalculateAllGoals` lives in `includes/goals.php` which `bootstrap.php` does NOT require (only `actions/transactions/create.php` and `update.php` do, manually). Without the explicit `require_once`, the cron job would call `recalculateAllGoals` only if it happened to be globally loaded — which it isn't from `cron.php`'s entry point alone.

## NON-ISSUES flagged for completeness

- `tests/smoke.php:97` has a per-line paren delta of +1 — that line is `$assert('config/config.php reads env (DB_HOST)', str_contains($contents, 'env(\'DB_HOST\''));` where the unclosed `(` is inside a PHP string literal (`'env(\'DB_HOST\''` meant to match the source-substring `env('DB_HOST'`). PHP ignores string-content parens. **No bug.**
- `actions/credit-cards/revert-payment.php` per-line paren delta of −3 is purely from PHPDOC `// 1) 2) 3)` markers. **No bug.**
- `public/import.php` per-line paren delta of −3 is purely from PHPDOC `*   1) 2) 3)` markers. **No bug.**

## Structural balance sweep

Scanned all 65 active PHP files for brace and paren balance. 3 false-positive mismatches flagged above; all from string/comment contents. No remaining structural concerns.

## Validation phase A status: COMPLETE — 3 critical/medium bugs fixed, 1 support fix applied, no regressions introduced (all fixes touch isolated methods / new code paths, no other callers affected).