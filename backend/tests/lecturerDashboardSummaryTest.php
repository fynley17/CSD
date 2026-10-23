<?php

use App\Models\Lecturer;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

final class LecturerDashboardSummaryTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        // Create a temporary database for each test.
        // This keeps the tests separate from the real development database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create only the tables and columns needed by the dashboard summary.
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

        // Inject the temporary database connection into the shared Model class.
        // This allows the real Lecturer model to run against the test data.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Reset the shared connection so another test cannot reuse this database.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsCorrectDashboardCounts(): void
    {
        // Create four student profiles, including one for a disabled student.
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

        // Two students are awaiting approval.
        $this->addUser('student-1', 'student', 'pending', 1);
        $this->addUser('student-2', 'student', 'pending', 1);

        // One student is approved and enabled.
        $this->addUser('student-3', 'student', 'approved', 1);

        // This student is approved but disabled.
        $this->addUser('student-disabled', 'student', 'approved', 0);

        // A pending lecturer must not count as a pending student approval.
        $this->addUser('lecturer-1', 'lecturer', 'pending', 1);

        // Disabled accounts are counted across account types.
        $this->addUser('admin-1', 'admin', 'approved', 0);

        // Create three completed test attempts.
        foreach (['attempt-1', 'attempt-2', 'attempt-3'] as $attemptID) {
            $stmt = $this->db->prepare(
                'INSERT INTO CompletedTests (completedID) VALUES (?)'
            );
            $stmt->execute([$attemptID]);
        }

        // Call the actual model method and compare its results with the expected counts.
        $summary = Lecturer::getDashboardSummary();

        self::assertSame([
            'totalStudents' => 4,
            'pendingApprovals' => 2,
            'completedTestAttempts' => 3,
            'disabledAccounts' => 2,
        ], $summary);
    }

    public function testReturnsZeroWhenThereIsNoDashboardData(): void
    {
        // An empty database should return zero for every dashboard card.
        self::assertSame([
            'totalStudents' => 0,
            'pendingApprovals' => 0,
            'completedTestAttempts' => 0,
            'disabledAccounts' => 0,
        ], Lecturer::getDashboardSummary());
    }

    private function addUser(
        string $userID,
        string $accountType,
        string $approvalStatus,
        int $isEnabled
    ): void {
        // Use a prepared statement so values are bound separately from the SQL.
        // This avoids building SQL by concatenating supplied values.
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
