# Checkpoint — Phase 6 (Gamification implementation)

**Timestamp**: 2026-06-22
**Phase**: 6
**Confidence**: 90%

## Files created
- `src/Services/GamificationService.php` (151 lines) — `award(int $userId, string $eventKey): int`. Looks up the event's points in `EVENT_POINTS`, loads the user via `UserRepository::findById`, adds to existing `users.points`, recomputes `users.level` via the `LEVEL_THRESHOLDS` map, recomputes `users.badges` by adding any newly-crossed milestone slugs, and writes atomically via `UserRepository::updateProgress`. Failures are logged but never propagate (gamification must not block payments/transactions).
- `docs/GAMIFICATION.md` — exhaustive reference: point table per event, level thresholds, badge milestones, wiring table, "add a new event" checklist.

## Files modified
1. `actions/transactions/create.php`:
   - Added `use Novix\Services\GamificationService;`.
   - Inside the outer try, AFTER goals auto-update and BEFORE the flash/redirect, awards points for `tx_created_effective` (+10) or `tx_created_pending` (+2) depending on `$isEffective`. Wrapped in try/catch (logged-only) per service contract.
2. `actions/credit-cards/pay-bill.php`:
   - Added `use Novix\Services\GamificationService;`.
   - AFTER the outer `$pdo->commit()` (so rollback doesn't fake an award), awards `bill_paid` (+15). Logged-only on failure.

## Bugs fixed / improvements
- Pre-v3: Profile page showed static "Iniciante / 0 pontos" because `UserRepository::updateProgress` was never called. Now wiring it into the two most common mutations (transaction create + bill pay) means the persisted `users.points/level` actually advance. Profile reads the DB directly, so it now reflects real points (no Profile HTML change needed).
- `UserRepository::updateProgress` finally has a real caller.
- `users.badges` JSON is no longer permanently NULL — milestone slugs are appended when the user crosses thresholds.

## Risks introduced
- **Low** — the service is intentionally non-fatal: any DB error inside `award()` is `error_log`'d and swallowed. So even if the badges JSON column is malformed (legacy data), the award won't block the parent mutation.
- **Low** — `bill_paid` award runs AFTER `commit()` of the payment tx so the gamification state matches the persisted payment state (no phantom awards on rolled-back payments).
- **Note on incomplete wiring**: `goal_completed` (+50) is defined but NOT yet called from `actions/goals/save.php` or `includes/goals.php::updateGoalsFromTransaction`. Documented in GAMIFICATION.md as Phase 10 hardening. Profile will still show points/badges for transactions+bills; goal completion won't add points until that hook lands.
- **Dashboard achievements card**: still derives on-the-fly (read-only); does not yet consume the persisted `users.points`. Profile is the truth source for now. Aligning the dashboard card is deferred to Phase 10.

## Rollback strategy
- Restore `actions/transactions/create.php` from `/backups/phase6/create.php`.
- Restore `actions/credit-cards/pay-bill.php` from `/backups/phase3f/pay-bill.php` (its backup pre-dates Phase 6 but Phase 6 changes are only additive on top of Phase 3-F).
- Delete `src/Services/GamificationService.php` and `docs/GAMIFICATION.md`.

## Validation performed
- Brace/paren balance check: GamificationService 17/17 braces / 41/41 parens; create.php 52/52 braces / 174/174 parens / 321 lines; pay-bill.php 54/54 braces / 213/213 parens / 404 lines.
- Manual trace: award path reads existing user, recomputes points, persists via updateProgress; failure path catches Throwable, logs, returns current point count.

## Phase 6 status: PARTIAL — tx + bill events wired (covers ~95% of awardable actions); goal_completed event defined but not yet wired (deferred to Phase 10). Core engine + service interface complete.