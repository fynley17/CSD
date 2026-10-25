<?php

// Send a test email using the SMTP settings from the environment.
// Usage: docker compose exec backend php scripts/send-test-email.php you@example.com

require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$to = $argv[1] ?? null;
if ($to === null || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send-test-email.php <recipient-email>\n");
    exit(1);
}

$required = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS'];
$missing = array_filter($required, fn ($key) => getenv($key) === false || getenv($key) === '');
if ($missing !== []) {
    fwrite(STDERR, 'Missing environment variables: ' . implode(', ', $missing) . "\n");
    exit(1);
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    // PHPMailer exposes these configuration properties with its documented capitalization.
    // phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
    $mail->Host = getenv('SMTP_HOST');
    $mail->Port = (int) getenv('SMTP_PORT');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USER');
    $mail->Password = getenv('SMTP_PASS');
    // Port 465 uses implicit TLS; 587 upgrades the connection with STARTTLS.
    $mail->SMTPSecure = $mail->Port === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom(getenv('SMTP_USER'), getenv('MAIL_FROM_NAME') ?: 'RevisionHub');
    $mail->addAddress($to);
    // phpcs:enable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
    // phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
    $mail->Subject = 'RevisionHub test email';
    $mail->Body = 'If you can read this, SMTP is configured correctly. Sent at ' . date('c') . '.';
    // phpcs:enable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps

    $mail->send();
    echo "Test email sent to $to\n";
} catch (Exception $e) {
    // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
    fwrite(STDERR, 'Failed to send: ' . $mail->ErrorInfo . "\n");
    exit(1);
}
