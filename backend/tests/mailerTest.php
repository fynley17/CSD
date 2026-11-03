<?php

use App\Helpers\Mailer;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    // Check the welcome email content without needing a mail server.
    public function testWelcomeEmailIncludesStudentDetails(): void
    {
        // Arrange
        $firstName = 'Fyn';
        $lastName = 'Geary';
        $cohortName = 'Computing 2026';

        // Act
        $email = Mailer::buildWelcomeEmail($firstName, $lastName, $cohortName);

        // Assert
        self::assertStringContainsString('pending approval', $email['subject']);
        self::assertStringContainsString('Welcome to RevisionHub, Fyn', $email['html']);
        self::assertStringContainsString('Fyn Geary', $email['html']);
        self::assertStringContainsString('Computing 2026', $email['html']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
        self::assertStringContainsString('Cohort: Computing 2026', $email['text']);
    }

    public function testWelcomeEmailEscapesHtml(): void
    {
        // Arrange
        $cohortName = '<script>alert(1)</script>';

        // Act
        $email = Mailer::buildWelcomeEmail('Fyn', 'Geary', $cohortName);

        // Assert
        self::assertStringNotContainsString('<script>', $email['html']);
        self::assertStringContainsString('&lt;script&gt;', $email['html']);
    }

    // The verification email should only contain the verification link.
    public function testVerificationEmailIncludesLinkButNoPersonalDetails(): void
    {
        // Arrange
        $link = 'http://localhost:5173/verify-email?token=abc123';

        // Act
        $email = Mailer::buildVerificationEmail($link);

        // Assert
        self::assertStringContainsString('Verify your email address', $email['subject']);
        self::assertStringContainsString('href="' . $link . '"', $email['html']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
        self::assertStringContainsString($link, $email['text']);
        self::assertStringNotContainsString('Cohort', $email['html']);
    }

    public function testVerificationLinkUsesAppUrl(): void
    {
        // Arrange
        // Restore the original environment value afterwards.
        $originalUrl = getenv('APP_URL');
        putenv('APP_URL=https://revisionhub.example/');

        // Act
        try {
            $link = Mailer::verificationLink('abc123');
        } finally {
            putenv($originalUrl === false ? 'APP_URL' : 'APP_URL=' . $originalUrl);
        }

        // Assert
        self::assertSame(
            'https://revisionhub.example/verify-email?token=abc123',
            $link
        );
    }

    // Check that the student approval email contains the correct details.
    public function testStudentApprovalEmailIncludesApprovalDetails(): void
    {
        // Arrange
        $firstName = 'Fyn';

        // Act
        $email = Mailer::buildStudentApprovalEmail($firstName);

        // Assert
        self::assertSame(
            'Your RevisionHub account has been approved',
            $email['subject']
        );
        self::assertStringContainsString('Your account has been approved!', $email['html']);
        self::assertStringContainsString('Hi Fyn,', $email['html']);
        self::assertStringContainsString('approved by your lecturer', $email['html']);
        self::assertStringContainsString('start using RevisionHub', $email['html']);
        self::assertStringContainsString('Hi Fyn,', $email['text']);
        self::assertStringContainsString('approved by your lecturer', $email['text']);
        self::assertStringContainsString('start using RevisionHub', $email['text']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
    }

    // Check that a student's first name is safely escaped in the HTML email.
    public function testStudentApprovalEmailEscapesHtml(): void
    {
        // Arrange
        $firstName = '<script>alert(1)</script>';

        // Act
        $email = Mailer::buildStudentApprovalEmail($firstName);

        // Assert
        self::assertStringNotContainsString('<script>', $email['html']);
        self::assertStringContainsString('&lt;script&gt;', $email['html']);
    }

    public function testStudentAccountEmailIncludesLoginDetails(): void
    {
        $email = Mailer::buildStudentAccountEmail(
            'Fyn',
            'ws123456',
            'TemporaryPass123!'
        );

        self::assertSame(
            'Your RevisionHub student account is ready',
            $email['subject']
        );
        self::assertStringContainsString('Welcome to RevisionHub, Fyn', $email['html']);
        self::assertStringContainsString('ws123456', $email['html']);
        self::assertStringContainsString('TemporaryPass123!', $email['html']);
        self::assertStringContainsString('ws123456', $email['text']);
        self::assertStringContainsString('TemporaryPass123!', $email['text']);
    }

    public function testStudentAccountEmailEscapesHtml(): void
    {
        $email = Mailer::buildStudentAccountEmail(
            '<script>alert(1)</script>',
            '<username>',
            '<password>'
        );

        self::assertStringNotContainsString('<script>', $email['html']);
        self::assertStringContainsString('&lt;script&gt;', $email['html']);
        self::assertStringContainsString('&lt;username&gt;', $email['html']);
        self::assertStringContainsString('&lt;password&gt;', $email['html']);
    }
}
