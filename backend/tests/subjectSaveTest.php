<?php

use App\Models\Model;
use App\Models\Subject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Subject::save() and Subject::setActive(), the create, edit and archive buttons
 * Each test has its own SQLite database in memory so the real one is never touched
 * Laid out Arrange, Act, Assert
 */
final class SubjectSaveTest extends TestCase
{
    private PDO $db;

    /**
     * Empty tables with just the columns the save and archive queries use
     */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL, '
            . 'cohortID TEXT NOT NULL, isActive INTEGER NOT NULL)'
        );
        $this->db->exec('CREATE TABLE LecturerSubjects (lecturerID TEXT NOT NULL, subjectID TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Lecturer (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL)'
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
     * A new subject starts active and gets the lecturer that was picked (normal)
     */
    public function testCreatesAnActiveSubjectWithItsLecturer(): void
    {
        // Act
        $saved = Subject::save(null, '  Test Databases  ', 'cohort-1', ['lecturer-1']);

        // Assert
        $subject = $this->db->query('SELECT * FROM Subjects')->fetch(PDO::FETCH_ASSOC);
        $lecturers = $this->db->query('SELECT lecturerID FROM LecturerSubjects')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertTrue($saved);
        $this->assertSame('Test Databases', $subject['subjectName']);
        $this->assertSame(1, (int) $subject['isActive']);
        $this->assertSame(['lecturer-1'], $lecturers);
    }

    /**
     * Editing changes the name and swaps the old lecturer for the new one (normal)
     */
    public function testEditChangesTheNameAndReplacesTheLecturers(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Subjects VALUES ('subject-1', 'Test Databases', 'cohort-1', 1)");
        $this->db->exec("INSERT INTO LecturerSubjects VALUES ('lecturer-1', 'subject-1')");

        // Act
        $saved = Subject::save('subject-1', 'Test Networks', 'cohort-1', ['lecturer-2']);

        // Assert
        $name = $this->db->query('SELECT subjectName FROM Subjects')->fetchColumn();
        $lecturers = $this->db->query('SELECT lecturerID FROM LecturerSubjects')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertTrue($saved);
        $this->assertSame('Test Networks', $name);
        $this->assertSame(['lecturer-2'], $lecturers);
    }

    /**
     * Same name in the same cohort is turned away, even with different letter case (error)
     */
    public function testRejectsADuplicateNameInTheSameCohort(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Subjects VALUES ('subject-1', 'Test Databases', 'cohort-1', 1)");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This cohort already has a subject with that name');

        // Act
        Subject::save(null, 'test databases', 'cohort-1', []);
    }

    /**
     * Archive turns the subject off, restore turns it back on (normal)
     */
    public function testArchiveAndRestoreChangeIsActive(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Subjects VALUES ('subject-1', 'Test Databases', 'cohort-1', 1)");

        // Act and Assert
        $this->assertTrue(Subject::setActive('subject-1', false));
        $this->assertSame(0, (int) $this->db->query('SELECT isActive FROM Subjects')->fetchColumn());

        $this->assertTrue(Subject::setActive('subject-1', true));
        $this->assertSame(1, (int) $this->db->query('SELECT isActive FROM Subjects')->fetchColumn());
    }

    /**
     * The form's pick list has every lecturer, surname A to Z (normal)
     */
    public function testListsTheLecturersForTheForm(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Lecturer VALUES ('lecturer-1', 'Zoe', 'Younger')");
        $this->db->exec("INSERT INTO Lecturer VALUES ('lecturer-2', 'Alex', 'Adams')");

        // Act
        $options = Subject::getLecturerOptions();

        // Assert
        $this->assertSame(
            [
                ['lecturerID' => 'lecturer-2', 'name' => 'Alex Adams'],
                ['lecturerID' => 'lecturer-1', 'name' => 'Zoe Younger'],
            ],
            $options
        );
    }
}
