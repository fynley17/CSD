<?php

use App\Models\Administrator;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the login checks in User
 * uses made up users so it doesn't need the database
 */
final class UserTest extends TestCase
{
    // fake password the test users get
    private const TEST_PASSWORD = 'Test1234!';

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
        self::assertTrue($this->makeUser()->login(self::TEST_PASSWORD));
    }

    /**
     * Wrong password shouldn't log in (error)
     */
    public function testLoginRejectsWrongPassword(): void
    {
        self::assertFalse($this->makeUser()->login('WrongPass1!'));
    }

    /**
     * Same password with the wrong capitals shouldn't work either (boundary)
     * checks passwords are case sensitive
     */
    public function testLoginRejectsPasswordInWrongCase(): void
    {
        self::assertFalse($this->makeUser()->login('test1234!'));
    }

    /**
     * Blank password should never match (boundary)
     * the controller already stops blank ones, this makes sure the model does too
     */
    public function testLoginRejectsEmptyPassword(): void
    {
        self::assertFalse($this->makeUser()->login(''));
    }

    /**
     * Approved and enabled account can get in and isn't pending (normal)
     */
    public function testApprovedEnabledAccountIsActive(): void
    {
        $user = $this->makeUser();

        self::assertTrue($user->isActive());
        self::assertFalse($user->isPending());
    }

    /**
     * Student still waiting on a lecturer can't get in (error)
     * isPending() is what lets the controller show the waiting for approval message
     */
    public function testPendingAccountIsNotActive(): void
    {
        $user = $this->makeUser(['approvalStatus' => 'pending']);

        self::assertFalse($user->isActive());
        self::assertTrue($user->isPending());
    }

    /**
     * Disabled account can't get in even if it's approved (error)
     * uses 0 because that's what the database gives back
     */
    public function testDisabledAccountIsNotActive(): void
    {
        self::assertFalse($this->makeUser(['isEnabled' => 0])->isActive());
    }

    /**
     * Getters give back the right details (normal)
     * these are what end up in the session and the /api/current-user reply
     */
    public function testGettersReturnAccountDetails(): void
    {
        $user = $this->makeUser();

        self::assertSame('test-user-id', $user->getUserID());
        self::assertSame('ws000001', $user->getUsername());
        self::assertSame('student', $user->getAccountType());
    }

    /**
     * Each account type is a kind of User, and an Administrator is a Lecturer too (normal)
     * this is the inheritance on the class diagram, it's why an admin can do lecturer jobs
     */
    public function testAccountTypesInheritFromUser(): void
    {
        $student = $this->makeUser();
        $lecturer = new Lecturer($this->makeRow(['accountType' => 'lecturer']));
        $admin = new Administrator($this->makeRow(['accountType' => 'admin']));

        self::assertInstanceOf(User::class, $student);
        self::assertInstanceOf(User::class, $lecturer);
        self::assertInstanceOf(Lecturer::class, $admin);
    }

    /**
     * Account that's never had a wrong password isn't locked (normal)
     */
    public function testNewAccountIsNotLocked(): void
    {
        self::assertFalse($this->makeUser()->isLocked());
    }

    /**
     * 2 wrong passwords isn't enough to lock it (boundary, one below the limit)
     */
    public function testTwoFailedAttemptsDoNotLockAccount(): void
    {
        $user = $this->makeUser();
        $user->addFailedAttempt();
        $user->addFailedAttempt();

        self::assertFalse($user->isLocked());
        self::assertSame(2, $user->getFailedAttempts());
    }

    /**
     * The 3rd wrong password in a row locks it, and the count goes back to 0 ready for after the lock (boundary)
     */
    public function testLocksAccountOnThirdFailedAttempt(): void
    {
        $user = $this->makeUser();
        for ($attempt = 1; $attempt <= User::MAX_FAILED_ATTEMPTS; $attempt++) {
            $user->addFailedAttempt();
        }

        self::assertTrue($user->isLocked());
        self::assertSame(0, $user->getFailedAttempts());
    }

    /**
     * Unlock time still to come means it's locked (error)
     */
    public function testAccountIsLockedUntilUnlockTime(): void
    {
        $lockedUntil = date('Y-m-d H:i:s', time() + 60);

        self::assertTrue($this->makeUser(['lockedUntil' => $lockedUntil])->isLocked());
    }

    /**
     * Unlock time already gone means it's unlocked again, no administrator needed (boundary)
     */
    public function testLockRunsOutByItself(): void
    {
        $lockedUntil = date('Y-m-d H:i:s', time() - 1);

        self::assertFalse($this->makeUser(['lockedUntil' => $lockedUntil])->isLocked());
    }

    /**
     * A right password wipes earlier wrong ones, so they don't count towards a lock later (normal)
     */
    public function testClearFailedAttemptsStartsCountAgain(): void
    {
        $user = $this->makeUser(['failedAttempts' => 2]);
        $user->clearFailedAttempts();
        $user->addFailedAttempt();

        self::assertFalse($user->isLocked());
        self::assertSame(1, $user->getFailedAttempts());
    }
}
