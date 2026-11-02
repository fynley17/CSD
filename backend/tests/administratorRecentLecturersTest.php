<?php

use App\Models\Administrator;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

/**
 * Checks which lecturers end up in the admin dashboard table and what order they're in
 * Every test gets its own SQLite database in memory, the real one isn't used
 * Tests are laid out Arrange (add the accounts), Act (get the rows), Assert (check them)
 */
final class AdministratorRecentLecturersTest extends TestCase
{
    private PDO $db;

    /**
     * Sets up an empty database with just the columns the query reads
     */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, username TEXT NOT NULL, email TEXT NOT NULL, '
            . 'accountType TEXT NOT NULL, isEnabled INTEGER NOT NULL, lastLogin TEXT)'
        );
        $this->db->exec(
            'CREATE TABLE Lecturer (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL)'
        );

        // Model holds one shared connection (a static property), this replaces it with the test one
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    /**
     * Empties the shared connection again once the test is done
     */
    protected function tearDown(): void
    {
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * Two lecturers, the one who logged in last comes first (normal)
     */
    public function testListsLecturersWithTheLatestLoginFirst(): void
    {
        // Arrange
        $this->addLecturer('ws000101', 'Test', 'Lecturer', 'lecturer', 1, '2026-10-20 09:00:00');
        $this->addLecturer('ws000102', 'Second', 'Lecturer', 'lecturer', 0, '2026-10-25 14:30:00');

        // Act
        $lecturers = Administrator::getRecentLecturers();

        // Assert
        $this->assertSame(
            [
                [
                    'name' => 'Second Lecturer',
                    'staffID' => 'ws000102',
                    'email' => 'ws000102@example.test',
                    'isEnabled' => false,
                    'lastLogin' => '2026-10-25 14:30:00',
                ],
                [
                    'name' => 'Test Lecturer',
                    'staffID' => 'ws000101',
                    'email' => 'ws000101@example.test',
                    'isEnabled' => true,
                    'lastLogin' => '2026-10-20 09:00:00',
                ],
            ],
            $lecturers
        );
    }

    /**
     * Six lecturers but only 5 rows come back, and the one who's never logged in is left off (boundary)
     */
    public function testShowsFiveAndPutsNeverLoggedInLast(): void
    {
        // Arrange
        $this->addLecturer('ws000100', 'Never', 'Lecturer', 'lecturer', 1, null);
        for ($day = 1; $day <= 5; $day++) {
            $lastLogin = '2026-10-0' . $day . ' 10:00:00';
            $this->addLecturer('ws00010' . $day, 'Lecturer', (string) $day, 'lecturer', 1, $lastLogin);
        }

        // Act
        $lecturers = Administrator::getRecentLecturers();

        // Assert
        $this->assertCount(5, $lecturers);
        $this->assertSame('ws000105', $lecturers[0]['staffID']);
        $this->assertNotContains('ws000100', array_column($lecturers, 'staffID'));
    }

    /**
     * An admin and a student are added too, only the lecturer should show (error)
     */
    public function testLeavesAdminsAndStudentsOut(): void
    {
        // Arrange
        $this->addLecturer('ws000101', 'Test', 'Lecturer', 'lecturer', 1, '2026-10-20 09:00:00');
        $this->addLecturer('ws000201', 'Test', 'Administrator', 'admin', 1, '2026-10-25 09:00:00');
        $this->addLecturer('ws000301', 'Test', 'Student', 'student', 1, '2026-10-25 10:00:00');

        // Act
        $lecturers = Administrator::getRecentLecturers();

        // Assert
        $this->assertSame(['ws000101'], array_column($lecturers, 'staffID'));
    }

    /**
     * With the Lecturer table missing the query fails and throws (error)
     */
    public function testThrowsWhenTheDatabaseCannotBeRead(): void
    {
        // Arrange
        // no Lecturer table means the JOIN has nothing to join to
        $this->db->exec('DROP TABLE Lecturer');
        $this->expectException(PDOException::class);

        // Act
        Administrator::getRecentLecturers();

        // Assert
        // nothing to check here, expectException() fails the test if no error is thrown
    }

    /**
     * Adds a Users row and its matching Lecturer row, the email is just username@example.test
     */
    private function addLecturer(
        string $username,
        string $firstName,
        string $lastName,
        string $accountType,
        int $isEnabled,
        ?string $lastLogin
    ): void {
        $userID = 'id-' . $username;

        $stmt = $this->db->prepare(
            'INSERT INTO Users (userID, username, email, accountType, isEnabled, lastLogin) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userID, $username, $username . '@example.test', $accountType, $isEnabled, $lastLogin]);

        $stmt = $this->db->prepare('INSERT INTO Lecturer (userID, firstName, lastName) VALUES (?, ?, ?)');
        $stmt->execute([$userID, $firstName, $lastName]);
    }
}
