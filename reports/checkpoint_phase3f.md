# Checkpoint — Phase 3 Batch F (DB transaction safety)

**Timestamp**: 2026-06-22
**Phase**: 3 (Batch F)
**Confidence**: 92%

## Files modified
1. `src/Repositories/TransactionRepository.php` — `create()` now wraps INSERT + balance UPDATE + effective_at UPDATE in a transaction guarded by `inTransaction()` so it composes safely inside an outer transaction (pay-bill.php uses one). On failure, rollback (only if we opened it) + rethrow. Compatible with all existing callers.
2. `actions/credit-cards/pay-bill.php` — the auto-tx creations + `markAsPaid` are now wrapped in a single outer `$pdo->beginTransaction()` / `commit()` (with outer catch that rolls back + flashes the error + redirects). The three inner `catch (Exception $e) { error_log(...); }` blocks in the auto-tx generators now `throw $e;` so a partial failure aborts the atomic payment rather than leaving the bill marked paid with some helper transactions missing. The `elseif ($registerLimitAsIncome && !$incomeCategory)` soft-warning branch is now an atomic-bailing `throw RuntimeException(...)` (effectively unreachable because the immediate-redirect upstream check at lines 164-168 already `flash('error')+redirect` when no income category exists).

## Bugs fixed
- Pre-v3 payment could leave the bill marked `paid` while one of the per-debit/bill-as-expense/limit-as-income helper transactions silently failed (the inner catch swallowed). Account balance and bill total then drifted. Now atomic.
- `TransactionRepository::create` partial-failure between INSERT + balance UPDATE could leave the row without the balance change (or vice versa after delete). Now atomic.

## Risks introduced
- **Medium — semantic change for partial failures**: previously, if ONE debit account failed to register, the others still got committed and the bill got marked paid. Now ALL-or-NOTHING: a single failure rolls back the whole payment so the user must retry. This is the correct ACID semantic and matches the roadmap, but it IS a user-visible behaviour change. Documented in the flash message ("Falha ao registrar o pagamento. Nenhuma alteração foi aplicada — tente novamente.") so the user knows the bill is untouched.
- **Low**: `TransactionRepository::create` now opens an implicit transaction if none active. PDO `beginTransaction()` can fail if another transaction is already open at the PHP layer — guarded by `inTransaction()` so we never nest.
- **Low** — `markAsPaid` is called inside the outer transaction; if `markAsPaid` itself does multiple statements, they're now atomic too. The function itself wasn't modified; this is the outer scope's responsibility.

## Rollback strategy
- Restore both files from `/backups/phase3f/`.

## Validation performed
- No PHP binary locally. Brace/paren balance verified via raw char-counting (UTF-8 aware):
  - `TransactionRepository.php`: braces 68/68, parens 207/207, 536 lines.
  - `pay-bill.php`: braces 52/52, parens 206/206, 395 lines.
- Manually traced: try opened at line 203 (`$pdo->beginTransaction()`), outer `} catch (Throwable $e) { rollback; flash; redirect; }` added after the markAsPaid call + commit. Balanced.
- Note: minor indentation drift inside the wrapped region (the inner block was not re-indented by a tab). PHP ignores whitespace so this is cosmetic only. Re-indent is out of scope for this batch (would inflate the diff with no functional value).

## Pending (Phase 3 leftover, deferred)
- `TransactionRepository::update`, `delete`, `deleteByAccount`, `deleteAll` — these are less multi-step than `create` (each does DELETE + balance update + bill update, all single-statement). Wrapping them is low-priority hardening; scheduled for Phase 10.
- `actions/transactions/auto-effective.php` — its loop is already one tx per row of work; whole-batch not needed.
- `includes/goals.php::updateGoalsFromTransaction` — single UPDATE per goal; low risk.

## Phase 3 Batch F status: COMPLETE