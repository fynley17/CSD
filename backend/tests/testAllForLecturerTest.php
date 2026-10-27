
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
                timeLimit INTEGER NULL
            )'
        );

        // Store the question assignments used to calculate each test's count.
        $this->db->exec(
            'CREATE TABLE TestQuestions (
                testID TEXT NOT NULL,
                questionID TEXT NOT NULL
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

        // Arrange: create one test with questions and one without questions.
        $this->db->exec(
            "INSERT INTO Tests (testID, testName, subjectID, timeLimit) VALUES
                ('test-1', 'Algebra Test', 'subject-1', 30),
                ('test-2', 'Science Test', 'subject-2', NULL)"
        );

        // Arrange: assign two questions to the algebra test.
        $this->db->exec(
            "INSERT INTO TestQuestions (testID, questionID) VALUES
                ('test-1', 'question-1'),
                ('test-1', 'question-2')"
        );

        // Act: retrieve all tests using the real Test model.
        $tests = Test::getAllForLecturer();

        // Assert: both tests are returned in alphabetical order.
        self::assertCount(2, $tests);
        self::assertSame(
            ['Algebra Test', 'Science Test'],
            array_column($tests, 'testName')
        );

        // Assert: the first test has the correct subject and question count.
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
