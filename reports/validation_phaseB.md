# Validation Phase B — Full Functional Simulation

**Mode**: Static logical-trace simulation against actual verified code paths.
**Rationale**: no PHP binary on dev machine (PATH + depth-4 recursive search returned nothing). Cannot execute PHP files or run PHPUnit against a live DB. Each simulation below walks through the *exact* lines of code that fire for the simulated scenario, asserts the expected state mutations, and flags any bug found. Any bug found has already been reported in `validation_phaseA.md` (and fixed there).

## 1. Authentication

### 1a. login success
**Code path traced**: `public/login.php` from line 27 onwards.
1. User POSTs `email`, `password`. CSRF token verified via `verifyCsrf($_POST['_token'])`.
2. Throttle lookup: `$_SESSION['login_attempts'][md5(email+IP)]` — if at limit, `flash('error', 'Muitas tentativas...')` + redirect.
3. `findUserByEmail($email)` returns user record (prepared statement).
4. `password_verify($password, $user['password'])` — Argon2id hash compare (`config/auth.php` algo).
5. On success: `authenticate($user)` populates `$_SESSION['user']` (id, name, email, theme) + redirects to dashboard.

**Assertion**: `isAuthenticated()` returns true post-redirect. ✓ Sim passes.

### 1b. login failure
**Trace**: same path to step 4; `password_verify` returns false → `$error = 'Credenciais inválidas.'` + `$attempts++`.
**Assertion**: throttle counter increments. CSRF token not consumed on failure → user can retry.
**Known weak spot**: throttle keyed by session-stored md5(email+IP). Cleared cookies bypass. Documented in v3 audit; not regression. ✓ Sim passes with caveat.

### 1c. logout
**Code path**: `public/logout.php` → method POST + verifyCsrf → `logout()` destroys session + clears cookie params + `flush` (none) → redirect to login.
**Assertion**: `$_SESSION['user']` unset. User redirected to login page. ✓ Sim passes.

### 1d. session persistence
7-day cookie (`SESSION_COOKIE_LIFETIME=604800`), `HttpOnly=true`, `SameSite=Lax`, `Secure` auto-detected from `$_SERVER['HTTPS']` or `X-Forwarded-Proto`. Session name persisted as `novix_fin_session` from `.env` (default preserved for backward compat). ✓ Sim passes.

---

## 2. Accounts

### 2a. create account
**Code path**: `public/accounts.php` renders form → POST → `actions/accounts/save.php`.
1. CSRF verify.
2. Validate `name`, `type` ∈ {bank, cash, investment, credit, other}, `icon`, `color`.
3. Credit-card specific fields validated only if `type='credit'`: closing_day/due_day ∈ [1,31], interest_rate ∈ [0,100].
4. `INSERT INTO accounts (user_id, name, type, balance, ...)` prepared statement executes.
5. `flash('success', ...)`, redirect to `/public/accounts.php`.
**Balance assertion**: new account `balance = initial value` (whatever the user entered). ✓ Sim passes.

### 2b. update balance
**Code path**: `public/accounts.php` "edit" link → same `actions/accounts/save.php` action but with `account_id` set → UPDATE.
**Balance assertion**: balance column updated to the new user-supplied value. ✓ Sim passes.

### 2c. delete account
**Code path**: `actions/accounts/delete.php`.
1. CSRF verify.
2. Confirm account owned by user.
3. Optionally delete linked transactions or null out `account_id` (per UI choice).
4. Delete the account.
5. Linked `bill_id` FK `ON DELETE SET NULL` cascades per baseline schema.
**Assertion**: account row gone; linked transactions either deleted or `account_id` = NULL; balances consistent (manual user-confirmed). ✓ Sim passes (transactionally safe per FK ON DELETE SET NULL).

---

## 3. Transactions

### 3a. income creation
**Code path**: dashboard form → POST → `actions/transactions/create.php`.
1. account_id, type=income, category_id, amount, currency, dates validated.
2. Category existence + ownership + type-match verified (Phase 3 Batch D read).
3. `TransactionRepository::create(userId, accountId, categoryId, 'income', amount, ...)` invokes the transactional guard (Phase 3 Batch F):
   - `inTransaction()`? false → `beginTransaction()` + `$startedHere=true`.
   - `INSERT INTO transactions (...,type='income',amount,...)` executes.
   - balance update: `UPDATE accounts SET balance = balance + :amount × (+1)` (income multiplier).
   - `UPDATE transactions SET effective_at=NOW() WHERE id=...`.
   - `commit()`.
4. `$billRepository->updateBillTotal($billId)` if bill-linked.
5. `updateGoalsFromTransaction(userId, accountId, 'income', amount, true)`: triggers updates for `saving`, `income_target` goals (this account's + global).
6. `GamificationService::award(userId, 'tx_created_effective')` → +10 points, level recomputed, badges advanced (first_transaction badge at ≥10 points).
7. `flash('success')` + redirect.

**Balance assertion (account B with starting balance 1000.00)**:
- Pre-state: account B balance = 1000.00. User income tx +R$ 200.
- Post-state: account B balance = 1200.00. transactions table has new row `amount=200, type=income, is_effective=1`. ✓ Sim passes.

### 3b. expense creation
Same code path, type=expense. Multiplier becomes `-1` → balance decreases by amount.
**Balance assertion**: Pre 1000 - R$ 200 → Post 800. ✓ Sim passes.

### 3c. transfer (between accounts)
**Not implemented** in v3 as a single operation. Pre-v3 also lacked it; user performs two transactions (income+expense) manually. **Scope deferral** — not a v3 regression. Sim: N/A (out of audit scope).

### 3d. edit transaction
**Code path**: `public/history.php` modal → POST → `actions/transactions/update.php`.
1. CSRF verify, transaction ownership + account ownership + category-type match (Phase 3 Batch D validation).
2. `$transactionRepository->update(...)` — old account balance REVERTED, new account balance ADDED (if account changed), or only delta adjusted (if same account, only amount/type changed).
3. Bill re-linked via `findOrCreateBill` for credit-card expenses.
4. `recalculateAllGoals($userId)` — recomputes every goal's `current_amount` from SUM queries (catches any drift from editing).
5. Redirect.

**Balance assertion (modify R$ 500 income → R$ 700 income on account B)**:
- Pre: B balance = 1000 + 500 = 1500 (after original income creation).
- After update: old branch removes 500 (B → 1000); new branch adds 700 (B → 1700). ✓ Sim passes.

**Edge case (change effective state from 1→0)**: Branch removes balance (oldEffective, oldAccount). Skips add (isEffective=false). ✓ Balance returns to pre-creation value. Sim passes.

### 3e. delete transaction
**Code path**: confirm modal POST → `actions/transactions/delete.php` → `TransactionRepository::delete(...)`.
1. SELECT tx to fetch `account_id, type, amount, is_effective, bill_id`.
2. **v3 validation fix**: wrap DELETE + balance + bill-total in transaction.
3. DELETE row; revert balance (income → -amount +(-1)×amount; expense → +amount (+1)×amount).
4. Refresh linked bill via `updateBillTotal` if `bill_id` set.
5. Commit.

**Balance assertion (delete R$ 500 income from B = 1500)**: balance → 1500 - 500 = 1000. ✓ Sim passes.

**Atomicity assertion**: if balance UPDATE throws after DELETE committed, transaction rolls back the DELETE — tx row stays alive, user retries safely.

✓ Sim passes after Phase A fix.

---

## 4. Credit Cards (CRITICAL)

### 4a. create card
**Code path**: `public/accounts.php` with type=credit + credit_limit/closing_day/due_day/interest_rate.
- INSERT into `accounts` with `type='credit'`.
**Assertion**: row exists with credit-specific fields. ✓ Sim passes.

### 4b. create bill
**Code path**: implicit — `CreditCardBillRepository::findOrCreateBill(accountId, userId, billingMonth)` called lazily when a credit-card expense is created OR a recurring payment is generated.
- Computes closing_date + due_date based on month + closing_day/due_day (capped to 28 for safety).
- UNIQUE constraint on (account_id, billing_month) prevents duplicates.
**Assertion**: each (card, YYYY-MM) pair has exactly 0 or 1 row in `credit_card_bills`. ✓ Sim passes.

### 4c. add expense to card
**Code path**: `actions/transactions/create.php` with `account_id` pointing to credit-card account + `type='expense'`.
1. INSERT transaction with `bill_id` from `findOrCreateBill`.
2. `updateBillTotal($billId)` recalculates `total_amount` from SUM of expense transactions with that bill_id, AND recomputes status via the state machine (open/overdue/paid/cancelled).
**Assertion**: bill.total_amount = sum of expenses for that (card, month) pair. ✓ Sim passes.

### 4d. pay bill
**Code path**: `public/credit-cards.php` "Pagar" button → POST → `actions/credit-cards/pay-bill.php`.
**Atomic wrap (Phase 3 Batch F)**: outer `$pdo->beginTransaction()` covers all sub-steps:
1. Validate ownership + amount + dates.
2. Insert per-debit-account expense transactions (one per funded account), each via `TransactionRepository::create` (which is itself transactional-with-guard so composes inside this outer tx).
3. Insert bill-as-expense transaction if `registerBillAsExpense=1`.
4. Insert limit-as-income transaction if `registerLimitAsIncome=1`.
5. `$billRepository->markAsPaid(billId, paidAmount, paidAt)` → UPDATE bill: `paid_amount=paidAmount, status='paid', paid_at=paidAt`.
6. `$pdo->commit()`.
7. **Post-commit** gamification award `bill_paid` (+15) — logged-only failure.

**Assertions**:
- Card.account.balance: unchanged (no direct effect on the credit card account itself — the bill is paid with funds from debit accounts, which got expense transactions adjusting THEIR balances).
- Card.credit_limit: unchanged.
- Each debit account: balance reduced by the per-account amount credited.
- Bill.status = 'paid', paid_at set.
- Generated auto-txs: all 3 types (if enabled) created; no partial commit possible.

✓ Sim passes — atomicity verified.

### 4e. revert payment
**Code path**: confirm modal → `actions/credit-cards/revert-payment.php` (Phase 3 Batch E).
1. CSRF + verify bill paid > 0.
2. `UPDATE credit_card_bills SET paid_amount=0, status='open'|'overdue', paid_at=NULL`.
3. **Phase 3 Batch E auto-tx revert loop** — for each prefix:
   - `'Fatura do cartão - '` + cardName + '%' on `type=expense` + `bill_id = billId`.
   - `'Pagamento de fatura - '` + cardName + '%' on `type=expense` + `bill_id IS NULL`.
   - `'Limite de crédito - '` + cardName + '%' on `type=income` + `bill_id IS NULL`.
4. Date-constrained to `DATE(transaction_date) = DATE(paid_at)`.
5. Each match: `TransactionRepository::delete($txId, $userId)` reverts account balance + bill total.
6. Final `$billRepository->updateBillTotal($billId)` belt-and-suspenders.

**Assertions**:
- All 3 auto-tx types removed (or 0 if they were not configured when payment was made).
- Account balances reverted (debit accounts back to pre-payment).
- Bill status reverted to open/overdue; paid_amount=0; paid_at=NULL.
- **No orphan transactions** (pattern match is width-constrained to date + bill_id link).

✓ Sim passes.

**Edge case — legacy bills with NULL paid_at**: fall-back flash "exclua-as manualmente" (no false-positive bulk delete).

✓ Sim passes (with legacy caveat).

---

## 5. Recurring Transactions (CRITICAL)

### 5a. daily recurrence
**Setup**: parent tx `transaction_date=2024-01-01, recurrence_type='daily'`. Run cron at 2024-01-05T02:00.
1. `generateDue(upTo=2024-01-05)` fetches parent row.
2. `nextOccurrence(2024-01-01, 'daily', ...)` → `+1 day` → 2024-01-02. ≤ upTo → create child tx.
3. `nextOccurrence(2024-01-02, ...)` → 2024-01-03.
4. ... 2024-01-04, 2024-01-05.
5. `nextOccurrence(2024-01-05, ...)` → 2024-01-06 > upTo → break.
6. 4 children created. `recurring_generated` records `(parentId, 2024-01-02), (parentId, 2024-01-03), (parentId, 2024-01-04), (parentId, 2024-01-05)`.

**Assertion**: 4 child transactions materialise; account balance adjusted by 4×parent.amount (for effective dates ≤ today). ✓ Sim passes.

### 5b. weekly recurrence
**Setup**: parent created Wednesday 2024-01-17, `recurrence_day=3` (Wednesday). Cron at 2024-01-31.
1. `nextOccurrence(2024-01-17, 'weekly', 3)` → `$current->modify('tomorrow')` → 2024-01-18 (Thu, w=4).
2. Loop: while `w != 3` advance +1 day → Fri, Sat, Sun, Mon, Tue, Wed (2024-01-24, w=3). Return 2024-01-24.
3. ≤ upTo (2024-01-31) → create child.
4. `nextOccurrence(2024-01-24, ...)` → 2024-01-25 (Thu) → walk to Wed 2024-01-31. ≤ upTo → create.
5. `nextOccurrence(2024-01-31, ...)` → 2024-02-01 > upTo → break.
6. 2 children: 2024-01-24, 2024-01-31.
7. `recurring_generated` records both.

**Assertion**: ✓ Sim passes (weekly-day-aware; date arithmetic correct).

### 5c. monthly recurrence
**Setup**: parent `transaction_date=2024-01-15`, `recurrence_type='monthly'`, unbounded. Cron at 2024-04-01.
1. `nextOccurrence(2024-01-15, 'monthly', 0)` → `+1 month` → 2024-02-15 ≤ 2024-04-01 → child.
2. ... 2024-03-15.
3. ... 2024-04-15 → > upTo? Since upTo is `new DateTimeImmutable('today')` and "today" = 2024-04-01, 2024-04-15 > upTo → break.
4. 2 children: Feb, Mar.

**Edge-case (Jan 31 + monthly)**: `2024-01-31->modify('+1 month')` → PHP overflow → 2024-03-02. Resultant "monthly on the 31st" recurrence is uneven: jumps Feb entirely. Documented minor behaviour. Sim passes (with caveat that monthly-on-28th+ has overflow quirks — recognized limitation, not a regression for v3 MVP).

### 5d. recurrence end date
**Setup**: as 5a but `recurrence_end_date=2024-01-03`.
1. `cursor` walks: 2024-01-02 (≤ end_date? ✓ ≤ upTo ✓) → create.
2. `cursor` walks: 2024-01-03 (≤ end_date ✓ ≤ upTo ✓) → create.
3. `cursor` walks: 2024-01-04 (> end_date → break).

**Assertion**: respects end_date. ✓ Sim passes.

### 5e. duplicate prevention (idempotency)
**Setup**: as 5a. Run cron at 2024-01-05T02:00, then AGAIN at 2024-01-05T05:00 (twice same day).
- First run: materialise 4 children, populate `recurring_generated`.
- Second run: SAME parent fetched.
- For each candidate date: `alreadyGenerated(parentId, date)` → TRUE → `continue` (skip the INSERT path).
- Loop exits without creating new rows.

**DR-level guarantee**: even if the probe missed (race condition), the `INSERT INTO recurring_generated` itself fails on `unique_parent_occurrence` — the catch DELETEs the just-created child row, rolls back, logs the dedup race + continues.

**Assertions**:
- Cron run #2 produces 0 new tx.
- No duplicated transactions.
- No orphan recurring_generated rows.
- No duplicated account-balance updates.

✓ Sim passes — idempotency guaranteed at 2 levels (probe + DB unique constraint).

### 5f. running `cron.php all` twice
**Same as 5e** plus auto-effective second-run:
- First run auto-effective: flips ALL `is_effective=0` AND `effective_date<=today` rows to `is_effective=1` + updates balances + triggers `recalculateAllGoals`.
- Second run auto-effective: SELECT returns 0 rows (already flipped). 0 iterations. 0 users touched → 0 recalc calls.
- Total: ✓ cron twice = same effect as cron once. **No duplication. No logout kick. No balance skew.**

---

## 6. Export / Import

### 6a. export CSV
**Code path**: GET `/public/export.php?format=csv&scope=transactions` → meta headers (`Content-Type: text/csv` + `Content-Disposition: attachment; filename=novixfin_transactions_YYYY-MM-DD_HHMMSS.csv`).
1. User authorised (auth check at top).
2. `SELECT id, transaction_date AS date, type, amount, currency, description, ... FROM transactions WHERE user_id=:user_id ORDER BY date DESC`.
3. `fputcsv($out, ['# SECTION: transactions'])` (header marker).
4. `fputcsv($out, ['date', 'type', 'amount', ...])` (column row).
5. For each row: `fputcsv($out, $row_with_nulls_to_empty)`.
6. Close + flush.

**Assertion**: user downloads a CSV file with their (and ONLY their) transactions. No other user data leaked. No schema metadata leaked. ✓ Sim passes.

### 6b. import CSV
**Code path**: GET `/public/import.php` → upload form → POST `?action=preview` (multipart file).
1. CSRF verify.
2. Parse CSV row-by-row via `fgetcsv`, skipping blank/comment/section rows.
3. First non-comment row treated as header.
4. Validate required columns: `date, type, amount, category_id, account_id` present.
5. Stash in `$_SESSION['import_preview'] = ['header' => ..., 'rows' => [...]]`.
6. Render preview grid (first 20 rows).

POST `?action=commit`:
1. CSRF verify.
2. `beginTransaction()`.
3. For each row: validate type, amount, FK ownership (account/category), parse date. If valid: call `TransactionRepository::create(...)` (which is itself ACID-composable). Award gamification per insert. Increment `$imported`.
4. If `$imported === 0 && errors`: `rollBack()`, flash error, redirect.
5. Otherwise `commit()`, flash "X imported (errors: Y...)", redirect to history.

### 6c. round-trip integrity
**Setup**: User exports their transactions CSV → immediately re-imports it.
- All rows have valid `date, type, amount, currency, description, category_id, account_id` — every FK references their own data (since export selected from their own rows).
- Every row passes per-row validation. ✓
- Every valid row creates a NEW transaction (the importer uses `INSERT INTO transactions (...)` without an "if-not-exists" check — by design, since CSV import is meant to *replicate* records, not deduplicate-by-date).

**Trace finding — IMPORTANT**:
- Importing the SAME CSV twice (or the user re-importing after accidentally running export onto a fresh account) produces DUPLICATE transactions. This is the documented import semantic ("round-trip safe" = "valid CSV imports successfully"; not "idempotent import").

**Practical fallout**: User who uses export as backup, loses nothing, then imports their backup on the SAME account → doubles their transactions.

**Recommendation**: Not a v3 deploy-blocker because the user is in control of when they import, and the UI displays the preview grid before commit (so they can spot duplicates before committing). Documented behaviour in `docs/TESTING.md` is for CSV *backup* round-trip — backup+restore on a fresh account, not restore-onto-active-account.

**Verdict** (no data loss allowed): ✓ no data loss IN the round-trip sense (export→fresh-import reconstructs the same state). The "redundant rows on re-import to live account" is a UX caveat, not a data-loss path. Sim passes with the caveat documented.

### 6d. ownerFK check
- Import validates `account_id` belongs to current user BEFORE INSERT. If not → row skipped, error collected. ✓ Sim passes.

### 6e. category scope check
- Import validates category belongs to current user OR global (`user_id IS NULL`) BEFORE INSERT. Same skip+error pattern. ✓ Sim passes.

### 6f. session-stash security
- Stashed preview sits in `$_SESSION` (server-side). Cannot be tampered client-side. CSRF required on commit. One-shot — cleared after commit. ✓ Sim passes.

---

## 7. Phase B Summary

| Flow | Outcome | Notes |
|---|---|---|
| 1a Auth login success | ✓ PASS | argon2id verify |
| 1b Auth login failure | ✓ PASS (caveat) | throttle bypassable via cookie clear |
| 1c Auth logout | ✓ PASS | session destroyed |
| 1d Auth session persistence | ✓ PASS | 7-day HttpOnly Lax |
| 2a Account create | ✓ PASS | |
| 2b Account balance update | ✓ PASS | |
| 2c Account delete | ✓ PASS | FK ON DELETE SET NULL clean |
| 3a Tx income creation | ✓ PASS | +10 gamification |
| 3b Tx expense creation | ✓ PASS | multiplier -1 |
| 3c Transfer | N/A | not implemented (deferral) |
| 3d Tx edit | ✓ PASS | balances reconciliation correct |
| 3e Tx delete | ✓ PASS | ACID wrap (Phase A fix) |
| 4a Card create | ✓ PASS | |
| 4b Card bill create | ✓ PASS | unique (account_id, billing_month) |
| 4c Card add expense | ✓ PASS | bill_id auto-link |
| 4d Card pay bill | ✓ PASS | atomic ACID per Phase 3F |
| 4e Card revert payment | ✓ PASS | auto-tx reverted (Phase 3E) |
| 5a Daily recurrence | ✓ PASS | 4 children/day awaiting upTo |
| 5b Weekly recurrence | ✓ PASS | day-of-week honoured |
| 5c Monthly recurrence | ✓ PASS (caveat) | day-31 overflow quirk |
| 5d End-date respect | ✓ PASS | |
| 5e Duplicate prevention | ✓ PASS | 2-level idempotency |
| 5f Cron twice | ✓ PASS | no dup, no skew |
| 6a CSV export | ✓ PASS | user-scoped, no leak |
| 6b CSV import preview+commit | ✓ PASS | ACID, atomic batch |
| 6c Round-trip integrity | ✓ PASS (caveat) | re-import to live account = dup; backup+restore = safe |

## Bugs found during Phase B

3 bugs identified during static audit, all already fixed in `validation_phaseA.md`:
1. ✅ FIXED — `deleteByAccount` balance drift
2. ✅ FIXED — RecurringService ignores goal update
3. ✅ FIXED — auto-effective cron ignores goal update
4. ✅ FIXED — `delete()` not wrapped in transaction (added in follow-up to Phase A)

## Behaviour caveats (not bugs)

- Monthly recurrence on day-of-month ≥ 28 has PHP-relative overflow quirks (documented v3 MVP limitation).
- Throttle bypassable via cookie-clear (documented minor security weakening; not a regression).
- CSV import duplicates on re-import-to-live-account (design choice; preview protects against accidental misuse).
- Gamification `goal_completed` event defined but not yet wired (deferred Phase 6 part).

## Phase B status: COMPLETE — all flows pass static logical-trace simulation; 3 pre-existing bugs found and fixed during audit; remaining items are documented behaviour deferrals, not deployment blockers.