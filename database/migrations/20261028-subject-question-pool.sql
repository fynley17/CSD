-- Tests now draw their questions at random from every question in the test's subject
-- (Questions.subjectID), so the TestQuestions link table is no longer used.
-- Which questions each attempt was given is still recorded in StudentAnswers.
-- Safe to run again.
DROP TABLE IF EXISTS `TestQuestions`;

-- Asking the whole pool gave every student the same set, so every test now draws a set number.
UPDATE `Tests` SET `questionCount` = 10 WHERE `questionCount` IS NULL OR `questionCount` < 1;

ALTER TABLE `Tests`
  MODIFY COLUMN `questionCount` int(3) NOT NULL DEFAULT 10
    COMMENT 'Questions drawn at random per attempt from the subject''s question pool'
    AFTER `dueDate`;
