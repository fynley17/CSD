<?php

use App\Models\Model;
use App\Models\Result;
use PHPUnit\Framework\TestCase;

final class ResultModelTest extends TestCase
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
                subjectID TEXT NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE CompletedTests (
                completedID TEXT PRIMARY KEY,
                userID TEXT NOT NULL,
                testID TEXT NOT NULL,
                completedDate TEXT NOT NULL,
                questionCount INTEGER NOT NULL,
                pointsEarned INTEGER NOT NULL,
                percentageCorrect REAL NOT NULL
            )'
        );

        $this->db->exec(
            'CREATE TABLE Students (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL,
                points INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec('CREATE TABLE Users (userID TEXT PRIMARY KEY, username TEXT NOT NULL)');

        $this->db->exec(
            "INSERT INTO Students VALUES
                ('student-user-1', 'Ada', 'Lovelace', 250),
                ('student-user-2', 'Alan', 'Turing', 300),
                ('student-user-3', 'Grace', 'Hopper', 40);
             INSERT INTO Users VALUES
                ('student-user-1', 'ws000001'),
                ('student-user-2', 'ws000002'),
                ('student-user-3', 'ws000003');
             INSERT INTO Subjects VALUES ('subject-1', 'Web Apps');
             INSERT INTO Tests VALUES
                ('test-1', 'Web Apps Test', 'Review web apps', 'subject-1'),
                ('test-2', 'Untitled Test', NULL, 'subject-1');
             INSERT INTO CompletedTests VALUES
                ('completed-1', 'student-user-1', 'test-1', '2026-10-20 09:00:00', 10, 260, 80.00),
                ('completed-2', 'student-user-1', 'test-2', '2026-10-25 14:30:00', 5, -10, 40.50),
                ('completed-3', 'student-user-2', 'test-1', '2026-10-26 11:00:00', 10, 300, 100.00)"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsCompletedTestFieldsNewestFirst(): void
    {
        $results = Result::getCompletedForStudent('student-user-1');

        self::assertSame(['completed-2', 'completed-1'], array_column($results, 'completedID'));
        self::assertSame(
            [
                'completedID',
                'testID',
                'testName',
                'testDescription',
                'subjectName',
                'completedDate',
                'questionCount',
                'pointsEarned',
                'percentageCorrect',
            ],
            array_keys($results[0])
        );

        self::assertSame('Web Apps Test', $results[1]['testName']);
        self::assertSame('Review web apps', $results[1]['testDescription']);
        self::assertSame('Web Apps', $results[1]['subjectName']);
        self::assertSame(10, $results[1]['questionCount']);
        self::assertSame(260, $results[1]['pointsEarned']);
        self::assertSame(80.0, $results[1]['percentageCorrect']);
    }

    public function testUsesFallbackDescriptionAndKeepsNegativePoints(): void
    {
        $results = Result::getCompletedForStudent('student-user-1');

        self::assertSame('No description provided.', $results[0]['testDescription']);
        self::assertSame(-10, $results[0]['pointsEarned']);
        self::assertSame(40.5, $results[0]['percentageCorrect']);
    }

    public function testReturnsOnlyTheLoggedInStudentsResults(): void
    {
        $results = Result::getCompletedForStudent('student-user-2');

        self::assertSame(['completed-3'], array_column($results, 'completedID'));
    }

    public function testSummaryOnlyCountsTheStudentsOwnAttempts(): void
    {
        self::assertSame(
            [
                'studentName' => 'Ada Lovelace',
                'studentNumber' => 'ws000001',
                'totalPoints' => 250,
                'averageScore' => 60.3,
                'testsCompleted' => 2,
            ],
            Result::getSummaryForStudent('student-user-1')
        );
        self::assertSame(
            [
                'studentName' => 'Alan Turing',
                'studentNumber' => 'ws000002',
                'totalPoints' => 300,
                'averageScore' => 100.0,
                'testsCompleted' => 1,
            ],
            Result::getSummaryForStudent('student-user-2')
        );
    }

    public function testSummaryHasNoAverageBeforeAnyTestIsCompleted(): void
    {
        self::assertSame(
            [
                'studentName' => 'Grace Hopper',
                'studentNumber' => 'ws000003',
                'totalPoints' => 40,
                'averageScore' => null,
                'testsCompleted' => 0,
            ],
            Result::getSummaryForStudent('student-user-3')
        );
    }

    public function testSummaryIsEmptyForAnUnknownStudent(): void
    {
        self::assertSame(
            [
                'studentName' => '',
                'studentNumber' => '',
                'totalPoints' => 0,
                'averageScore' => null,
                'testsCompleted' => 0,
            ],
            Result::getSummaryForStudent('missing-user')
        );
    }
}
