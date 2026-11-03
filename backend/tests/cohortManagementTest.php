<?php

use App\Controllers\CohortController;
use App\Models\Cohort;
use App\Models\Model;
use App\Models\Student;
use App\Models\Subject;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'CREATE TABLE Cohorts (cohortID TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))), '
            . "cohortName TEXT NOT NULL UNIQUE COLLATE NOCASE, createdDate TEXT NOT NULL DEFAULT '2026-11-03', "
            . 'isActive INTEGER NOT NULL DEFAULT 1)'
        );
        $this->db->exec(
            'CREATE TABLE Students (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL, '
            . 'cohortID TEXT NOT NULL)'
        );
        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, username TEXT NOT NULL, email TEXT NOT NULL, '
            . 'lastLogin TEXT, isEmailVerified INTEGER NOT NULL DEFAULT 0, '
            . 'isEnabled INTEGER NOT NULL, disabledByCohort INTEGER NOT NULL DEFAULT 0, '
            . 'approvalStatus TEXT NOT NULL)'
        );
        $this->db->exec(
            'CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL, cohortID TEXT, '
            . 'isActive INTEGER NOT NULL DEFAULT 1, archivedByCohort INTEGER NOT NULL DEFAULT 0)'
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
                    'isActive' => true,
                ],
                [
                    'subjectID' => 'subject-2',
                    'subjectName' => 'Test Networks',
                    'testCount' => 0,
                    'lecturerNames' => null,
                    'isActive' => true,
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
     * Disabling stops the cohort's students logging in and archives its subjects, other cohorts are left alone (normal)
     */
    public function testDisablingDisablesStudentsAndArchivesSubjectsInThatCohortOnly(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->addStudent('user-2', 'Sam', 'Outside', 'cohort-2', 1, 'approved', null);
        $this->addSubject('subject-1', 'cohort-1', 1);
        $this->addSubject('subject-2', 'cohort-2', 1);

        // Act
        Cohort::setActive('cohort-1', false);

        // Assert
        $this->assertSame(0, $this->userIsEnabled('user-1'));
        $this->assertSame(1, $this->userIsEnabled('user-2'));
        $this->assertSame(0, $this->subjectIsActive('subject-1'));
        $this->assertSame(1, $this->subjectIsActive('subject-2'));
    }

    /**
     * Enabling brings back the students and subjects disabling changed (normal)
     */
    public function testEnablingRestoresWhatDisablingChanged(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->addSubject('subject-1', 'cohort-1', 1);
        Cohort::setActive('cohort-1', false);

        // Act
        Cohort::setActive('cohort-1', true);

        // Assert
        $this->assertSame(1, $this->userIsEnabled('user-1'));
        $this->assertSame(1, $this->subjectIsActive('subject-1'));
    }

    /**
     * A student already disabled and a subject already archived stay that way after enabling (edge)
     */
    public function testEnablingLeavesThingsChangedForOtherReasonsAlone(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Banned', 'cohort-1', 0, 'approved', null);
        $this->addSubject('subject-1', 'cohort-1', 0);
        Cohort::setActive('cohort-1', false);

        // Act
        Cohort::setActive('cohort-1', true);

        // Assert
        $this->assertSame(0, $this->userIsEnabled('user-1'));
        $this->assertSame(0, $this->subjectIsActive('subject-1'));
    }

    /**
     * Disabling twice doesn't lose track of what to restore (boundary)
     */
    public function testDisablingTwiceStillRestoresOnEnable(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);

        // Act
        Cohort::setActive('cohort-1', false);
        Cohort::setActive('cohort-1', false);
        Cohort::setActive('cohort-1', true);

        // Assert
        $this->assertSame(1, $this->userIsEnabled('user-1'));
    }

    /**
     * If a later update fails, the cohort isn't left half disabled (error)
     */
    public function testDisablingRollsBackWhenAnUpdateFails(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->db->exec('DROP TABLE Subjects');

        // Act
        try {
            Cohort::setActive('cohort-1', false);
            $this->fail('Expected the update to fail');
        } catch (PDOException) {
            // Assert
            $this->assertSame(1, $this->userIsEnabled('user-1'));
            $isActive = $this->db->query("SELECT isActive FROM Cohorts WHERE cohortID = 'cohort-1'")->fetchColumn();
            $this->assertSame(1, (int) $isActive);
        }
    }

    /**
     * The verification flow can see a student's account was disabled along with their cohort (normal)
     */
    public function testVerificationDetailsShowTheStudentWasDisabled(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'pending', null);
        $before = Student::findVerificationDetails('user-1');

        // Act
        Cohort::setActive('cohort-1', false);
        $after = Student::findVerificationDetails('user-1');

        // Assert
        $this->assertTrue($before['isEnabled']);
        $this->assertFalse($after['isEnabled']);
    }

    /**
     * Finding one cohort gives the same details as the list (normal)
     */
    public function testFindsOneCohortForManagement(): void
    {
        // Act
        $cohort = Cohort::findForManagement('cohort-1');

        // Assert
        $this->assertSame('Test Beta', $cohort['cohortName']);
    }

    /**
     * Looking for a cohort that doesn't exist gives null (error)
     */
    public function testFindingAnUnknownCohortGivesNull(): void
    {
        // Act and Assert
        $this->assertNull(Cohort::findForManagement('cohort-missing'));
    }

    /**
     * The registration form is only offered active cohorts (normal)
     */
    public function testRegistrationListLeavesOutDisabledCohorts(): void
    {
        // Act
        $cohorts = Cohort::getAll();

        // Assert
        $this->assertSame([['cohortID' => 'cohort-1', 'cohortName' => 'Test Beta']], $cohorts);
    }

    /**
     * Only active subjects with no cohort can be assigned (normal)
     */
    public function testUnassignedListOnlyHasActiveSubjectsWithNoCohort(): void
    {
        // Arrange
        $this->addSubject('subject-free', null, 1);
        $this->addSubject('subject-archived', null, 0);
        $this->addSubject('subject-taken', 'cohort-1', 1);
        $this->db->exec("INSERT INTO Tests (testID, subjectID) VALUES ('test-1', 'subject-free')");

        // Act
        $subjects = Subject::getUnassigned();

        // Assert
        $this->assertSame(
            [['subjectID' => 'subject-free', 'subjectName' => 'Test subject-free', 'testCount' => 1]],
            $subjects
        );
    }

    /**
     * Assigning moves the subject into the cohort and off the unassigned list (normal)
     */
    public function testAssignsSubjectsToACohort(): void
    {
        // Arrange
        $this->addSubject('subject-1', null, 1);
        $this->addSubject('subject-2', null, 1);

        // Act
        $result = Cohort::assignSubjects('cohort-1', ['subject-1', 'subject-2']);

        // Assert
        $this->assertTrue($result);
        $this->assertSame('cohort-1', $this->subjectCohort('subject-2'));
        $this->assertSame(1, $this->subjectIsActive('subject-1'));
        $this->assertSame([], Subject::getUnassigned());
    }

    /**
     * A subject in another cohort can't be taken, and nothing else in the request is saved (error)
     */
    public function testAssigningASubjectInAnotherCohortChangesNothing(): void
    {
        // Arrange
        $this->addSubject('subject-free', null, 1);
        $this->addSubject('subject-taken', 'cohort-2', 1);

        // Act
        try {
            Cohort::assignSubjects('cohort-1', ['subject-free', 'subject-taken']);
            $this->fail('Expected the assign to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            $this->assertNull($this->subjectCohort('subject-free'));
            $this->assertSame('cohort-2', $this->subjectCohort('subject-taken'));
        }
    }

    /**
     * An archived subject can't be assigned (error)
     */
    public function testAssigningAnArchivedSubjectIsRefused(): void
    {
        // Arrange
        $this->addSubject('subject-archived', null, 0);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        Cohort::assignSubjects('cohort-1', ['subject-archived']);
    }

    /**
     * Assigning to a cohort that doesn't exist is reported back (error)
     */
    public function testAssigningToAnUnknownCohortReturnsFalse(): void
    {
        // Arrange
        $this->addSubject('subject-1', null, 1);

        // Act
        $result = Cohort::assignSubjects('cohort-missing', ['subject-1']);

        // Assert
        $this->assertFalse($result);
        $this->assertNull($this->subjectCohort('subject-1'));
    }

    /**
     * A subject added to a disabled cohort is archived, then comes back when the cohort is enabled (edge)
     */
    public function testSubjectAssignedToADisabledCohortFollowsTheCohort(): void
    {
        // Arrange
        $this->addSubject('subject-1', null, 1);

        // Act
        Cohort::assignSubjects('cohort-2', ['subject-1']);
        $whileDisabled = $this->subjectIsActive('subject-1');
        Cohort::setActive('cohort-2', true);

        // Assert
        $this->assertSame(0, $whileDisabled);
        $this->assertSame(1, $this->subjectIsActive('subject-1'));
    }

    /**
     * Removing leaves the subject without a cohort, ready to assign again (normal)
     */
    public function testRemovingASubjectMakesItUnassigned(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'cohort-1', 1);

        // Act
        $result = Cohort::removeSubject('cohort-1', 'subject-1');

        // Assert
        $this->assertTrue($result);
        $this->assertNull($this->subjectCohort('subject-1'));
        $this->assertSame(['subject-1'], array_column(Subject::getUnassigned(), 'subjectID'));
    }

    /**
     * A subject can't be removed through a cohort it isn't in (error)
     */
    public function testRemovingFromTheWrongCohortChangesNothing(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'cohort-1', 1);

        // Act
        $result = Cohort::removeSubject('cohort-2', 'subject-1');

        // Assert
        $this->assertFalse($result);
        $this->assertSame('cohort-1', $this->subjectCohort('subject-1'));
    }

    /**
     * Removing from a disabled cohort undoes its archive, but not an archive from elsewhere (edge)
     */
    public function testRemovingOnlyUndoesAnArchiveFromTheCohort(): void
    {
        // Arrange
        $this->addSubject('subject-cohort', 'cohort-1', 1);
        $this->addSubject('subject-manual', 'cohort-1', 0);
        Cohort::setActive('cohort-1', false);

        // Act
        Cohort::removeSubject('cohort-1', 'subject-cohort');
        Cohort::removeSubject('cohort-1', 'subject-manual');

        // Assert
        $this->assertSame(1, $this->subjectIsActive('subject-cohort'));
        $this->assertSame(0, $this->subjectIsActive('subject-manual'));
    }

    /**
     * Only a non-empty list of ID shaped strings is accepted (boundary)
     */
    public function testValidatesTheListOfSubjectIDs(): void
    {
        // Act and Assert
        $this->assertTrue(CohortController::validSubjectIDs(['subject-1', 'subject-2']));
        $this->assertFalse(CohortController::validSubjectIDs([]));
        $this->assertFalse(CohortController::validSubjectIDs('subject-1'));
        $this->assertFalse(CohortController::validSubjectIDs([1]));
        $this->assertFalse(CohortController::validSubjectIDs(['id' => 'subject-1']));
        $this->assertFalse(CohortController::validSubjectIDs(["subject-1' OR 1=1"]));
    }

    /**
     * An unknown cohort doesn't leave a transaction open, now that the status is read inside one (error)
     * the race itself needs two MariaDB connections, SQLite has no FOR UPDATE, so it can't be tested here
     */
    public function testAssigningToAnUnknownCohortClosesItsTransaction(): void
    {
        // Arrange
        $this->addSubject('subject-1', null, 1);

        // Act
        $result = Cohort::assignSubjects('cohort-missing', ['subject-1']);

        // Assert
        $this->assertFalse($result);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * The cohort's status is read locked and inside the transaction, before any subject is saved (normal)
     * stops the cohort being disabled or enabled between reading its status and saving the subjects
     * the race itself needs two MariaDB connections, so this pretends to be MariaDB and checks the queries
     */
    public function testAssignLocksTheCohortStatusInsideItsTransaction(): void
    {
        // Arrange: a connection that says it's MariaDB and notes each query, with the same tables as setUp()
        $recordingDB = new class ('sqlite::memory:') extends PDO {
            /** @var array<int, array{sql: string, inTransaction: bool}> */
            public array $queries = [];

            public function getAttribute(int $attribute): mixed
            {
                return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->queries[] = ['sql' => $query, 'inTransaction' => $this->inTransaction()];

                // SQLite can't run FOR UPDATE, it's only noted
                return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
            }
        };
        $recordingDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (['Cohorts', 'Subjects'] as $table) {
            $create = $this->db->query("SELECT sql FROM sqlite_master WHERE name = '$table'")->fetchColumn();
            $recordingDB->exec($create);
        }
        $recordingDB->exec(
            "INSERT INTO Cohorts (cohortID, cohortName, createdDate, isActive)
                VALUES ('cohort-2', 'Test Alpha', '2025-09-01', 0);
             INSERT INTO Subjects (subjectID, subjectName, cohortID, isActive) VALUES ('subject-1', 'Test', NULL, 1)"
        );
        (new ReflectionProperty(Model::class, 'db'))->setValue(null, $recordingDB);

        // Act
        Cohort::assignSubjects('cohort-2', ['subject-1']);

        // Assert
        $statusRead = $recordingDB->queries[0];
        $subjectSave = $recordingDB->queries[1];
        $this->assertStringContainsString('SELECT isActive FROM Cohorts', $statusRead['sql']);
        $this->assertStringEndsWith(' FOR UPDATE', $statusRead['sql']);
        $this->assertTrue($statusRead['inTransaction']);
        $this->assertStringStartsWith('UPDATE Subjects', $subjectSave['sql']);
        $isActive = $recordingDB->query("SELECT isActive FROM Subjects WHERE subjectID = 'subject-1'")->fetchColumn();
        $this->assertSame(0, (int) $isActive);
    }

    /**
     * Enabling one cohort doesn't bring back students or subjects from another disabled cohort (edge)
     */
    public function testEnablingOneCohortLeavesAnotherDisabledCohortAlone(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Beta', 'cohort-1', 1, 'approved', null);
        $this->addStudent('user-2', 'Sam', 'Alpha', 'cohort-2', 1, 'approved', null);
        $this->addSubject('subject-1', 'cohort-1', 1);
        $this->addSubject('subject-2', 'cohort-2', 1);
        Cohort::setActive('cohort-1', false);
        Cohort::setActive('cohort-2', false);

        // Act
        Cohort::setActive('cohort-1', true);

        // Assert
        $this->assertSame(1, $this->userIsEnabled('user-1'));
        $this->assertSame(0, $this->userIsEnabled('user-2'));
        $this->assertSame(1, $this->subjectIsActive('subject-1'));
        $this->assertSame(0, $this->subjectIsActive('subject-2'));
    }

    /**
     * Enabling a cohort that was never disabled changes nothing (boundary)
     */
    public function testEnablingAnActiveCohortLeavesADisabledStudentAlone(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Banned', 'cohort-1', 0, 'approved', null);

        // Act
        Cohort::setActive('cohort-1', true);

        // Assert
        $this->assertSame(0, $this->userIsEnabled('user-1'));
    }

    /**
     * Finding a cohort shows the status and students as they are after disabling it (normal)
     */
    public function testFindingACohortShowsItAfterDisabling(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        Cohort::setActive('cohort-1', false);

        // Act
        $cohort = Cohort::findForManagement('cohort-1');

        // Assert
        $this->assertFalse($cohort['isActive']);
        $this->assertSame('Disabled', $cohort['students'][0]['status']);
    }

    /**
     * Registering into a disabled cohort is refused, even if the form was opened before it was disabled (error)
     */
    public function testRegisteringIntoADisabledCohortIsRefused(): void
    {
        // Arrange: setUp() disables cohort-2, the Users table is empty
        $data = [
            'firstName' => 'Sam',
            'lastName' => 'Sample',
            'email' => 'sam@example.test',
            'studentNumber' => 'ws000001',
            'password' => 'Sample123!',
            'confirmPassword' => 'Sample123!',
            'cohortID' => 'cohort-2',
        ];

        // Assert: PHPUnit has to be told which exception to expect before the Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected cohort was not found');

        // Act
        Student::register($data);
    }

    /**
     * An empty cohort is deleted and the others stay (normal)
     */
    public function testDeletesAnEmptyCohort(): void
    {
        // Act
        $result = Cohort::delete('cohort-1');

        // Assert
        $this->assertTrue($result);
        $this->assertSame(['Test Alpha'], array_column(Cohort::getAllForManagement(), 'cohortName'));
    }

    /**
     * A cohort with a student can't be deleted (error)
     */
    public function testCannotDeleteACohortWithStudents(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);

        // Act
        try {
            Cohort::delete('cohort-1');
            $this->fail('Expected the delete to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            $this->assertCount(2, Cohort::getAllForManagement());
        }
    }

    /**
     * A cohort with a subject can't be deleted, even an archived one (error)
     */
    public function testCannotDeleteACohortWithSubjects(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'cohort-1', 0);

        // Act
        try {
            Cohort::delete('cohort-1');
            $this->fail('Expected the delete to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            $this->assertCount(2, Cohort::getAllForManagement());
        }
    }

    /**
     * Once its last subject is removed the cohort can be deleted (boundary)
     */
    public function testCanDeleteACohortOnceItsSubjectIsRemoved(): void
    {
        // Arrange
        $this->addSubject('subject-1', 'cohort-1', 1);
        Cohort::removeSubject('cohort-1', 'subject-1');

        // Act and Assert
        $this->assertTrue(Cohort::delete('cohort-1'));
    }

    /**
     * Deleting a cohort that doesn't exist is reported back (error)
     */
    public function testDeletingAnUnknownCohortReturnsFalse(): void
    {
        // Act and Assert
        $this->assertFalse(Cohort::delete('cohort-missing'));
    }

    /**
     * A new cohort starts active and empty (normal)
     */
    public function testCreatesACohort(): void
    {
        // Act
        $cohortID = Cohort::create('Test Gamma');

        // Assert
        $cohort = Cohort::findForManagement($cohortID);
        $this->assertSame('Test Gamma', $cohort['cohortName']);
        $this->assertTrue($cohort['isActive']);
        $this->assertSame([], $cohort['students']);
    }

    /**
     * Spaces around and doubled inside a name are tidied before saving (edge)
     */
    public function testTidiesSpacesInANewName(): void
    {
        // Act
        $cohortID = Cohort::create("  Test   Gamma\t");

        // Assert
        $this->assertSame('Test Gamma', Cohort::findForManagement($cohortID)['cohortName']);
    }

    /**
     * Empty names, or ones that aren't text, are rejected (error)
     */
    #[DataProvider('emptyNames')]
    public function testRejectsAnEmptyName(mixed $name): void
    {
        // Assert: PHPUnit has to be told which exception to expect before the Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enter a cohort name');

        // Act
        Cohort::create($name);
    }

    public static function emptyNames(): array
    {
        return [
            'nothing' => [''],
            'only spaces' => ['   '],
            'missing' => [null],
            'a list' => [['Test Gamma']],
            'a number' => [2026],
        ];
    }

    /**
     * 50 characters is the longest allowed, as the column only holds 50 (boundary)
     */
    public function testNameLengthLimit(): void
    {
        // Act
        $longestName = Cohort::cleanName(str_repeat('a', 50));

        // Assert
        $this->assertSame(50, strlen($longestName));
        $this->expectException(InvalidArgumentException::class);
        Cohort::cleanName(str_repeat('a', 51));
    }

    /**
     * HTML can't get into a name (error)
     */
    public function testRejectsHtmlInAName(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('can only use letters');

        // Act
        Cohort::create('<script>alert(1)</script>');
    }

    /**
     * Usual cohort names with punctuation are allowed (normal)
     */
    public function testAllowsPunctuationInAName(): void
    {
        // Act
        $name = Cohort::cleanName("BSc (Hons) Maths & Art - Yr 1/2, St. John's");

        // Assert
        $this->assertSame("BSc (Hons) Maths & Art - Yr 1/2, St. John's", $name);
    }

    /**
     * A duplicate name is rejected, whatever its upper and lower case (error)
     */
    public function testRejectsADuplicateNameOnCreate(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A cohort with this name already exists');

        // Act
        Cohort::create('TEST beta');
    }

    /**
     * Renaming changes only that cohort (normal)
     */
    public function testRenamesACohort(): void
    {
        // Act
        $result = Cohort::update('cohort-1', 'Test Gamma', true);

        // Assert
        $this->assertTrue($result);
        $this->assertSame(['Test Alpha', 'Test Gamma'], array_column(Cohort::getAllForManagement(), 'cohortName'));
    }

    /**
     * A cohort can't take another cohort's name (error)
     */
    public function testRejectsADuplicateNameOnRename(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A cohort with this name already exists');

        // Act
        Cohort::update('cohort-1', 'test alpha', true);
    }

    /**
     * A cohort can keep its own name, or change only its case (boundary)
     */
    public function testCanKeepOwnNameOrChangeItsCase(): void
    {
        // Act
        $sameName = Cohort::update('cohort-1', 'Test Beta', true);
        $newCase = Cohort::update('cohort-1', 'TEST BETA', true);

        // Assert
        $this->assertTrue($sameName);
        $this->assertTrue($newCase);
        $this->assertSame('TEST BETA', Cohort::findForManagement('cohort-1')['cohortName']);
    }

    /**
     * An empty name is rejected on rename and the old name stays (error)
     */
    public function testRenameRejectsAnEmptyNameAndKeepsTheOldOne(): void
    {
        // Act
        try {
            Cohort::update('cohort-1', '  ', true);
            $this->fail('Expected the rename to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            $this->assertSame('Test Beta', Cohort::findForManagement('cohort-1')['cohortName']);
        }
    }

    /**
     * Renaming a cohort that doesn't exist is reported back (error)
     */
    public function testRenamingAnUnknownCohortReturnsFalse(): void
    {
        // Act and Assert
        $this->assertFalse(Cohort::update('cohort-missing', 'Test Gamma', true));
    }

    /**
     * A cohort can be created as disabled (normal)
     */
    public function testCreatesADisabledCohort(): void
    {
        // Act
        $cohortID = Cohort::create('Test Gamma', false);

        // Assert
        $this->assertFalse(Cohort::findForManagement($cohortID)['isActive']);
        $this->assertSame([], array_column(
            array_filter(Cohort::getAll(), fn ($cohort) => $cohort['cohortID'] === $cohortID),
            'cohortName'
        ));
    }

    /**
     * Disabling through the edit form locks out students and archives subjects, as the button does (normal)
     */
    public function testUpdateCanDisableACohortAndRenameItTogether(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->addSubject('subject-1', 'cohort-1', 1);

        // Act
        Cohort::update('cohort-1', 'Test Gamma', false);

        // Assert
        $cohort = Cohort::findForManagement('cohort-1');
        $this->assertSame('Test Gamma', $cohort['cohortName']);
        $this->assertFalse($cohort['isActive']);
        $this->assertSame(0, $this->userIsEnabled('user-1'));
        $this->assertSame(0, $this->subjectIsActive('subject-1'));
    }

    /**
     * Enabling through the edit form brings back only what disabling changed (normal)
     */
    public function testUpdateCanEnableACohort(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->addStudent('user-2', 'Sam', 'Banned', 'cohort-1', 0, 'approved', null);
        Cohort::setActive('cohort-1', false);

        // Act
        Cohort::update('cohort-1', 'Test Beta', true);

        // Assert
        $this->assertSame(1, $this->userIsEnabled('user-1'));
        $this->assertSame(0, $this->userIsEnabled('user-2'));
    }

    /**
     * A refused name doesn't lock anyone out, nothing is saved (error)
     */
    public function testRefusedNameLeavesTheStatusAlone(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);

        // Act
        try {
            Cohort::update('cohort-1', 'Test Alpha', false);
            $this->fail('Expected the update to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            $this->assertTrue(Cohort::findForManagement('cohort-1')['isActive']);
            $this->assertSame(1, $this->userIsEnabled('user-1'));
        }
    }

    /**
     * If the status updates fail, the new name isn't saved either (error)
     */
    public function testUpdateRollsBackTheNameWhenTheStatusFails(): void
    {
        // Arrange
        $this->db->exec('DROP TABLE Subjects');

        // Act
        try {
            Cohort::update('cohort-1', 'Test Gamma', false);
            $this->fail('Expected the update to fail');
        } catch (PDOException) {
            // Assert
            $name = $this->db->query("SELECT cohortName FROM Cohorts WHERE cohortID = 'cohort-1'")->fetchColumn();
            $this->assertSame('Test Beta', $name);
        }
    }

    /**
     * Names in other alphabets are allowed (normal)
     */
    public function testAllowsNamesInOtherAlphabets(): void
    {
        // Act
        $name = Cohort::cleanName('Génie Логика 2026');

        // Assert
        $this->assertSame('Génie Логика 2026', $name);
    }

    /**
     * The limit counts characters, not bytes, so 50 accented letters still fit (boundary)
     */
    public function testNameLengthCountsCharactersNotBytes(): void
    {
        // Act
        $name = Cohort::cleanName(str_repeat('é', 50));

        // Assert
        $this->assertSame(50, mb_strlen($name));
    }

    /**
     * New lines and tabs pasted into a name become single spaces (edge)
     */
    public function testNewLinesBecomeSpaces(): void
    {
        // Act
        $name = Cohort::cleanName("Test\n\tGamma");

        // Assert
        $this->assertSame('Test Gamma', $name);
    }

    /**
     * A cohort is active unless it's asked to be disabled (normal)
     */
    public function testNewCohortIsActiveByDefaultAndOfferedAtRegistration(): void
    {
        // Act
        $cohortID = Cohort::create('Test Gamma');

        // Assert
        $this->assertContains($cohortID, array_column(Cohort::getAll(), 'cohortID'));
    }

    /**
     * Two admins creating the same name at once, the database's unique key stops the second (edge)
     */
    public function testDuplicateCaughtOnlyByTheDatabaseOnCreateGivesTheSameMessage(): void
    {
        // Arrange: this trigger acts like the other admin's save, after the name check has passed
        $this->db->exec(
            "CREATE TRIGGER otherAdminSaved BEFORE INSERT ON Cohorts
                BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed'); END"
        );

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A cohort with this name already exists');

        // Act
        Cohort::create('Test Gamma');
    }

    /**
     * The same for renaming, and nothing is left half saved (edge)
     */
    public function testDuplicateCaughtOnlyByTheDatabaseOnUpdateChangesNothing(): void
    {
        // Arrange: this trigger acts like the other admin's save, after the name check has passed
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->db->exec(
            "CREATE TRIGGER otherAdminSaved BEFORE UPDATE OF cohortName ON Cohorts
                BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed'); END"
        );

        // Act
        try {
            Cohort::update('cohort-1', 'Test Gamma', false);
            $this->fail('Expected the update to be refused');
        } catch (InvalidArgumentException $e) {
            // Assert
            $this->assertSame('A cohort with this name already exists', $e->getMessage());
            $this->assertFalse($this->db->inTransaction());
            $this->assertSame(1, $this->userIsEnabled('user-1'));
        }
    }

    /**
     * Editing keeps the cohort's date, students and subjects (normal)
     */
    public function testUpdateKeepsTheDateStudentsAndSubjects(): void
    {
        // Arrange
        $this->addStudent('user-1', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->addSubject('subject-1', 'cohort-1', 1);

        // Act
        Cohort::update('cohort-1', 'Test Gamma', true);

        // Assert
        $cohort = Cohort::findForManagement('cohort-1');
        $this->assertSame('2026-09-01', $cohort['createdDate']);
        $this->assertCount(1, $cohort['students']);
        $this->assertCount(1, $cohort['subjects']);
    }

    /**
     * Editing only changes the chosen cohort (normal)
     */
    public function testUpdateLeavesOtherCohortsAlone(): void
    {
        // Arrange
        $this->addStudent('user-2', 'Sam', 'Alpha', 'cohort-2', 1, 'approved', null);

        // Act
        Cohort::update('cohort-1', 'Test Gamma', false);

        // Assert
        $other = Cohort::findForManagement('cohort-2');
        $this->assertSame('Test Alpha', $other['cohortName']);
        $this->assertSame(1, $this->userIsEnabled('user-2'));
    }

    /**
     * A freed up name can be used again, as the check only looks at current cohorts (boundary)
     */
    public function testNameCanBeReusedOnceFreedUp(): void
    {
        // Arrange
        Cohort::update('cohort-1', 'Test Gamma', true);

        // Act
        $cohortID = Cohort::create('Test Beta');

        // Assert
        $this->assertSame('Test Beta', Cohort::findForManagement($cohortID)['cohortName']);
    }

    /**
     * The add students list has everyone in other cohorts, with the cohort they're in now (normal)
     */
    public function testListsStudentsInOtherCohorts(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'Inside', 'cohort-1', 1, 'approved', null);
        $this->addStudent('ws000002', 'Ali', 'Outside', 'cohort-2', 1, 'approved', null);

        // Act
        $students = Cohort::getStudentsOutside('cohort-1');

        // Assert
        $this->assertSame(
            [['studentNumber' => 'ws000002', 'name' => 'Ali Outside', 'currentCohort' => 'Test Alpha']],
            $students
        );
    }

    /**
     * A move takes the student out of the old cohort and into the new one, so they're only ever in one (normal)
     */
    public function testMovesStudentsIntoACohort(): void
    {
        // Arrange
        $this->db->exec("UPDATE Cohorts SET isActive = 1");
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);
        $this->addStudent('ws000002', 'Sam', 'Two', 'cohort-1', 1, 'approved', null);

        // Act
        $result = Cohort::moveStudents('cohort-2', ['ws000001', 'ws000002']);

        // Assert
        $this->assertTrue($result);
        $this->assertCount(0, Cohort::findForManagement('cohort-1')['students']);
        $this->assertCount(2, Cohort::findForManagement('cohort-2')['students']);
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM Students')->fetchColumn());
    }

    /**
     * A student moved into a disabled cohort is locked out, and comes back when it's enabled (edge)
     */
    public function testMovingIntoADisabledCohortLocksTheStudentOut(): void
    {
        // Arrange: setUp() disables cohort-2
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);

        // Act
        Cohort::moveStudents('cohort-2', ['ws000001']);
        $whileDisabled = $this->userIsEnabled('ws000001');
        Cohort::setActive('cohort-2', true);

        // Assert
        $this->assertSame(0, $whileDisabled);
        $this->assertSame(1, $this->userIsEnabled('ws000001'));
    }

    /**
     * A student locked out by a disabled cohort can log in again once moved to an active one (normal)
     */
    public function testMovingOutOfADisabledCohortLetsTheStudentBackIn(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-2', 1, 'approved', null);
        Cohort::setActive('cohort-2', true);
        Cohort::setActive('cohort-2', false);

        // Act
        Cohort::moveStudents('cohort-1', ['ws000001']);

        // Assert
        $this->assertSame(1, $this->userIsEnabled('ws000001'));
    }

    /**
     * A student disabled for another reason stays disabled wherever they're moved (edge)
     */
    public function testMovingLeavesAStudentDisabledForAnotherReasonDisabled(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'Banned', 'cohort-2', 0, 'approved', null);

        // Act
        Cohort::moveStudents('cohort-1', ['ws000001']);

        // Assert
        $this->assertSame(0, $this->userIsEnabled('ws000001'));
    }

    /**
     * Moving someone who's already there is refused, and nobody else in the request moves (error)
     */
    public function testMovingAStudentAlreadyInTheCohortChangesNothing(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'Other', 'cohort-2', 1, 'approved', null);
        $this->addStudent('ws000002', 'Sam', 'Here', 'cohort-1', 1, 'approved', null);

        // Act
        try {
            Cohort::moveStudents('cohort-1', ['ws000001', 'ws000002']);
            $this->fail('Expected the move to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            $this->assertCount(1, Cohort::findForManagement('cohort-2')['students']);
            $this->assertCount(1, Cohort::findForManagement('cohort-1')['students']);
        }
    }

    /**
     * A student number that doesn't exist is refused (error)
     */
    public function testMovingAnUnknownStudentIsRefused(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        Cohort::moveStudents('cohort-1', ['ws999999']);
    }

    /**
     * Moving into a cohort that doesn't exist is reported back and the student stays put (error)
     */
    public function testMovingIntoAnUnknownCohortReturnsFalse(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);

        // Act
        $result = Cohort::moveStudents('cohort-missing', ['ws000001']);

        // Assert
        $this->assertFalse($result);
        $this->assertCount(1, Cohort::findForManagement('cohort-1')['students']);
    }

    /**
     * Only a non-empty list of real looking student numbers is accepted (boundary)
     */
    public function testValidatesTheListOfStudentNumbers(): void
    {
        // Act and Assert
        $this->assertTrue(CohortController::validStudentNumbers(['ws000001', 'ws000002']));
        $this->assertFalse(CohortController::validStudentNumbers([]));
        $this->assertFalse(CohortController::validStudentNumbers('ws000001'));
        $this->assertFalse(CohortController::validStudentNumbers([1]));
        $this->assertFalse(CohortController::validStudentNumbers(['id' => 'ws000001']));
        $this->assertFalse(CohortController::validStudentNumbers(["ws000001' OR 1=1"]));
    }

    /**
     * The add students list is A to Z by last name (normal)
     */
    public function testListsOtherStudentsAToZByLastName(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'Young', 'cohort-2', 1, 'approved', null);
        $this->addStudent('ws000002', 'Ali', 'Adams', 'cohort-2', 1, 'approved', null);

        // Act
        $students = Cohort::getStudentsOutside('cohort-1');

        // Assert
        $this->assertSame(['Ali Adams', 'Sam Young'], array_column($students, 'name'));
    }

    /**
     * Only what the pop-up needs is sent, no emails or account details (normal)
     */
    public function testOtherStudentsListOnlySendsWhatThePopUpNeeds(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-2', 1, 'approved', null);

        // Act
        $students = Cohort::getStudentsOutside('cohort-1');

        // Assert
        $this->assertSame(['studentNumber', 'name', 'currentCohort'], array_keys($students[0]));
    }

    /**
     * With nobody in another cohort the list is empty (boundary)
     */
    public function testOtherStudentsListIsEmptyWhenEveryoneIsInThisCohort(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);

        // Act
        $students = Cohort::getStudentsOutside('cohort-1');

        // Assert
        $this->assertSame([], $students);
    }

    /**
     * Moving only touches the students chosen (normal)
     */
    public function testMovingLeavesOtherStudentsWhereTheyAre(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'Moving', 'cohort-1', 1, 'approved', null);
        $this->addStudent('ws000002', 'Sam', 'Staying', 'cohort-1', 1, 'approved', null);

        // Act
        Cohort::moveStudents('cohort-2', ['ws000001']);

        // Assert
        $staying = Cohort::findForManagement('cohort-1')['students'];
        $this->assertSame(['Sam Staying'], array_column($staying, 'name'));
        $this->assertSame(1, $this->userIsEnabled('ws000002'));
    }

    /**
     * A student waiting for approval is still waiting after moving (normal)
     */
    public function testMovingKeepsThePendingStatus(): void
    {
        // Arrange
        $this->db->exec("UPDATE Cohorts SET isActive = 1");
        $this->addStudent('ws000001', 'Sam', 'Pending', 'cohort-1', 1, 'pending', null);

        // Act
        Cohort::moveStudents('cohort-2', ['ws000001']);

        // Assert
        $this->assertSame('Pending', Cohort::findForManagement('cohort-2')['students'][0]['status']);
    }

    /**
     * Moving between two disabled cohorts keeps them locked out, and enabling the new one lets them back in (edge)
     */
    public function testMovingBetweenDisabledCohortsStillRestoresOnEnable(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);
        Cohort::setActive('cohort-1', false);

        // Act
        Cohort::moveStudents('cohort-2', ['ws000001']);
        $whileDisabled = $this->userIsEnabled('ws000001');
        Cohort::setActive('cohort-2', true);

        // Assert
        $this->assertSame(0, $whileDisabled);
        $this->assertSame(1, $this->userIsEnabled('ws000001'));
    }

    /**
     * Once moved away, enabling the old cohort has nothing to do with them (edge)
     */
    public function testOldCohortNoLongerControlsAMovedStudent(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);
        Cohort::setActive('cohort-1', false);
        Cohort::moveStudents('cohort-2', ['ws000001']);

        // Act: cohort-2 is still disabled, so enabling cohort-1 mustn't let them in
        Cohort::setActive('cohort-1', true);

        // Assert
        $this->assertSame(0, $this->userIsEnabled('ws000001'));
    }

    /**
     * A student who's moved out can no longer stop their old cohort being deleted (boundary)
     */
    public function testCohortCanBeDeletedOnceItsLastStudentHasMoved(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);
        Cohort::moveStudents('cohort-2', ['ws000001']);

        // Act and Assert
        $this->assertTrue(Cohort::delete('cohort-1'));
    }

    /**
     * If the account update fails, the student stays in their old cohort (error)
     */
    public function testMovingRollsBackWhenAnUpdateFails(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);
        $this->db->exec(
            "CREATE TRIGGER accountUpdateFails BEFORE UPDATE ON Users
                BEGIN SELECT RAISE(ABORT, 'account update failed'); END"
        );

        // Act
        try {
            Cohort::moveStudents('cohort-2', ['ws000001']);
            $this->fail('Expected the move to fail');
        } catch (PDOException) {
            // Assert
            $this->assertFalse($this->db->inTransaction());
            $this->assertCount(1, Cohort::findForManagement('cohort-1')['students']);
        }
    }

    /**
     * The cohort and each student are read locked inside the transaction, before anyone is moved (normal)
     * stops the cohort being toggled, or the same student being moved by someone else, part way through
     * the race itself needs two MariaDB connections, so this pretends to be MariaDB and checks the queries
     */
    public function testMoveLocksTheCohortAndStudentsInsideItsTransaction(): void
    {
        // Arrange: a connection that says it's MariaDB and notes each query it runs, with the same tables as setUp()
        $recordingDB = new class ('sqlite::memory:') extends PDO {
            /** @var array<int, array{sql: string, inTransaction: bool}> */
            public array $queries = [];

            public function getAttribute(int $attribute): mixed
            {
                return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->queries[] = ['sql' => $query, 'inTransaction' => $this->inTransaction()];

                // SQLite can't run FOR UPDATE, it's only noted
                return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
            }
        };
        $recordingDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (['Cohorts', 'Students', 'Users'] as $table) {
            $create = $this->db->query("SELECT sql FROM sqlite_master WHERE name = '$table'")->fetchColumn();
            $recordingDB->exec($create);
        }
        $recordingDB->exec(
            "INSERT INTO Cohorts (cohortID, cohortName, createdDate, isActive)
                VALUES ('cohort-1', 'Test Beta', '2026-09-01', 1), ('cohort-2', 'Test Alpha', '2025-09-01', 0);
             INSERT INTO Students (userID, firstName, lastName, cohortID) VALUES ('user-1', 'Sam', 'One', 'cohort-1');
             INSERT INTO Users (userID, username, email, isEnabled, approvalStatus)
                VALUES ('user-1', 'ws000001', 'a@example.test', 1, 'approved')"
        );
        (new ReflectionProperty(Model::class, 'db'))->setValue(null, $recordingDB);

        // Act
        Cohort::moveStudents('cohort-2', ['ws000001']);

        // Assert
        $reads = array_values(array_filter($recordingDB->queries, fn ($query) => str_starts_with(
            ltrim($query['sql']),
            'SELECT'
        )));
        $this->assertStringContainsString('FROM Cohorts', $reads[0]['sql']);
        $this->assertStringContainsString('FROM Students', $reads[1]['sql']);
        foreach ($reads as $read) {
            $this->assertStringEndsWith(' FOR UPDATE', $read['sql']);
            $this->assertTrue($read['inTransaction']);
        }
        $isEnabled = $recordingDB->query("SELECT isEnabled FROM Users WHERE userID = 'user-1'")->fetchColumn();
        $this->assertSame(0, (int) $isEnabled);
    }

    /**
     * Students are moved in student number order, whatever order they're sent in (normal)
     * locking in the same order means two admins moving some of the same students can't deadlock
     */
    public function testMovesStudentsInStudentNumberOrder(): void
    {
        // Arrange: a trigger notes the order the students are moved in
        $this->db->exec("UPDATE Cohorts SET isActive = 1");
        $this->addStudent('ws000002', 'Sam', 'Two', 'cohort-1', 1, 'approved', null);
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);
        $this->db->exec('CREATE TABLE MoveOrder (position INTEGER PRIMARY KEY, userID TEXT)');
        $this->db->exec(
            "CREATE TRIGGER noteMove AFTER UPDATE OF cohortID ON Students
                BEGIN INSERT INTO MoveOrder (userID) VALUES (NEW.userID); END"
        );

        // Act
        Cohort::moveStudents('cohort-2', ['ws000002', 'ws000001']);

        // Assert
        $order = $this->db->query('SELECT userID FROM MoveOrder ORDER BY position')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['ws000001', 'ws000002'], $order);
    }

    /**
     * An unknown cohort doesn't leave a transaction open, now that the cohort is read inside one (error)
     */
    public function testMovingIntoAnUnknownCohortClosesItsTransaction(): void
    {
        // Arrange
        $this->addStudent('ws000001', 'Sam', 'One', 'cohort-1', 1, 'approved', null);

        // Act
        Cohort::moveStudents('cohort-missing', ['ws000001']);

        // Assert
        $this->assertFalse($this->db->inTransaction());
    }

    private function subjectCohort(string $subjectID): ?string
    {
        $stmt = $this->db->prepare('SELECT cohortID FROM Subjects WHERE subjectID = ?');
        $stmt->execute([$subjectID]);

        return $stmt->fetchColumn() ?: null;
    }

    private function userIsEnabled(string $userID): int
    {
        $stmt = $this->db->prepare('SELECT isEnabled FROM Users WHERE userID = ?');
        $stmt->execute([$userID]);

        return (int) $stmt->fetchColumn();
    }

    private function subjectIsActive(string $subjectID): int
    {
        $stmt = $this->db->prepare('SELECT isActive FROM Subjects WHERE subjectID = ?');
        $stmt->execute([$subjectID]);

        return (int) $stmt->fetchColumn();
    }

    private function addSubject(string $subjectID, ?string $cohortID, int $isActive): void
    {
        $this->db->prepare(
            'INSERT INTO Subjects (subjectID, subjectName, cohortID, isActive) VALUES (?, ?, ?, ?)'
        )->execute([$subjectID, 'Test ' . $subjectID, $cohortID, $isActive]);
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
