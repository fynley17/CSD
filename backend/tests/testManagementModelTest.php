<?php

use App\Models\Model;
use App\Models\Test;
use PHPUnit\Framework\TestCase;

final class TestManagementModelTest extends TestCase
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
                testDescription TEXT NULL,
                timeLimit INTEGER NULL,
                dueDate TEXT NULL,
                questionCount INTEGER NOT NULL DEFAULT 10,
                subjectID TEXT NOT NULL,
                createdBy TEXT NOT NULL
            )'
        );

        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL,
                cohortID TEXT NOT NULL,
                isActive INTEGER NOT NULL DEFAULT 1
            )'
        );

        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName, cohortID, isActive) VALUES
                ('subject-1', 'Web Applications', 'cohort-1', 1),
                ('subject-2', 'Cyber Security', 'cohort-2', 1),
                ('subject-3', 'Archived Subject', 'cohort-1', 0)"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsOnlyActiveSubjects(): void
    {
        // Arrange: setUp() creates two active subjects and one archived subject.

        // Act
        $subjects = Test::getActiveSubjects();

        // Assert
        self::assertCount(2, $subjects);
        self::assertSame(
            ['Cyber Security', 'Web Applications'],
            array_column($subjects, 'subjectName')
        );
        self::assertSame(
            ['subject-2', 'subject-1'],
            array_column($subjects, 'subjectID')
        );
    }

    public function testCreatesTestWithAllSuppliedDetails(): void
    {
        // Arrange
        $testName = 'Database Fundamentals';
        $subjectID = 'subject-1';
        $description = 'An introduction to databases';
        $timeLimit = 45;
        $dueDate = '2026-12-15';
        $questionCount = 10;
        $createdBy = 'lecturer-1';

        // Act
        $testID = Test::create(
            $testName,
            $subjectID,
            $description,
            $timeLimit,
            $dueDate,
            $questionCount,
            $createdBy
        );

        // Assert
        self::assertNotEmpty($testID);

        $stmt = $this->db->prepare('SELECT * FROM Tests WHERE testID = ?');
        $stmt->execute([$testID]);

        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertNotFalse($test);
        self::assertSame($testName, $test['testName']);
        self::assertSame($subjectID, $test['subjectID']);
        self::assertSame($description, $test['testDescription']);
        self::assertSame(45, (int) $test['timeLimit']);
        self::assertSame($dueDate, $test['dueDate']);
        self::assertSame(10, (int) $test['questionCount']);
        self::assertSame($createdBy, $test['createdBy']);
    }

    public function testRejectsAnEmptyTestName(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Test name must be between 1 and 30 characters.'
        );

        // Act
        Test::create(
            '',
            'subject-1',
            null,
            null,
            null,
            10,
            'lecturer-1'
        );
    }

    public function testRejectsAnInactiveSubject(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Please select an active subject.'
        );

        // Act
        Test::create(
            'Archived Subject Test',
            'subject-3',
            null,
            null,
            null,
            10,
            'lecturer-1'
        );
    }
}
