# NovixFin v3.1 - Hotfix Balance Visibility, Profile Preference, Bulk Delete

Date: 2026-07-10

## Task
- Fix non-dashboard balance visibility toggle not responding reliably.
- Fix profile "Ocultar saldos por padrao" disabled state redirect/persistence flow.
- Fix "Excluir todas as transacoes" failing silently or returning to the wrong page.

## Files Changed
- `public/scripts/balance-visibility.js`
- `actions/profile/update-balance-visibility.php`
- `public/profile.php`
- `public/history.php`
- `actions/transactions/delete_all.php`
- `actions/transactions/delete_by_account.php`
- `src/Repositories/TransactionRepository.php`
- `tests/probe_batch2_features.py`
- `reports/checkpoint_hotfix_balance_visibility_profile_bulk_delete.md`
- `state/progress.md`
- `state/context.md`

## Root Cause
- The shared balance button used a one-time direct listener on `#balance-visibility-btn`. The page-level script now uses delegated clicks so authenticated non-dashboard pages get the same behavior even if the button is rendered or replaced differently.
- The profile balance preference action redirected with an absolute `/public/profile.php` path. The action now uses the existing `redirect('profile.php')` helper path, matching the other profile actions.
- The bulk transaction delete action had no exception guard and the repository `deleteAll()` was older than `deleteByAccount()`: it deleted rows without an ACID wrapper, did not reverse affected account balances, and did not clean `recurring_generated` rows for deleted recurring parents/children.

## Fix
- `balance-visibility.js` now delegates clicks from `document`, updates every matching balance toggle button, avoids `Promise.finally()`, and keeps `body[data-hide-balances-default]` aligned with the current page state.
- `update-balance-visibility.php` now redirects through `redirect('profile.php')`.
- Profile/history forms now post explicit `redirect_to` values.
- `delete_all.php` now validates redirect target, catches repository failures, flashes an error, and returns to history.
- `delete_by_account.php` now follows the same scoped redirect target.
- `TransactionRepository::deleteAll()` now runs inside a transaction, preloads effective rows and affected bills, removes matching `recurring_generated` rows before deleting transactions, reverses account balance effects for effective transactions, updates affected credit-card bill totals, and rolls back on failure.

## Validation Performed Locally
- `node --check public\scripts\balance-visibility.js` -> passed.
- `node --check public\scripts\main.js` -> passed.
- `node --check public\scripts\history.js` -> passed.
- PHP 7.4 blocker static scan -> no matches.
- Static PowerShell assertions for this hotfix -> passed.

## Validation Not Available Locally
- `php --version` -> unavailable locally.
- `python -m pytest ...` -> unavailable because `python` is not installed.
- `py -m pytest ...` -> Python 3.11 exists but `pytest` is not installed.
- `git status/diff` through the parent worktree is blocked by sandbox permissions/dubious ownership.

## Server Validation Required
```bash
php -l actions/profile/update-balance-visibility.php
php -l public/profile.php
php -l public/history.php
php -l actions/transactions/delete_all.php
php -l actions/transactions/delete_by_account.php
php -l src/Repositories/TransactionRepository.php
php tests/smoke.php
```

Manual browser validation:
- In profile, disable "Ocultar saldos por padrao"; it should return to profile and refresh with balances visible by default.
- On accounts, click "Mostrar saldos"; values should stay visible.
- On accounts, click again; values should hide.
- On history, use "Excluir todas as transacoes"; it should return to history with a success or error flash and the transaction list should reflect the delete.
- Refresh dashboard/accounts/history and confirm the saved balance preference is respected.

## Risks
- Local PHP lint and smoke could not run.
- The bulk delete repository path touches financial state, but only inside the requested destructive transaction delete operation and now matches the ACID pattern already used by `deleteByAccount()`.
- Browser validation is still required on the deployed Docker stack.

## Confidence
82% until PHP lint/smoke and manual browser validation pass on the server.
