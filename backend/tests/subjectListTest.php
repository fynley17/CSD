<?php

use App\Models\Model;
use App\Models\Subject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Subject::getAll(), which loads the subjects table on the admin dashboard
 * Each test uses its own database in memory, the real database isn't used
 */
final class SubjectListTest extends TestCase
{
    private PDO $db;

    /**
     * Create the tables the query uses, with the columns it needs
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

        // Point the models at the test database.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    /**
     * Clear the test database connection after each test
     */
    protected function tearDown(): void
    {
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * Normal: a subject comes back with its cohort, test count, lecturer and status
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
     * Boundary: a subject with no tests has a test count of 0
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
     * Boundary: a subject with two lecturers is one row, with both names sorted by surname
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
     * Boundary: a subject with no lecturer has null for lecturerNames
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
     * Boundary: archived subjects come after active ones, and each group is sorted by name
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
     * Error: if the query fails, getAll() throws so the controller can send back an error
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
        // expectException() fails the test if nothing is thrown
    }

    /**
     * Add a subject to Test Cohort with the given number of tests
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
     * Add a lecturer and link them to the given subjects
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
