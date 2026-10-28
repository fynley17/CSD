
<?php

use App\Models\Model;
use App\Models\Test;
use PHPUnit\Framework\TestCase;

final class TestAllForLecturerTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        // Arrange: create an isolated in-memory SQLite database.
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create the minimum Subjects table structure required by the query.
        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL
            )'
        );

        // Create the minimum Tests table structure required by the query.
        $this->db->exec(
            'CREATE TABLE Tests (
                testID TEXT PRIMARY KEY,
                testName TEXT NOT NULL,
                subjectID TEXT NOT NULL,
                questionCount INTEGER NOT NULL DEFAULT 10,
                timeLimit INTEGER NULL
            )'
        );

        // Each subject's questions are the pool its tests draw from.
        $this->db->exec(
            'CREATE TABLE Questions (
                questionID TEXT PRIMARY KEY,
                subjectID TEXT NOT NULL
            )'
        );

        // Give the Test model the temporary database connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Clean up: prevent other tests from reusing this connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsAllTestsWithSubjectQuestionCountAndTimeLimit(): void
    {
        // Arrange: create two subjects.
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName) VALUES
                ('subject-1', 'Mathematics'),
                ('subject-2', 'Science')"
        );

        // Arrange: create one test whose subject has questions and one whose subject has none.
        $this->db->exec(
            "INSERT INTO Tests (testID, testName, subjectID, questionCount, timeLimit) VALUES
                ('test-1', 'Algebra Test', 'subject-1', 2, 30),
                ('test-2', 'Science Test', 'subject-2', 5, NULL)"
        );

        // Arrange: give the maths subject a pool of three questions.
        $this->db->exec(
            "INSERT INTO Questions (questionID, subjectID) VALUES
                ('question-1', 'subject-1'),
                ('question-2', 'subject-1'),
                ('question-3', 'subject-1')"
        );

        // Act: retrieve all tests using the real Test model.
        $tests = Test::getAllForLecturer();

        // Assert: both tests are returned in alphabetical order.
        self::assertCount(2, $tests);
        self::assertSame(
            ['Algebra Test', 'Science Test'],
            array_column($tests, 'testName')
        );

        // Assert: the first test has the correct subject and asks 2 of its 3 pool questions.
        self::assertSame('Mathematics', $tests[0]['subjectName']);
        self::assertSame(2, $tests[0]['questionCount']);
        self::assertSame(30, $tests[0]['timeLimit']);

        // Assert: tests without questions are still returned.
        self::assertSame('Science', $tests[1]['subjectName']);
        self::assertSame(0, $tests[1]['questionCount']);

        // Assert: an untimed test returns null for its time limit.
        self::assertNull($tests[1]['timeLimit']);
    }

    public function testReturnsAnEmptyArrayWhenThereAreNoTests(): void
    {
        // Arrange: add a subject but leave the Tests table empty.
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName)
             VALUES ('subject-1', 'Mathematics')"
        );

        // Act: retrieve all tests.
        $tests = Test::getAllForLecturer();

        // Assert: no tests are returned.
        self::assertSame([], $tests);
    }
}
