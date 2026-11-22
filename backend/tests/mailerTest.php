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

    // Check that the password setup email includes account details and setup link.
    public function testPasswordSetupEmailIncludesAccountDetails(): void
    {
        // Arrange
        $firstName = 'Fyn';
        $username = 'ws123456';
        $link = 'https://revisionhub.example/setup-password?token=abc123';

        // Act
        $email = Mailer::buildPasswordSetupEmail($firstName, $username, $link);

        // Assert
        self::assertStringContainsString('Fyn', $email['html']);
        self::assertStringContainsString($username, $email['html']);
        self::assertStringContainsString($link, $email['html']);
        self::assertStringContainsString($username, $email['text']);
        self::assertStringContainsString($link, $email['text']);

        // The email must provide a password setup link, not a temporary password.
        self::assertStringNotContainsString('TemporaryPass123!', $email['html']);
        self::assertStringNotContainsString('TemporaryPass123!', $email['text']);
    }

    public function testPasswordSetupEmailEscapesHtml(): void
    {
        // Arrange
        $firstName = '<script>alert(1)</script>';
        $username = '<username>';
        $link = 'https://revisionhub.example/setup-password?token=abc123';

        // Act
        $email = Mailer::buildPasswordSetupEmail($firstName, $username, $link);

        // Assert
        self::assertStringNotContainsString('<script>', $email['html']);
        self::assertStringContainsString('&lt;script&gt;', $email['html']);
        self::assertStringContainsString('&lt;username&gt;', $email['html']);
    }

    // A lecturer added by an admin gets lecturer wording, not the student wording.
    public function testPasswordSetupEmailForALecturerSaysLecturer(): void
    {
        // Arrange
        $link = 'https://revisionhub.example/setup-password?token=abc123';

        // Act
        $email = Mailer::buildPasswordSetupEmail('Lena', 'L1001', $link, 'lecturer');

        // Assert
        self::assertSame('Set up your RevisionHub lecturer account', $email['subject']);
        self::assertStringContainsString('An administrator has created', $email['text']);
        self::assertStringNotContainsString('student', $email['text']);
    }

    // The reset email only has the link in it, nothing about the account.
    public function testPasswordResetEmailIncludesLinkButNoAccountDetails(): void
    {
        // Arrange
        $link = 'http://localhost:5173/reset-password?token=abc123';

        // Act
        $email = Mailer::buildPasswordResetEmail($link);

        // Assert
        self::assertStringContainsString('Reset your RevisionHub password', $email['subject']);
        self::assertStringContainsString('href="' . $link . '"', $email['html']);
        self::assertStringContainsString($link, $email['text']);
        self::assertStringContainsString('60 minutes', $email['text']);
        self::assertStringContainsString('ignore this email', $email['text']);
        self::assertStringNotContainsString('Username', $email['html']);
    }

    public function testPasswordResetLinkUsesAppUrl(): void
    {
        // Arrange
        $originalUrl = getenv('APP_URL');
        putenv('APP_URL=https://revisionhub.example/');

        // Act
        try {
            $link = Mailer::passwordResetLink('abc123');
        } finally {
            putenv($originalUrl === false ? 'APP_URL' : 'APP_URL=' . $originalUrl);
        }

        // Assert
        self::assertSame('https://revisionhub.example/reset-password?token=abc123', $link);
    }

    // The changed email tells them what to do if it wasn't them.
    public function testPasswordChangedEmailSaysWhatToDoIfItWasNotThem(): void
    {
        // Act
        $email = Mailer::buildPasswordChangedEmail();

        // Assert
        self::assertStringContainsString('password has been changed', $email['subject']);
        self::assertStringContainsString('If it was not you', $email['text']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
    }

    // The locked email gives the real lock time and the reset link in case it wasn't them.
    public function testAccountLockedEmailGivesLockTimeAndResetLink(): void
    {
        // Act
        $email = Mailer::buildAccountLockedEmail();

        // Assert
        self::assertStringContainsString('has been locked', $email['subject']);
        self::assertStringContainsString('locked it for 15 minutes', $email['text']);
        self::assertStringContainsString('/forgot-password', $email['text']);
    }
}
