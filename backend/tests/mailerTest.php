<?php

use App\Helpers\Mailer;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    // Check the welcome email content without needing a mail server.
    public function testWelcomeEmailIncludesStudentDetails(): void
    {
        $email = Mailer::buildWelcomeEmail('Fyn', 'Geary', 'Computing 2026');

        self::assertStringContainsString('pending approval', $email['subject']);
        self::assertStringContainsString('Welcome to RevisionHub, Fyn', $email['html']);
        self::assertStringContainsString('Fyn Geary', $email['html']);
        self::assertStringContainsString('Computing 2026', $email['html']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
        self::assertStringContainsString('Cohort: Computing 2026', $email['text']);
    }

    public function testWelcomeEmailEscapesHtml(): void
    {
        $email = Mailer::buildWelcomeEmail('Fyn', 'Geary', '<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $email['html']);
        self::assertStringContainsString('&lt;script&gt;', $email['html']);
    }

    // The verification email can reach a stranger if the address was mistyped, so it only holds the link.
    public function testVerificationEmailIncludesLinkButNoPersonalDetails(): void
    {
        $link = 'http://localhost:5173/verify-email?token=abc123';
        $email = Mailer::buildVerificationEmail($link);

        self::assertStringContainsString('Verify your email address', $email['subject']);
        self::assertStringContainsString('href="' . $link . '"', $email['html']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
        self::assertStringContainsString($link, $email['text']);
        self::assertStringNotContainsString('Cohort', $email['html']);
    }

    public function testVerificationLinkUsesAppUrl(): void
    {
        // put the container's own value back afterwards so other tests aren't affected
        $originalUrl = getenv('APP_URL');
        putenv('APP_URL=https://revisionhub.example/');

        try {
            self::assertSame(
                'https://revisionhub.example/verify-email?token=abc123',
                Mailer::verificationLink('abc123')
            );
        } finally {
            putenv($originalUrl === false ? 'APP_URL' : 'APP_URL=' . $originalUrl);
        }
    }
}
