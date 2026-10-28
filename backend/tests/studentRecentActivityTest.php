<?php

use App\Models\Model;
use App\Models\Student;
use PHPUnit\Framework\TestCase;

final class StudentRecentActivityTest extends TestCase
{
    private \Pdo\Sqlite $db;

    protected function setUp(): void
    {
        // Arrange: create an isolated in-memory SQLite database.
        // This prevents the test from changing the development database.
        $this->db = new \Pdo\Sqlite('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Register CONCAT_WS so SQLite can run the name-building query
        // used by the Student model in MariaDB.
        $this->db->createFunction(
            'CONCAT_WS',
            static function ($separator, ...$values): string {
                $values = array_filter(
                    $values,
                    static fn ($value): bool => $value !== null
                );

                return implode((string) $separator, $values);
            },
            -1
        );

        // Create the minimum Users table structure required by the query.
        $this->db->exec(
            'CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                username TEXT NOT NULL,
                email TEXT NOT NULL,
                accountType TEXT NOT NULL,
                approvalStatus TEXT NOT NULL,
                isEnabled INTEGER NOT NULL,
                lastLogin TEXT NULL
            )'
        );

        // Create the minimum Students table structure required by the query.
        $this->db->exec(
            'CREATE TABLE Students (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL
            )'
        );

        // Give the Student model the temporary database connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Clean up: prevent other tests from reusing this connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsLoggedInStudentsInNewestFirstOrderAndExcludesMissingLogins(): void
    {
        // Arrange: create students with different login and account statuses.
        $this->addStudent(
            'user-old',
            'Alex',
            'Old',
            'ws000001',
            'alex@example.com',
            '2026-10-20 09:00:00'
        );

        $this->addStudent(
            'user-disabled',
            'Casey',
            'Disabled',
            'ws000002',
            'casey@example.com',
            '2026-10-22 09:00:00',
            'approved',
            0
        );

        $this->addStudent(
            'user-pending',
            'Jamie',
            'Pending',
            'ws000003',
            'jamie@example.com',
            '2026-10-23 09:00:00',
            'pending'
        );

        // This student must not appear because they have never logged in.
        $this->addStudent(
            'user-no-login',
            'Taylor',
            'New',
            'ws000004',
            'taylor@example.com',
            null
        );

        // Act: retrieve recent activity using the real Student model.
        $students = Student::getRecentActivity();

        // Assert: only logged-in students are returned, newest first.
        self::assertCount(3, $students);

        self::assertSame(
            ['ws000003', 'ws000002', 'ws000001'],
            array_column($students, 'studentNumber')
        );

        // Assert: the returned data contains the correct student details.
        self::assertSame('Jamie Pending', $students[0]['name']);
        self::assertSame('jamie@example.com', $students[0]['email']);
        self::assertSame('Pending approval', $students[0]['status']);

        self::assertSame('Disabled', $students[1]['status']);
        self::assertSame('Active', $students[2]['status']);
    }

    public function testReturnsNoMoreThanFiveStudents(): void
    {
        // Arrange: create six students with recorded login times.
        for ($i = 1; $i <= 6; $i++) {
            $this->addStudent(
                'user-' . $i,
                'Student',
                (string) $i,
                sprintf('ws%06d', $i),
                'student' . $i . '@example.com',
                sprintf('2026-10-%02d 09:00:00', 19 + $i)
            );
        }

        // Act: retrieve recent activity.
        $students = Student::getRecentActivity();

        // Assert: only the five most recent students are returned.
        self::assertCount(5, $students);

        self::assertSame(
            ['ws000006', 'ws000005', 'ws000004', 'ws000003', 'ws000002'],
            array_column($students, 'studentNumber')
        );
    }

    private function addStudent(
        string $userID,
        string $firstName,
        string $lastName,
        string $username,
        string $email,
        ?string $lastLogin,
        string $approvalStatus = 'approved',
        int $isEnabled = 1
    ): void {
        // Arrange: insert the account information using a prepared statement.
        $stmt = $this->db->prepare(
            'INSERT INTO Users (
                userID,
                username,
                email,
                accountType,
                approvalStatus,
                isEnabled,
                lastLogin
            ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $userID,
            $username,
            $email,
            'student',
            $approvalStatus,
            $isEnabled,
            $lastLogin,
        ]);

        // Arrange: insert the matching student profile.
        $stmt = $this->db->prepare(
            'INSERT INTO Students (userID, firstName, lastName)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([$userID, $firstName, $lastName]);
    }
}
