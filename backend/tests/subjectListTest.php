<?php

use App\Models\Model;
use App\Models\Subject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Subject::getAll(), what the admin dashboard subjects table gets
 * Each test has its own database in memory, the real one is never touched
 * Laid out Arrange, Act, Assert
 */
final class SubjectListTest extends TestCase
{
    private PDO $db;

    /**
     * Empty tables with just the columns the query reads
     */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec('CREATE TABLE Cohorts (cohortID TEXT PRIMARY KEY, cohortName TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL, '
            . 'cohortID TEXT NOT NULL, isActive INTEGER NOT NULL)'
        );
        $this->db->exec('CREATE TABLE Tests (testID TEXT PRIMARY KEY, subjectID TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Lecturer (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL)'
        );
        $this->db->exec('CREATE TABLE LecturerSubjects (lecturerID TEXT NOT NULL, subjectID TEXT NOT NULL)');

        $this->db->exec("INSERT INTO Cohorts (cohortID, cohortName) VALUES ('cohort-1', 'Test Cohort')");

        // swap Model's database connection for the test one
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    /**
     * Clears the test connection after each test
     */
    protected function tearDown(): void
    {
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * A subject comes back with its cohort, test count, lecturer and status (normal)
     */
    public function testReturnsTheSubjectWithItsCohortCountAndLecturer(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'Test Databases', 1, 2);
        $this->addLecturer('lecturer-1', 'Sample', 'Lecturer', ['subject-1']);

        // Act
        $subjects = Subject::getAll();

        // Assert
        $this->assertSame(
            [
                [
                    'subjectName' => 'Test Databases',
                    'cohortName' => 'Test Cohort',
                    'testCount' => 2,
                    'lecturerNames' => 'Sample Lecturer',
                    'isActive' => true,
                ],
            ],
            $subjects
        );
    }

    /**
     * New subject with no tests shows 0, not null (boundary)
     */
    public function testCountsZeroForASubjectWithNoTests(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'Test Networking', 1, 0);

        // Act
        $subjects = Subject::getAll();

        // Assert
        $this->assertSame(0, $subjects[0]['testCount']);
    }

    /**
     * Two lecturers on one subject is still one row, names A to Z by surname (boundary)
     */
    public function testListsEveryLecturerOnASubjectInOneRow(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'Test Databases', 1, 0);
        $this->addLecturer('lecturer-1', 'Zoe', 'Younger', ['subject-1']);
        $this->addLecturer('lecturer-2', 'Alex', 'Adams', ['subject-1']);

        // Act
        $subjects = Subject::getAll();

        // Assert
        $this->assertCount(1, $subjects);
        $this->assertSame('Alex Adams, Zoe Younger', $subjects[0]['lecturerNames']);
    }

    /**
     * No lecturer assigned gives null, so the table can say Not assigned (boundary)
     */
    public function testHasNoLecturerNamesWhenNobodyIsAssigned(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'Test Databases', 1, 0);

        // Act
        $subjects = Subject::getAll();

        // Assert
        $this->assertNull($subjects[0]['lecturerNames']);
    }

    /**
     * Archived subjects go after the active ones, each group A to Z (boundary)
     */
    public function testListsActiveSubjectsBeforeArchivedOnes(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'Test Web Apps', 1, 0);
        $this->addSubject('subject-2', 'Test Archived', 0, 0);
        $this->addSubject('subject-3', 'Test Databases', 1, 0);

        // Act
        $subjects = Subject::getAll();

        // Assert
        $this->assertSame(
            ['Test Databases', 'Test Web Apps', 'Test Archived'],
            array_column($subjects, 'subjectName')
        );
        $this->assertFalse($subjects[2]['isActive']);
    }

    /**
     * Query fails (Cohorts table gone) so it throws, then the controller sends a 500 (error)
     */
    public function testThrowsWhenTheDatabaseCannotBeRead(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'Test Databases', 1, 0);
        $this->db->exec('DROP TABLE Cohorts');
        $this->expectException(PDOException::class);

        // Act
        Subject::getAll();

        // Assert
        // expectException() above fails the test if nothing's thrown
    }

    /**
     * Adds a subject to Test Cohort with however many tests
     */
    private function addSubject(string $subjectID, string $subjectName, int $isActive, int $tests): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Subjects (subjectID, subjectName, cohortID, isActive) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$subjectID, $subjectName, 'cohort-1', $isActive]);

        for ($i = 1; $i <= $tests; $i++) {
            $stmt = $this->db->prepare('INSERT INTO Tests (testID, subjectID) VALUES (?, ?)');
            $stmt->execute([$subjectID . '-test-' . $i, $subjectID]);
        }
    }

    /**
     * Adds a lecturer and links them to their subjects
     *
     * @param array<int, string> $subjectIDs
     */
    private function addLecturer(string $lecturerID, string $firstName, string $lastName, array $subjectIDs): void
    {
        $stmt = $this->db->prepare('INSERT INTO Lecturer (userID, firstName, lastName) VALUES (?, ?, ?)');
        $stmt->execute([$lecturerID, $firstName, $lastName]);

        foreach ($subjectIDs as $subjectID) {
            $stmt = $this->db->prepare('INSERT INTO LecturerSubjects (lecturerID, subjectID) VALUES (?, ?)');
            $stmt->execute([$lecturerID, $subjectID]);
        }
    }
}
