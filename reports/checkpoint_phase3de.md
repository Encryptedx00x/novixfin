# Checkpoint — Phase 3 Batches D + E (functional bug fixes)

**Timestamp**: 2026-06-22
**Phase**: 3 (Batches D + E)
**Confidence**: 93%

## Files modified
1. `actions/transactions/update.php` (Batch D)
   - Added category-type + ownership validation parity with `create.php:60-90`:
     after the auth check, the handler now probes `categories.user_id`, SELECTs `type FROM categories WHERE id=? AND user_id=?` (or unscoped on probe-miss), errors out if the category doesn't belong to the user OR its `type` doesn't match the submitted transaction `type`. Without this, editing a transaction could move it under a category whose `type` disagreed with the transaction `type` → broken totals-by-category reports.
   - Also added account ownership validation (previously the update handler implicitly trusted the posted `account_id`).
2. `actions/credit-cards/revert-payment.php` (Batch E)
   - Replaces the previous "don't revert auto-tx, flash a warning, tell the user to delete manually" behaviour with an actual revert.
   - After the bill paid_amount/status reset, the handler:
     * Reads the card account name from `accounts.name` for `bill.account_id` scoped to the user.
     * Defines a closure `$findAndRevert($descPrefix, $type, $requiresBillLink)`.
     * Calls it three times with the canonical auto-generated description prefixes:
       1. `'Fatura do cartão - '` + cardName + '%' on `type=expense` AND `bill_id = billId` (bill-as-expense — identified by its linked bill_id which is unique to bill-as-expense only).
       2. `'Pagamento de fatura - '` + cardName + '%' on `type=expense` AND `bill_id IS NULL` (per-debit-account expense).
       3. `'Limite de crédito - '` + cardName + '%' on `type=income` AND `bill_id IS NULL` (limit-as-income).
     * Restricts all matches to `DATE(transaction_date) = DATE(bill.paid_at)` so we only revert transactions created at the exact payment moment (eliminates any cross-user or off-date false positives).
     * Calls `TransactionRepository::delete($txId, $userId)` for each match — `delete()` already reverts the account balance (income/expense multiplier) AND calls `updateBillTotal` on any linked bill — so balance reversion is automatic.
     * Refreshes `updateBillTotal` once at the end for belt-and-suspenders.
   - Backward compatibility: legacy bills whose `paid_at` is NULL (paid before this feature) fall back to the OLD behaviour (reset + manual-cleanup flash). No silent data migration, no bulk delete.
   - LIKE wildcards in the card name (`%`, `_`) are escaped with `addcslashes($cardName, '%_\\')` so user-chosen card names with `%` don't blow up the pattern match.

## Bugs fixed
- update.php silently let users move a transaction into a category whose `type` didn't match (e.g. income transaction moved under "Alimentação" expense category). Reports/totals by category were silently corrupted. Now blocked.
- update.php didn't validate account ownership — a crafted POST could move a transaction onto another user's account. Now blocked.
- revert-payment.php stopped half-way through the revert: it cleared `paid_amount` / `status` / `paid_at` but left the bill-as-expense, debit-account-expense, and limit-as-income transactions in `transactions` → orphan rows that inflated totals forever and that the user had to delete one-by-one. Now reverted automatically + balances rolled back.

## Risks introduced
- **Low/Medium** — auto-revert by description pattern.
  - False-positive risk: a user could have manually created a transaction with a description literally beginning with one of the canonical prefixes (`'Fatura do cartão - <cardName>'`, etc.) on the same date as a payment they later reverted. The unit date match (`DATE(transaction_date) = DATE(paid_at)`) plus the `bill_id` linkage dramatically narrows false positives: the bill-as-expense path also requires `bill_id = billId` which only pay-bill.php sets.
  - User create-form description is free-text — therefore a user matching `'Fatura do cartão - Meu Cartão'` exactly AND on `paid_at` date would get caught. Edge case (these specific 3 exact strings aren't typical user phrasings). Risk acceptable, and the alternative (NO revert) caused guaranteed data drift.
  - Mitigations in place: (1) literal `bill_id` constraint for category 1, (2) date match for all three, (3) per-tx try/catch so one failure doesn't abort all reverts, (4) error_log on each failure for audit trail.
- **Low** — `addcslashes($cardName, '%_\\')` second arg is PHP's escape set. The third backslash in the source is a normal PHP escape (`\\` → `\`). Verified visually.

## Rollback strategy
- Restore `actions/transactions/update.php` from `/backups/phase0/actions__transactions__update.php` (the closest pre-Phase-3 backup).
- For `revert-payment.php`: I did NOT back this file up in phase0/phase3a/phase3b (oversight — acknowledged). Use the D:\ read-only twin at `D:\[ Importante ]\[ Serviços e ferramentas ]\Novix Fin v2\actions\credit-cards\revert-payment.php` (byte-identical to the pre-v3 source). Copy from there to revert.

## Validation performed
- No PHP locally. Brace/paren balance check: update.php 37/37 braces / 125/125 parens / 237 lines (balanced). revert-payment.php 19/19 braces / 93/96 parens / 181 lines — the 3-paren delta is cosmetic (comment list markers `// 1)`, `// 2)`, `// 3)` and matching prose parens `(linked ...)` are PHP-ignored). PHP code itself is balanced.
- Manually traced the multi-statement logic: `billRepository->updateBillTotal($billId)` at the end re-derives `total_amount`/`status` for the now-reverted bill via the existing state machine in `CreditCardBillRepository`.
- Verified the closure variable `&$revertedCount` is captured by reference so the count propagates to the post-loop flash.

## Phase 3 Batches D + E status: COMPLETE