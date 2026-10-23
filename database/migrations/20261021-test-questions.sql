CREATE TABLE `TestQuestions` (
  `testID` uuid NOT NULL,
  `questionID` uuid NOT NULL,
  `questionOrder` int unsigned NOT NULL,
  PRIMARY KEY (`testID`,`questionID`),
  UNIQUE KEY `uq_test_questions_order` (`testID`,`questionOrder`),
  KEY `idx_test_questions_question` (`questionID`),
  CONSTRAINT `fk_test_questions_test` FOREIGN KEY (`testID`)
    REFERENCES `Tests` (`testID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_test_questions_question` FOREIGN KEY (`questionID`)
    REFERENCES `Questions` (`questionID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `TestQuestions` (`testID`, `questionID`, `questionOrder`)
SELECT `testID`, `questionID`,
       ROW_NUMBER() OVER (PARTITION BY `testID` ORDER BY `questionID`)
FROM `Questions`
WHERE `testID` IS NOT NULL;

ALTER TABLE `Questions`
  DROP FOREIGN KEY `fk_questions_test`,
  DROP INDEX `idx_questions_test`,
  DROP COLUMN `testID`;
