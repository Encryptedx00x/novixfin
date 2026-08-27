-- NovixFin v3.1 — Task 5: server-persisted "hide balances by default"
-- privacy preference. Default is TRUE (values hidden on login) per the
-- spec, the user can disable it on the profile page and the choice is
-- remembered across sessions and devices.
--
-- Engine: MariaDB 11.8.x. Idempotent via ADD COLUMN IF NOT EXISTS.

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `hide_balances` BOOLEAN NOT NULL DEFAULT 1;
