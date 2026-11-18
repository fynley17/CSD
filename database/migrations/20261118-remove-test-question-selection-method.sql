-- Remove the lecturer-configurable question selection method.
-- Question selection is now automatic for all tests.
-- Safe to run again.

ALTER TABLE `Tests`
  DROP COLUMN IF EXISTS `questionSelectionMethod`;
