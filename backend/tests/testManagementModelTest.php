
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
                questionSelectionMethod TEXT NOT NULL DEFAULT "random",
                subjectID TEXT NOT NULL,
                createdBy TEXT NOT NULL,
                isActive INTEGER NOT NULL DEFAULT 1
            )'
        );

        $this->db->exec(
            'CREATE TABLE Cohorts (
                cohortID TEXT PRIMARY KEY,
                cohortName TEXT NOT NULL,
                isActive INTEGER NOT NULL DEFAULT 1
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
            'CREATE TABLE Questions (
                questionID TEXT PRIMARY KEY,
                subjectID TEXT NOT NULL
            )'
        );

        $this->db->exec(
            "INSERT INTO Questions (questionID, subjectID) VALUES
                ('question-1', 'subject-1'),
                ('question-2', 'subject-1')"
        );

        $this->db->exec(
            'CREATE TABLE CompletedTests (
                testID TEXT NOT NULL,
                userID TEXT NOT NULL,
                percentageCorrect REAL NOT NULL
            )'
        );

        $this->db->exec(
            "INSERT INTO Cohorts (cohortID, cohortName, isActive) VALUES
                ('cohort-1', '2025 Computing', 1),
                ('cohort-2', '2026 Computing', 1)"
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

        self::assertSame(
            ['2026 Computing', '2025 Computing'],
            array_column($subjects, 'cohortName')
        );
    }

    public function testActiveSubjectsIncludeQuestionPoolSize(): void
    {
        // Act
        $subjects = Test::getActiveSubjects();

        // Assert, Cyber Security has no questions so the test form can warn about it
        self::assertSame(
            ['Cyber Security' => 0, 'Web Applications' => 2],
            array_column($subjects, 'questionPoolSize', 'subjectName')
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

        $stmt = $this->db->prepare(
            'SELECT * FROM Tests WHERE testID = ?'
        );
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
        self::assertSame(1, (int) $test['isActive']);
    }

    public function testRejectsAnEmptyTestName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Test name must be between 1 and 30 characters.'
        );

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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Please select an active subject.'
        );

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

    public function testUpdatesAllTestDetails(): void
    {
        // Arrange
        $this->addTest();

        // Act
        $updated = Test::update(
            'test-1',
            'Updated Test',
            'subject-1',
            'Updated description',
            60,
            '2026-12-20',
            15
        );

        // Assert
        self::assertTrue($updated);

        $stmt = $this->db->prepare(
            'SELECT * FROM Tests WHERE testID = ?'
        );
        $stmt->execute(['test-1']);

        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertSame('Updated Test', $test['testName']);
        self::assertSame('subject-1', $test['subjectID']);
        self::assertSame('Updated description', $test['testDescription']);
        self::assertSame(60, (int) $test['timeLimit']);
        self::assertSame('2026-12-20', $test['dueDate']);
        self::assertSame(15, (int) $test['questionCount']);
    }

    public function testAllowsSubjectChangeWhenNoCompletedAttemptsExist(): void
    {
        // Arrange
        $this->addTest();

        // Act
        $updated = Test::update(
            'test-1',
            'Updated Test',
            'subject-2',
            'Updated description',
            null,
            null,
            10
        );

        // Assert
        self::assertTrue($updated);

        $stmt = $this->db->prepare(
            'SELECT subjectID FROM Tests WHERE testID = ?'
        );
        $stmt->execute(['test-1']);

        self::assertSame('subject-2', $stmt->fetchColumn());
    }

    public function testRejectsSubjectChangeWhenCompletedAttemptsExist(): void
    {
        // Arrange
        $this->addTest();

        $this->db->exec(
            "INSERT INTO CompletedTests (testID, userID, percentageCorrect)
             VALUES ('test-1', 'student-1', 80)"
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The subject cannot be changed because students have completed this test.'
        );

        // Act
        Test::update(
            'test-1',
            'Updated Test',
            'subject-2',
            'Updated description',
            null,
            null,
            10
        );
    }

    public function testReturnsFalseWhenUpdatingANonexistentTest(): void
    {
        // Act
        $updated = Test::update(
            'nonexistent-test',
            'Updated Test',
            'subject-1',
            null,
            null,
            null,
            10
        );

        // Assert
        self::assertFalse($updated);
    }

    public function testRejectsInvalidUpdateData(): void
    {
        // Arrange
        $this->addTest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Test name must be between 1 and 30 characters.'
        );

        // Act
        Test::update(
            'test-1',
            '',
            'subject-1',
            null,
            null,
            null,
            10
        );
    }

    public function testArchivesTestAndPreservesCompletedAttempts(): void
    {
        // Arrange
        $this->addTest();

        $this->db->exec(
            "INSERT INTO CompletedTests (testID, userID, percentageCorrect) VALUES
                ('test-1', 'student-1', 80),
                ('test-1', 'student-2', 100),
                ('another-test', 'student-3', 70)"
        );

        // Act
        $result = Test::delete('test-1');

        // Assert
        self::assertSame('archived', $result);

        $stmt = $this->db->prepare(
            'SELECT isActive FROM Tests WHERE testID = ?'
        );
        $stmt->execute(['test-1']);

        self::assertSame(0, (int) $stmt->fetchColumn());

        // Both completed attempts must remain.
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM CompletedTests WHERE testID = ?'
        );
        $stmt->execute(['test-1']);

        self::assertSame(2, (int) $stmt->fetchColumn());

        // All three attempts, including the other test's attempt, remain.
        self::assertSame(
            3,
            (int) $this->db->query(
                'SELECT COUNT(*) FROM CompletedTests'
            )->fetchColumn()
        );
    }

    public function testPermanentlyDeletesTestWithoutCompletedAttempts(): void
    {
        // Arrange
        $this->addTest();

        // Act
        $result = Test::delete('test-1');

        // Assert
        self::assertSame('deleted', $result);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM Tests WHERE testID = ?'
        );
        $stmt->execute(['test-1']);

        self::assertSame(0, (int) $stmt->fetchColumn());

        self::assertSame(
            0,
            (int) $this->db->query(
                'SELECT COUNT(*) FROM CompletedTests'
            )->fetchColumn()
        );
    }

    public function testReturnsFalseWhenDeletingANonexistentTest(): void
    {
        // Act
        $result = Test::delete('nonexistent-test');

        // Assert
        self::assertFalse($result);

        self::assertSame(
            0,
            (int) $this->db->query(
                'SELECT COUNT(*) FROM CompletedTests'
            )->fetchColumn()
        );
    }

    public function testRollsBackArchiveWhenArchivingFails(): void
    {
        // Arrange
        $this->addTest();

        $this->db->exec(
            "INSERT INTO CompletedTests (testID, userID, percentageCorrect) VALUES
                ('test-1', 'student-1', 80),
                ('test-1', 'student-2', 100)"
        );

        // Force the archive update to fail.
        $this->db->exec(
            "CREATE TRIGGER prevent_test_archiving
             BEFORE UPDATE OF isActive ON Tests
             BEGIN
                 SELECT RAISE(ABORT, 'Test archiving blocked');
             END"
        );

        // Act and assert: the database error should be rethrown.
        try {
            Test::delete('test-1');
            self::fail('Expected the test archiving to fail.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString(
                'Test archiving blocked',
                $exception->getMessage()
            );
        }

        // Assert: the test remains active and both attempts remain.
        self::assertSame(
            1,
            (int) $this->db->query(
                "SELECT isActive FROM Tests WHERE testID = 'test-1'"
            )->fetchColumn()
        );

        self::assertSame(
            2,
            (int) $this->db->query(
                "SELECT COUNT(*) FROM CompletedTests WHERE testID = 'test-1'"
            )->fetchColumn()
        );
    }

    /**
     * Adds a test record for update and deletion scenarios.
     */
    private function addTest(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Tests
                (testID, testName, testDescription, timeLimit, dueDate,
                 questionCount, subjectID, createdBy)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            'test-1',
            'Original Test',
            'Original description',
            30,
            '2026-11-30',
            10,
            'subject-1',
            'lecturer-1',
        ]);
    }
}
