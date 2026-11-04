<?php

use App\Models\Model;
use App\Models\Student;
use PHPUnit\Framework\TestCase;

final class StudentManagementTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->db->exec("
            CREATE TABLE Cohorts (
                cohortID TEXT PRIMARY KEY,
                cohortName TEXT NOT NULL,
                isActive INTEGER NOT NULL
            );

            CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                accountType TEXT NOT NULL,
                approvalStatus TEXT NOT NULL,
                isEnabled INTEGER NOT NULL DEFAULT 1,
                disabledByCohort INTEGER NOT NULL DEFAULT 0,
                isEmailVerified INTEGER NOT NULL DEFAULT 0,
                lastLogin TEXT
            );

            CREATE TABLE Students (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL,
                cohortID TEXT NOT NULL,
                points INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (userID) REFERENCES Users(userID) ON DELETE CASCADE,
                FOREIGN KEY (cohortID) REFERENCES Cohorts(cohortID)
            );
        ");

        $this->db->exec("
            INSERT INTO Cohorts (cohortID, cohortName, isActive)
            VALUES
                ('active-cohort', 'Active Cohort', 1),
                ('inactive-cohort', 'Inactive Cohort', 0)
        ");

        $this->db->sqliteCreateFunction('UUID', function (): string {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

            $hex = bin2hex($bytes);

            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12)
            );
        });

        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, null);
    }

    private function createStudent(
        string $userID = 'student-1',
        string $cohortID = 'active-cohort',
        int $isEnabled = 1,
        int $disabledByCohort = 0,
        string $approvalStatus = 'approved',
        string $username = 'ws123456',
        string $email = 'student@example.com',
        int $isEmailVerified = 1
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO Users (
                userID, username, email, password, accountType,
                approvalStatus, isEnabled, disabledByCohort, isEmailVerified
            ) VALUES (?, ?, ?, 'password-hash', 'student', ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userID,
            $username,
            $email,
            $approvalStatus,
            $isEnabled,
            $disabledByCohort,
            $isEmailVerified,
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO Students (userID, firstName, lastName, cohortID, points)
            VALUES (?, 'Test', 'Student', ?, 0)
        ");

        $stmt->execute([$userID, $cohortID]);
    }

    private function getAccount(string $userID): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM Users WHERE userID = ?'
        );

        $stmt->execute([$userID]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getStudent(string $userID): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM Students WHERE userID = ?'
        );

        $stmt->execute([$userID]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------
    // createByLecturer tests
    // ---------------------------------------------------------

    public function testCreateByLecturerCreatesStudentAccount(): void
    {
        $result = Student::createByLecturer([
            'firstName' => 'Fyn',
            'lastName' => 'Geary',
            'email' => 'Fyn@example.com',
            'studentNumber' => 'WS123456',
            'cohortID' => 'active-cohort',
        ]);

        self::assertTrue($result['success']);
        self::assertSame('fyn@example.com', $result['email']);
        self::assertSame('ws123456', $result['username']);

        // No temporary password should be returned.
        self::assertArrayNotHasKey('temporaryPassword', $result);

        $account = $this->getAccount($result['userID']);

        self::assertSame('student', $account['accountType']);
        self::assertSame('approved', $account['approvalStatus']);
        self::assertSame(1, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
        self::assertSame(0, (int) $account['isEmailVerified']);

        // The account has a securely hashed, unusable initial password.
        self::assertNotEmpty($account['password']);
        self::assertFalse(password_verify('', $account['password']));
    }

    public function testCreateByLecturerRejectsNonStringInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Student::createByLecturer([
            'firstName' => ['Fyn'],
            'lastName' => 'Geary',
            'email' => 'fyn@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);
    }

    public function testCreateByLecturerRejectsInactiveCohort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected cohort is inactive');

        Student::createByLecturer([
            'firstName' => 'Fyn',
            'lastName' => 'Geary',
            'email' => 'fyn@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'inactive-cohort',
        ]);
    }

    public function testCreateByLecturerRejectsDuplicateEmail(): void
    {
        $this->createStudent();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('email address already exists');

        Student::createByLecturer([
            'firstName' => 'Another',
            'lastName' => 'Student',
            'email' => 'student@example.com',
            'studentNumber' => 'ws654321',
            'cohortID' => 'active-cohort',
        ]);
    }

    public function testCreateByLecturerRejectsDuplicateStudentNumber(): void
    {
        $this->createStudent();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('student number already exists');

        Student::createByLecturer([
            'firstName' => 'Another',
            'lastName' => 'Student',
            'email' => 'another@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);
    }

    // ---------------------------------------------------------
    // setEnabled tests
    // ---------------------------------------------------------

    public function testSetEnabledManuallyDisablingClearsCohortFlag(): void
    {
        $this->createStudent(
            isEnabled: 1,
            disabledByCohort: 1
        );

        self::assertTrue(Student::setEnabled('student-1', false));

        $account = $this->getAccount('student-1');

        self::assertSame(0, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
    }

    public function testSetEnabledEnablesStudentInInactiveCohort(): void
    {
        $this->createStudent(
            cohortID: 'inactive-cohort',
            isEnabled: 0,
            disabledByCohort: 1
        );

        self::assertTrue(Student::setEnabled('student-1', true));

        $account = $this->getAccount('student-1');

        self::assertSame(1, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
    }

    public function testSetEnabledEnablesStudentInActiveCohort(): void
    {
        $this->createStudent(
            isEnabled: 0,
            disabledByCohort: 0
        );

        self::assertTrue(Student::setEnabled('student-1', true));

        $account = $this->getAccount('student-1');

        self::assertSame(1, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
    }

    public function testSetEnabledReturnsFalseForUnknownStudent(): void
    {
        self::assertFalse(Student::setEnabled('missing-student', true));
    }

    // ---------------------------------------------------------
    // approveApplication tests
    // ---------------------------------------------------------

    public function testApproveApplicationPreservesDisabledAccountStatus(): void
    {
        $this->createStudent(
            approvalStatus: 'pending',
            isEnabled: 0,
            disabledByCohort: 0
        );

        self::assertTrue(Student::approveApplication('student-1'));

        $account = $this->getAccount('student-1');

        self::assertSame('approved', $account['approvalStatus']);
        self::assertSame(0, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
    }

    public function testApproveApplicationPreservesCohortDisabledStatus(): void
    {
        $this->createStudent(
            cohortID: 'inactive-cohort',
            approvalStatus: 'pending',
            isEnabled: 0,
            disabledByCohort: 1
        );

        self::assertTrue(Student::approveApplication('student-1'));

        $account = $this->getAccount('student-1');

        self::assertSame('approved', $account['approvalStatus']);
        self::assertSame(0, (int) $account['isEnabled']);
        self::assertSame(1, (int) $account['disabledByCohort']);
    }

    public function testApproveApplicationPreservesEnabledAccountStatus(): void
    {
        $this->createStudent(
            approvalStatus: 'pending',
            isEnabled: 1,
            disabledByCohort: 0
        );

        self::assertTrue(Student::approveApplication('student-1'));

        $account = $this->getAccount('student-1');

        self::assertSame('approved', $account['approvalStatus']);
        self::assertSame(1, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
    }

    public function testApproveApplicationDoesNotApproveAlreadyApprovedStudent(): void
    {
        $this->createStudent();

        self::assertFalse(Student::approveApplication('student-1'));
    }

    // ---------------------------------------------------------
    // declineApplication tests
    // ---------------------------------------------------------

    public function testDeclineApplicationDeletesPendingStudent(): void
    {
        $this->createStudent(approvalStatus: 'pending');

        self::assertTrue(Student::declineApplication('student-1'));
        self::assertFalse($this->getAccount('student-1'));
    }

    public function testDeclineApplicationDoesNotDeleteApprovedStudent(): void
    {
        $this->createStudent();

        self::assertFalse(Student::declineApplication('student-1'));
        self::assertIsArray($this->getAccount('student-1'));
    }

    // ---------------------------------------------------------
    // updateDetails tests
    // ---------------------------------------------------------

    public function testUpdateDetailsUpdatesStudentAndChangesEmail(): void
    {
        $this->createStudent();

        $emailChanged = Student::updateDetails('student-1', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => 'updated@example.com',
            'studentNumber' => 'ws654321',
            'cohortID' => 'active-cohort',
        ]);

        self::assertTrue($emailChanged);

        $account = $this->getAccount('student-1');
        $student = $this->getStudent('student-1');

        self::assertSame('updated@example.com', $account['email']);
        self::assertSame('ws654321', $account['username']);
        self::assertSame(0, (int) $account['isEmailVerified']);

        self::assertSame('Updated', $student['firstName']);
        self::assertSame('Name', $student['lastName']);
        self::assertSame('active-cohort', $student['cohortID']);
    }

    public function testUpdateDetailsKeepsEmailVerifiedWhenEmailIsUnchanged(): void
    {
        $this->createStudent(isEmailVerified: 1);

        $emailChanged = Student::updateDetails('student-1', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => 'student@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);

        self::assertFalse($emailChanged);

        $account = $this->getAccount('student-1');

        self::assertSame('student@example.com', $account['email']);
        self::assertSame(1, (int) $account['isEmailVerified']);
    }

    public function testUpdateDetailsKeepsUnverifiedEmailUnverified(): void
    {
        $this->createStudent(isEmailVerified: 0);

        $emailChanged = Student::updateDetails('student-1', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => 'student@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);

        self::assertFalse($emailChanged);

        $account = $this->getAccount('student-1');

        self::assertSame(0, (int) $account['isEmailVerified']);
    }

    public function testUpdateDetailsRejectsCohortChange(): void
    {
        $this->createStudent();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Student cohort changes must be completed through cohort management.'
        );

        Student::updateDetails('student-1', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => 'student@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'inactive-cohort',
        ]);
    }

    public function testUpdateDetailsDoesNotChangeCohortWhenRejected(): void
    {
        $this->createStudent();

        try {
            Student::updateDetails('student-1', [
                'firstName' => 'Updated',
                'lastName' => 'Name',
                'email' => 'student@example.com',
                'studentNumber' => 'ws123456',
                'cohortID' => 'inactive-cohort',
            ]);

            self::fail('Expected the cohort change to be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertSame(
                'Student cohort changes must be completed through cohort management.',
                $e->getMessage()
            );
        }

        $student = $this->getStudent('student-1');

        self::assertSame('active-cohort', $student['cohortID']);
        self::assertSame('Test', $student['firstName']);
    }

    public function testUpdateDetailsPreservesManualDisabledStatus(): void
    {
        $this->createStudent(
            isEnabled: 0,
            disabledByCohort: 0
        );

        Student::updateDetails('student-1', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => 'student@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);

        $account = $this->getAccount('student-1');

        self::assertSame(0, (int) $account['isEnabled']);
        self::assertSame(0, (int) $account['disabledByCohort']);
    }

    public function testUpdateDetailsRejectsUnknownStudent(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Student::updateDetails('missing-student', [
            'firstName' => 'Test',
            'lastName' => 'Student',
            'email' => 'student@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);
    }

    public function testUpdateDetailsRejectsNonStringInput(): void
    {
        $this->createStudent();

        $this->expectException(InvalidArgumentException::class);

        Student::updateDetails('student-1', [
            'firstName' => ['Test'],
            'lastName' => 'Student',
            'email' => 'student@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);
    }

    public function testUpdateDetailsRejectsDuplicateEmail(): void
    {
        $this->createStudent();
        $this->createStudent(
            userID: 'student-2',
            username: 'ws654321',
            email: 'another@example.com'
        );

        $this->expectException(InvalidArgumentException::class);

        Student::updateDetails('student-1', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => 'another@example.com',
            'studentNumber' => 'ws123456',
            'cohortID' => 'active-cohort',
        ]);
    }

    // ---------------------------------------------------------
    // deleteStudent tests
    // ---------------------------------------------------------

    public function testDeleteStudentDeletesStudentAccount(): void
    {
        $this->createStudent();

        self::assertTrue(Student::deleteStudent('student-1'));
        self::assertFalse($this->getAccount('student-1'));
    }

    public function testDeleteStudentDoesNotDeleteOtherAccountTypes(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO Users (
                userID, username, email, password, accountType,
                approvalStatus, isEnabled, disabledByCohort
            ) VALUES (
                'admin-1', 'admin1', 'admin@example.com', 'hash',
                'administrator', 'approved', 1, 0
            )
        ");

        $stmt->execute();

        self::assertFalse(Student::deleteStudent('admin-1'));
        self::assertIsArray($this->getAccount('admin-1'));
    }
}
