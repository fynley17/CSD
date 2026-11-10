-- Add the question selection method to tests.
-- Existing tests retain random question selection.
-- Safe to run again.

ALTER TABLE `Tests`
  ADD COLUMN IF NOT EXISTS `questionSelectionMethod`
    varchar(32) NOT NULL DEFAULT 'random'
    COMMENT 'random or frequently_incorrect'
    AFTER `questionCount`;
