<?php

use App\Helpers\Mailer;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    // Check the welcome email content without needing a mail server.
    public function testRegistrationEmailIncludesStudentDetails(): void
    {
        $email = Mailer::buildRegistrationEmail('Fyn', 'Geary', 'Computing 2026');

        self::assertStringContainsString('pending approval', $email['subject']);
        self::assertStringContainsString('Welcome to RevisionHub, Fyn', $email['html']);
        self::assertStringContainsString('Fyn Geary', $email['html']);
        self::assertStringContainsString('Computing 2026', $email['html']);
        self::assertStringContainsString('cid:revisionhub-logo', $email['html']);
        self::assertStringContainsString('Cohort: Computing 2026', $email['text']);
    }

    public function testRegistrationEmailEscapesHtml(): void
    {
        $email = Mailer::buildRegistrationEmail('Fyn', 'Geary', '<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $email['html']);
        self::assertStringContainsString('&lt;script&gt;', $email['html']);
    }
}
