<?php

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
     * Makes a pretend user so the tests don't need the database
     * keeps them quick, and they won't break if someone changes the data in phpMyAdmin
     * starts as a normal approved student, a test only passes in what it's testing, e.g. ['isEnabled' => 0]
     */
    private function makeUser(array $changes = []): User
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
        ];

        // goes through each change the test passed in and overwrites that column in the row
        // so each test only writes the one thing it's checking, not the whole row every time
        // if nothing's passed in the loop doesn't run and it stays a normal student
        foreach ($changes as $column => $value) {
            $row[$column] = $value;
        }

        // made the same way findByUsername() makes one, so the tests check the real User code
        return new User($row);
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
     * these are what end up in the session and the /api/me reply
     */
    public function testGettersReturnAccountDetails(): void
    {
        $user = $this->makeUser(['accountType' => 'admin']);

        self::assertSame('test-user-id', $user->getUserID());
        self::assertSame('ws000001', $user->getUsername());
        self::assertSame('admin', $user->getAccountType());
    }
}
