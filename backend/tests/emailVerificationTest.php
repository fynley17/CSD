<?php

use App\Controllers\VerificationController;
use App\Models\EmailVerification;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the email verification link codes and the check the controller does on what the page sends
 * none of these touch the database, so no server is needed
 */
final class EmailVerificationTest extends TestCase
{
    /**
     * A new code is 64 hex characters and passes the format check (normal)
     */
    public function testMadeTokenIsValid(): void
    {
        $token = EmailVerification::makeToken();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertTrue(EmailVerification::isValidToken($token));
    }

    /**
     * Two codes are never the same, so one student's link can't be guessed from another's (normal)
     */
    public function testTokensAreDifferentEachTime(): void
    {
        self::assertNotSame(EmailVerification::makeToken(), EmailVerification::makeToken());
    }

    /**
     * The saved hash isn't the code itself, so a leaked database can't be used to verify accounts (normal)
     */
    public function testHashIsNotTheToken(): void
    {
        $token = EmailVerification::makeToken();
        $hash = EmailVerification::hashToken($token);

        self::assertNotSame($token, $hash);
        self::assertSame(64, strlen($hash));
        self::assertSame($hash, EmailVerification::hashToken($token));
    }

    /**
     * Codes that are the wrong length, have other characters or aren't text are turned away (error)
     */
    public function testRejectsBadTokens(): void
    {
        self::assertFalse(EmailVerification::isValidToken(str_repeat('a', 63)));
        self::assertFalse(EmailVerification::isValidToken(str_repeat('a', 65)));
        self::assertFalse(EmailVerification::isValidToken(str_repeat('g', 64)));
        self::assertFalse(EmailVerification::isValidToken(str_repeat('a', 64) . "\n"));
        self::assertFalse(EmailVerification::isValidToken(['token']));
        self::assertFalse(EmailVerification::isValidToken(null));
    }

    /**
     * The controller only passes on a code that's there and the right shape (normal and error)
     */
    public function testControllerChecksToken(): void
    {
        $token = EmailVerification::makeToken();

        self::assertSame($token, VerificationController::checkToken(['token' => $token]));
        self::assertNull(VerificationController::checkToken(['token' => "' OR 1=1 --"]));
        self::assertNull(VerificationController::checkToken([]));
        self::assertNull(VerificationController::checkToken('not an array'));
    }
}
