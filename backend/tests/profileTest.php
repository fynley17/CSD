<?php

use App\Controllers\ProfileController;
use App\Helpers\Authentication;
use App\Models\Administrator;
use App\Models\EmailVerification;
use App\Models\Lecturer;
use App\Models\Model;
use App\Models\Student;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the My profile page (CTS-203), the User model methods and the checks ProfileController uses
 * jsonResponse() stops the script so the endpoints can't be called here, these call what they use instead
 * each test is laid out Arrange, Act, Assert
 */
final class ProfileTest extends TestCase
{
    private const OLD_PASSWORD = 'OldPass1!';
    private const NEW_PASSWORD = 'NewPass1!';

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->db->exec("
            CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                accountType TEXT NOT NULL,
                approvalStatus TEXT NOT NULL DEFAULT 'approved',
                isEnabled INTEGER NOT NULL DEFAULT 1,
                isEmailVerified INTEGER NOT NULL DEFAULT 1,
                failedAttempts INTEGER NOT NULL DEFAULT 0,
                lockedUntil TEXT NULL,
                lastFailedAt TEXT NULL
            );

            CREATE TABLE Cohorts (cohortID TEXT PRIMARY KEY, cohortName TEXT NOT NULL);

            CREATE TABLE Students (
                userID TEXT PRIMARY KEY REFERENCES Users(userID) ON DELETE CASCADE,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL,
                cohortID TEXT NOT NULL REFERENCES Cohorts(cohortID)
            );

            CREATE TABLE Lecturer (
                userID TEXT PRIMARY KEY REFERENCES Users(userID) ON DELETE CASCADE,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL
            );

            CREATE TABLE EmailVerifications (
                userID TEXT PRIMARY KEY REFERENCES Users(userID) ON DELETE CASCADE,
                tokenHash TEXT NOT NULL UNIQUE,
                expiresAt TEXT NOT NULL,
                sentAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                newEmail TEXT NULL
            );

            CREATE TABLE PasswordResetTokens (
                userID TEXT PRIMARY KEY REFERENCES Users(userID) ON DELETE CASCADE,
                tokenHash TEXT NOT NULL UNIQUE,
                expiresAt TEXT NOT NULL,
                sentAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $hash = password_hash(self::OLD_PASSWORD, PASSWORD_DEFAULT);
        $user = $this->db->prepare(
            'INSERT INTO Users (userID, username, email, password, accountType) VALUES (?, ?, ?, ?, ?)'
        );
        $user->execute(['student-1', 'ws000001', 'student@example.com', $hash, 'student']);
        $user->execute(['student-2', 'ws000002', 'other@example.com', $hash, 'student']);
        $user->execute(['lecturer-1', 'L1001', 'lecturer@example.com', $hash, 'lecturer']);
        $user->execute(['admin-1', 'A1001', 'admin@example.com', $hash, 'admin']);

        $this->db->exec("INSERT INTO Cohorts VALUES ('cohort-1', 'Computing 2026')");
        $this->db->exec("
            INSERT INTO Students VALUES
                ('student-1', 'Sam', 'Smith', 'cohort-1'),
                ('student-2', 'Ola', 'Jones', 'cohort-1');
            INSERT INTO Lecturer VALUES ('lecturer-1', 'Lee', 'Brown'), ('admin-1', 'Ada', 'Admin');
        ");

        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, null);
    }

    /**
     * Loads an account from the pretend database, the same way the controller does from the session's user ID
     */
    private function account(string $userID): User
    {
        $user = User::findById($userID);
        self::assertNotNull($user);

        return $user;
    }

    /**
     * Reads one column of a Users row
     */
    private function usersColumn(string $column, string $userID): mixed
    {
        $stmt = $this->db->prepare("SELECT $column FROM Users WHERE userID = ?");
        $stmt->execute([$userID]);

        return $stmt->fetchColumn();
    }

    /**
     * A student sees their own names, email, username, account type and cohort (normal)
     */
    public function testStudentProfileShowsTheirDetailsAndCohort(): void
    {
        // Act
        $profile = $this->account('student-1')->getProfile();

        // Assert
        self::assertSame([
            'username' => 'ws000001',
            'accountType' => 'student',
            'firstName' => 'Sam',
            'lastName' => 'Smith',
            'email' => 'student@example.com',
            'pendingEmail' => null,
            'cohortName' => 'Computing 2026',
        ], $profile);
    }

    /**
     * Lecturers and admins read their names from the Lecturer table and have no cohort (normal)
     */
    public function testStaffProfilesComeFromTheLecturerTable(): void
    {
        // Act
        $lecturer = $this->account('lecturer-1');
        $admin = $this->account('admin-1');

        // Assert
        self::assertInstanceOf(Lecturer::class, $lecturer);
        self::assertInstanceOf(Administrator::class, $admin);
        self::assertSame('Lee', $lecturer->getProfile()['firstName']);
        self::assertSame('Admin', $admin->getProfile()['lastName']);
        self::assertSame('admin', $admin->getProfile()['accountType']);
        self::assertArrayNotHasKey('cohortName', $lecturer->getProfile());
    }

    /**
     * The form's details are trimmed and the email is saved in lower case (normal)
     */
    public function testCheckProfileDetailsTrimsAndLowercases(): void
    {
        // Act
        $details = User::checkProfileDetails([
            'firstName' => '  Mary-Jane ',
            'lastName' => " O'Neil ",
            'email' => ' New@Example.COM ',
        ]);

        // Assert
        self::assertSame(
            ['firstName' => 'Mary-Jane', 'lastName' => "O'Neil", 'email' => 'new@example.com'],
            $details
        );
    }

    /**
     * Missing, wrong shape or badly formatted details are turned away before anything is saved (error)
     */
    #[DataProvider('badProfileDetails')]
    public function testCheckProfileDetailsRejectsBadInput(mixed $data, string $expectedWord): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/' . $expectedWord . '/');

        // Act
        User::checkProfileDetails($data);
    }

    public static function badProfileDetails(): array
    {
        $good = ['firstName' => 'Sam', 'lastName' => 'Smith', 'email' => 'sam@example.com'];

        return [
            'not an array' => ['nonsense', 'first name'],
            'first name missing' => [['lastName' => 'Smith', 'email' => 'sam@example.com'], 'first name'],
            'first name too short' => [['firstName' => 'S'] + $good, 'first name'],
            'first name has numbers' => [['firstName' => 'Sam1'] + $good, 'first name'],
            'last name an array' => [['lastName' => ['Smith']] + $good, 'last name'],
            'last name too long' => [['lastName' => str_repeat('a', 51)] + $good, 'last name'],
            'email wrong format' => [['email' => 'not-an-email'] + $good, 'email address'],
            'email blank' => [['email' => '   '] + $good, 'email address'],
        ];
    }

    /**
     * Saving names only changes this student's Students row, not anyone else's, and starts no email change (normal)
     */
    public function testUpdateProfileSavesOnlyThisStudentsNames(): void
    {
        // Arrange
        $details = ['firstName' => 'Samuel', 'lastName' => 'Smyth', 'email' => 'student@example.com'];

        // Act
        $token = $this->account('student-1')->updateProfile($details);

        // Assert
        self::assertNull($token);
        $names = $this->db->query('SELECT userID, firstName, lastName FROM Students ORDER BY userID')
            ->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([
            ['userID' => 'student-1', 'firstName' => 'Samuel', 'lastName' => 'Smyth'],
            ['userID' => 'student-2', 'firstName' => 'Ola', 'lastName' => 'Jones'],
        ], $names);
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM EmailVerifications')->fetchColumn());
    }

    /**
     * A lecturer's names are saved to the Lecturer table (normal)
     */
    public function testUpdateProfileSavesLecturerNames(): void
    {
        // Act
        $this->account('lecturer-1')->updateProfile(
            ['firstName' => 'Leo', 'lastName' => 'Browne', 'email' => 'lecturer@example.com']
        );

        // Assert
        self::assertSame('Leo Browne', $this->account('lecturer-1')->getFullName());
    }

    /**
     * A new email keeps the old one on the account until the link is opened (normal)
     */
    public function testNewEmailWaitsForTheLink(): void
    {
        // Arrange
        $student = $this->account('student-1');

        // Act
        $token = $student->updateProfile(['firstName' => 'Sam', 'lastName' => 'Smith', 'email' => 'new@example.com']);

        // Assert
        self::assertNotNull($token);
        self::assertSame('student@example.com', $this->usersColumn('email', 'student-1'));
        self::assertSame('new@example.com', $student->getProfile()['pendingEmail']);

        $link = EmailVerification::findByToken($token);
        self::assertNotNull($link);
        self::assertSame('student-1', $link['userID']);
        self::assertSame('new@example.com', $link['newEmail']);
        self::assertFalse($link['isExpired']);
    }

    /**
     * Opening the link swaps in the new address and the link can't be used again (normal)
     */
    public function testConfirmingTheLinkChangesTheEmail(): void
    {
        // Arrange
        $token = $this->account('student-1')->updateProfile(
            ['firstName' => 'Sam', 'lastName' => 'Smith', 'email' => 'new@example.com']
        );

        // Act
        $newEmail = EmailVerification::confirmEmailChange('student-1');

        // Assert
        self::assertSame('new@example.com', $newEmail);
        self::assertSame('new@example.com', $this->usersColumn('email', 'student-1'));
        self::assertNull(EmailVerification::findByToken($token));
        self::assertNull($this->account('student-1')->getProfile()['pendingEmail']);
    }

    /**
     * An address another account already uses is refused, and the names aren't saved either (error)
     */
    public function testEmailUsedByAnotherAccountIsRefused(): void
    {
        // Arrange
        $student = $this->account('student-1');

        // Act
        try {
            $student->updateProfile(['firstName' => 'Changed', 'lastName' => 'Smith', 'email' => 'other@example.com']);
            self::fail('Expected the address to be refused');
        } catch (InvalidArgumentException $e) {
            // Assert
            self::assertSame('Another account is already using this email address', $e->getMessage());
        }
        self::assertSame('Sam', $student->getProfile()['firstName']);
        self::assertSame('student@example.com', $this->usersColumn('email', 'student-1'));
    }

    /**
     * If someone else takes the address before the link is opened, the change is refused (edge)
     */
    public function testConfirmRefusesAnAddressTakenSinceTheLinkWasSent(): void
    {
        // Arrange
        $this->account('student-1')->updateProfile(
            ['firstName' => 'Sam', 'lastName' => 'Smith', 'email' => 'new@example.com']
        );
        $this->db->exec("UPDATE Users SET email = 'new@example.com' WHERE userID = 'student-2'");

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        try {
            EmailVerification::confirmEmailChange('student-1');
        } finally {
            self::assertSame('student@example.com', $this->usersColumn('email', 'student-1'));
        }
    }

    /**
     * A change that couldn't be emailed is thrown away, the old address stays (normal)
     */
    public function testCancelEmailChangeForgetsTheNewAddress(): void
    {
        // Arrange
        $this->account('student-1')->updateProfile(
            ['firstName' => 'Sam', 'lastName' => 'Smith', 'email' => 'new@example.com']
        );

        // Act
        EmailVerification::cancelEmailChange('student-1');

        // Assert
        self::assertNull(EmailVerification::findPendingEmail('student-1'));
        self::assertSame('student@example.com', $this->usersColumn('email', 'student-1'));
    }

    /**
     * An expired link no longer shows as a change waiting (boundary)
     */
    public function testExpiredChangeIsNotShownAsWaiting(): void
    {
        // Arrange
        $this->account('student-1')->updateProfile(
            ['firstName' => 'Sam', 'lastName' => 'Smith', 'email' => 'new@example.com']
        );
        $this->db->exec("UPDATE EmailVerifications SET expiresAt = datetime('now', '-1 seconds')");

        // Act
        $pending = EmailVerification::findPendingEmail('student-1');

        // Assert
        self::assertNull($pending);
    }

    /**
     * The right current password and a strong new one changes the password, and old reset links stop working (normal)
     */
    public function testChangePasswordWithTheRightCurrentPassword(): void
    {
        // Arrange
        $this->db->exec("INSERT INTO PasswordResetTokens VALUES ('student-1', 'hash', datetime('now', '+1 hours'), " .
            "datetime('now'))");
        $student = $this->account('student-1');

        // Act
        $changed = $student->changePassword(self::OLD_PASSWORD, self::NEW_PASSWORD, self::NEW_PASSWORD);

        // Assert
        self::assertTrue($changed);
        self::assertTrue($this->account('student-1')->login(self::NEW_PASSWORD));
        self::assertFalse($this->account('student-1')->login(self::OLD_PASSWORD));
        self::assertTrue($this->account('student-2')->login(self::OLD_PASSWORD));
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM PasswordResetTokens')->fetchColumn());
        // stored as a hash, never the password itself
        self::assertNotSame(self::NEW_PASSWORD, $this->usersColumn('password', 'student-1'));
    }

    /**
     * A wrong current password changes nothing and counts towards the lock (error)
     */
    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        // Arrange
        $student = $this->account('student-1');

        // Act
        $changed = $student->changePassword('WrongPass1!', self::NEW_PASSWORD, self::NEW_PASSWORD);

        // Assert
        self::assertFalse($changed);
        self::assertTrue($this->account('student-1')->login(self::OLD_PASSWORD));
        self::assertSame(1, (int) $this->usersColumn('failedAttempts', 'student-1'));
    }

    /**
     * Three wrong current passwords lock the account, the same as the login page (boundary)
     */
    public function testThreeWrongCurrentPasswordsLockTheAccount(): void
    {
        // Arrange
        $student = $this->account('student-1');

        // Act
        for ($try = 0; $try < User::MAX_FAILED_ATTEMPTS; $try++) {
            $student->changePassword('WrongPass1!', self::NEW_PASSWORD, self::NEW_PASSWORD);
        }

        // Assert
        self::assertTrue($student->isLocked());
        self::assertTrue($this->account('student-1')->isLocked());
    }

    /**
     * A new password that breaks the rules, or doesn't match its confirm box, is refused (error)
     */
    #[DataProvider('badNewPasswords')]
    public function testChangePasswordRejectsBadNewPasswords(string $newPassword, string $confirm): void
    {
        // Arrange
        $student = $this->account('student-1');

        // Act
        try {
            $student->changePassword(self::OLD_PASSWORD, $newPassword, $confirm);
            self::fail('Expected the new password to be refused');
        } catch (InvalidArgumentException) {
            // Assert
            self::assertTrue($this->account('student-1')->login(self::OLD_PASSWORD));
            // breaking the rules isn't a wrong guess, so it doesn't count towards the lock
            self::assertSame(0, (int) $this->usersColumn('failedAttempts', 'student-1'));
        }
    }

    public static function badNewPasswords(): array
    {
        return [
            'too short' => ['Ab1!', 'Ab1!'],
            'seven characters' => ['Abcde1!', 'Abcde1!'],
            'no uppercase' => ['newpass1!', 'newpass1!'],
            'no symbol' => ['NewPass12', 'NewPass12'],
            'confirm does not match' => [self::NEW_PASSWORD, 'NewPass2!'],
        ];
    }

    /**
     * The controller only accepts three non-blank text passwords (normal and error)
     */
    public function testCheckPasswordFields(): void
    {
        // Arrange
        $good = ['currentPassword' => 'a b', 'newPassword' => 'c', 'confirmPassword' => 'c'];

        // Act and Assert
        self::assertSame($good, ProfileController::checkPasswordFields($good));
        self::assertNull(ProfileController::checkPasswordFields(null));
        self::assertNull(ProfileController::checkPasswordFields(['currentPassword' => ''] + $good));
        self::assertNull(ProfileController::checkPasswordFields(['newPassword' => 123] + $good));
        self::assertNull(ProfileController::checkPasswordFields(['currentPassword' => 'a', 'newPassword' => 'b']));
    }

    /**
     * No endpoint takes a user ID, from the address or anywhere else, so nobody can pick another account (security)
     */
    public function testEndpointsCannotBeGivenAnotherUsersID(): void
    {
        // Arrange
        $router = require __DIR__ . '/../routes.php';
        $routes = (new ReflectionProperty($router, 'routes'))->getValue($router);

        // Act
        $profileRoutes = array_filter(
            $routes,
            fn (array $route) => $route['callback'][0] instanceof ProfileController
        );

        // Assert
        self::assertCount(3, $profileRoutes);
        foreach ($profileRoutes as $route) {
            // no ( in the pattern means nothing from the address is passed to the method
            self::assertStringNotContainsString('(', $route['pattern']);
            $method = new ReflectionMethod($route['callback'][0], $route['callback'][1]);
            self::assertSame(0, $method->getNumberOfParameters());
        }
    }

    /**
     * Updating one account's profile never changes another account (security)
     */
    public function testOneUserCannotChangeAnotherUsersProfile(): void
    {
        // Arrange
        $before = $this->account('student-2')->getProfile();

        // Act
        $this->account('student-1')->updateProfile(
            ['firstName' => 'Ola', 'lastName' => 'Jones', 'email' => 'mine@example.com']
        );
        $this->account('student-1')->changePassword(self::OLD_PASSWORD, self::NEW_PASSWORD, self::NEW_PASSWORD);

        // Assert
        self::assertSame($before, $this->account('student-2')->getProfile());
        self::assertTrue($this->account('student-2')->login(self::OLD_PASSWORD));
    }

    /**
     * Without a login session there's no user for the profile endpoints to load (error)
     * in its own process, as starting a session sends headers
     */
    #[RunInSeparateProcess]
    public function testNoSessionMeansNoProfileUser(): void
    {
        // Act
        $userID = Authentication::getLoggedInUserID();

        // Assert
        self::assertNull($userID);
    }

    /**
     * The session decides whose profile is loaded, the logged in user always gets their own (security)
     * in its own process, as starting a session sends headers
     */
    #[RunInSeparateProcess]
    public function testSessionPicksTheLoggedInUsersProfile(): void
    {
        // Arrange
        (new Authentication())->saveLogin($this->account('student-2'));

        // Act
        $profile = $this->account((string) Authentication::getLoggedInUserID())->getProfile();

        // Assert
        self::assertSame('ws000002', $profile['username']);
        self::assertSame('other@example.com', $profile['email']);
    }

    /**
     * Students are made by the right class, so they read from the Students table (normal)
     */
    public function testStudentAccountIsAStudent(): void
    {
        self::assertInstanceOf(Student::class, $this->account('student-1'));
    }
}
