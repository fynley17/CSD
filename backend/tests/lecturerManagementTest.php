<?php

use App\Models\Administrator;
use App\Models\Lecturer;
use App\Models\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for what an admin can do to lecturer accounts
 * uses a pretend Users table in memory, the real database isn't touched
 * Tests are laid out Arrange (make the accounts), Act (press the button), Assert (check the table)
 */
final class LecturerManagementTest extends TestCase
{
    private \PDO $db;

    /**
     * Fresh Users, Lecturer and Tests tables for every test, one admin and two lecturers all enabled
     */
    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // the extra columns have defaults so the old tests don't need to fill them in
        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, username TEXT NOT NULL, email TEXT NOT NULL, '
            . "isEnabled INTEGER NOT NULL, password TEXT NOT NULL DEFAULT 'not-a-real-hash', "
            . "accountType TEXT NOT NULL DEFAULT 'lecturer', approvalStatus TEXT NOT NULL DEFAULT 'approved', "
            . 'isEmailVerified INTEGER NOT NULL DEFAULT 1)'
        );
        $this->db->exec(
            'INSERT INTO Users (userID, username, email, isEnabled) VALUES '
            . "('admin-id', 'adm01', 'adm01@example.test', 1), "
            . "('lecturer-id', 'lec01', 'lec01@example.test', 1), ('other-id', 'lec02', 'lec02@example.test', 1)"
        );
        $this->db->exec("UPDATE Users SET accountType = 'admin' WHERE userID = 'admin-id'");

        // Add Lecturer makes its ID with MariaDB's UUID(), SQLite doesn't have one so the test gives it one
        $this->db->sqliteCreateFunction('UUID', function (): string {
            return 'new-id';
        }, 0);

        // where the set password links go, same as PasswordSetupTest
        $this->db->exec(
            'CREATE TABLE PasswordSetupTokens (userID TEXT PRIMARY KEY, tokenHash TEXT NOT NULL UNIQUE, '
            . 'expiresAt TEXT NOT NULL, sentAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)'
        );
        $this->db->exec(
            'CREATE TABLE Lecturer (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL)'
        );
        $this->db->exec(
            "INSERT INTO Lecturer VALUES ('admin-id', 'Test', 'Admin'), ('lecturer-id', 'Test', 'One'), "
            . "('other-id', 'Test', 'Two')"
        );
        // 2 tests for the lecturer being deleted and one for the other lecturer
        // a test can only belong to someone in Lecturer, like the real table
        // SQLite ignores that unless foreign keys are switched on
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec(
            'CREATE TABLE Tests (testID TEXT PRIMARY KEY, createdBy TEXT NOT NULL REFERENCES Lecturer(userID))'
        );
        $this->db->exec(
            "INSERT INTO Tests VALUES ('test-1', 'lecturer-id'), ('test-2', 'lecturer-id'), ('test-3', 'other-id')"
        );

        // points the models at the pretend database instead of the real one
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    /**
     * Unhooks the pretend database so the next test file starts clean
     */
    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * Disable account turns off that lecturer and leaves the other one alone (normal)
     */
    public function testDisablesOnlyThatLecturer(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));

        // Act
        $admin->disableLecturer($lecturer);

        // Assert
        $this->assertSame(0, $this->getIsEnabled('lecturer-id'));
        $this->assertSame(1, $this->getIsEnabled('other-id'));
    }

    /**
     * Enable account brings a disabled lecturer back, this is also what Undo uses (normal)
     */
    public function testEnablesADisabledLecturerAgain(): void
    {
        // Arrange
        $this->db->exec("UPDATE Users SET isEnabled = 0 WHERE userID = 'lecturer-id'");
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));

        // Act
        $admin->enableLecturer($lecturer);

        // Assert
        $this->assertSame(1, $this->getIsEnabled('lecturer-id'));
    }

    /**
     * An admin can't disable an admin account, their own included, and nothing changes (error)
     */
    public function testRefusesToDisableAnAdministrator(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $message = '';

        // Act
        try {
            $admin->disableLecturer($admin);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $this->assertSame("Administrator accounts can't be changed on this page", $message);
        $this->assertSame(1, $this->getIsEnabled('admin-id'));
    }

    /**
     * Edit saves the new details, the spaces are trimmed and the email goes lowercase (normal)
     */
    public function testSavesTheEditedDetails(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));
        $details = [
            'firstName' => ' Edited ',
            'lastName' => 'Lecturer',
            'staffID' => 'L1002',
            'email' => 'Edited.Lecturer@Example.test',
        ];

        // Act
        $admin->editLecturer($lecturer, $details);

        // Assert
        $this->assertSame(
            [
                'username' => 'L1002',
                'email' => 'edited.lecturer@example.test',
                'firstName' => 'Edited',
                'lastName' => 'Lecturer',
            ],
            $this->getAccount('lecturer-id')
        );
    }

    /**
     * Saving with the lecturer's own staff ID and email still works, they don't clash with themselves (boundary)
     */
    public function testAllowsKeepingTheSameStaffIdAndEmail(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));
        $details = [
            'firstName' => 'Changed',
            'lastName' => 'One',
            'staffID' => 'lec01',
            'email' => 'lec01@example.test',
        ];

        // Act
        $admin->editLecturer($lecturer, $details);

        // Assert
        $this->assertSame('Changed', $this->getAccount('lecturer-id')['firstName']);
    }

    /**
     * A staff ID another lecturer already has is refused and nothing is saved (error)
     */
    public function testRefusesAStaffIdThatIsAlreadyTaken(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));
        $details = [
            'firstName' => 'Changed',
            'lastName' => 'One',
            'staffID' => 'lec02',
            'email' => 'new@example.test',
        ];
        $message = '';

        // Act
        try {
            $admin->editLecturer($lecturer, $details);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $this->assertSame('An account with this staff ID already exists', $message);
        $this->assertSame('lec01', $this->getAccount('lecturer-id')['username']);
    }

    /**
     * Each box filled in wrong, or an email someone else has, gets its own message and nothing is saved (error)
     */
    #[DataProvider('badDetails')]
    public function testRefusesEachBadBox(string $key, string $value, string $expected): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));
        $details = ['firstName' => 'Test', 'lastName' => 'One', 'staffID' => 'lec01', 'email' => 'lec01@example.test'];
        $details[$key] = $value;
        $message = '';

        // Act
        try {
            $admin->editLecturer($lecturer, $details);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $this->assertSame($expected, $message);
        $this->assertSame(['username' => 'lec01', 'email' => 'lec01@example.test', 'firstName' => 'Test',
            'lastName' => 'One'], $this->getAccount('lecturer-id'));
    }

    /**
     * one wrong box at a time and the message the pop-up should show
     */
    public static function badDetails(): array
    {
        return [
            'no first name' => ['firstName', '', 'Enter a first name using letters, spaces, hyphens or apostrophes'],
            'bad surname' => ['lastName', 'On3', 'Enter a last name using letters, spaces, hyphens or apostrophes'],
            'short staff ID' => ['staffID', 'ab', 'Staff ID must be 3 to 8 letters or numbers, like L1001'],
            'bad email' => ['email', 'nope', 'Enter an email address in the correct format, like name@example.com'],
            'email taken' => ['email', 'LEC02@example.test', 'An account with this email address already exists'],
        ];
    }

    /**
     * Delete removes the lecturer and their tests move to the admin, the other lecturer's test stays (normal)
     */
    public function testDeletesTheLecturerAndKeepsTheirTests(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));

        // Act
        $admin->deleteLecturer($lecturer);

        // Assert
        $this->assertSame(0, $this->countAccounts('lecturer-id'));
        $this->assertSame(
            ['test-1' => 'admin-id', 'test-2' => 'admin-id', 'test-3' => 'other-id'],
            $this->getTestOwners()
        );
    }

    /**
     * An admin with no Lecturer row can't take the tests over, so the delete is refused and nothing changes (error)
     */
    public function testRefusesDeleteWhenTheAdminHasNoLecturerRow(): void
    {
        // Arrange
        $this->db->exec("DELETE FROM Lecturer WHERE userID = 'admin-id'");
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $lecturer = new Lecturer($this->makeRow('lecturer-id', 'lecturer'));
        $message = '';

        // Act
        try {
            $admin->deleteLecturer($lecturer);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $expected = "Your account has no lecturer profile, so this lecturer's tests can't be moved to you";
        $this->assertSame($expected, $message);
        $this->assertSame(1, $this->countAccounts('lecturer-id'));
        $this->assertSame('lecturer-id', $this->getTestOwners()['test-1']);
    }

    /**
     * An admin account can't be deleted, their own included, and its still there afterwards (error)
     */
    public function testRefusesToDeleteAnAdministrator(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $message = '';

        // Act
        try {
            $admin->deleteLecturer($admin);
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $this->assertSame("Administrator accounts can't be changed on this page", $message);
        $this->assertSame(1, $this->countAccounts('admin-id'));
    }

    /**
     * Add Lecturer makes the account with no password anyone knows, an admin gets a Lecturer row too (normal)
     */
    #[DataProvider('newRoles')]
    public function testAddsAnAccountThatSetsItsOwnPassword(string $role): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));

        // Act
        $created = $admin->createLecturer($this->newDetails($role));

        // Assert
        $this->assertSame($role, $created['role']);
        $this->assertSame(
            [
                'username' => 'L2001',
                'email' => 'new.lecturer@example.test',
                'firstName' => 'New',
                'lastName' => 'Lecturer',
            ],
            $this->getAccount('new-id')
        );
        $stmt = $this->db->query("SELECT accountType, isEmailVerified, password FROM Users WHERE userID = 'new-id'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame($role, $row['accountType']);
        // 0 means they haven't used the emailed link yet so nobody knows the password
        $this->assertSame(0, (int) $row['isEmailVerified']);
        $this->assertNotSame('', $row['password']);
    }

    /**
     * the two roles the Add Lecturer pop-up can send
     */
    public static function newRoles(): array
    {
        return ['lecturer' => ['lecturer'], 'admin' => ['admin']];
    }

    /**
     * Only Lecturer or Administrator can be picked, sending student is refused and nothing is saved (error)
     */
    public function testRefusesARoleThatIsNotLecturerOrAdmin(): void
    {
        // Arrange
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $message = '';

        // Act
        try {
            $admin->createLecturer($this->newDetails('student'));
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
        }

        // Assert
        $this->assertSame('Choose Lecturer or Administrator', $message);
        $this->assertSame(0, $this->countAccounts('new-id'));
    }

    /**
     * Resend link makes a new link for someone who hasn't set a password yet (normal)
     */
    public function testResendMakesALinkForSomeoneWithoutAPassword(): void
    {
        // Arrange
        $this->db->exec("UPDATE Users SET isEmailVerified = 0 WHERE userID = 'lecturer-id'");
        $admin = new Administrator($this->makeRow('admin-id', 'admin'));
        $row = $this->makeRow('lecturer-id', 'lecturer');
        $row['isEmailVerified'] = 0;
        $lecturer = new Lecturer($row);

        // Act
        $link = $admin->makeSetupLink($lecturer);

        // Assert
        $this->assertSame('lec01@example.test', $link['email']);
        $this->assertSame(64, strlen($link['token']));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM PasswordSetupTokens')->fetchColumn());
    }

    /**
     * What the Add Lecturer pop-up sends, made up test data
     */
    private function newDetails(string $role): array
    {
        return [
            'firstName' => 'New',
            'lastName' => 'Lecturer',
            'staffID' => 'L2001',
            'email' => 'New.Lecturer@example.test',
            'role' => $role,
        ];
    }

    /**
     * A made up Users row, same keys the real query gives, the password is just filler
     */
    private function makeRow(string $userID, string $accountType): array
    {
        return [
            'userID' => $userID,
            'username' => 'test' . $accountType,
            'password' => 'not-a-real-hash',
            'accountType' => $accountType,
            'approvalStatus' => 'approved',
            'isEnabled' => 1,
            'failedAttempts' => 0,
            'lockedUntil' => null,
            'isEmailVerified' => 1,
        ];
    }

    /**
     * Reads isEnabled straight from the table so the test checks what was really saved
     */
    private function getIsEnabled(string $userID): int
    {
        $stmt = $this->db->prepare('SELECT isEnabled FROM Users WHERE userID = ?');
        $stmt->execute([$userID]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Reads the saved login details and names of one lecturer so the test checks what was really saved
     */
    private function getAccount(string $userID): array
    {
        $stmt = $this->db->prepare(
            'SELECT Users.username, Users.email, Lecturer.firstName, Lecturer.lastName '
            . 'FROM Users JOIN Lecturer ON Lecturer.userID = Users.userID WHERE Users.userID = ?'
        );
        $stmt->execute([$userID]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * How many Users rows an ID has, 1 if the account is still there and 0 if it was deleted
     */
    private function countAccounts(string $userID): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM Users WHERE userID = ?');
        $stmt->execute([$userID]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * who owns each test, as testID => createdBy
     */
    private function getTestOwners(): array
    {
        $stmt = $this->db->prepare('SELECT testID, createdBy FROM Tests ORDER BY testID');
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
