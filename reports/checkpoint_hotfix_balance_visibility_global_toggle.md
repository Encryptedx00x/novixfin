# NovixFin v3.1 - Hotfix Balance Visibility Global Toggle

Date: 2026-07-10

## Task
- Fix balance visibility preference not respecting disabled `hide_balances`.
- Make the show/hide balances toggle available outside the dashboard.
- Preserve the DB-backed privacy model and avoid touching financial logic, roadmap features, credentials, deployment config, or cron behavior.

## Files Changed
- `includes/layout/header.php`
- `includes/layout/footer.php`
- `public/profile.php`
- `tests/probe_batch2_features.py`
- `reports/checkpoint_hotfix_balance_visibility_global_toggle.md`
- `state/progress.md`
- `state/context.md`

## Root Cause
- `includes/layout/header.php` rendered `body.hide-balances` from `$_SESSION['user']['hide_balances'] ?? true`.
- If a session was created before the field existed, or if the session was stale/incomplete, the layout fell back to `true` even when `users.hide_balances = 0`.
- `public/scripts/balance-visibility.js` was loaded globally and masked `[data-balance]` values across pages, but the actual toggle button was rendered only in `public/dashboard.php`.
- Pages like accounts, history, and credit cards could therefore be masked with no visible way to show balances.

## Fix
- `includes/layout/header.php` now fetches the current user when the page did not pass `$user`.
- The header now prefers `$user['hide_balances']` from the database and syncs that value into `$_SESSION['user']['hide_balances']`.
- The balance visibility button is rendered by the shared layout on authenticated non-dashboard pages.
- Dashboard keeps its existing button, and the shared layout skips dashboard to avoid duplicate element ids.
- `public/profile.php` now posts an explicit hidden `hide_balances=0` value when the checkbox is disabled.
- `includes/layout/footer.php` comment now reflects that the toggle is shared globally.
- `tests/probe_batch2_features.py` was extended to cover the DB/session sync and non-dashboard global toggle.

## Validation Performed Locally
- `node --check public\scripts\balance-visibility.js` -> passed.
- `node --check public\scripts\main.js` -> passed.
- `node --check public\scripts\dashboard.js` -> passed.
- `node --check public\scripts\history.js` -> passed.
- PHP 7.4 blocker static scan -> no matches.
- Static PowerShell assertions for the new balance visibility invariants -> passed.

## Validation Not Available Locally
- `php --version` -> unavailable locally.
- `python -m pytest ...` -> unavailable in this sandbox.
- `py -m pytest tests\probe_batch2_features.py` resolved to Python 3.11 without pytest.
- `py -3.13 -m pytest tests\probe_batch2_features.py` was blocked by WindowsApps access.

## Server Validation Required
```bash
php -l includes/layout/header.php
php -l includes/layout/footer.php
php -l public/profile.php
php tests/smoke.php
```

Manual browser validation:
- Disable "Ocultar saldos por padrão" in profile.
- Refresh dashboard, accounts, history, and credit cards: values should be visible by default.
- Enable the setting again: values should be hidden on first paint.
- Use the show/hide button on dashboard and non-dashboard pages.

## Risks
- Local PHP lint and PHP smoke could not run.
- Python probes could not run in this sandbox, but equivalent static assertions were executed with PowerShell.
- The shared header now performs a lightweight user fetch when a page did not already pass `$user`.

## Confidence
88% until PHP lint/smoke and manual browser validation pass on the deployed environment.
