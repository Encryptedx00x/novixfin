# NovixFin v3 — Gamification

> Introduced in Phase 6. The pre-v3 columns `users.points`, `users.level`,
> and `users.badges` existed but were never written to. v3 introduces a
> minimal, deterministic point-awarding engine + level mapping + badge
> milestones.

## Engine

`src/Services/GamificationService.php` exposes a single entrypoint:

```php
(new GamificationService($pdo))->award(int $userId, string $eventKey): int
```

It is **fire-and-forget — failures are logged but never fail the parent
mutation** (so a points-write bug can never block a user's payment or
transaction creation). Returns the user's new points total (or 0 if the
event was unknown or persisted invisibly).

## Point awards

| Event key              | Points | Wired at                                    |
|------------------------|--------|---------------------------------------------|
| `tx_created_effective` | +10    | `actions/transactions/create.php`           |
| `tx_created_pending`   | +2     | `actions/transactions/create.php`           |
| `bill_paid`            | +15    | `actions/credit-cards/pay-bill.php`         |
| `goal_completed`       | +50    | (not yet wired — Phase 10 hardening)        |
| `spending_limit_held`  | +20    | (reserved — future hook in includes/goals) |

To add a new event, define its point value in `EVENT_POINTS` and call
`award($userId, '<event>')` at the right point in your action.

## Levels (derived from cumulative points)

| Points       | Level             |
|--------------|-------------------|
| 0 – 99       | Iniciante         |
| 100 – 299    | Aprendiz          |
| 300 – 699    | Esperto           |
| 700 – 1499   | Mestre Financeiro |
| 1500+        | Lendário          |

Level strings are stored on `users.level`. `UserRepository::updateProgress`
writes the new level + points + badges atomically (a single UPDATE).

## Badges (`users.badges` JSON array)

Milestone-based slugs are appended to the user's badges JSON when points
cross each threshold. Existing badges are preserved.

| Points threshold | Badge slug       |
|------------------|------------------|
| 10               | first_transaction |
| 100              | ten_transactions  |
| 150              | goal_complete     |
| 700              | dedicated         |
| 1500             | legendary         |

## Where the user sees the progress

- `public/profile.php` — reads `users.level` and `users.points` directly
  from the database (via `UserRepository::findById`). No HTML change needed.
- `public/dashboard.php` — still derives an "achievements" card from live
  transaction totals (the pre-v3 read-only heuristic). It does NOT yet read
  the persisted `users.points` value. Aligning the dashboard card with the
  persisted value is an optional cleanup deferred to Phase 10.

## Re-invoking the engine

The engine is called automatically inside the relevant action handlers (no
extra HTTP call, no cron). It short-circuits silently on failure. No DB
transaction concern: it is invoked AFTER the parent mutation's commit (for
`bill_paid`), or after `TransactionRepository::create` returns successfully
(for `tx_created_*`).

## Event additions checklist

1. Add the event key + points value to `EVENT_POINTS` in
   `src/Services/GamificationService.php`.
2. Optionally add a matching badge milestone in `BADGE_MILESTONES`.
3. Insert `(new GamificationService($pdo))->award($userId, '<eventKey>')`
   in the action handler where the event fires.
4. Document the event in this file's table.