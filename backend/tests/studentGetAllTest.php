
<?php

use App\Models\Model;
use App\Models\Student;
use PHPUnit\Framework\TestCase;

class StudentGetAllTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        // Arrange: Create an in-memory SQLite database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("
            CREATE TABLE Users (
                userID INTEGER PRIMARY KEY,
                username TEXT,
                email TEXT,
                accountType TEXT,
                approvalStatus TEXT,
                isEnabled INTEGER,
                lastLogin TEXT
            );

            CREATE TABLE Students (
                userID INTEGER PRIMARY KEY,
                firstName TEXT,
                lastName TEXT,
                cohortID INTEGER
            );

            CREATE TABLE Cohorts (
                cohortID INTEGER PRIMARY KEY,
                cohortName TEXT
            );

            CREATE TABLE Subjects (
                subjectID INTEGER PRIMARY KEY,
                subjectName TEXT,
                cohortID INTEGER
            );
        ");

        // Inject the test database into the model.
        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Clean up the database connection.
        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, null);
    }

    public function testReturnsStudentsWithTheirCohortSubjectsAndStatuses(): void
    {
        // Arrange: Add cohorts.
        $this->db->exec("
            INSERT INTO Cohorts (cohortID, cohortName) VALUES
            (1, 'Cohort A'),
            (2, 'Cohort B')
        ");

        // Arrange: Add subjects.
        $this->db->exec("
            INSERT INTO Subjects (subjectID, subjectName, cohortID) VALUES
            (1, 'Mathematics', 1),
            (2, 'English', 1),
            (3, 'Science', 2)
        ");

        // Arrange: Add student and non-student users.
        $this->db->exec("
            INSERT INTO Users
                (userID, username, email, accountType, approvalStatus, isEnabled, lastLogin)
            VALUES
                (1, 'ws000001', 'alex@example.com', 'student', 'approved', 1, '2026-10-01'),
                (2, 'ws000002', 'jamie@example.com', 'student', 'pending', 1, NULL),
                (3, 'ws000003', 'taylor@example.com', 'student', 'approved', 0, NULL),
                (4, 'admin001', 'admin@example.com', 'admin', 'approved', 1, NULL)
        ");

        // Arrange: Add student records.
        $this->db->exec("
            INSERT INTO Students (userID, firstName, lastName, cohortID) VALUES
                (1, 'Alex', 'Morgan', 1),
                (2, 'Jamie', 'Wilson', 2),
                (3, 'Taylor', 'Smith', 1)
        ");

        // Act: Retrieve all students.
        $students = Student::getAll();

        // Assert: Only student accounts are returned, in surname order.
        $this->assertCount(3, $students);
        $this->assertSame(
            ['ws000001', 'ws000003', 'ws000002'],
            array_column($students, 'studentNumber')
        );

        // Assert: Student details, cohort and subjects are returned.
        $this->assertSame('Alex Morgan', $students[0]['name']);
        $this->assertSame('Cohort A', $students[0]['cohort']);
        $this->assertSame('English, Mathematics', $students[0]['subjects']);
        $this->assertSame('alex@example.com', $students[0]['email']);
        $this->assertSame('2026-10-01', $students[0]['lastLogin']);

        // Assert: Student statuses are correctly identified.
        $this->assertSame('Active', $students[0]['status']);
        $this->assertSame('Disabled', $students[1]['status']);
        $this->assertSame('Pending', $students[2]['status']);

        // Assert: The second student's details are correct.
        $this->assertSame('Taylor Smith', $students[1]['name']);
        $this->assertSame('Cohort A', $students[1]['cohort']);
        $this->assertSame('English, Mathematics', $students[1]['subjects']);

        // Assert: The third student's details are correct.
        $this->assertSame('Jamie Wilson', $students[2]['name']);
        $this->assertSame('Cohort B', $students[2]['cohort']);
        $this->assertSame('Science', $students[2]['subjects']);
    }

    public function testReturnsAnEmptyArrayWhenThereAreNoStudents(): void
    {
        // Arrange: No student records are inserted.

        // Act: Retrieve all students.
        $students = Student::getAll();

        // Assert: An empty array is returned.
        $this->assertSame([], $students);
    }
}
