-- CTS-59: wrong passwords only count towards a lock if they're within 15 minutes of each other.
-- Existing accounts start with NULL, so their next wrong password counts as the first one.
ALTER TABLE `Users`
  ADD COLUMN `lastFailedAt` datetime DEFAULT NULL
    COMMENT 'Time of the last wrong password, older than 15 minutes stops counting' AFTER `lockedUntil`;
