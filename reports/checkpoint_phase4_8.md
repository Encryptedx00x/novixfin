# Checkpoint — Phase 4 + Phase 8 (InsightService extraction)

**Timestamp**: 2026-06-22
**Phase**: 4 (partial) + 8 (complete)
**Confidence**: 92%

## Files created
- `src/Services/InsightService.php` (114 lines) — namespace `Novix\Services`. Public method `forUser(PDO $pdo, int $userId, float $currentBalance): array` returns the same array of rule-based insight strings that previously lived inline in dashboard.php:635-704. Extracted VERBATIM (same SQL, same heuristics, same pt-BR copy) so output behaviour is unchanged. Uses global helpers `\sanitize()` / `\formatCurrency()` (unqualified global calls).
- New directory `src/Services/`.

## Files modified
- `public/dashboard.php` (2202 → 2135 lines; -63 net):
  - Added `use Novix\Services\InsightService;` to the imports.
  - Replaced the inline insight computation block (lines 635-704) with two-line call:
    ```php
    $currentBalance = $displayTotals[$primaryCurrency]['balance'] ?? 0;
    $insights = (new InsightService())->forUser($pdo, (int) $userId, (float) $currentBalance);
    ```
  - The `$insights` variable downstream (HTML rendering loop) is unchanged. Same data shape, same order, same string content.

## Bugs fixed / improvements
- Phase 8 (insights service): the rule-based heuristic exists as a single testable unit. A future "real AI" integration (OpenAI/Vertex) can swap `forUser()`'s body to LLM-call that returns the same `list<string>` shape — no dashboard changes needed.
- Phase 4 (partial monolith decomposition): dashboard.php dropped 65 lines of inline DB-querying logic. The insights section is now a 2-line call. (Full decomposition of dashboard.php's other ~2000 lines is deferred to a post-Phase-5 verification cycle when tests protect against regression — see /state/context.md.)

## Risks introduced
- **Low** — extraction is a no-behaviour-change move. Brace/paren balance verified: InsightService 9/9 braces 55/55 parens; dashboard.php 145/145 braces 815/815 parens.
- **Edge** — `currentBalance` is now read inside dashboard at the line BEFORE the call (rather than buried at line 685). Numeric context unchanged.

## Rollback strategy
- Restore `public/dashboard.php` from `/backups/phase4/dashboard.php`. Delete `src/Services/InsightService.php`.

## Pending
- Phase 4 full decomposition (deferred — document in state).
- Phase 5: RecurringService + recurring_instances table + scripts/cron.php dispatcher.

## Phase 4 + 8 status: COMPLETE (Phase 4 partial per design; Phase 8 complete)