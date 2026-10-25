<?php

use App\Models\Administrator;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the admin dashboard totals, the numbers on the four cards
 * a small pretend database is made in memory for each test, so the real one is never touched
 * each test is laid out Arrange (add the rows), Act (count them), Assert (check the numbers)
 */
final class AdministratorDashboardSummaryTest extends TestCase
{
    private PDO $db;

    /**
     * Makes an empty in-memory database with only the columns the totals need, and hands it to the models
     */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, accountType TEXT NOT NULL, isEnabled INTEGER NOT NULL)'
        );
        $this->db->exec('CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, isActive INTEGER NOT NULL)');

        // the models share one connection, this swaps it for the pretend database
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    /**
     * Puts the shared connection back to empty, so the next test file can't end up using this database
     */
    protected function tearDown(): void
    {
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * Counts lecturers and subjects, including the disabled and archived ones (normal)
     */
    public function testCountsSubjectsAndLecturers(): void
    {
        // Arrange
        $this->addUser('lecturer-1', 'lecturer', 1);
        $this->addUser('lecturer-2', 'lecturer', 1);
        $this->addUser('lecturer-disabled', 'lecturer', 0);
        $this->addSubject('subject-1', 1);
        $this->addSubject('subject-2', 1);
        $this->addSubject('subject-archived', 0);

        // Act
        $summary = Administrator::getDashboardSummary();

        // Assert
        $this->assertSame(
            ['totalSubjects' => 3, 'totalLecturers' => 3, 'archivedSubjects' => 1, 'disabledLecturers' => 1],
            $summary
        );
    }

    /**
     * A brand new system with no subjects or lecturers shows 0 on every card, not an error (boundary)
     */
    public function testShowsZeroWhenThereIsNothingYet(): void
    {
        // Arrange
        // nothing added, both tables are empty

        // Act
        $summary = Administrator::getDashboardSummary();

        // Assert
        $this->assertSame(
            ['totalSubjects' => 0, 'totalLecturers' => 0, 'archivedSubjects' => 0, 'disabledLecturers' => 0],
            $summary
        );
    }

    /**
     * Admins and students, even disabled ones, aren't counted as lecturers (error)
     */
    public function testLeavesAdminsAndStudentsOutOfTheLecturerTotals(): void
    {
        // Arrange
        $this->addUser('lecturer-1', 'lecturer', 1);
        $this->addUser('admin-1', 'admin', 1);
        $this->addUser('student-disabled', 'student', 0);

        // Act
        $summary = Administrator::getDashboardSummary();

        // Assert
        $this->assertSame(1, $summary['totalLecturers']);
        $this->assertSame(0, $summary['disabledLecturers']);
    }

    /**
     * If the database can't be read the model throws, so the page shows an error and not a row of 0s (error)
     */
    public function testThrowsWhenTheDatabaseCannotBeRead(): void
    {
        // Arrange
        // takes away the Subjects table so the counts can't run
        $this->db->exec('DROP TABLE Subjects');
        $this->expectException(PDOException::class);

        // Act
        Administrator::getDashboardSummary();

        // Assert
        // expectException() above is the check, the test fails if nothing gets thrown
    }

    /**
     * Adds one account to the pretend Users table
     */
    private function addUser(string $userID, string $accountType, int $isEnabled): void
    {
        $stmt = $this->db->prepare('INSERT INTO Users (userID, accountType, isEnabled) VALUES (?, ?, ?)');
        $stmt->execute([$userID, $accountType, $isEnabled]);
    }

    /**
     * Adds one subject to the pretend Subjects table, isActive 0 means archived
     */
    private function addSubject(string $subjectID, int $isActive): void
    {
        $stmt = $this->db->prepare('INSERT INTO Subjects (subjectID, isActive) VALUES (?, ?)');
        $stmt->execute([$subjectID, $isActive]);
    }
}
