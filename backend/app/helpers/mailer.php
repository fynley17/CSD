<?php

namespace App\Helpers;

use App\Models\EmailVerification;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mailer helper, sends email through the SMTP account set in the environment.
 *
 * The SMTP settings come from the root .env file.
 *
 * Sending never throws; it returns false and logs the reason,
 * so a mail problem can't undo something that already worked.
 */
class Mailer
{
    // The logo is attached to the email and shown using a cid: link.
    private const LOGO_PATH = __DIR__ . '/../../resources/email/logo.png';
    private const LOGO_CID = 'revisionhub-logo';

    // Used when APP_URL isn't set.
    private const DEFAULT_APP_URL = 'http://localhost:5173';

    /**
     * Sends one email.
     *
     * @param string $toEmail Recipient email address.
     * @param string $toName Recipient name.
     * @param string $subject Email subject.
     * @param string $htmlBody HTML email body.
     * @param string $textBody Plain text email body.
     * @return bool True if the SMTP server accepted it.
     */
    public static function sendEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            // PHPMailer exposes these configuration properties with their documented capitalisation.
            // phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
            $mail->Host = (string) getenv('SMTP_HOST');
            $mail->Port = (int) getenv('SMTP_PORT');
            $mail->SMTPAuth = true;
            $mail->Username = (string) getenv('SMTP_USER');
            $mail->Password = (string) getenv('SMTP_PASS');

            // Port 465 uses implicit TLS; 587 uses STARTTLS.
            $mail->SMTPSecure = $mail->Port === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Timeout = 10;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            // phpcs:enable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps

            $mail->setFrom(
                (string) getenv('SMTP_USER'),
                getenv('MAIL_FROM_NAME') ?: 'RevisionHub'
            );

            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);

            // phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            // phpcs:enable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps

            if (is_file(self::LOGO_PATH)) {
                $mail->addEmbeddedImage(
                    self::LOGO_PATH,
                    self::LOGO_CID,
                    'logo.png'
                );
            }

            $mail->send();

            return true;
        } catch (Exception $e) {
            // The recipient address is deliberately left out of the log.
            // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
            error_log('Sending email failed: ' . $mail->ErrorInfo);

            return false;
        }
    }

    /**
     * Sends the verification email a student receives after registering.
     */
    public static function sendVerificationEmail(
        string $toEmail,
        string $token
    ): bool {
        $email = self::buildVerificationEmail(
            self::verificationLink($token)
        );

        // No name, in case the address was mistyped.
        return self::sendEmail(
            $toEmail,
            '',
            $email['subject'],
            $email['html'],
            $email['text']
        );
    }

    /**
     * Sends the login details to a student created by a lecturer.
     */
    public static function sendStudentAccountDetails(
        string $toEmail,
        string $firstName,
        string $username,
        string $temporaryPassword
    ): bool {
        $email = self::buildStudentAccountEmail(
            $firstName,
            $username,
            $temporaryPassword
        );

        return self::sendEmail(
            $toEmail,
            $firstName,
            $email['subject'],
            $email['html'],
            $email['text']
        );
    }

    /**
     * Builds the email containing a newly created student's login details.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public static function buildStudentAccountEmail(
        string $firstName,
        string $username,
        string $temporaryPassword
    ): array {
        // Escape values before inserting them into HTML.
        $name = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars(
            $temporaryPassword,
            ENT_QUOTES,
            'UTF-8'
        );

        $subject = 'Your RevisionHub student account is ready';

        $content = <<<HTML
            <h1 style="margin:0 0 16px;font-size:22px;color:#0f1b33;">
                Welcome to RevisionHub, {$name}
            </h1>

            <p style="margin:0 0 16px;">
                Your student account has been created by your lecturer
                and is ready to use.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                style="margin:0 0 20px;background-color:#eef6ff;border-left:4px solid #1a8cff;">
                <tr>
                    <td style="padding:16px 20px;font-size:15px;">
                        <strong>Username:</strong> {$safeUsername}<br>
                        <strong>Temporary password:</strong> {$safePassword}
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 16px;">
                You can now sign in to RevisionHub using these details.
                Please keep your password secure.
            </p>

            <p style="margin:24px 0 0;">
                Kind regards,<br>
                <strong>The RevisionHub Team</strong>
            </p>
        HTML;

        $text = "Welcome to RevisionHub, {$firstName}\n\n"
            . "Your student account has been created by your lecturer "
            . "and is ready to use.\n\n"
            . "Username: {$username}\n"
            . "Temporary password: {$temporaryPassword}\n\n"
            . "You can now sign in to RevisionHub using these details.\n"
            . "Please keep your password secure.\n\n"
            . "Kind regards,\nThe RevisionHub Team\n\n"
            . "This is an automated message, please do not reply to this email.";

        return [
            'subject' => $subject,
            'html' => self::wrapLayout($subject, $content),
            'text' => $text,
        ];
    }

    /**
     * Sends the welcome email a student receives once they've verified their email.
     */
    public static function sendWelcomeEmail(
        string $toEmail,
        string $firstName,
        string $lastName,
        string $cohortName
    ): bool {
        $email = self::buildWelcomeEmail(
            $firstName,
            $lastName,
            $cohortName
        );

        return self::sendEmail(
            $toEmail,
            $firstName . ' ' . $lastName,
            $email['subject'],
            $email['html'],
            $email['text']
        );
    }

    /**
     * Sends the approval confirmation email when a lecturer approves a student.
     */
    public static function sendStudentApprovalEmail(
        string $toEmail,
        string $firstName
    ): bool {
        $email = self::buildStudentApprovalEmail($firstName);

        return self::sendEmail(
            $toEmail,
            $firstName,
            $email['subject'],
            $email['html'],
            $email['text']
        );
    }

    /**
     * Builds the approval email for a student whose application has been approved.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public static function buildStudentApprovalEmail(string $firstName): array
    {
        $name = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $subject = 'Your RevisionHub account has been approved';

        $content = <<<HTML
            <h1 style="margin:0 0 16px;font-size:22px;color:#0f1b33;">
                Your account has been approved!
            </h1>

            <p style="margin:0 0 16px;">
                Hi {$name},
            </p>

            <p style="margin:0 0 16px;">
                Great news! Your RevisionHub account has been approved by your lecturer.
                You can now log in and start using RevisionHub.
            </p>

            <p style="margin:24px 0 0;">
                Kind regards,<br>
                <strong>The RevisionHub Team</strong>
            </p>
        HTML;

        $text = "Hi {$firstName},\n\n"
            . "Great news! Your RevisionHub account has been approved by your lecturer. "
            . "You can now log in and start using RevisionHub.\n\n"
            . "Kind regards,\nThe RevisionHub Team\n\n"
            . "This is an automated message, please do not reply to this email.";

        return [
            'subject' => $subject,
            'html' => self::wrapLayout($subject, $content),
            'text' => $text,
        ];
    }

    /**
     * Makes the link opened by the email verification button.
     */
    public static function verificationLink(string $token): string
    {
        $appUrl = rtrim(
            getenv('APP_URL') ?: self::DEFAULT_APP_URL,
            '/'
        );

        return $appUrl . '/verify-email?token=' . urlencode($token);
    }

    /**
     * Builds the verification email's subject and both bodies.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public static function buildVerificationEmail(string $link): array
    {
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $hours = EmailVerification::LINK_LIFETIME_HOURS;
        $subject = 'Verify your email address for RevisionHub';

        $content = <<<HTML
            <h1 style="margin:0 0 16px;font-size:22px;color:#0f1b33;">
                Verify your email address
            </h1>

            <p style="margin:0 0 16px;">
                Thank you for registering with RevisionHub.
                Please confirm this is your email address by selecting the button below.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0"
                style="margin:8px 0 24px;">
                <tr>
                    <td style="border-radius:6px;background-color:#0a66c2;">
                        <a href="{$safeLink}"
                            style="display:inline-block;padding:14px 28px;font-size:16px;
                            font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">
                            Verify my email
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 16px;">
                This link will expire in {$hours} hours.
                If the button does not work, copy this link into your browser:
            </p>

            <p style="margin:0 0 16px;font-size:14px;word-break:break-all;">
                <a href="{$safeLink}" style="color:#0a66c2;">{$safeLink}</a>
            </p>

            <p style="margin:0 0 16px;">
                If you did not create a RevisionHub account, you can ignore this email.
            </p>

            <p style="margin:24px 0 0;">
                Kind regards,<br>
                <strong>The RevisionHub Team</strong>
            </p>
        HTML;

        $text = "Verify your email address\n\n"
            . "Thank you for registering with RevisionHub. Please confirm this is your email address "
            . "by opening the link below.\n\n"
            . "{$link}\n\n"
            . "This link will expire in {$hours} hours.\n\n"
            . "If you did not create a RevisionHub account, you can ignore this email.\n\n"
            . "Kind regards,\nThe RevisionHub Team\n\n"
            . "This is an automated message, please do not reply to this email.";

        return [
            'subject' => $subject,
            'html' => self::wrapLayout($subject, $content),
            'text' => $text,
        ];
    }

    /**
     * Builds the welcome email for a student who has verified their email.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public static function buildWelcomeEmail(
        string $firstName,
        string $lastName,
        string $cohortName
    ): array {
        // Escape values before inserting them into HTML.
        $name = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $fullName = htmlspecialchars(
            $firstName . ' ' . $lastName,
            ENT_QUOTES,
            'UTF-8'
        );

        $cohort = htmlspecialchars($cohortName, ENT_QUOTES, 'UTF-8');

        $subject = 'Welcome to RevisionHub – your account is pending approval';

        $content = <<<HTML
            <h1 style="margin:0 0 16px;font-size:22px;color:#0f1b33;">
                Welcome to RevisionHub, {$name}
            </h1>

            <p style="margin:0 0 16px;">
                Thank you for verifying your email address.
                Your account request is now
                <strong>pending approval</strong> by your lecturer.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                style="margin:0 0 20px;background-color:#eef6ff;border-left:4px solid #1a8cff;">
                <tr>
                    <td style="padding:16px 20px;font-size:15px;">
                        <strong>Name:</strong> {$fullName}<br>
                        <strong>Cohort:</strong> {$cohort}<br>
                        <strong>Status:</strong> Pending approval
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 16px;">
                You will not be able to sign in until your account has been approved.
                We will email you again as soon as it is ready.
            </p>

            <p style="margin:0 0 16px;">
                If you selected the wrong cohort, or you did not create this account,
                please contact your lecturer.
            </p>

            <p style="margin:24px 0 0;">
                Kind regards,<br>
                <strong>The RevisionHub Team</strong>
            </p>
        HTML;

        $text = "Welcome to RevisionHub, {$firstName}\n\n"
            . "Thank you for verifying your email address. Your account request is now "
            . "pending approval by your lecturer.\n\n"
            . "Name: {$firstName} {$lastName}\n"
            . "Cohort: {$cohortName}\n"
            . "Status: Pending approval\n\n"
            . "You will not be able to sign in until your account has been approved. "
            . "We will email you again as soon as it is ready.\n\n"
            . "If you selected the wrong cohort, or you did not create this account, please contact your lecturer.\n\n"
            . "Kind regards,\nThe RevisionHub Team\n\n"
            . "This is an automated message, please do not reply to this email.";

        return [
            'subject' => $subject,
            'html' => self::wrapLayout($subject, $content),
            'text' => $text,
        ];
    }

    /**
     * Puts an email's content inside the shared RevisionHub layout.
     * Uses tables and inline styles for email client compatibility.
     */
    private static function wrapLayout(
        string $subject,
        string $content
    ): string {
        $title = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $logoCid = self::LOGO_CID;
        $year = date('Y');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en-GB">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$title}</title>
            </head>
            <body style="margin:0;padding:0;background-color:#f3f6fa;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="background-color:#f3f6fa;">
                    <tr>
                        <td align="center" style="padding:32px 16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                style="max-width:600px;background-color:#ffffff;border-radius:8px;
                                overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#0f1b33;">
                                <tr>
                                    <td align="center"
                                        style="padding:28px 32px 20px;border-bottom:4px solid #1a8cff;">
                                        <img src="cid:{$logoCid}" alt="RevisionHub" width="240"
                                            style="display:block;width:240px;max-width:100%;
                                            height:auto;border:0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:32px;font-size:16px;line-height:1.6;">
                                        {$content}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:20px 32px;background-color:#0f1b33;
                                        color:#c9d3e3;font-size:12px;line-height:1.5;text-align:center;">
                                        This is an automated message, please do not reply to this email.<br>
                                        © {$year} RevisionHub
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        HTML;
    }
}
