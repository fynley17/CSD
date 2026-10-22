<?php

use App\Helpers\Authentication;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the secret code check that stops other websites faking requests as a logged in user,
 * and the 30 minute idle logout
 * runs isValidCsrfToken() and hasBeenIdleTooLong() on their own, so no session, browser or server is needed
 * each test is laid out Arrange (set up the data), Act (run the one function), Assert (check the answer)
 */
final class AuthenticationTest extends TestCase
{
    /**
     * Makes a code the same way the real one is made, 64 random letters and numbers
     */
    private function makeToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * The page sends back the exact code from the session, so it's let through (normal)
     */
    public function testAcceptsMatchingToken(): void
    {
        // Arrange
        $token = $this->makeToken();

        // Act
        $isValid = Authentication::isValidCsrfToken($token, $token);

        // Assert
        self::assertTrue($isValid);
    }

    /**
     * One letter different is still wrong, nearly right isn't sufficient (boundary)
     */
    public function testRejectsTokenWithOneLetterChanged(): void
    {
        // Arrange
        $token = $this->makeToken();
        // swaps the last letter for an X, which a real code never has since it only uses 0-9 and a-f
        $nearlyRight = substr($token, 0, -1) . 'X';

        // Act
        $isValid = Authentication::isValidCsrfToken($token, $nearlyRight);

        // Assert
        self::assertFalse($isValid);
    }

    /**
     * No code sent at all, i.e. a request from another website that couldn't read ours (error)
     */
    public function testRejectsMissingToken(): void
    {
        // Arrange
        $sessionToken = $this->makeToken();

        // Act
        $isValid = Authentication::isValidCsrfToken($sessionToken, '');

        // Assert
        self::assertFalse($isValid);
    }

    /**
     * Session has no code yet and nothing was sent, two blanks shouldn't count as a match (error)
     */
    public function testRejectsWhenSessionHasNoToken(): void
    {
        // Arrange
        $sessionToken = '';
        $sentToken = '';

        // Act
        $isValid = Authentication::isValidCsrfToken($sessionToken, $sentToken);

        // Assert
        self::assertFalse($isValid);
    }

    /**
     * Last request was 29 minutes 59 seconds ago, one second short, so still logged in (boundary)
     */
    public function testStillLoggedInJustBeforeTimeout(): void
    {
        // Arrange
        $now = time();
        $lastActivity = $now - Authentication::IDLE_TIMEOUT_SECONDS + 1;

        // Act
        $isIdle = Authentication::hasBeenIdleTooLong($lastActivity, $now);

        // Assert
        self::assertFalse($isIdle);
    }

    /**
     * Exactly 30 minutes since the last request, that's the limit so they're logged out (boundary)
     */
    public function testLoggedOutAtExactlyTimeout(): void
    {
        // Arrange
        $now = time();
        $lastActivity = $now - Authentication::IDLE_TIMEOUT_SECONDS;

        // Act
        $isIdle = Authentication::hasBeenIdleTooLong($lastActivity, $now);

        // Assert
        self::assertTrue($isIdle);
    }

    /**
     * A request a few seconds ago, the normal case while someone's using the site (normal)
     */
    public function testStillLoggedInAfterRecentRequest(): void
    {
        // Arrange
        $now = time();
        $lastActivity = $now - 5;

        // Act
        $isIdle = Authentication::hasBeenIdleTooLong($lastActivity, $now);

        // Assert
        self::assertFalse($isIdle);
    }

    /**
     * Saved time is ahead of now, i.e. the server clock was put back, still counts as active (error)
     */
    public function testStillLoggedInWhenSavedTimeIsAheadOfNow(): void
    {
        // Arrange
        $now = time();
        $lastActivity = $now + 60;

        // Act
        $isIdle = Authentication::hasBeenIdleTooLong($lastActivity, $now);

        // Assert
        self::assertFalse($isIdle);
    }
}
