<?php

namespace App\Controllers;

use App\Helpers\Mailer;
use App\Helpers\Validation;
use App\Models\PasswordReset;

/**
 * Forgot password: emails a reset link, then sets the new password when the link is used.
 */
class PasswordResetController extends Controller
{
    // the same reply whether or not the email has an account, so the form can't be used to find out who's registered
    public const REQUEST_SENT = 'If an account uses that email address, we have sent it a link to reset the password. '
        . 'The link expires in ' . PasswordReset::LINK_LIFETIME_MINUTES . ' minutes';
    private const INVALID_LINK = 'This reset link is not valid, has expired or has already been used. '
        . 'Ask for a new link from the login page';

    /**
     * POST /api/forgot-password, emails a reset link to the address if an account can use one
     */
    public function requestReset(): void
    {
        $email = $this->readBody()['email'] ?? null;

        if (!is_string($email) || trim($email) === '') {
            $this->jsonResponse(['error' => 'Enter your email address'], 400);
        }

        // only the format is checked here, saying it's in the wrong format gives nothing away
        if (!Validation::isValidEmail(trim($email))) {
            $this->jsonResponse(
                ['error' => 'Enter an email address in the correct format, like name@example.com'],
                400
            );
        }

        try {
            $reset = PasswordReset::createForEmail($email);

            // a failed send is logged by the Mailer, the reply stays the same so it can't give anything away
            if ($reset !== null) {
                Mailer::sendPasswordResetEmail($reset['email'], $reset['token']);
            }

            $this->jsonResponse(['message' => self::REQUEST_SENT]);
        } catch (\Throwable $e) {
            $this->serverError('Requesting a password reset', $e);
        }
    }

    /**
     * POST /api/reset-password, sets the new password using the code from the email link
     */
    public function resetPassword(): void
    {
        $data = $this->readBody();
        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;
        $confirmPassword = $data['confirmPassword'] ?? null;

        if (!PasswordReset::isValidToken($token)) {
            $this->jsonResponse(['error' => self::INVALID_LINK], 400);
        }

        if (!is_string($password) || $password === '' || !is_string($confirmPassword)) {
            $this->jsonResponse(['error' => 'Enter and confirm your new password'], 400);
        }

        if ($password !== $confirmPassword) {
            $this->jsonResponse(['error' => 'Your passwords do not match'], 400);
        }

        try {
            $email = PasswordReset::resetPassword($token, $password);

            if ($email === null) {
                $this->jsonResponse(['error' => self::INVALID_LINK], 400);
            }

            // the password has already changed, so a failed send doesn't undo it
            Mailer::sendPasswordChangedEmail($email);

            $this->jsonResponse(['message' => 'Your password has been reset. You can now login']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->serverError('Resetting a password', $e);
        }
    }

    /**
     * Puts the real error in the server log and gives the user a plain message
     */
    private function serverError(string $action, \Throwable $e): void
    {
        error_log($action . ' failed: ' . $e->getMessage());
        $this->jsonResponse(['error' => 'Something went wrong. Try again later'], 500);
    }
}
