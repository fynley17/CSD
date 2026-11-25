<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Helpers\Mailer;
use App\Models\EmailVerification;
use App\Models\User;

/**
 * Profile controller, answers the My profile page for every account type
 * every request works on whoever is logged in, read from the session on the server,
 * no user ID is taken from the request, so nobody can see or change someone else's account
 */
class ProfileController extends Controller
{
    // anyone logged in can open their own profile
    private const ALL_ACCOUNT_TYPES = ['student', 'lecturer', 'admin'];

    // messages the user sees, using house style section 7, the page adds "There is a problem:" in front
    private const WAIT_BEFORE_RESEND = 'Wait a minute before asking for another email';
    private const EMAIL_NOT_SENT = 'Your name has been saved, but we could not send the email to confirm '
        . 'your new address. Try again later';
    private const MISSING_PASSWORDS = 'Enter your current password, a new password and confirm the new password';
    private const WRONG_PASSWORD = 'Your current password is incorrect';

    /**
     * GET /api/profile, the logged in user's own details
     */
    public function getProfile(): void
    {
        $user = $this->loggedInUser();

        try {
            $this->jsonResponse($user->getProfile());
        } catch (\Throwable $e) {
            $this->serverError('Loading a profile', $e);
        }
    }

    /**
     * PUT /api/profile, saves the logged in user's names (and an admin's staff ID),
     * and starts a change of email if it's different
     * the new address only replaces the old one once the link sent to it is opened
     */
    public function updateProfile(): void
    {
        $user = $this->loggedInUser();

        try {
            // each kind of account reads its own form, an admin's also has their staff ID
            $details = $user->readProfileDetails($this->readBody());
            $currentEmail = strtolower($user->getProfile()['email']);

            // the wait is checked before anything is saved, so the names don't save while the email gets refused
            if ($details['email'] !== $currentEmail && !EmailVerification::canResend($user->getUserID())) {
                $this->jsonResponse(['error' => self::WAIT_BEFORE_RESEND], 429);
            }

            $token = $user->updateProfile($details);
            $message = 'Your details have been saved';

            if ($token !== null) {
                if (!Mailer::sendEmailChangeEmail($details['email'], $token)) {
                    // nobody can open a link that never arrived, so the change is dropped and they can try again
                    EmailVerification::cancelEmailChange($user->getUserID());
                    $this->jsonResponse(['error' => self::EMAIL_NOT_SENT, 'profile' => $user->getProfile()], 500);
                }
                $message = 'Your details have been saved. We have sent a link to ' . $details['email']
                    . '. Your email address will change when you open it';
            }

            $this->jsonResponse(['message' => $message, 'profile' => $user->getProfile()]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->serverError('Updating a profile', $e);
        }
    }

    /**
     * PUT /api/profile/password, changes the logged in user's password, the current one has to be right
     */
    public function changePassword(): void
    {
        $user = $this->loggedInUser();
        $passwords = self::checkPasswordFields($this->readBody());

        if ($passwords === null) {
            $this->jsonResponse(['error' => self::MISSING_PASSWORDS], 400);
            return;
        }

        // locked after too many wrong passwords, here or on the login page, so no more guesses for now
        if ($user->isLocked()) {
            $this->jsonResponse(['error' => self::lockedMessage($user)], 429);
        }

        try {
            $changed = $user->changePassword(
                $passwords['currentPassword'],
                $passwords['newPassword'],
                $passwords['confirmPassword']
            );

            if (!$changed) {
                // that wrong one might have been the 3rd in a row
                if ($user->isLocked()) {
                    $this->jsonResponse(['error' => self::lockedMessage($user)], 429);
                }
                $this->jsonResponse(['error' => self::WRONG_PASSWORD], 400);
            }

            // the password has already changed, so a failed send doesn't undo it
            Mailer::sendPasswordChangedEmail($user->getProfile()['email'], true);

            $this->jsonResponse(['message' => 'Your password has been changed']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->serverError('Changing a password', $e);
        }
    }

    /**
     * Checks the three password boxes are there and are text
     * public static so the unit tests can pass in made up input, same as AuthController::checkLoginDetails()
     *
     * @param mixed $data whatever the page sent, after it's been turned from JSON into PHP
     * @return array{currentPassword: string, newPassword: string, confirmPassword: string}|null
     *         null if any of them is missing, blank or not text
     */
    public static function checkPasswordFields(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $passwords = [];
        foreach (['currentPassword', 'newPassword', 'confirmPassword'] as $field) {
            // not trimmed, a password can have spaces in it
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                return null;
            }
            $passwords[$field] = $data[$field];
        }

        return $passwords;
    }

    /**
     * Stops the request with a 401 unless someone is logged in, then gives back their account
     * the account comes from the session cookie only, never from anything in the request
     */
    private function loggedInUser(): User
    {
        // stops with 401 if nobody's logged in, or the account was disabled or deleted since
        Authentication::requireRole(self::ALL_ACCOUNT_TYPES);

        $userID = Authentication::getLoggedInUserID();
        $user = $userID === null ? null : User::findById($userID);

        if ($user === null) {
            $this->jsonResponse(['error' => 'Your session has ended. Go to the login page to continue'], 401);
        }

        return $user;
    }

    /**
     * The locked message, matching the one on the login page
     */
    private static function lockedMessage(User $user): string
    {
        $minutes = $user->getMinutesUntilUnlock();
        $timeLeft = $minutes === 1 ? '1 minute' : $minutes . ' minutes';

        return 'This account is locked after ' . User::MAX_FAILED_ATTEMPTS . ' failed password attempts. '
            . 'Try again in ' . $timeLeft;
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
