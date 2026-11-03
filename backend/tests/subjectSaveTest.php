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
     * Tables with just the columns the queries use, plus a cohort and 2 lecturers
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

        $this->db->exec('CREATE TABLE Cohorts (cohortID TEXT PRIMARY KEY, cohortName TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE Tests (testID TEXT PRIMARY KEY, subjectID TEXT NOT NULL)');

        // every test can use this cohort and these 2 lecturers
        $this->db->exec("INSERT INTO Cohorts VALUES ('cohort-1', 'Test Cohort')");
        $this->db->exec("INSERT INTO Cohorts VALUES ('cohort-2', 'Second Cohort')");
        $this->db->exec("INSERT INTO Lecturer VALUES ('lecturer-1', 'Zoe', 'Younger')");
        $this->db->exec("INSERT INTO Lecturer VALUES ('lecturer-2', 'Alex', 'Adams')");

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
     * Saving into a cohort that isn't there gives the clear message (error)
     */
    public function testRejectsACohortThatDoesNotExist(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('That cohort no longer exists');

        // Act
        Subject::save(null, 'Test Databases', 'cohort-9', ['lecturer-1']);
    }

    /**
     * One made up lecturer in the list stops the whole save (error)
     */
    public function testRejectsALecturerThatDoesNotExist(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('One of the lecturers no longer exists');

        // Act
        Subject::save(null, 'Test Databases', 'cohort-1', ['lecturer-1', 'lecturer-9']);
    }

    /**
     * A subject with tests can't be moved to another cohort (error)
     */
    public function testRejectsMovingASubjectThatHasTests(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Subjects VALUES ('subject-1', 'Test Databases', 'cohort-1', 1)");
        $this->db->exec("INSERT INTO Tests VALUES ('test-1', 'subject-1')");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A subject with tests cannot move to another cohort');

        // Act
        Subject::save('subject-1', 'Test Databases', 'cohort-2', []);
    }

    /**
     * A subject with tests can still be renamed while it stays in its cohort (normal)
     */
    public function testRenamesASubjectThatHasTests(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Subjects VALUES ('subject-1', 'Test Databases', 'cohort-1', 1)");
        $this->db->exec("INSERT INTO Tests VALUES ('test-1', 'subject-1')");

        // Act
        $saved = Subject::save('subject-1', 'Test Networks', 'cohort-1', []);

        // Assert
        $name = $this->db->query('SELECT subjectName FROM Subjects')->fetchColumn();
        $this->assertTrue($saved);
        $this->assertSame('Test Networks', $name);
    }

    /**
     * A subject with no tests can move to another cohort (boundary)
     */
    public function testMovesASubjectThatHasNoTests(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO Subjects VALUES ('subject-1', 'Test Databases', 'cohort-1', 1)");

        // Act
        $saved = Subject::save('subject-1', 'Test Databases', 'cohort-2', []);

        // Assert
        $cohort = $this->db->query('SELECT cohortID FROM Subjects')->fetchColumn();
        $this->assertTrue($saved);
        $this->assertSame('cohort-2', $cohort);
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
        // Arrange (setUp already put the 2 lecturers in)

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
