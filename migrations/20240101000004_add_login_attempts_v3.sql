-- NovixFin v3 — Phase validation W2 fix: persistent login-throttle table.
--
-- Pre-v3/v3 throttled login attempts in $_SESSION keyed by
-- md5(email+REMOTE_ADDR). Clearing the session cookie reset the counter
-- to zero, defeating the throttle entirely. This new table stores
-- attempts in the DB so the throttle is bound to the (email+IP) tuple
-- independent of the client's cookies.
--
-- Engine: MySQL 8.0+ / MariaDB 10.6+. Idempotent.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `identifier` CHAR(64) NOT NULL COMMENT 'sha256(email_lower|ip) hex',
  `ip` VARCHAR(45) NULL DEFAULT NULL COMMENT 'REMOTE_ADDR kept for audit / IP-side ban',
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_identifier_time` (`identifier`, `attempted_at`),
  INDEX `idx_ip_time` (`ip`, `attempted_at`),
  INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;