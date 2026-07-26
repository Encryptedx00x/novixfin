# Checkpoint — Phase 3 Batch A (low-risk bug fixes)

**Timestamp**: 2026-06-22
**Phase**: 3 (Batch A of N)
**Confidence**: 94%

## Files modified
1. `src/Repositories/CurrencySettingsRepository.php` (saveSettings)
   - Replaced deprecated MySQL `ON DUPLICATE KEY UPDATE col = VALUES(col)` with the alias form `… AS new … ON DUPLICATE KEY UPDATE col = new.col`.
   - Compatible with MySQL 8.0.19+ and MariaDB 10.6+ (Hostinger runs 8.0+). Avoids deprecation warning and the future removed-function breakage.
2. `includes/helpers.php` (redirect())
   - Whitelist now includes the two missing pages: `wallets.php`, `credit-cards.php`. Behavior for existing pages unchanged.
3. `includes/currency.php` (formatCurrency)
   - Branch on currency: BRL keeps `,` decimal + `.` thousands; USD/EUR switch to `.` decimal + `,` thousands. Previous code formatted every currency in the pt-BR style.
4. `public/index.php`
   - Removed the broken `<img src="/public/images/dashboard-preview.png">` (no such file). Replaced with a self-contained inline HTML/SVG dashboard mockup card (no external asset dependency).
   - Fixed dead `href="roadmap.html"` link → `../docs/ROADMAP.md`.
   - Net delta: the page renders identically (hero section visual kept) but no broken asset; the roadmap link resolves to the authoritative doc.
5. `public/credit-cards.php` (the "Excluir fatura vazia" branch)
   - Line 380 used undefined `$totalAmount` / `$paidAmount`. Now reads `(float) ($bill['total_amount'] ?? 0)` / `(float) ($bill['paid_amount'] ?? 0)`. The branch now actually triggers for empty bills instead of throwing notices and never matching.
6. `.htaccess`
   - Rewrite whitelist extended to include `accounts|categories|credit-cards|currency-settings|wallets`. Pretty-URL redirects now work for those pages at the root level.

## Bugs fixed
- undefined variables in the empty-bill delete branch of credit-cards.php (the "Excluir fatura vazia" button now appears when intended).
- broken hero image on the landing page.
- dead roadmap.html link.
- ODKU `VALUES()` deprecation in CurrencySettingsRepository (future-proof).
- USD/EUR currency formatting using Brazilian separators.
- root-level pretty URLs for wallets/credit-cards/accounts/etc.

## Risks introduced
- **Low**: alias-ODKU syntax requires MySQL 8.0.19+ / MariaDB 10.6+. Hostinger runs MySQL 8.0+. Local dev / CI on older MySQL could fail. Mitigation documented; not applicable to Hostinger.
- **Low**: redirect() change is additive (only added missing entries); no path that worked before is broken.
- **Low**: index.php mockup is purely presentational; visual not preserved byte-for-byte but the layout (rounded card, mock chart, alert badge) is structurally similar.

## Rollback strategy
- Restore each of the 6 files from /backups/phase3a/.

## Validation performed
- No PHP binary locally. Brace/paren balance check passed for every edited PHP file.
- Visual review of the inline SVG/HTML mockup (no script injections, no broken tags).
- Verified .htaccess regex syntax (alternation list, captured group preserved via `$1`).

## Pending (Phase 3 remaining batches)
- Batch B: remove runtime DDL from action handlers + public pages (output of Phase 2 migrations create canonical tables; runtime CREATE TABLE IF NOT EXISTS becomes a no-op, so removal is safe).
- Batch C: remove runtime SHOW COLUMNS / SHOW TABLES probes.
- Batch D: actions/transactions/update.php — add category-type validation parity with create.php.
- Batch E: actions/credit-cards/revert-payment.php — actually revert auto-generated expense/limit transactions.
- Batch F: wrap multi-statement mutations in PDO transactions.

## Phase 3 Batch A status: COMPLETE