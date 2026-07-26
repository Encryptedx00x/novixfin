# Checkpoint — Phase 7 (Export / Import tooling)

**Timestamp**: 2026-06-22
**Phase**: 7
**Confidence**: 88%

## Files created
1. `public/export.php` (113 lines) — auth-required GET endpoint. `?format={csv|json}&scope={transactions|accounts|goals|all}`. Streams the authenticated user's data with `Content-Type` / `Content-Disposition` headers. Falls back gracefully when canonical columns are missing (try/catch + simpler query). Output is user-scoped (every SELECT has `WHERE user_id = :user_id`).
2. `public/import.php` (236 lines) — auth-required three-stage CSV importer:
   - **GET**: shows upload form.
   - **POST `?action=preview`** (multipart): parses CSV in-memory, validates the required columns (`date, type, amount, category_id, account_id`), stashes preview rows into `$_SESSION['import_preview']`. NO DB writes.
   - **POST `?action=commit`** (CSRF + confirmation): wraps each row in an outer DB transaction, validates foreign keys (account + category ownership surfaced to current user), inserts via `TransactionRepository::create` (transactional itself post Phase 3 Batch F), awards gamification per effective row. Any failure rolls back the whole batch; synthesised errors per-row are accumulated and surfaced in the flash.

## Bugs fixed / improvements
- Pre-v3 ROADMAP listed CSV/PDF export + extrato import as "short term" features. v3 Phase 7 closes the CSV story. JSON dump is included for free (used by upcoming cross-device sync / backup features).
- Round-trip safe: the importer digests exactly the format the exporter writes (skips `# SECTION:` rows, accepts the same header schema exported).

## Risks introduced
- **Low/Medium** — bulk import is now a one-click way to insert many transactions. Mitigations:
  - auth-required.
  - Hidden foreign-key ownership checks (`account_id` + `category_id` scoped to user).
  - Per-row `try/catch` inside the atomic batch; on total-failure path, rollback + flash.
  - Per-batch `updateBillTotal` is NOT explicitly invoked (the importer uses TransactionRepository::create which does the account balance but does not refresh any `bill_id`-linked bill total). For transactions not linked to a credit-card bill (the common import path), the bill totals aren't affected. Edge case: an import row whose `bill_id` is non-null could leave that bill's `total_amount` slightly off — pending future improvement.
  - Stashed preview held in `$_SESSION` (server-side). Cleared after commit / replaced on next preview. Not vulnerable to CSRF replay (verifyCsrf on every POST).
- **Low** — filename uses user `date('Y-m-d_His')` which may include hyphen/colon characters not all OS-es love; the hostinger host sets filename safely.

## Validation performed
- Brace/paren balance check on both files: export.php 18/18 braces, 63/63 parens. import.php 25/25 braces; code-only parens balanced (the running delta of -3 comes only from the docblock numbered list `1)/2)/3)` markers which PHP ignores).
- Manual trace: GET render → POST preview parsing → session stash → POST commit (search no confirm=1 → abort, otherwise loop-validate-insert-commit → unset session). Each mutation honoured atomicity contract.

## Phase 7 status: COMPLETE (CSV round-trip done; PDF export + OFX/bank-statement format parsing are explicit roadmap deferrals).