<?php

use App\Controllers\AuthController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the checks the login controller does on what the page sends
 * runs checkLoginDetails() immediately, so no server, browser or database is needed
 */
final class AuthControllerTest extends TestCase
{
    /**
     * Username and password both filled in should get through (normal)
     */
    public function testAcceptsUsernameAndPassword(): void
    {
        $details = AuthController::checkLoginDetails(['username' => 'stu01', 'password' => 'Test1234!']);

        self::assertSame(['username' => 'stu01', 'password' => 'Test1234!'], $details);
    }

    /**
     * Spaces either side of the username get taken off (boundary)
     * so that a space copied in by accident shouldn't stop someone logging in
     */
    public function testTrimsSpacesOffUsername(): void
    {
        $details = AuthController::checkLoginDetails(['username' => '  stu01 ', 'password' => 'Test1234!']);

        self::assertSame('stu01', $details['username']);
    }

    /**
     * Spaces in the password are left alone (boundary)
     * they could be part of someone's real password
     */
    public function testKeepsSpacesInPassword(): void
    {
        $details = AuthController::checkLoginDetails(['username' => 'stu01', 'password' => ' Test 1234! ']);

        self::assertSame(' Test 1234! ', $details['password']);
    }

    /**
     * Anything missing, blank or not text gets turned away (error)
     * null is what makes login() send back "Enter your username and password"
     * #[DataProvider] tells PHPUnit to get its input from badLoginDetails() below,
     * then run this test once for each case in that list, passing the case in as $data
     */
    #[DataProvider('badLoginDetails')]
    public function testRejectsBadLoginDetails(mixed $data): void
    {
        self::assertNull(AuthController::checkLoginDetails($data));
    }

    /**
     * Each bad thing the page, or someone skipping the page, could send
     * the values are made up - checkLoginDetails() only looks at whether they're there and are text,
     * it doesn't check them against a real account
     *
     * Each line is laid out as 'label' => [what goes into $data]
     * the label shows up in the PHPUnit output if that case fails, so it's clear which one broke
     * the outer [ ] is the list of things passed into the test, it only takes $data so there's one thing in it
     * the inner [ ] is the pretend request, so what the login form would send
     */
    public static function badLoginDetails(): array
    {
        return [
            // body wasn't JSON, so json_decode() gave back null
            'not JSON' => [null],
            // one of the two boxes left out of the request completely
            'missing username' => [['password' => 'Test1234!']],
            'missing password' => [['username' => 'stu01']],
            // boxes sent but empty, the spaces one is only blank once it's trimmed
            'blank username' => [['username' => '', 'password' => 'Test1234!']],
            'username only spaces' => [['username' => '   ', 'password' => 'Test1234!']],
            'blank password' => [['username' => 'stu01', 'password' => '']],
            // something that isn't text, only possible if someone sends the request without the form
            'username is a list' => [['username' => ['stu01'], 'password' => 'Test1234!']],
            'password is a number' => [['username' => 'stu01', 'password' => 12345678]],
        ];
    }
}
