<?php

use App\Models\Lecturer;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

final class LecturerDashboardSummaryTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        // Arrange: create an isolated in-memory database for each test.
        // This prevents the tests from changing the development database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the minimum table structures needed by the summary query.
        $this->db->exec(
            'CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                accountType TEXT NOT NULL,
                approvalStatus TEXT NOT NULL,
                isEnabled INTEGER NOT NULL
            )'
        );

        $this->db->exec(
            'CREATE TABLE Students (
                userID TEXT PRIMARY KEY
            )'
        );

        $this->db->exec(
            'CREATE TABLE CompletedTests (
                completedID TEXT PRIMARY KEY
            )'
        );

        // Give the model the temporary database connection.
        // This allows the real Lecturer model to run against test data.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Clean up: reset the shared connection after each test.
        // This stops another test from accidentally reusing this database.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsCorrectDashboardCounts(): void
    {
        // Arrange: create four student profiles.
        foreach ([
            'student-1',
            'student-2',
            'student-3',
            'student-disabled',
        ] as $studentID) {
            $stmt = $this->db->prepare(
                'INSERT INTO Students (userID) VALUES (?)'
            );
            $stmt->execute([$studentID]);
        }

        // Arrange: create users with different approval and enabled statuses.
        $this->addUser('student-1', 'student', 'pending', 1);
        $this->addUser('student-2', 'student', 'pending', 1);
        $this->addUser('student-3', 'student', 'approved', 1);
        $this->addUser('student-disabled', 'student', 'approved', 0);

        // A pending lecturer must not count as a pending student approval.
        $this->addUser('lecturer-1', 'lecturer', 'pending', 1);

        // Disabled accounts are counted across all account types.
        $this->addUser('admin-1', 'admin', 'approved', 0);

        // Arrange: create three completed test attempts.
        foreach (['attempt-1', 'attempt-2', 'attempt-3'] as $attemptID) {
            $stmt = $this->db->prepare(
                'INSERT INTO CompletedTests (completedID) VALUES (?)'
            );
            $stmt->execute([$attemptID]);
        }

        // Act: retrieve the dashboard summary from the model.
        $summary = Lecturer::getDashboardSummary();

        // Assert: verify each count matches the expected result.
        self::assertSame([
            'totalStudents' => 4,
            'pendingApprovals' => 2,
            'completedTestAttempts' => 3,
            'disabledAccounts' => 2,
        ], $summary);
    }

    public function testReturnsZeroWhenThereIsNoDashboardData(): void
    {
        // Arrange: setUp() has created empty tables.

        // Act: retrieve the summary without inserting any records.
        $summary = Lecturer::getDashboardSummary();

        // Assert: every dashboard count should be zero.
        self::assertSame([
            'totalStudents' => 0,
            'pendingApprovals' => 0,
            'completedTestAttempts' => 0,
            'disabledAccounts' => 0,
        ], $summary);
    }

    public function testThrowsExceptionWhenCompletedTestsTableIsMissing(): void
    {
        // Arrange: remove a table required by the dashboard summary query.
        $this->db->exec('DROP TABLE CompletedTests');

        // Act and assert: the model should throw a database exception.
        // If no exception is thrown, PHPUnit marks this test as failed.
        $this->expectException(PDOException::class);

        Lecturer::getDashboardSummary();
    }

    private function addUser(
        string $userID,
        string $accountType,
        string $approvalStatus,
        int $isEnabled
    ): void {
        // Use a prepared statement to keep values separate from the SQL.
        // This avoids constructing SQL by concatenating values.
        $stmt = $this->db->prepare(
            'INSERT INTO Users (
                userID, accountType, approvalStatus, isEnabled
            ) VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $userID,
            $accountType,
            $approvalStatus,
            $isEnabled,
        ]);
    }
}
