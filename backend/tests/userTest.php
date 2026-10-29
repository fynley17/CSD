<?php

use App\Models\Administrator;
use App\Models\Lecturer;
use App\Models\Model;
use App\Models\Student;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the login checks in User
 * uses made up users, and a pretend database in memory for the failed attempt counting
 */
final class UserTest extends TestCase
{
    // fake password the test users get
    private const TEST_PASSWORD = 'Test1234!';

    private \PDO $db;

    /**
     * addFailedAttempt() counts in the database, so each test gets a fresh pretend Users table in memory
     * the real database is never touched, same way testModelTest.php does it
     */
    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, failedAttempts INTEGER, lockedUntil TEXT, lastFailedAt TEXT)'
        );
        $this->db->exec("INSERT INTO Users VALUES ('test-user-id', 0, NULL, NULL)");

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
     * Makes a pretend Users row so the tests don't need the database
     * keeps them quick, and they won't break if someone changes the data in phpMyAdmin
     * starts as a normal approved student, a test only passes in what it's testing, e.g. ['isEnabled' => 0]
     */
    private function makeRow(array $changes = []): array
    {
        // same column names as the real Users table, because the User constructor looks for those names
        $row = [
            'userID' => 'test-user-id',
            'username' => 'ws000001',
            // hashed like registration does it, so login() is checking against a real-looking hash
            'password' => password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT),
            'accountType' => 'student',
            'approvalStatus' => 'approved',
            'isEnabled' => 1,
            'failedAttempts' => 0,
            'lockedUntil' => null,
            'isEmailVerified' => 1,
        ];

        // goes through each change the test passed in and overwrites that column in the row
        // so each test only writes the one thing it's checking, not the whole row every time
        // if nothing's passed in the loop doesn't run and it stays a normal student
        foreach ($changes as $column => $value) {
            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * Puts a count and the time of the last wrong password into the pretend Users table
     */
    private function setStoredAttempts(int $failedAttempts, int $lastFailedAt): void
    {
        $stmt = $this->db->prepare('UPDATE Users SET failedAttempts = ?, lastFailedAt = ? WHERE userID = ?');
        $stmt->execute([$failedAttempts, date('Y-m-d H:i:s', $lastFailedAt), 'test-user-id']);
    }

    /**
     * Makes a pretend student from that row
     * a Student because User is abstract, the login code it runs is still all from User
     */
    private function makeUser(array $changes = []): User
    {
        return new Student($this->makeRow($changes));
    }

    /**
     * Right password should log in (normal)
     */
    public function testLoginAcceptsCorrectPassword(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $loggedIn = $user->login(self::TEST_PASSWORD);

        // Assert
        self::assertTrue($loggedIn);
    }

    /**
     * Wrong password shouldn't log in (error)
     */
    public function testLoginRejectsWrongPassword(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $loggedIn = $user->login('WrongPass1!');

        // Assert
        self::assertFalse($loggedIn);
    }

    /**
     * Same password with the wrong capitals shouldn't work either (boundary)
     * checks passwords are case sensitive
     */
    public function testLoginRejectsPasswordInWrongCase(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $loggedIn = $user->login('test1234!');

        // Assert
        self::assertFalse($loggedIn);
    }

    /**
     * Blank password should never match (boundary)
     * the controller already stops blank ones, this makes sure the model does too
     */
    public function testLoginRejectsEmptyPassword(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $loggedIn = $user->login('');

        // Assert
        self::assertFalse($loggedIn);
    }

    /**
     * Approved and enabled account can get in and isn't pending (normal)
     */
    public function testApprovedEnabledAccountIsActive(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $isActive = $user->isActive();
        $isPending = $user->isPending();

        // Assert
        self::assertTrue($isActive);
        self::assertFalse($isPending);
    }

    /**
     * Student still waiting on a lecturer can't get in (error)
     * isPending() is what lets the controller show the waiting for approval message
     */
    public function testPendingAccountIsNotActive(): void
    {
        // Arrange
        $user = $this->makeUser(['approvalStatus' => 'pending']);

        // Act
        $isActive = $user->isActive();
        $isPending = $user->isPending();

        // Assert
        self::assertFalse($isActive);
        self::assertTrue($isPending);
    }

    /**
     * Disabled account can't get in even if it's approved (error)
     * uses 0 because that's what the database gives back
     */
    public function testDisabledAccountIsNotActive(): void
    {
        // Arrange
        $user = $this->makeUser(['isEnabled' => 0]);

        // Act
        $isActive = $user->isActive();

        // Assert
        self::assertFalse($isActive);
    }

    /**
     * Account that hasn't clicked its email link shows as unverified (error)
     * the login controller uses this to send them to the check your email page
     */
    public function testUnverifiedEmailIsReported(): void
    {
        // Arrange
        $verifiedUser = $this->makeUser();
        $unverifiedUser = $this->makeUser(['isEmailVerified' => 0]);

        // Act
        $verifiedResult = $verifiedUser->isEmailVerified();
        $unverifiedResult = $unverifiedUser->isEmailVerified();

        // Assert
        self::assertTrue($verifiedResult);
        self::assertFalse($unverifiedResult);
    }

    /**
     * Getters give back the right details (normal)
     * these are what end up in the session and the /api/current-user reply
     */
    public function testGettersReturnAccountDetails(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $userID = $user->getUserID();
        $username = $user->getUsername();
        $accountType = $user->getAccountType();

        // Assert
        self::assertSame('test-user-id', $userID);
        self::assertSame('ws000001', $username);
        self::assertSame('student', $accountType);
    }

    /**
     * Each account type is a kind of User, and an Administrator is a Lecturer too (normal)
     * this is the inheritance on the class diagram, it's why an admin can do lecturer jobs
     */
    public function testAccountTypesInheritFromUser(): void
    {
        // Arrange and Act, making each account type is the thing being tested
        $student = $this->makeUser();
        $lecturer = new Lecturer($this->makeRow(['accountType' => 'lecturer']));
        $admin = new Administrator($this->makeRow(['accountType' => 'admin']));

        // Assert
        self::assertInstanceOf(User::class, $student);
        self::assertInstanceOf(User::class, $lecturer);
        self::assertInstanceOf(Lecturer::class, $admin);
    }

    /**
     * Account that's never had a wrong password isn't locked, and has no lock time left (normal)
     */
    public function testNewAccountIsNotLocked(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $isLocked = $user->isLocked();
        $minutesLeft = $user->getMinutesUntilUnlock();

        // Assert
        self::assertFalse($isLocked);
        self::assertSame(0, $minutesLeft);
    }

    /**
     * 2 wrong passwords isn't enough to lock it (boundary, one below the limit)
     */
    public function testTwoFailedAttemptsDoNotLockAccount(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        $user->addFailedAttempt();
        $user->addFailedAttempt();

        // Assert
        self::assertFalse($user->isLocked());
        self::assertSame(2, $user->getFailedAttempts());
    }

    /**
     * The 3rd wrong password in a row locks it for the full 15 minutes,
     * and the count goes back to 0 ready for after the lock (boundary)
     */
    public function testLocksAccountOnThirdFailedAttempt(): void
    {
        // Arrange
        $user = $this->makeUser();

        // Act
        for ($attempt = 1; $attempt <= User::MAX_FAILED_ATTEMPTS; $attempt++) {
            $user->addFailedAttempt();
        }

        // Assert
        self::assertTrue($user->isLocked());
        self::assertSame(User::LOCK_MINUTES, $user->getMinutesUntilUnlock());
        self::assertSame(0, $user->getFailedAttempts());
    }

    /**
     * Unlock time still to come means it's locked (error)
     */
    public function testAccountIsLockedUntilUnlockTime(): void
    {
        // Arrange
        $lockedUntil = date('Y-m-d H:i:s', time() + 60);
        $user = $this->makeUser(['lockedUntil' => $lockedUntil]);

        // Act
        $isLocked = $user->isLocked();

        // Assert
        self::assertTrue($isLocked);
    }

    /**
     * Unlock time already gone means it's unlocked again, no administrator needed (boundary)
     */
    public function testLockRunsOutByItself(): void
    {
        // Arrange
        $lockedUntil = date('Y-m-d H:i:s', time() - 1);
        $user = $this->makeUser(['lockedUntil' => $lockedUntil]);

        // Act
        $isLocked = $user->isLocked();

        // Assert
        self::assertFalse($isLocked);
    }

    /**
     * A right password wipes earlier wrong ones, so they don't count towards a lock later (normal)
     */
    public function testClearFailedAttemptsStartsCountAgain(): void
    {
        // Arrange, 2 wrong passwords a minute ago
        $this->setStoredAttempts(2, time() - 60);
        $user = $this->makeUser(['failedAttempts' => 2]);

        // Act, right password then a wrong one
        $user->clearFailedAttempts();
        $user->saveLoginAttempts();
        $user->addFailedAttempt();

        // Assert
        self::assertFalse($user->isLocked());
        self::assertSame(1, $user->getFailedAttempts());
    }

    /**
     * Wrong passwords sent at the same time all count, Fyn's race condition (error)
     * three requests each load the user while the count is still 0, the old code had every one save 1
     */
    public function testWrongPasswordsAtTheSameTimeStillLock(): void
    {
        // Arrange, three copies of the same account, like three login requests arriving together
        $firstRequest = $this->makeUser();
        $secondRequest = $this->makeUser();
        $thirdRequest = $this->makeUser();

        // Act
        $firstRequest->addFailedAttempt();
        $secondRequest->addFailedAttempt();
        $thirdRequest->addFailedAttempt();

        // Assert, the third one sees the real count of 3 and locks
        self::assertTrue($thirdRequest->isLocked());
    }

    /**
     * Wrong passwords over 15 minutes ago are forgotten, so they can't add up over weeks (boundary)
     */
    public function testOldFailedAttemptsStopCounting(): void
    {
        // Arrange, 2 wrong passwords, the last one 16 minutes ago
        $this->setStoredAttempts(2, time() - (User::ATTEMPT_WINDOW_MINUTES + 1) * 60);
        $user = $this->makeUser(['failedAttempts' => 2]);

        // Act
        $user->addFailedAttempt();

        // Assert, counts as the first one again, not the third
        self::assertFalse($user->isLocked());
        self::assertSame(1, $user->getFailedAttempts());
    }

    /**
     * Wrong passwords just inside the 15 minutes still add up (boundary)
     */
    public function testRecentFailedAttemptsStillCount(): void
    {
        // Arrange, 2 wrong passwords, the last one 14 minutes ago
        $this->setStoredAttempts(2, time() - (User::ATTEMPT_WINDOW_MINUTES - 1) * 60);
        $user = $this->makeUser(['failedAttempts' => 2]);

        // Act
        $user->addFailedAttempt();

        // Assert
        self::assertTrue($user->isLocked());
    }

    /**
     * 30 seconds left still shows as 1 minute, never 0 while locked (boundary)
     */
    public function testUnderAMinuteLeftRoundsUpToOne(): void
    {
        // Arrange
        $lockedUntil = date('Y-m-d H:i:s', time() + 30);
        $user = $this->makeUser(['lockedUntil' => $lockedUntil]);

        // Act
        $minutesLeft = $user->getMinutesUntilUnlock();

        // Assert
        self::assertSame(1, $minutesLeft);
    }
}
