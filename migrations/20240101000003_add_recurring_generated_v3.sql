-- NovixFin v3 — Phase 5: tracking table for materialized recurring occurrences.
--
-- Each materialized recurring occurrence is recorded here so the recurrence
-- engine is idempotent (re-running the cron never produces duplicates). The
-- (parent_transaction_id, occurrence_date) pair is unique: a second insert
-- for an already-generated pair simply fails on the constraint and is
-- ignored, so the engine can safely be run more than once with the same
-- outcome.
--
-- Engine: MySQL 8.0+ / MariaDB 10.6+ (portable, no IF NOT EXISTS clauses on
-- FK creation, so the reconcile step is run AFTER the table exists).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `recurring_generated` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `parent_transaction_id` INT UNSIGNED NOT NULL,
  `occurrence_date` DATE NOT NULL,
  `child_transaction_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_parent_occurrence` (`parent_transaction_id`, `occurrence_date`),
  INDEX `idx_parent` (`parent_transaction_id`),
  INDEX `idx_child` (`child_transaction_id`),
  INDEX `idx_occurrence` (`occurrence_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
