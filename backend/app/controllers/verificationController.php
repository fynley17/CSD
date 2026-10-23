<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Helpers\Mailer;
use App\Models\EmailVerification;
use App\Models\Student;

/**
 * Verification controller, answers the requests from the check your email and verify email pages
 * a student verifies their address by clicking the link in their email, until then they can't login,
 * and the browser that registered them can resend the email or fix a mistyped address
 */
class VerificationController extends Controller
{
    // messages the user sees, using house style section 7, the page adds "There is a problem:" in front
    private const INVALID_LINK = 'This link is not valid or has already been used. '
        . 'Login to get a new link if you still need one';
    private const EXPIRED_LINK = 'This link has expired. Login to get a new one';
    private const NO_PENDING_ACCOUNT = 'Your session has ended. Login to verify your email address';
    private const ALREADY_VERIFIED = 'Your email address has already been verified';
    private const WAIT_BEFORE_RESEND = 'Wait a minute before asking for another email';
    private const EMAIL_NOT_SENT = 'We could not send the email. Try again later';
    private const MISSING_EMAIL = 'Enter your email address';

    /**
     * POST /api/verify-email, checks the code from the email link and verifies the account
     */
    public function verifyEmail(): void
    {
        $token = self::checkToken(json_decode(file_get_contents('php://input'), true));

        if ($token === null) {
            $this->jsonResponse(['error' => self::INVALID_LINK], 400);
        }

        try {
            $verification = EmailVerification::findByToken($token);

            if ($verification === null) {
                $this->jsonResponse(['error' => self::INVALID_LINK], 400);
            }
            if ($verification['isExpired']) {
                $this->jsonResponse(['error' => self::EXPIRED_LINK], 400);
            }

            // the session is left alone, so if the check your email page is still open it shows them as verified
            // it can't be used for anything else now, resend and change email both stop once it's verified
            EmailVerification::markVerified($verification['userID']);

            // the address is confirmed now, so it's safe to send their name and cohort to it
            $student = Student::findVerificationDetails($verification['userID']);
            if ($student !== null) {
                Mailer::sendWelcomeEmail(
                    $student['email'],
                    $student['firstName'],
                    $student['lastName'],
                    $student['cohortName']
                );
            }

            $this->jsonResponse(['message' => 'Your email address has been verified']);
        } catch (\Throwable $e) {
            $this->serverError('Verifying an email address', $e);
        }
    }

    /**
     * GET /api/pending-verification, tells the check your email page which address the link was sent to
     */
    public function getPendingVerification(): void
    {
        try {
            $student = $this->findPendingStudent();

            $this->jsonResponse([
                'email' => $student['email'],
                'isEmailVerified' => $student['isEmailVerified'],
            ]);
        } catch (\Throwable $e) {
            $this->serverError('Checking a pending verification', $e);
        }
    }

    /**
     * POST /api/resend-verification, sends a new link to the same address
     */
    public function resendVerification(): void
    {
        try {
            $student = $this->findPendingStudent();
            $userID = Authentication::getPendingVerificationUserID();
            $this->checkCanSend($student, $userID);

            $this->sendNewLink($userID, $student['email']);
        } catch (\Throwable $e) {
            $this->serverError('Resending a verification email', $e);
        }
    }

    /**
     * POST /api/change-email, fixes a mistyped address and sends a new link to it
     */
    public function changeEmail(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = is_array($data) ? ($data['email'] ?? null) : null;

        if (!is_string($email) || trim($email) === '') {
            $this->jsonResponse(['error' => self::MISSING_EMAIL], 400);
        }

        try {
            $student = $this->findPendingStudent();
            $userID = Authentication::getPendingVerificationUserID();
            $this->checkCanSend($student, $userID);

            $newEmail = Student::changeEmail($userID, $email);
            $this->sendNewLink($userID, $newEmail);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->serverError('Changing an email address', $e);
        }
    }

    /**
     * Checks the link code the page sent is there and looks like a real one
     * public and static so a unit test can hand it made up input, same as AuthController::checkLoginDetails()
     *
     * @param mixed $data whatever the page sent, after it's been turned from JSON into PHP
     * @return string|null the code, or null if it's missing or the wrong shape
     */
    public static function checkToken(mixed $data): ?string
    {
        if (!is_array($data) || !EmailVerification::isValidToken($data['token'] ?? null)) {
            return null;
        }

        return $data['token'];
    }

    /**
     * Finds the unverified account this browser registered or logged in to, stops with a 401 if there isn't one
     */
    private function findPendingStudent(): array
    {
        $userID = Authentication::getPendingVerificationUserID();
        $student = $userID === null ? null : Student::findVerificationDetails($userID);

        if ($student === null) {
            Authentication::clearPendingVerification();
            $this->jsonResponse(['error' => self::NO_PENDING_ACCOUNT], 401);
        }

        return $student;
    }

    /**
     * Stops the request if the account is already verified, or an email was sent less than a minute ago
     */
    private function checkCanSend(array $student, string $userID): void
    {
        if ($student['isEmailVerified']) {
            $this->jsonResponse(['error' => self::ALREADY_VERIFIED], 400);
        }

        // 429 = too many requests
        if (!EmailVerification::canResend($userID)) {
            $this->jsonResponse(['error' => self::WAIT_BEFORE_RESEND], 429);
        }
    }

    /**
     * Makes a new link, which stops the old one working, and emails it
     */
    private function sendNewLink(string $userID, string $email): void
    {
        $token = EmailVerification::createToken($userID);

        if (!Mailer::sendVerificationEmail($email, $token)) {
            $this->jsonResponse(['error' => self::EMAIL_NOT_SENT], 500);
        }

        $this->jsonResponse(['message' => 'We have sent a new link to ' . $email, 'email' => $email]);
    }

    /**
     * Puts the real error in the server log and gives the user a plain message
     */
    private function serverError(string $action, \Throwable $e): void
    {
        // doesn't send the real error to the browser, it can show how the system works
        error_log($action . ' failed: ' . $e->getMessage());
        $this->jsonResponse(['error' => 'Something went wrong. Try again later'], 500);
    }
}
