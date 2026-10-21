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
                subjectID TEXT NOT NULL,
                createdBy TEXT NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Lecturer (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE TestQuestions (testID TEXT NOT NULL, questionID TEXT NOT NULL)');

        $this->db->exec(
            "INSERT INTO Subjects VALUES ('subject-1', 'Web Apps');
             INSERT INTO Lecturer VALUES ('lecturer-1', 'Alex', 'Lecturer');
             INSERT INTO Tests VALUES
                ('test-with-questions', 'Web Apps Test', 'Review web apps', 75,
                 '2026-10-30', 'subject-1', 'lecturer-1'),
                ('untimed-test', 'Untimed Test', NULL, NULL, NULL, 'subject-1', 'lecturer-1'),
                ('empty-test', 'Empty Test', 'No assigned questions', NULL, NULL, 'subject-1', 'lecturer-1');
             INSERT INTO TestQuestions VALUES
                ('test-with-questions', 'question-1'),
                ('test-with-questions', 'question-2'),
                ('untimed-test', 'question-3')"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsCardFieldsAndCountsAssignedQuestions(): void
    {
        $tests = Test::getAvailableForStudents();

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
    }

    public function testUsesCardFallbacksForMissingOptionalValues(): void
    {
        $tests = Test::getAvailableForStudents();
        $untimedTest = $tests[1];

        self::assertSame('No description provided.', $untimedTest['testDescription']);
        self::assertNull($untimedTest['timeLimit']);
        self::assertNull($untimedTest['dueDate']);
        self::assertSame(1, $untimedTest['questionCount']);
    }

    public function testDoesNotReturnTestsWithoutAssignedQuestionsOrAnswerData(): void
    {
        $tests = Test::getAvailableForStudents();
        $testIDs = array_column($tests, 'testID');

        self::assertNotContains('empty-test', $testIDs);
        self::assertNotContains('answerID', array_keys($tests[0]));
        self::assertNotContains('isCorrect', array_keys($tests[0]));
    }
}
