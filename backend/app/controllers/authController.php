<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\User;
use App\Models\Lecturer;

/**
 * Auth controller, answers the login page's login, logout and "who's logged in" requests
 * goes in the same order as the login flowchart
 * the session itself is handled by the Authentication helper
 */
class AuthController extends Controller
{
    // messages the user sees, using house style section 7, the page adds "There is a problem:" in front
    private const MISSING_DETAILS = 'Enter your username and password';
    private const WRONG_DETAILS = 'Username or password is incorrect';
    private const ACCOUNT_PENDING = 'Your account is waiting for lecturer approval. '
        . 'You will get an email when it is ready';
    private const ACCOUNT_DISABLED = 'Your account has been disabled. Contact your lecturer or an administrator';
    private const NOT_LOGGED_IN = 'You are not logged in';

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

            // same error for a wrong username or wrong password, stops people fishing for real usernames
            if ($user === null || !$user->login($details['password'])) {
                $this->jsonResponse(['error' => self::WRONG_DETAILS], 401);
            }

            // these two only get checked once the password is right, a stranger never sees them
            if ($user->isPending()) {
                $this->jsonResponse(['error' => self::ACCOUNT_PENDING], 403);
            }
            if (!$user->isActive()) {
                $this->jsonResponse(['error' => self::ACCOUNT_DISABLED], 403);
            }

            $user->recordLogin();
            Authentication::saveLogin($user);

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
        Authentication::endSession();
        $this->jsonResponse(['message' => 'You have signed out']);
    }

    /**
     * GET /api/current-user, tells the page who's logged in so the header can change by account type
     */
    public function currentUser(): void
    {
        $userID = Authentication::getLoggedInUserID();

        if ($userID === null) {
            $this->jsonResponse(['error' => self::NOT_LOGGED_IN], 401);
        }

        try {
            $user = User::findById($userID);

            // account got deleted or disabled after they logged in, so log them out
            if ($user === null || !$user->isActive()) {
                Authentication::endSession();
                $this->jsonResponse(['error' => self::NOT_LOGGED_IN], 401);
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
                'username' => $user->getUsername(),
                'displayName' => $displayName,
                'accountType' => $user->getAccountType(),
            ]);
        } catch (\Throwable $e) {
            $this->serverError('Checking the session', $e);
        }
    }

    /**
     * Reads the username and password the page sent
     * the login form sends them as JSON in the body of the request, not in the URL,
     * so they don't end up in the browser history or the server logs
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
     * Checks the login details are both there and are text, and tidies up the username
     * this runs before the database is touched, so bad input never reaches a query
     * it's public and static so a unit test can hand it made up input and check what comes back
     *
     * @param mixed $data whatever the page sent, after it's been turned from JSON into PHP
     * @return array|null the cleaned username and password, or null if either one is missing or blank
     */
    public static function checkLoginDetails(mixed $data): ?array
    {
        // check again here as well as on the page, page checks can be skipped
        // e.g. someone sending a request straight to /api/login without using the form
        // the page should always send JSON, if it didn't there's nothing to check
        if (!is_array($data)) {
            return null;
        }

        // both boxes have to be there and be text, not a number or a list
        // isset() catches a missing box, is_string() catches someone sending ['stu01'] or 1234 instead
        if (!isset($data['username']) || !is_string($data['username'])) {
            return null;
        }
        if (!isset($data['password']) || !is_string($data['password'])) {
            return null;
        }

        // trim spaces off the username but not the password, a password can have spaces in it
        // i.e. ' stu01 ' copied in by accident still logs in as stu01
        $username = trim($data['username']);

        // a username that was only spaces is blank once it's trimmed, so it's turned away here too
        if ($username === '' || $data['password'] === '') {
            return null;
        }

        return ['username' => $username, 'password' => $data['password']];
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
