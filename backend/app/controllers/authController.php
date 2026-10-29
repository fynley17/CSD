<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\User;
use App\Models\Lecturer;

/**
 * Auth controller, answers the login page's login, logout and "who's logged in" requests
 * goes in the same order as the login flowchart
 * the session itself is looked after by the Authentication object this controller keeps
 */
class AuthController extends Controller
{
    // messages the user sees, using house style section 7, the page adds "There is a problem:" in front
    private const MISSING_DETAILS = 'Enter your username and password';
    private const WRONG_DETAILS = 'Username or password is incorrect';
    private const EMAIL_NOT_VERIFIED = 'You need to verify your email address before you can login';
    private const ACCOUNT_PENDING = 'Your account is waiting for lecturer approval. '
        . 'You will get an email when it is ready';
    private const ACCOUNT_DISABLED = 'Your account has been disabled. Contact your lecturer or an administrator';

    // the login session helper, made once and shared by every method below
    private Authentication $authentication;

    /**
     * Sets up the session helper as soon as the controller is made
     */
    public function __construct()
    {
        $this->authentication = new Authentication();
    }

    /**
     * POST /api/login, checks the username and password then logs them in
     */
    public function login(): void
    {
        $details = $this->readLoginDetails();

        if ($details === null) {
            $this->jsonResponse(['error' => self::MISSING_DETAILS], 400);
        }

        try {
            $user = User::findByUsername($details['username']);

            // no account with that username, still does the slow password hashing once
            // the reply takes as long as a real wrong password, and the timing can't show which usernames exist
            if ($user === null) {
                password_hash($details['password'], PASSWORD_DEFAULT);
                $this->jsonResponse(['error' => self::WRONG_DETAILS], 401);
            }

            // stopped before the password is even checked, so guessing can't carry on during the lock
            // and the reply is the same whether they typed the right password or not
            // 429 means too many tries - the login page opens the locked pop up when it sees it
            if ($user->isLocked()) {
                $this->jsonResponse(['error' => $this->lockedMessage($user)], 429);
            }

            // same message for a wrong password as for a wrong username, stops people fishing for real usernames
            if (!$user->login($details['password'])) {
                $user->addFailedAttempt();

                // that was the 3rd wrong one in a row, so it's locked now
                // it unlocks by itself after 15 minutes, no administrator required,
                // so someone locking a student out on purpose can only cost them 15 minutes
                // it would also deny learning for too long a period if admins were away
                if ($user->isLocked()) {
                    $this->jsonResponse(['error' => $this->lockedMessage($user)], 429);
                }
                $this->jsonResponse(['error' => self::WRONG_DETAILS], 401);
            }

            // right password, so earlier wrong ones don't count towards a lock any more
            $user->clearFailedAttempts();
            $user->saveLoginAttempts();

            // these only get checked once the password is right, a stranger never sees them
            // an unverified email comes first, the page sends them to check your email to resend or fix it
            if (!$user->isEmailVerified()) {
                $this->authentication->savePendingVerification($user->getUserID());
                $this->jsonResponse(['error' => self::EMAIL_NOT_VERIFIED, 'emailNotVerified' => true], 403);
            }
            if ($user->isPending()) {
                $this->jsonResponse(['error' => self::ACCOUNT_PENDING], 403);
            }
            if (!$user->isActive()) {
                $this->jsonResponse(['error' => self::ACCOUNT_DISABLED], 403);
            }

            $user->recordLogin();
            $this->authentication->saveLogin($user);

            // page uses accountType to pick which dashboard to go to
            $this->jsonResponse(['accountType' => $user->getAccountType()]);
        } catch (\Throwable $e) {
            $this->serverError('Login', $e);
        }
    }

    /**
     * POST /api/logout, ends the session
     */
    public function logout(): void
    {
        $this->authentication->endSession();
        $this->jsonResponse(['message' => 'You have signed out']);
    }

    /**
     * GET /api/current-user, tells the page who's logged in so the header can change by account type
     * logged out = 200 with loggedIn false, a 401 here would be a console error on every page
     */
    public function currentUser(): void
    {
        $userID = $this->authentication->getLoggedInUserID();

        if ($userID === null) {
            $this->jsonResponse(['loggedIn' => false]);
        }

        try {
            $user = User::findById($userID);

            // account got deleted or disabled after they logged in, so log them out
            if ($user === null || !$user->isActive()) {
                $this->authentication->endSession();
                $this->jsonResponse(['loggedIn' => false]);
            }

            // just what the page needs, the hash never leaves the server
            // Use the lecturer's name for the dashboard greeting when available.
            $displayName = $user->getUsername();

            if ($user instanceof Lecturer) {
                $displayName = $user->getFullName() ?? $displayName;
            }

            // Return the account details needed by the frontend.
            // Never include the user's password or password hash.
            $this->jsonResponse([
                'loggedIn' => true,
                'username' => $user->getUsername(),
                'displayName' => $displayName,
                'accountType' => $user->getAccountType(),
            ]);
        } catch (\Throwable $e) {
            $this->serverError('Checking the session', $e);
        }
    }

    /**
     * GET /api/csrf-token, gives the page the anti-CSRF code it has to send back with anything that changes data
     * only our own pages can read this reply, browsers don't let another website see it
     */
    public function csrfToken(): void
    {
        $this->jsonResponse(['csrfToken' => $this->authentication->getCsrfToken()]);
    }

    /**
     * Reads the username and password from the JSON body (keeps them out of the URL and logs)
     *
     * @return array|null null if either one is missing or blank
     */
    private function readLoginDetails(): ?array
    {
        // php://input is the raw body of the request, true turns the JSON into a normal PHP array
        // if the body isn't valid JSON this gives back null, which checkLoginDetails() turns away
        $data = json_decode(file_get_contents('php://input'), true);

        // the checking happens in its own function so it can be tested without a real request
        return self::checkLoginDetails($data);
    }

    /**
     * Checks both login details are there and are text, and trims the username
     * public static so the unit tests can pass in made up input
     *
     * @param mixed $data whatever the page sent, after it's been turned from JSON into PHP
     * @return array|null the cleaned username and password, or null if either one is missing or blank
     */
    public static function checkLoginDetails(mixed $data): ?array
    {
        // checked again here, anyone can skip the form and call /api/login directly
        if (!is_array($data)) {
            return null;
        }

        // both boxes have to be there and be text, not a number or an array - input validation
        // isset() catches a missing box, is_string() catches someone sending ['stu01'] or 1234 instead
        // an array would crash the trim() below and give them a server error
        if (!isset($data['username']) || !is_string($data['username'])) {
            return null;
        }
        if (!isset($data['password']) || !is_string($data['password'])) {
            return null;
        }

        // trim spaces off the username only, a password can have spaces in it
        // i.e. ' stu01 ' copied in by accident still logs in as stu01
        $username = trim($data['username']);

        // a username that was only spaces is blank once it's trimmed, so it's turned away here too
        if ($username === '' || $data['password'] === '') {
            return null;
        }

        return ['username' => $username, 'password' => $data['password']];
    }

    /**
     * The locked message, with the real time left so it's still right if they come back after 10 minutes
     * built from User's numbers so it can't say something different to what the code does
     */
    private function lockedMessage(User $user): string
    {
        $minutes = $user->getMinutesUntilUnlock();

        // "1 minute", not "1 minutes"
        if ($minutes === 1) {
            $timeLeft = '1 minute';
        } else {
            $timeLeft = $minutes . ' minutes';
        }

        return 'This account is locked after ' . User::MAX_FAILED_ATTEMPTS . ' failed login attempts. '
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
