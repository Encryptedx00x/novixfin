# NovixFin v3.1 - Hotfix PHP 7.4 + Balance Visibility

Date: 2026-07-04

## Task
- Stop roadmap work and ship an urgent production hotfix for Hostinger CLI PHP 7.4.33 compatibility.
- Fix the balance visibility regression where real values and dot placeholders could appear together, or toggles could visually re-hide.

## Files Changed
- `AGENTS.md`
- `includes/env.php`
- `public/goals.php`
- `public/history.php`
- `public/dashboard.php`
- `public/styles/main.css`
- `public/scripts/balance-visibility.js`
- `actions/transactions/create.php`
- `actions/goals/save.php`
- `actions/credit-cards/save-transaction-settings.php`
- `actions/credit-cards/pay-bill.php`
- `src/Repositories/UserRepository.php`
- `src/Repositories/CurrencySettingsRepository.php`
- `src/Repositories/TransactionRepository.php`
- `src/Repositories/CreditCardBillRepository.php`
- `src/Services/GamificationService.php`
- `src/Services/RecurringService.php`
- `tests/smoke.php`
- `tests/probe_batch2_features.py`
- `state/progress.md`
- `state/context.md`
- `reports/checkpoint_hotfix_php74_balance_visibility.md`

## PHP 7.4 Syntax Issues Fixed
- Fixed every active variableless catch found by scan:
  - `public/goals.php`
  - `public/history.php`
  - `public/dashboard.php`
  - `actions/transactions/create.php`
  - `actions/goals/save.php`
  - `actions/credit-cards/save-transaction-settings.php`
  - `actions/credit-cards/pay-bill.php`
  - `src/Repositories/CreditCardBillRepository.php`
- Replaced PHP 8 constructor property promotion with explicit PHP 7.4-compatible typed properties and constructor assignments in:
  - `src/Repositories/UserRepository.php`
  - `src/Repositories/CurrencySettingsRepository.php`
  - `src/Repositories/TransactionRepository.php`
  - `src/Repositories/CreditCardBillRepository.php`
  - `src/Services/GamificationService.php`
  - `src/Services/RecurringService.php`
- Removed PHP 8 `mixed` type declarations from:
  - `includes/env.php`
  - `src/Services/GamificationService.php`
- Replaced PHP 8 `str_contains()` in `tests/smoke.php` with PHP 7.4-compatible `strpos()` checks.
- Added static regression probes for active PHP 7.4 blockers:
  - variableless catch
  - constructor property promotion
  - `mixed`
  - nullsafe operator
  - `match`
  - `str_contains`

## Balance Visibility Root Cause
- The previous implementation used CSS pseudo-elements (`[data-balance]::after`) to draw dot placeholders over the original text while leaving the original monetary value in the DOM.
- Theme color overrides could keep the real value visible while the pseudo-element also rendered dots, especially in light/black theme combinations.
- The JS only toggled `body.hide-balances`; it did not replace text, so it could not guarantee "only dots" or "only values."

## Balance Visibility Fix
- `balance-visibility.js` now stores the original text in `data-balance-value`.
- Hidden state replaces each `[data-balance]` element's `textContent` with `••••`.
- Visible state restores the original value from `data-balance-value`.
- Dynamic `[data-balance]` nodes are handled through `MutationObserver`.
- CSS pseudo masking is retained only as a first-paint fallback before JS marks `body.balance-visibility-ready`.
- No `localStorage` balance preference is used; server/session remains the source of truth.

## Validation Performed
- `node --check public\scripts\balance-visibility.js` -> passed.
- `node --check public\scripts\dashboard.js` -> passed.
- `node --check public\scripts\kanban.js` -> passed.
- `node --check public\scripts\forecast-chart.js` -> passed.
- `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 7 passed.
- Static scans found no active matches for:
  - variableless `catch (Exception|Throwable|PDOException)`
  - constructor property promotion
  - `mixed` type declarations
  - nullsafe operator
  - `match`
  - `str_contains`

## Hostinger Validation Still Required
```bash
php -l public/dashboard.php
php -l actions/transactions/create.php
php -l public/history.php
php -l public/goals.php
php -l actions/goals/save.php
php -l actions/credit-cards/save-transaction-settings.php
php -l actions/credit-cards/pay-bill.php
php -l src/Repositories/CreditCardBillRepository.php
php tests/smoke.php
```

## Manual Browser Validation Still Required
- Light theme: hidden shows only dots; visible shows only values; no overlap.
- Dark/blue theme: clicking "Mostrar saldos" keeps values visible; no re-hide.
- Black theme: no duplicate values plus dots.
- Refresh respects the saved preference from the server/session.

## Risks
- Local PHP is unavailable, so PHP parse validation must run on Hostinger.
- `src/Services/RecurringService.php` and `src/Repositories/TransactionRepository.php` were touched only to remove PHP 8 constructor promotion. No recurrence or balance logic was changed.
- First-paint masking still relies on the server-rendered `body.hide-balances` class until JS runs.

## Confidence
88% until Hostinger `php -l` and browser validation are completed.
