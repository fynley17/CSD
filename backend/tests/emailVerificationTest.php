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
        // Arrange: nothing to set up, the code is made from scratch

        // Act
        $token = EmailVerification::makeToken();

        // Assert
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertTrue(EmailVerification::isValidToken($token));
    }

    /**
     * Two codes are never the same, so one student's link can't be guessed from another's (normal)
     */
    public function testTokensAreDifferentEachTime(): void
    {
        // Arrange: nothing to set up, the codes are made from scratch

        // Act
        $first = EmailVerification::makeToken();
        $second = EmailVerification::makeToken();

        // Assert
        self::assertNotSame($first, $second);
    }

    /**
     * The saved hash isn't the code itself, so a leaked database can't be used to verify accounts (normal)
     */
    public function testHashIsNotTheToken(): void
    {
        // Arrange
        $token = EmailVerification::makeToken();

        // Act
        $hash = EmailVerification::hashToken($token);

        // Assert
        self::assertNotSame($token, $hash);
        self::assertSame(64, strlen($hash));
        self::assertSame($hash, EmailVerification::hashToken($token));
    }

    /**
     * Codes that are the wrong length, have other characters or aren't text are turned away (error)
     */
    public function testRejectsBadTokens(): void
    {
        // Arrange
        $badTokens = [
            'too short' => str_repeat('a', 63),
            'too long' => str_repeat('a', 65),
            'not hex' => str_repeat('g', 64),
            'trailing new line' => str_repeat('a', 64) . "\n",
            'a list' => ['token'],
            'nothing' => null,
        ];

        // Act
        $results = array_map(fn (mixed $token) => EmailVerification::isValidToken($token), $badTokens);

        // Assert
        foreach ($results as $label => $isValid) {
            self::assertFalse($isValid, $label);
        }
    }

    /**
     * The controller only passes on a code that's there and the right shape (normal and error)
     */
    public function testControllerChecksToken(): void
    {
        // Arrange
        $token = EmailVerification::makeToken();

        // Act
        $goodToken = VerificationController::checkToken(['token' => $token]);
        $wrongShape = VerificationController::checkToken(['token' => "' OR 1=1 --"]);
        $noToken = VerificationController::checkToken([]);
        $notAnArray = VerificationController::checkToken('not an array');

        // Assert
        self::assertSame($token, $goodToken);
        self::assertNull($wrongShape);
        self::assertNull($noToken);
        self::assertNull($notAnArray);
    }

    /**
     * Change email only waits a minute when the address is the same one again, a new address is sent straight away
     */
    public function testOnlyTheSameAddressCountsAsAResend(): void
    {
        // Arrange
        $current = 'student@example.com';

        // Act
        $sameAddress = VerificationController::isSameEmail($current, 'student@example.com');
        $sameWithCaseAndSpaces = VerificationController::isSameEmail($current, '  Student@Example.COM ');
        $newAddress = VerificationController::isSameEmail($current, 'student@example.org');

        // Assert
        self::assertTrue($sameAddress);
        self::assertTrue($sameWithCaseAndSpaces);
        self::assertFalse($newAddress);
    }
}
