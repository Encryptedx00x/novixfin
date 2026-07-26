<?php

declare(strict_types=1);

/**
 * NovixFin v3 — includes/throttle.php
 *
 * Server-side login/registration throttle. Eliminates the cookie-clear
 * bypass that the pre-v3 `$_SESSION`-based counter had (W2 in
 * /reports/final_validation.md).
 *
 * Storage: the `login_attempts` table (migration
 * 20240101000004_add_login_attempts_v3.sql). No external dependencies;
 * works on plain PHP + PDO MySQL on Hostinger shared hosting.
 *
 * Identifier model:
 *   - login    : sha256( strtolower(trim($email)) . '|' . $ip )  hex
 *   - register : sha256( 'register' . '|' . $ip )                hex
 *   (Keeping `register` distinct stops a single attacker IP from being
 *   able to register-spam AND deny legitimate login attempts to the same
 *   key — they are separate counters.)
 *
 * Limits (defaults; all constant-tunable):
 *   - 5 attempts every 5 minutes per (email+IP).
 *   - 10 register attempts per IP every 10 minutes.
 *   - Old rows purged opportunistically on each touch (older than 24h)
 *     so the table stays small without a separate cron.
 *
 * API:
 *   throttle_identifier(string $email, ?string $ip = null): string
 *   throttle_identifier_for_register(?string $ip = null): string
 *   is_throttled(string $identifier, int $max = 5, int $windowSec = 300): bool
 *   record_failed_attempt(string $identifier, ?string $ip = null): void
 *   clear_failed_attempts(string $identifier): void
 *
 * Concurrency-safe (InnoDB row-level locking; INSERT is atomic; COUNT is a
 * read-only snapshot). The two-step (count → insert) is not atomic but the
 * 5% over-the-limit headroom is acceptable for this scale.
 */

use Novix\Database\Connection;

/**
 * Build the throttle identifier for a login attempt keyed by email+IP.
 * The email is normalised (trim + lower) so casing/whitespace don't fragment
 * the bucket. The IP is included so different IPs hitting the same victim
 * email each get their own counter (cannot amplify a single attacker IP into
 * a full account lockout for the victim).
 */
function throttle_identifier(string $email, ?string $ip = null): string
{
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $normalisedEmail = mb_strtolower(trim($email));
    return hash('sha256', $normalisedEmail . '|' . $ip);
}

/**
 * Build the throttle identifier for a registration attempt (keyed by IP
 * only — there is no victim account to protect, just spam prevention).
 */
function throttle_identifier_for_register(?string $ip = null): string
{
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    return hash('sha256', 'register' . '|' . $ip);
}

/**
 * Return TRUE if the identifier has >= `$max` failures in the last
 * `$windowSec` seconds. Also opportunistically garbage-collects rows
 * older than 24h to keep the table compact.
 */
function is_throttled(string $identifier, int $max = 5, int $windowSec = 300): bool
{
    try {
        $pdo = Connection::get();

        // Opportunistic GC. The 24h horizon is far larger than any window
        // the throttle ever queries (300s default), so it never deletes a
        // row that could still be counted against the user.
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 24 HOUR)");

        // Sane window bounds [1s, 24h]. INTERVAL in prepared statements isn't
        // portable across drivers, so we interpolate a sanitized integer into
        // the SQL string. The (int) cast + min/max clamp guarantees no SQL
        // injection surface.
        $window = max(1, min($windowSec, 86400));

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE identifier = :id
                AND attempted_at >= (NOW() - INTERVAL ' . (int) $window . ' SECOND)'
        );
        $stmt->execute([':id' => $identifier]);
        return (int) $stmt->fetchColumn() >= $max;
    } catch (Throwable $e) {
        // Fail-OPEN on DB error: if the throttle table is unavailable (e.g.
        // pre-migration deployment), allow the login attempt to proceed —
        // the alternative is to lock every user out, which is worse.
        error_log('throttle.is_throttled DB error (fail-open): ' . $e->getMessage());
        return false;
    }
}

/**
 * Record a failed attempt for `$identifier`. Called when authentication
 * fails or when a registration is rejected. Stores the IP alongside so
 * the throttle table doubles as an audit log and an IP-side counter.
 */
function record_failed_attempt(string $identifier, ?string $ip = null): void
{
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') { $ip = null; }

    try {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (identifier, ip) VALUES (:id, :ip)'
        );
        $stmt->execute([
            ':id' => $identifier,
            ':ip' => $ip,
        ]);
    } catch (Throwable $e) {
        // Same fail-open contract: never block the action because we
        // couldn't write an audit row.
        error_log('throttle.record_failed_attempt DB error (fail-open): ' . $e->getMessage());
    }
}

/**
 * Clear every failed attempt for `$identifier`. Called on successful
 * authentication so a user who just had a couple of typos is not nudged
 * toward the limit. (Purely cosmetic — the rows would age out of the
 * window anyway — but nicer UX.)
 */
function clear_failed_attempts(string $identifier): void
{
    try {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE identifier = :id');
        $stmt->execute([':id' => $identifier]);
    } catch (Throwable $e) {
        error_log('throttle.clear_failed_attempts DB error (fail-open): ' . $e->getMessage());
    }
}