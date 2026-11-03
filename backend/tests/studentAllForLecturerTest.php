
<?php

use App\Models\Model;
use App\Models\Student;
use PHPUnit\Framework\TestCase;

final class StudentAllForLecturerTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        // Arrange: create an isolated in-memory SQLite database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                username TEXT NOT NULL,
                email TEXT NOT NULL,
                accountType TEXT NOT NULL,
                approvalStatus TEXT NOT NULL,
                isEnabled INTEGER NOT NULL DEFAULT 1,
                lastLogin TEXT NULL
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
            'CREATE TABLE Students (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL,
                points INTEGER NOT NULL DEFAULT 0,
                cohortID TEXT NOT NULL
            )'
        );

        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL,
                cohortID TEXT NOT NULL
            )'
        );

        // Give the Student model the temporary database connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);

        // Arrange: create test cohorts.
        $this->db->exec(
            "INSERT INTO Cohorts (cohortID, cohortName) VALUES
                ('cohort-1', 'Demo Cohort'),
                ('cohort-2', 'Science Cohort')"
        );
    }

    protected function tearDown(): void
    {
        // Prevent other tests from reusing this connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    private function insertStudent(
        string $userID,
        string $firstName,
        string $lastName,
        string $username,
        string $email,
        string $cohortID,
        string $approvalStatus = 'approved',
        int $isEnabled = 1,
        ?string $lastLogin = null
    ): void {
        // Arrange: insert the student's user account.
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
            'INSERT INTO Students (
                userID,
                firstName,
                lastName,
                cohortID
            ) VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $userID,
            $firstName,
            $lastName,
            $cohortID,
        ]);
    }

    public function testReturnsStudentsWithTheirDetailsAndCohorts(): void
    {
        // Arrange: create two students.
        $this->insertStudent(
            'student-1',
            'Alex',
            'Morgan',
            'ws000001',
            'alex@example.com',
            'cohort-1'
        );

        $this->insertStudent(
            'student-2',
            'Jamie',
            'Wilson',
            'ws000002',
            'jamie@example.com',
            'cohort-2'
        );

        // Act: retrieve all students using the real Student model.
        $students = Student::getAll();

        // Assert: both students are returned.
        self::assertCount(2, $students);

        // Assert: students are ordered alphabetically by last name.
        self::assertSame(
            ['Alex Morgan', 'Jamie Wilson'],
            array_column($students, 'name')
        );

        // Assert: student numbers and email addresses are returned.
        self::assertSame('ws000001', $students[0]['studentNumber']);
        self::assertSame('alex@example.com', $students[0]['email']);

        // Assert: cohort names are included.
        self::assertSame('Demo Cohort', $students[0]['cohort']);
        self::assertSame('Science Cohort', $students[1]['cohort']);

        // Assert: active students have the correct status.
        self::assertSame('Active', $students[0]['status']);
        self::assertSame('Active', $students[1]['status']);
    }

    public function testReturnsPendingAndDisabledStudentStatuses(): void
    {
        // Arrange: create a pending student.
        $this->insertStudent(
            'student-1',
            'Alex',
            'Morgan',
            'ws000001',
            'alex@example.com',
            'cohort-1',
            'pending'
        );

        // Arrange: create a disabled student.
        $this->insertStudent(
            'student-2',
            'Jamie',
            'Wilson',
            'ws000002',
            'jamie@example.com',
            'cohort-1',
            'approved',
            0
        );

        // Act: retrieve all students.
        $students = Student::getAll();

        // Assert: both accounts are returned with their correct statuses.
        self::assertCount(2, $students);
        self::assertSame('Pending', $students[0]['status']);
        self::assertSame('Disabled', $students[1]['status']);
    }

    public function testReturnsLastLoginInformation(): void
    {
        // Arrange: create a student with a previous login.
        $lastLogin = '2026-11-01 10:30:00';

        $this->insertStudent(
            'student-1',
            'Alex',
            'Morgan',
            'ws000001',
            'alex@example.com',
            'cohort-1',
            'approved',
            1,
            $lastLogin
        );

        // Act: retrieve all students.
        $students = Student::getAll();

        // Assert: the last login date is returned.
        self::assertCount(1, $students);
        self::assertSame($lastLogin, $students[0]['lastLogin']);
    }

    public function testReturnsSubjectsForEachStudentsCohort(): void
    {
        // Arrange: create students in different cohorts.
        $this->insertStudent(
            'student-1',
            'Alex',
            'Morgan',
            'ws000001',
            'alex@example.com',
            'cohort-1'
        );

        $this->insertStudent(
            'student-2',
            'Jamie',
            'Wilson',
            'ws000002',
            'jamie@example.com',
            'cohort-2'
        );

        // Arrange: assign subjects to each cohort.
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName, cohortID) VALUES
                ('subject-1', 'Mathematics', 'cohort-1'),
                ('subject-2', 'English', 'cohort-1'),
                ('subject-3', 'Science', 'cohort-2'),
                ('subject-4', 'English', 'cohort-1')"
        );

        // Act: retrieve all students.
        $students = Student::getAll();

        // Assert: subjects are grouped by cohort.
        self::assertSame(
            'English, Mathematics',
            $students[0]['subjects']
        );

        self::assertSame(
            'Science',
            $students[1]['subjects']
        );

        // Assert: duplicate subjects are not returned.
        self::assertSame(
            2,
            count(explode(', ', $students[0]['subjects']))
        );
    }

    public function testDoesNotReturnNonStudentAccounts(): void
    {
        // Arrange: create a lecturer account.
        $this->db->exec(
            "INSERT INTO Users (
                userID,
                username,
                email,
                accountType,
                approvalStatus,
                isEnabled
            ) VALUES (
                'lecturer-1',
                'lecturer1',
                'lecturer@example.com',
                'lecturer',
                'approved',
                1
            )"
        );

        // Act: retrieve all students.
        $students = Student::getAll();

        // Assert: lecturer accounts are excluded.
        self::assertSame([], $students);
    }

    public function testReturnsAnEmptyArrayWhenThereAreNoStudents(): void
    {
        // Act: retrieve all students from an empty database.
        $students = Student::getAll();

        // Assert: an empty array is returned.
        self::assertSame([], $students);
    }
}
