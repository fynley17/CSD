<?php

use App\Helpers\Authentication;
use App\Helpers\LoginCode;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the 6 digit security code emailed at login when MFA is on
 * the times are passed in, so nothing has to wait 10 minutes
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
     * A made code is 6 digits and the right code logs in, even one starting with 0 (normal)
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
     * The code works up to 10 minutes then stops at exactly 10, and the whole login runs out at 30 (boundary)
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
     * A wrong code, or junk saved in the session, never logs anyone in (error)
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
     * Only the first letter and the domain of the email show on the page (normal)
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
     * Only getting the password right (MFA on) isn't a login, so protected endpoints still turn them away (error)
     * requireRole() stops any request with no userID in the session, which is what getLoggedInUserID() reads
     * in its own process, as starting a session sends headers
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
