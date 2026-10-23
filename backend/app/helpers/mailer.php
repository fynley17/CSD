<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mailer helper, sends email through the SMTP account set in the environment
 * the SMTP settings come from the root .env file, compose passes them into the container
 *
 * Sending never throws, it gives back false and logs the reason,
 * so a mail problem can't undo something that already worked, like a registration.
 */
class Mailer
{
    // the logo is attached to the email and shown with a cid: link, so it works without a public URL
    private const LOGO_PATH = __DIR__ . '/../../resources/email/logo.png';
    private const LOGO_CID = 'revisionhub-logo';

    /**
     * Sends one email
     *
     * @param string $toEmail who it goes to
     * @param string $toName their name, shown next to the address in their inbox
     * @param string $subject the subject line
     * @param string $htmlBody the formatted email
     * @param string $textBody plain text version, for mail apps that don't show HTML
     * @return bool true if the SMTP server accepted it
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
            // PHPMailer exposes these configuration properties with its documented capitalization.
            // phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
            $mail->Host = (string) getenv('SMTP_HOST');
            $mail->Port = (int) getenv('SMTP_PORT');
            $mail->SMTPAuth = true;
            $mail->Username = (string) getenv('SMTP_USER');
            $mail->Password = (string) getenv('SMTP_PASS');
            // Port 465 uses implicit TLS; 587 upgrades the connection with STARTTLS.
            $mail->SMTPSecure = $mail->Port === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            // don't keep the user waiting too long if the mail server is slow
            $mail->Timeout = 10;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            // phpcs:enable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps

            $mail->setFrom((string) getenv('SMTP_USER'), getenv('MAIL_FROM_NAME') ?: 'RevisionHub');
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            // phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            // phpcs:enable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps

            if (is_file(self::LOGO_PATH)) {
                $mail->addEmbeddedImage(self::LOGO_PATH, self::LOGO_CID, 'logo.png');
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            // ErrorInfo has the SMTP server's reply, the address is left out of the log on purpose
            // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
            error_log('Sending email failed: ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Sends the welcome email a student gets straight after registering
     */
    public static function sendRegistrationEmail(
        string $toEmail,
        string $firstName,
        string $lastName,
        string $cohortName
    ): bool {
        $email = self::buildRegistrationEmail($firstName, $lastName, $cohortName);

        return self::sendEmail(
            $toEmail,
            $firstName . ' ' . $lastName,
            $email['subject'],
            $email['html'],
            $email['text']
        );
    }

    /**
     * Builds the welcome email's subject and both bodies
     * kept separate from sending so a unit test can check the content without a mail server
     *
     * @return array{subject: string, html: string, text: string}
     */
    public static function buildRegistrationEmail(string $firstName, string $lastName, string $cohortName): array
    {
        // everything that came from the user or the database is escaped before it goes in the HTML
        $name = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $fullName = htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8');
        $cohort = htmlspecialchars($cohortName, ENT_QUOTES, 'UTF-8');
        $logoCid = self::LOGO_CID;
        $year = date('Y');

        $subject = 'Welcome to RevisionHub – your account is pending approval';

        // email apps ignore most modern CSS, so this uses tables and inline styles
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f6fa;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f6fa;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;
        font-family:Arial,Helvetica,sans-serif;color:#0f1b33;">
        <tr>
          <td align="center" style="padding:28px 32px 20px;border-bottom:4px solid #1a8cff;">
            <img src="cid:{$logoCid}" alt="RevisionHub" width="240"
              style="display:block;width:240px;max-width:100%;height:auto;border:0;">
          </td>
        </tr>
        <tr>
          <td style="padding:32px;font-size:16px;line-height:1.6;">
            <h1 style="margin:0 0 16px;font-size:22px;color:#0f1b33;">Welcome to RevisionHub, {$name}</h1>
            <p style="margin:0 0 16px;">
              Thank you for registering. We have received your account request and it is now
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
            <p style="margin:24px 0 0;">Kind regards,<br><strong>The RevisionHub Team</strong></p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px;background-color:#0f1b33;color:#c9d3e3;
            font-size:12px;line-height:1.5;text-align:center;">
            This is an automated message, please do not reply to this email.<br>
            &copy; {$year} RevisionHub
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        // plain text copy uses the raw values, there's no HTML to inject into
        $text = "Welcome to RevisionHub, {$firstName}\n\n"
            . "Thank you for registering. We have received your account request and it is now "
            . "pending approval by your lecturer.\n\n"
            . "Name: {$firstName} {$lastName}\n"
            . "Cohort: {$cohortName}\n"
            . "Status: Pending approval\n\n"
            . "You will not be able to sign in until your account has been approved. "
            . "We will email you again as soon as it is ready.\n\n"
            . "If you selected the wrong cohort, or you did not create this account, please contact your lecturer.\n\n"
            . "Kind regards,\nThe RevisionHub Team\n\n"
            . "This is an automated message, please do not reply to this email.";

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
