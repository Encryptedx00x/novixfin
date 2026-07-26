# NovixFin v3.1 - Hotfix Smoke, Balance Toggle, Sidebar

Date: 2026-07-04

## Task
- Continue the focused production hotfix after PHP 7.4 parse errors were fixed.
- Make `tests/smoke.php` compatible with Hostinger CLI PHP 7.4.33 and prevent the throttle smoke checks from fataling.
- Fix the balance visibility toggle re-hiding immediately after "Mostrar saldos".
- Fix sidebar footer alignment for the collapse button, username row, and logout button.

## Files Changed
- `tests/smoke.php`
- `tests/probe_batch2_features.py`
- `public/scripts/balance-visibility.js`
- `public/styles/main.css`
- `includes/layout/header.php`
- `reports/checkpoint_hotfix_smoke_balance_sidebar.md`
- `state/progress.md`
- `state/context.md`

## Root Causes
- Smoke fatal: `tests/smoke.php` checked throttle functions without explicitly requiring `includes/throttle.php`, then called `throttle_identifier()` even when the callable checks had failed.
- Smoke compatibility warning: `tests/smoke.php` still used the invalid/manual-patched `ReflectionClass::exists()` call shape and string-cast a `ReflectionType`, which is deprecated under PHP 7.4 error reporting.
- Balance flicker: the previous balance script had a click-time rollback path that could restore the previous hidden state after an async persistence failure. It also allowed the mutation observer to keep reapplying the old state.
- Sidebar alignment: the footer/user/logout area lacked scoped layout hooks, the logout form used inline layout, and collapsed-sidebar rules could leave the collapse control and logout row fighting broad sidebar styles.

## Summary
- Replaced `ReflectionClass::exists()` smoke checks with `class_exists()`.
- Replaced string-casting of the InsightService return type with `ReflectionNamedType::getName()`.
- Required `includes/throttle.php` before W2 throttle checks.
- Added a `$hasThrottleFunctions` guard so missing throttle functions are reported as failures without a fatal call.
- Reworked `balance-visibility.js` around one `currentHidden` state and one `setHidden(hidden)` path:
  - initial state is read once from `body[data-hide-balances-default]`
  - hidden state replaces `[data-balance]` text with dots
  - visible state restores `data-balance-value`
  - click handlers do not re-read the body dataset
  - AJAX persistence failures only `console.warn`; they do not roll back UI
- Added sidebar footer hooks in `includes/layout/header.php` and scoped CSS for username/logout/collapse alignment.
- Added static regression probes for the smoke, balance-toggle, and sidebar-layout contracts.

## Validation Performed
- `node --check public\scripts\balance-visibility.js` -> passed.
- `node --check public\scripts\main.js` -> passed.
- `node --check public\scripts\dashboard.js` -> passed.
- `node --check public\scripts\kanban.js` -> passed.
- `node --check public\scripts\forecast-chart.js` -> passed.
- `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 10 passed.
- Static PHP 7.4 blocker scan found no active matches for:
  - variableless `catch (Exception|Throwable|PDOException)`
  - nullsafe operator
  - `match`
  - constructor property promotion
  - `mixed` declarations
  - `str_contains`
- Balance script include scan found one global include in `includes/layout/footer.php` and one click listener in `public/scripts/balance-visibility.js`.

## Hostinger Validation Still Required
```bash
php -l tests/smoke.php
php -l public/dashboard.php
php -l includes/layout/header.php
php -l includes/throttle.php
php -l src/Services/RecurringService.php
php -l src/Repositories/TransactionRepository.php
php -d display_errors=1 -d error_reporting=E_ALL tests/smoke.php
```

## Manual Browser Validation Still Required
- Light theme: click "Mostrar saldos"; values stay visible; click again; values hide; no overlap.
- Dark/blue theme: same behavior; no flash back to hidden.
- Black theme: same behavior; no duplicate value/dots.
- Refresh: saved server preference is respected.
- Sidebar: username row, logout row, and collapse button are aligned; collapsed sidebar still works.

## Risks
- Local PHP is unavailable, so PHP lint and the actual smoke script still need Hostinger confirmation.
- Manual browser validation is still required because the local environment did not run the PHP app.
- Sidebar changes are scoped, but visual verification across all pages should still be done after deploy.

## Confidence
91% until Hostinger PHP lint/smoke and browser validation are completed.
