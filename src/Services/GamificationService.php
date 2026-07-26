<?php

declare(strict_types=1);

namespace Novix\Services;

use Novix\Repositories\UserRepository;
use PDO;
use Throwable;

/**
 * NovixFin v3 — Phase 6: gamification engine.
 *
 * Pre-v3 the `users.points`/`level`/`badges` columns existed and
 * `UserRepository::updateProgress(...)` was defined — but **nothing ever
 * called it**. The dashboard page silently derived an "achievements" card
 * from goal/transaction totals on each request (read-only). The Profile
 * page therefore always displayed "Iniciante / 0 pontos".
 *
 * This service introduces a small, deliberate point-awarding system driven
 * by named events. A future migration of the dashboard's "achievements"
 * card to consume the persisted values is an optional cleanup (deferred —
 * see /state/context.md).
 *
 * Point awards (documented in docs/GAMIFICATION.md):
 *   tx_created_effective  +10   every newly effective transaction
 *   tx_created_pending    +2    newly scheduled-but-pending transaction
 *   bill_paid             +15   credit-card bill marked paid
 *   goal_completed        +50   goal completion crossed
 *   spending_limit_held   +20  reserved for future use (not wired yet)
 *
 * Levels (derived from cumulative points):
 *      0–99    → Iniciante
 *    100–299   → Aprendiz
 *    300–699   → Esperto
 *    700–1499  → Mestre Financeiro
 *   1500+      → Lendário
 *
 * Badges (JSON-encoded array on `users.badges`):
 *   first_transaction, ten_transactions,goal_complete, dedicated, legendary
 *
 * Idempotency contract: award() is fire-and-forget; the caller is responsible
 * for calling it once per logical event (we hook it AT the point of the
 * mutation, so it runs exactly once per successful mutation).
 *
 * @final
 */
final class GamificationService
{
    private PDO $connection;

    // Visibilidade pública (v3.1 fatia Perfil): a tela de Perfil lê estes
    // mapas para exibir nível/progresso/conquistas a partir da MESMA fonte da
    // verdade que concede os pontos, em vez de duplicar os limiares na view.
    // Mudança apenas de visibilidade — nenhum comportamento alterado.
    public const LEVEL_THRESHOLDS = [
        1500 => 'Lendário',
        700  => 'Mestre Financeiro',
        300  => 'Esperto',
        100  => 'Aprendiz',
        0    => 'Iniciante',
    ];

    public const EVENT_POINTS = [
        'tx_created_effective'  => 10,
        'tx_created_pending'    => 2,
        'bill_paid'             => 15,
        'goal_completed'        => 50,
        'spending_limit_held'   => 20,
    ];

    public const BADGE_MILESTONES = [
        // points threshold => badge slug (added the first time points cross it)
        10   => 'first_transaction',
        100  => 'ten_transactions',
        150  => 'goal_complete',
        700  => 'dedicated',
        1500 => 'legendary',
    ];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Award points for the given event, recompute level + badges, persist.
     *
     * @param int    $userId
     * @param string $eventKey one of EVENT_POINTS keys; unknown keys are ignored.
     * @return int the new points total (0 if the event was unknown or the
     *     user could not be loaded).
     */
    public function award(int $userId, string $eventKey): int
    {
        $points = self::EVENT_POINTS[$eventKey] ?? null;
        if ($points === null) {
            return 0; // unknown event — quietly ignore.
        }

        $userRepository = new UserRepository($this->connection);
        $user = $userRepository->findById($userId);
        if (!$user) {
            return 0;
        }

        $currentPoints = (int) ($user['points'] ?? 0);
        $newPoints = $currentPoints + $points;
        $newLevel = $this->levelForPoints($newPoints);
        $newBadges = $this->recomputeBadges($user['badges'] ?? null, $newPoints);

        try {
            $userRepository->updateProgress($userId, $newPoints, $newLevel, $newBadges);
        } catch (Throwable $e) {
            // Gamification must NEVER block the parent mutation. Log + carry on.
            error_log('GamificationService: failed to persist progress for user ' . $userId . ': ' . $e->getMessage());
            return $currentPoints;
        }

        return $newPoints;
    }

    private function levelForPoints(int $points): string
    {
        foreach (self::LEVEL_THRESHOLDS as $threshold => $level) {
            if ($points >= $threshold) {
                return $level;
            }
        }
        return 'Iniciante';
    }

    /**
     * @return list<string>
     */
    private function recomputeBadges($existingBadgesJson, int $points): array
    {
        $badges = [];
        if (is_string($existingBadgesJson) && $existingBadgesJson !== '') {
            $decoded = json_decode($existingBadgesJson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $b) {
                    if (is_string($b)) {
                        $badges[$b] = true;
                    }
                }
            }
        }

        foreach (self::BADGE_MILESTONES as $threshold => $slug) {
            if ($points >= $threshold) {
                $badges[$slug] = true;
            }
        }

        return array_keys($badges);
    }
}
