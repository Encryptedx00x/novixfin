<?php

declare(strict_types=1);

namespace Novix\Services;

use PDO;
use PDOException;

/**
 * NovixFin v3 — rule-based financial insights service.
 *
 * Phase 4 + Phase 8: extracted verbatim from public/dashboard.php:635-704
 * (the inline `// Gera insights baseados em análise de dados financeiros`
 * block). The output contract is unchanged — dashboard.php still receives
 * the same array of insight strings — so this is a strict no-behaviour-change
 * extraction. Theheuristics remain rule-based (no real AI / no external
 * API). A future integration would simply replace `forUser()`'s body with
 * an LLM call returning the same shape.
 */
final class InsightService
{
    /**
     * Compute personalised insights for a user.
     *
     * @param PDO   $pdo             DB connection
     * @param int   $userId          authenticated user id
     * @param float $currentBalance  the user's current balance in the primary
     *                               currency (passed in from the dashboard so
     *                               the service stays currency-aware and
     *                               stateless)
     * @return list<string>  zero or more human-readable insight strings (pt-BR)
     */
    public function forUser(PDO $pdo, int $userId, float $currentBalance): array
    {
        $insights = [];

        // Insight 1: Top expense category last 30 days vs prior 30 days.
        $topCategoryStmt = $pdo->prepare('
            SELECT c.name, SUM(t.amount) as total
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :user_id
            AND t.type = "expense"
            AND t.is_effective = 1
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY c.id, c.name
            ORDER BY total DESC
            LIMIT 1
        ');
        $topCategoryStmt->execute(['user_id' => $userId]);
        $topCategory = $topCategoryStmt->fetch();

        if ($topCategory) {
            $categoryName = $topCategory['name'];
            $currentTotal = (float) ($topCategory['total'] ?? 0);

            $previousCategoryStmt = $pdo->prepare('
                SELECT SUM(t.amount) as total
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = :user_id
                AND c.name = :category_name
                AND t.type = "expense"
                AND t.is_effective = 1
                AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                AND t.transaction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ');
            $previousCategoryStmt->execute(['user_id' => $userId, 'category_name' => $categoryName]);
            $previousData = $previousCategoryStmt->fetch();
            $previousTotal = (float) ($previousData['total'] ?? 0);

            if ($previousTotal > 0 && $currentTotal > 0) {
                $changePercent = (($currentTotal - $previousTotal) / $previousTotal) * 100;
                if (abs($changePercent) >= 5) {
                    $direction = $changePercent > 0 ? 'aumentaram' : 'diminuíram';
                    // Use global helpers (unqualified-call resolution picks the
                    // global namespace via the leading backslash).
                    $insights[] = 'Gastos com ' . \sanitize((string) $categoryName)
                        . ' ' . $direction . ' ' . number_format(abs($changePercent), 0)
                        . '%. ' . ($changePercent > 0 ? 'Considere revisar o orçamento.' : 'Ótimo trabalho!');
                }
            }
        }

        // Insight 2: Balance state.
        if ($currentBalance < 0) {
            $insights[] = 'Seu saldo está negativo. Considere reduzir gastos ou aumentar receitas.';
        } elseif ($currentBalance > 0 && $currentBalance < 1000) {
            $insights[] = 'Seu saldo está positivo, mas baixo. Considere criar uma reserva de emergência.';
        }

        // Insight 3: Pending (non-effective) transactions.
        $pendingTransactionsStmt = $pdo->prepare(
            'SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE user_id = :user_id AND is_effective = 0'
        );
        $pendingTransactionsStmt->execute(['user_id' => $userId]);
        $pending = $pendingTransactionsStmt->fetch();
        if ($pending && (int) $pending['count'] > 0) {
            $pendingTotal = (float) ($pending['total'] ?? 0);
            $insights[] = 'Você tem ' . (int) $pending['count']
                . ' transação(ões) não efetivada(s) totalizando '
                . \formatCurrency($pendingTotal, 'BRL')
                . '. Revise e efetive quando necessário.';
        }

        // Fallback generic insight when nothing specific applied.
        if (empty($insights)) {
            $insights[] = 'Continue registrando suas transações para receber insights personalizados sobre suas finanças.';
        }

        return $insights;
    }
}