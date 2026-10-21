SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `Answers` (
  `answerID` uuid NOT NULL DEFAULT uuid(),
  `questionID` uuid NOT NULL,
  `answerText` varchar(255) NOT NULL,
  `isCorrect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = correct answer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Cohorts` (
  -- Cohort IDs are selected by the registration form and stored against each student.
  `cohortID` uuid NOT NULL DEFAULT uuid(),
  `cohortName` varchar(50) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = disabled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `CompletedTests` (
  `completedID` uuid NOT NULL DEFAULT uuid(),
  `userID` uuid NOT NULL COMMENT 'The student who took the test',
  `testID` uuid NOT NULL,
  `completedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `questionCount` int(3) NOT NULL,
  `pointsEarned` int(5) NOT NULL DEFAULT 0,
  `percentageCorrect` decimal(5,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Lecturer` (
  `userID` uuid NOT NULL,
  `firstName` text NOT NULL,
  `lastName` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `LecturerSubjects` (
  `lecturerID` uuid NOT NULL,
  `subjectID` uuid NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Questions` (
  `questionID` uuid NOT NULL DEFAULT uuid(),
  `subjectID` uuid NOT NULL,
  `question` text NOT NULL,
  `percentageCorrect` int(3) NOT NULL DEFAULT 0 COMMENT 'Percentage correct'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TestQuestions` (
  `testID` uuid NOT NULL,
  `questionID` uuid NOT NULL,
  `questionOrder` int unsigned NOT NULL,
  PRIMARY KEY (`testID`,`questionID`),
  UNIQUE KEY `uq_test_questions_order` (`testID`,`questionOrder`),
  KEY `idx_test_questions_question` (`questionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `StudentAnswers` (
  `completedID` uuid NOT NULL,
  `questionID` uuid NOT NULL,
  `selectedAnswerID` uuid DEFAULT NULL COMMENT 'NULL = unanswered (e.g. time expired)',
  `pointsAwarded` int(3) NOT NULL DEFAULT 0 COMMENT '30 correct, -10 incorrect, 0 unanswered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Students` (
  -- A student profile is linked to its login account and selected cohort.
  `userID` uuid NOT NULL,
  `firstName` text NOT NULL,
  `lastName` text NOT NULL,
  `points` int(5) NOT NULL DEFAULT 0 COMMENT '+30 correct, -10 incorrect; can be negative',
  `cohortID` uuid NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Subjects` (
  `subjectID` uuid NOT NULL DEFAULT uuid(),
  `subjectName` varchar(100) NOT NULL,
  `cohortID` uuid NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = archived'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Tests` (
  `testID` uuid NOT NULL DEFAULT uuid(),
  `testName` varchar(30) NOT NULL,
  `subjectID` uuid NOT NULL,
  `testDescription` varchar(100) DEFAULT NULL,
  `timeLimit` int(3) DEFAULT NULL COMMENT 'Minutes; NULL = untimed',
  `createdBy` uuid NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Users` (
  -- Registration stores account details here; passwords are always stored as hashes.
  `userID` uuid NOT NULL DEFAULT uuid(),
  `username` varchar(8) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Salted password hash, never plain text',
  `accountType` enum('student','lecturer','admin') NOT NULL DEFAULT 'student',
  `approvalStatus` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = can sign in, 0 = disabled',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Account creation time',
  `lastLogin` datetime DEFAULT NULL,
  `failedAttempts` tinyint(2) NOT NULL DEFAULT 0,
  `lockedUntil` datetime DEFAULT NULL,
  `mfaSecret` varchar(64) DEFAULT NULL COMMENT 'One-time-password secret (store encrypted)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `Answers`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `idx_answers_question` (`questionID`);

ALTER TABLE `Cohorts`
  ADD PRIMARY KEY (`cohortID`);

ALTER TABLE `CompletedTests`
  ADD PRIMARY KEY (`completedID`),
  ADD KEY `idx_ct_user` (`userID`),
  ADD KEY `idx_ct_test` (`testID`);

ALTER TABLE `Lecturer`
  ADD PRIMARY KEY (`userID`);

ALTER TABLE `LecturerSubjects`
  ADD PRIMARY KEY (`lecturerID`,`subjectID`),
  ADD KEY `idx_ls_subject` (`subjectID`);

ALTER TABLE `Questions`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `idx_questions_subject` (`subjectID`);

ALTER TABLE `TestQuestions`
  ADD CONSTRAINT `fk_test_questions_test` FOREIGN KEY (`testID`)
    REFERENCES `Tests` (`testID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_test_questions_question` FOREIGN KEY (`questionID`)
    REFERENCES `Questions` (`questionID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `StudentAnswers`
  ADD PRIMARY KEY (`completedID`,`questionID`),
  ADD KEY `idx_sa_question` (`questionID`),
  ADD KEY `idx_sa_answer` (`selectedAnswerID`);

ALTER TABLE `Students`
  ADD PRIMARY KEY (`userID`),
  ADD KEY `idx_students_cohort` (`cohortID`);

ALTER TABLE `Subjects`
  ADD PRIMARY KEY (`subjectID`),
  ADD KEY `idx_subjects_cohort` (`cohortID`);

ALTER TABLE `Tests`
  ADD PRIMARY KEY (`testID`),
  ADD KEY `idx_tests_subject` (`subjectID`),
  ADD KEY `idx_tests_creator` (`createdBy`);

ALTER TABLE `Users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);


ALTER TABLE `Answers`
  ADD CONSTRAINT `fk_answers_question` FOREIGN KEY (`questionID`)
    REFERENCES `Questions` (`questionID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `CompletedTests`
  ADD CONSTRAINT `fk_ct_student` FOREIGN KEY (`userID`)
    REFERENCES `Students` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ct_test` FOREIGN KEY (`testID`)
    REFERENCES `Tests` (`testID`) ON UPDATE CASCADE;

ALTER TABLE `Lecturer`
  ADD CONSTRAINT `fk_lecturer_user` FOREIGN KEY (`userID`)
    REFERENCES `Users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `LecturerSubjects`
  ADD CONSTRAINT `fk_ls_lecturer` FOREIGN KEY (`lecturerID`)
    REFERENCES `Lecturer` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ls_subject` FOREIGN KEY (`subjectID`)
    REFERENCES `Subjects` (`subjectID`) ON UPDATE CASCADE;

ALTER TABLE `Questions`
  ADD CONSTRAINT `fk_questions_subject` FOREIGN KEY (`subjectID`)
    REFERENCES `Subjects` (`subjectID`) ON UPDATE CASCADE;

ALTER TABLE `StudentAnswers`
  ADD CONSTRAINT `fk_sa_answer` FOREIGN KEY (`selectedAnswerID`)
    REFERENCES `Answers` (`answerID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sa_completed` FOREIGN KEY (`completedID`)
    REFERENCES `CompletedTests` (`completedID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sa_question` FOREIGN KEY (`questionID`)
    REFERENCES `Questions` (`questionID`) ON UPDATE CASCADE;

ALTER TABLE `Students`
  ADD CONSTRAINT `fk_students_cohort` FOREIGN KEY (`cohortID`)
    REFERENCES `Cohorts` (`cohortID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`userID`)
    REFERENCES `Users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `Subjects`
  ADD CONSTRAINT `fk_subjects_cohort` FOREIGN KEY (`cohortID`) REFERENCES `Cohorts` (`cohortID`) ON UPDATE CASCADE;

ALTER TABLE `Tests`
  ADD CONSTRAINT `fk_tests_creator` FOREIGN KEY (`createdBy`) REFERENCES `Lecturer` (`userID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tests_subject` FOREIGN KEY (`subjectID`) REFERENCES `Subjects` (`subjectID`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
