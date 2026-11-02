<?php

use App\Models\Administrator;
use App\Models\Lecturer;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests for what an admin can do to lecturer accounts
 * uses a pretend Users table in memory, the real database isn't touched
 * Tests are laid out Arrange (make the accounts), Act (press the button), Assert (check the table)
 */
final class LecturerManagementTest extends TestCase
{
    private \PDO $db;

    /**
     * Fresh Users table for every test with one admin and two lecturers, all enabled
     */
    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE Users (userID TEXT PRIMARY KEY, isEnabled INTEGER NOT NULL)');
        $this->db->exec("INSERT INTO Users VALUES ('admin-id', 1), ('lecturer-id', 1), ('other-id', 1)");

        // points the models at the pretend database instead of the real one
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    /**
     * Unhooks the pretend database so the next test file starts clean
     */
    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * Disable account turns off that lecturer and leaves the other one alone (normal)
     */
    public function testDisablesOnlyThatLecturer(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));

        // Act
        $admin->disableLecturer($lecturer);

        // Assert
        $this->assertSame(0, $this->getIsEnabled('lecturer-id'));
        $this->assertSame(1, $this->getIsEnabled('other-id'));
    }

    /**
     * Enable account brings a disabled lecturer back, this is also what Undo uses (normal)
     */
    public function testEnablesADisabledLecturerAgain(): void
    {
        // Arrange
        $this->db->exec("UPDATE Users SET isEnabled = 0 WHERE userID = 'lecturer-id'");
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));

        // Act
        $admin->enableLecturer($lecturer);

        // Assert
        $this->assertSame(1, $this->getIsEnabled('lecturer-id'));
    }

    /**
     * An admin can't disable an admin account, their own included, and nothing changes (error)
     */
    public function testRefusesToDisableAnAdministrator(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $message = '';

        // Act
        try {
            $admin->disableLecturer($admin);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $this->assertSame("Administrator accounts can't be changed on this page", $message);
        $this->assertSame(1, $this->getIsEnabled('admin-id'));
    }

    /**
     * A made up Users row, same keys the real query gives, the password is just filler
     */
    private function makeRow(string $userID, string $accountType): array
    {
        return [
            'userID' => $userID,
            'username' => 'test' . $accountType,
            'password' => 'not-a-real-hash',
            'accountType' => $accountType,
            'approvalStatus' => 'approved',
            'isEnabled' => 1,
            'failedAttempts' => 0,
            'lockedUntil' => null,
            'isEmailVerified' => 1,
        ];
    }

    /**
     * Reads isEnabled straight from the table so the test checks what was really saved
     */
    private function getIsEnabled(string $userID): int
    {
        $stmt = $this->db->prepare('SELECT isEnabled FROM Users WHERE userID = ?');
        $stmt->execute([$userID]);

        return (int) $stmt->fetchColumn();
    }
}
