-- How many questions each attempt draws at random from the test's pool in TestQuestions.
-- NULL keeps the old behaviour, every question in the pool is asked.
-- Safe to run again, and fixes databases that already had the column as NOT NULL.
ALTER TABLE `Tests`
  ADD COLUMN IF NOT EXISTS `questionCount` int(3) DEFAULT NULL AFTER `dueDate`;

ALTER TABLE `Tests`
  MODIFY COLUMN `questionCount` int(3) DEFAULT NULL
    COMMENT 'Questions drawn at random from the TestQuestions pool per attempt; NULL = all'
    AFTER `dueDate`;
