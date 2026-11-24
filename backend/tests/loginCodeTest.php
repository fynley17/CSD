<?php

use App\Helpers\Authentication;
use App\Helpers\LoginCode;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the 6 digit email code (MFA)
 * times are passed in so nothing waits 10 mins
 */
final class LoginCodeTest extends TestCase
{
    // made up account and time for the tests
    private const USER_ID = 'test-user-id';
    private const SENT_AT = 1700000000;

    /**
     * Makes a pending login with a code the test knows
     */
    private function makeLoginCode(string $code): LoginCode
    {
        return new LoginCode(self::USER_ID, LoginCode::hashCode($code), self::SENT_AT, self::SENT_AT);
    }

    /**
     * Codes are 6 digits and the right one matches, even with a leading 0 (normal)
     */
    public function testRightCodeMatches(): void
    {
        // Arrange
        $madeCode = LoginCode::makeCode();
        $loginCode = $this->makeLoginCode('004211');

        // Act
        $isMatch = $loginCode->matches('004211', self::SENT_AT + 30);

        // Assert
        self::assertTrue(LoginCode::isEmailCode($madeCode));
        self::assertTrue($isMatch);
    }

    /**
     * Works up to 10 mins, stops at exactly 10, whole login runs out at 30 (boundary)
     */
    public function testCodeStopsWorkingAfterTenMinutes(): void
    {
        // Arrange
        $loginCode = $this->makeLoginCode('123456');
        $tenMinutes = LoginCode::LIFETIME_MINUTES * 60;

        // Act
        $justBefore = $loginCode->matches('123456', self::SENT_AT + $tenMinutes - 1);
        $onTheMinute = $loginCode->matches('123456', self::SENT_AT + $tenMinutes);

        // Assert
        self::assertTrue($justBefore);
        self::assertFalse($onTheMinute);
        self::assertFalse($loginCode->hasRunOut(self::SENT_AT + 30 * 60 - 1));
        self::assertTrue($loginCode->hasRunOut(self::SENT_AT + 30 * 60));
    }

    /**
     * Wrong code or junk in the session doesnt match (error)
     */
    public function testWrongCodeAndBadSessionAreTurnedAway(): void
    {
        // Arrange
        $loginCode = $this->makeLoginCode('123456');

        // Act
        $isMatch = $loginCode->matches('123457', self::SENT_AT);
        $fromJunk = LoginCode::fromSession(['userID' => self::USER_ID]);

        // Assert
        self::assertFalse($isMatch);
        self::assertNull($fromJunk);
        self::assertNull(LoginCode::fromSession(null));
    }

    /**
     * Only the first letter and domain show (normal)
     */
    public function testEmailIsMasked(): void
    {
        // Arrange: nothing to set up

        // Act
        $masked = LoginCode::maskEmail('ws000001@example.com');

        // Assert
        self::assertSame('w***@example.com', $masked);
    }

    /**
     * Password right but no code isnt a login, no userID in the session (error)
     * requireRole() refuses anything without a userID, getLoggedInUserID() reads the same thing
     * own process, sessions send headers
     */
    #[RunInSeparateProcess]
    public function testPendingLoginCannotReachProtectedEndpoints(): void
    {
        // Arrange
        $authentication = new Authentication();
        $pending = new LoginCode(self::USER_ID, LoginCode::hashCode('123456'), self::SENT_AT, self::SENT_AT);

        // Act
        $authentication->savePendingLogin($pending);

        // Assert
        self::assertNotNull($authentication->getPendingLogin());
        self::assertNull(Authentication::getLoggedInUserID());
    }
}
