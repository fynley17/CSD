<?php

use App\Helpers\Authentication;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the secret code check that stops other websites faking requests as a logged in user
 * runs isValidCsrfToken() on its own, so no session, browser or server is needed
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
        $token = $this->makeToken();

        self::assertTrue(Authentication::isValidCsrfToken($token, $token));
    }

    /**
     * One letter different is still wrong, nearly right isn't sufficient (boundary)
     */
    public function testRejectsTokenWithOneLetterChanged(): void
    {
        $token = $this->makeToken();
        // swaps the last letter for an X, which a real code never has since it only uses 0-9 and a-f
        $nearlyRight = substr($token, 0, -1) . 'X';

        self::assertFalse(Authentication::isValidCsrfToken($token, $nearlyRight));
    }

    /**
     * No code sent at all, i.e. a request from another website that couldn't read ours (error)
     */
    public function testRejectsMissingToken(): void
    {
        self::assertFalse(Authentication::isValidCsrfToken($this->makeToken(), ''));
    }

    /**
     * Session has no code yet and nothing was sent, two blanks shouldn't count as a match (error)
     */
    public function testRejectsWhenSessionHasNoToken(): void
    {
        self::assertFalse(Authentication::isValidCsrfToken('', ''));
    }
}
