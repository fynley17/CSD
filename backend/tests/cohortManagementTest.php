<?php

use App\Models\Cohort;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Cohort model methods behind the cohort management page
 * Each test has its own database in memory, the real one is never touched
 * Laid out Arrange, Act, Assert
 */
final class CohortManagementTest extends TestCase
{
    private PDO $db;

    /**
     * Tables with just the columns the queries read, and two cohorts
     */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Cohorts (cohortID TEXT PRIMARY KEY, cohortName TEXT NOT NULL, '
            . 'createdDate TEXT NOT NULL, isActive INTEGER NOT NULL)'
        );
        $this->db->exec(
            'CREATE TABLE Students (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL, '
            . 'cohortID TEXT NOT NULL)'
        );
        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, username TEXT NOT NULL, email TEXT NOT NULL, '
            . 'lastLogin TEXT, isEnabled INTEGER NOT NULL, approvalStatus TEXT NOT NULL)'
        );
        $this->db->exec(
            'CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL, cohortID TEXT NOT NULL)'
        );
        $this->db->exec('CREATE TABLE Tests (testID TEXT PRIMARY KEY, subjectID TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Lecturer (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL)'
        );
        $this->db->exec('CREATE TABLE LecturerSubjects (lecturerID TEXT NOT NULL, subjectID TEXT NOT NULL)');

        $this->db->exec(
            "INSERT INTO Cohorts (cohortID, cohortName, createdDate, isActive) VALUES
                ('cohort-1', 'Test Beta', '2026-09-01', 1),
                ('cohort-2', 'Test Alpha', '2025-09-01', 0)"
        );

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
     * Cohorts come back A to Z with their status as true or false (normal)
     */
    public function testListsCohortsAToZWithTheirStatus(): void
    {
        // Act
        $cohorts = Cohort::getAllForManagement();

        // Assert
        $this->assertSame(['Test Alpha', 'Test Beta'], array_column($cohorts, 'cohortName'));
        $this->assertSame([false, true], array_column($cohorts, 'isActive'));
        $this->assertSame('2026-09-01', $cohorts[1]['createdDate']);
    }

    /**
     * A cohort with nobody in it still comes back, with empty lists (boundary)
     */
    public function testCohortWithNoStudentsOrSubjectsHasEmptyLists(): void
    {
        // Act
        $cohorts = Cohort::getAllForManagement();

        // Assert
        $this->assertSame([], $cohorts[0]['students']);
        $this->assertSame([], $cohorts[0]['subjects']);
    }

    /**
     * Students are grouped under their own cohort with the status the page shows (normal)
     */
    public function testGroupsStudentsWithTheirStatus(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Active', 'cohort-1', 1, 'approved', '2026-09-29 09:42:10');
        $this->addStudent('user-2', 'Sam', 'Pending', 'cohort-1', 1, 'pending', null);
        $this->addStudent('user-3', 'Sam', 'Disabled', 'cohort-1', 0, 'pending', null);

        // Act
        $cohorts = Cohort::getAllForManagement();

        // Assert
        $students = $cohorts[1]['students'];
        $this->assertSame([], $cohorts[0]['students']);
        $this->assertSame(['Sam Active', 'Sam Disabled', 'Sam Pending'], array_column($students, 'name'));
        $this->assertSame(['Active', 'Disabled', 'Pending'], array_column($students, 'status'));
        $this->assertSame('2026-09-29 09:42:10', $students[0]['lastLogin']);
    }

    /**
     * Subjects come back under their cohort with a test count and lecturer (normal)
     */
    public function testGroupsSubjectsWithTheirTestCountAndLecturer(): void
    {
        // Arrange
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName, cohortID) VALUES
                ('subject-1', 'Test Databases', 'cohort-1'), ('subject-2', 'Test Networks', 'cohort-1')"
        );
        $this->db->exec(
            "INSERT INTO Tests (testID, subjectID) VALUES ('test-1', 'subject-1'), ('test-2', 'subject-1')"
        );
        $this->db->exec(
            "INSERT INTO Lecturer (userID, firstName, lastName) VALUES ('lecturer-1', 'Sample', 'Lecturer')"
        );
        $this->db->exec("INSERT INTO LecturerSubjects (lecturerID, subjectID) VALUES ('lecturer-1', 'subject-1')");

        // Act
        $subjects = Cohort::getAllForManagement()[1]['subjects'];

        // Assert
        $this->assertSame(
            [
                [
                    'subjectID' => 'subject-1',
                    'subjectName' => 'Test Databases',
                    'testCount' => 2,
                    'lecturerNames' => 'Sample Lecturer',
                ],
                [
                    'subjectID' => 'subject-2',
                    'subjectName' => 'Test Networks',
                    'testCount' => 0,
                    'lecturerNames' => null,
                ],
            ],
            $subjects
        );
    }

    /**
     * Disabling and enabling only changes the chosen cohort (normal)
     */
    public function testSetActiveChangesOnlyTheChosenCohort(): void
    {
        // Act
        $disabled = Cohort::setActive('cohort-1', false);
        $enabled = Cohort::setActive('cohort-2', true);

        // Assert
        $this->assertTrue($disabled);
        $this->assertTrue($enabled);
        $this->assertSame([true, false], array_column(Cohort::getAllForManagement(), 'isActive'));
    }

    /**
     * Saving the status a cohort already has still counts as worked (boundary)
     */
    public function testSetActiveWithTheSameStatusStillSucceeds(): void
    {
        // Act and Assert
        $this->assertTrue(Cohort::setActive('cohort-1', true));
    }

    /**
     * A cohort that doesn't exist is reported back and nothing changes (error)
     */
    public function testSetActiveReturnsFalseForAnUnknownCohort(): void
    {
        // Act
        $result = Cohort::setActive('cohort-missing', false);

        // Assert
        $this->assertFalse($result);
        $this->assertSame([false, true], array_column(Cohort::getAllForManagement(), 'isActive'));
    }

    /**
     * Adds one student and their account
     */
    private function addStudent(
        string $userID,
        string $firstName,
        string $lastName,
        string $cohortID,
        int $isEnabled,
        string $approvalStatus,
        ?string $lastLogin
    ): void {
        $this->db->prepare(
            'INSERT INTO Students (userID, firstName, lastName, cohortID) VALUES (?, ?, ?, ?)'
        )->execute([$userID, $firstName, $lastName, $cohortID]);
        $this->db->prepare(
            'INSERT INTO Users (userID, username, email, lastLogin, isEnabled, approvalStatus) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userID, $userID, $userID . '@example.com', $lastLogin, $isEnabled, $approvalStatus]);
    }
}
