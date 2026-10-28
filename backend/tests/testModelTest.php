<?php

use App\Models\Model;
use App\Models\Test;
use PHPUnit\Framework\TestCase;

final class TestModelTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Tests (
                testID TEXT PRIMARY KEY,
                testName TEXT NOT NULL,
                testDescription TEXT,
                timeLimit INTEGER,
                dueDate TEXT,
                questionCount INTEGER,
                subjectID TEXT NOT NULL,
                createdBy TEXT NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL,
                cohortID TEXT NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE Students (userID TEXT PRIMARY KEY, cohortID TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Lecturer (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE Questions (questionID TEXT PRIMARY KEY, subjectID TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE CompletedTests (
                testID TEXT NOT NULL,
                userID TEXT NOT NULL,
                percentageCorrect REAL NOT NULL
            )'
        );

        $this->db->exec(
            "INSERT INTO Subjects VALUES
                ('subject-1', 'Web Apps', 'cohort-1'),
                ('subject-2', 'Cyber Security', 'cohort-2'),
                ('subject-3', 'Empty Subject', 'cohort-1');
             INSERT INTO Students VALUES
                ('student-user-1', 'cohort-1'),
                ('student-user-2', 'cohort-2');
             INSERT INTO Lecturer VALUES ('lecturer-1', 'Alex', 'Lecturer');
             INSERT INTO Tests VALUES
                ('test-with-questions', 'Web Apps Test', 'Review web apps', 75,
                 '2026-10-30', 2, 'subject-1', 'lecturer-1'),
                ('untimed-test', 'Untimed Test', NULL, NULL, NULL, NULL, 'subject-1', 'lecturer-1'),
                ('empty-test', 'Empty Test', 'Subject has no questions', NULL, NULL, 5, 'subject-3', 'lecturer-1'),
                ('other-cohort-test', 'Cyber Security Test', 'Other cohort test', NULL, NULL, NULL,
                 'subject-2', 'lecturer-1');
             INSERT INTO Questions VALUES
                ('question-1', 'subject-1'),
                ('question-2', 'subject-1'),
                ('question-3', 'subject-1'),
                ('question-4', 'subject-2')"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsCardFieldsAndHowManyQuestionsAnAttemptAsks(): void
    {
        $tests = Test::getAvailableForStudents('student-user-1');

        self::assertCount(2, $tests);
        self::assertSame(
            [
                'testID',
                'testName',
                'testDescription',
                'timeLimit',
                'dueDate',
                'questionCount',
                'subjectName',
                'createdByName',
                'earnsPoints',
            ],
            array_keys($tests[0])
        );
        self::assertSame('test-with-questions', $tests[0]['testID']);
        self::assertSame('Review web apps', $tests[0]['testDescription']);
        self::assertSame(75, $tests[0]['timeLimit']);
        self::assertSame('2026-10-30', $tests[0]['dueDate']);
        self::assertSame(2, $tests[0]['questionCount']);
        self::assertSame('Web Apps', $tests[0]['subjectName']);
        self::assertSame('Alex Lecturer', $tests[0]['createdByName']);
        self::assertTrue($tests[0]['earnsPoints']);
    }

    public function testUsesCardFallbacksForMissingOptionalValues(): void
    {
        $tests = Test::getAvailableForStudents('student-user-1');
        $untimedTest = $tests[1];

        self::assertSame('No description provided.', $untimedTest['testDescription']);
        self::assertNull($untimedTest['timeLimit']);
        self::assertNull($untimedTest['dueDate']);
        // no count set falls back to the default of 10, capped at the 3 questions in the subject
        self::assertSame(3, $untimedTest['questionCount']);
    }

    public function testQuestionCountIsHowManyQuestionsEachAttemptDraws(): void
    {
        // a set count is shown, but never more than the pool holds
        $this->db->exec("UPDATE Tests SET questionCount = 1 WHERE testID = 'test-with-questions'");
        $this->db->exec("UPDATE Tests SET questionCount = 4 WHERE testID = 'untimed-test'");

        $tests = Test::getAvailableForStudents('student-user-1');

        self::assertSame([1, 3], array_column($tests, 'questionCount'));
        self::assertArrayNotHasKey('questionsPerAttempt', $tests[0]);
    }

    public function testTestsInTheSameSubjectShareItsQuestionPool(): void
    {
        // a new question in the subject is added to the pool of every test in that subject
        $this->db->exec("INSERT INTO Questions VALUES ('question-5', 'subject-1')");
        $this->db->exec("UPDATE Tests SET questionCount = 10");

        $tests = Test::getAvailableForStudents('student-user-1');

        self::assertSame([4, 4], array_column($tests, 'questionCount'));
    }

    public function testDoesNotReturnTestsWhoseSubjectHasNoQuestionsOrAnswerData(): void
    {
        $tests = Test::getAvailableForStudents('student-user-1');
        $testIDs = array_column($tests, 'testID');

        self::assertNotContains('empty-test', $testIDs);
        self::assertNotContains('other-cohort-test', $testIDs);
        self::assertNotContains('answerID', array_keys($tests[0]));
        self::assertNotContains('isCorrect', array_keys($tests[0]));
    }

    public function testKeepsFullMarksTestsListedButStopsThemEarningPoints(): void
    {
        // a lower score still earns points, full marks stops it earning points for that student only
        $this->db->exec(
            "INSERT INTO CompletedTests VALUES
                ('untimed-test', 'student-user-1', 50),
                ('test-with-questions', 'student-user-1', 50),
                ('test-with-questions', 'student-user-1', 100),
                ('other-cohort-test', 'student-user-1', 100)"
        );

        $tests = Test::getAvailableForStudents('student-user-1');

        self::assertSame(['test-with-questions', 'untimed-test'], array_column($tests, 'testID'));
        self::assertSame([false, true], array_column($tests, 'earnsPoints'));
        self::assertSame([true], array_column(Test::getAvailableForStudents('student-user-2'), 'earnsPoints'));
    }

    public function testReturnsOnlyTestsForTheLoggedInStudentsCohort(): void
    {
        $tests = Test::getAvailableForStudents('student-user-2');
        $testIDs = array_column($tests, 'testID');

        self::assertSame(['other-cohort-test'], $testIDs);
    }
}
