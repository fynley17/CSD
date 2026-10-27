-- How many questions each attempt draws at random from the test's pool in TestQuestions.
-- NULL keeps the old behaviour, every question in the pool is asked.
ALTER TABLE `Tests`
  ADD COLUMN `questionCount` int(3) DEFAULT NULL AFTER `dueDate`;
