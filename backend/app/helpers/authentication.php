<?php

namespace App\Helpers;

use App\Models\User;

/**
 * Authentication helper, remembers who is logged in using a PHP session
 * a session is a small file on the server, the browser only gets a random ID in a cookie
 * protected routes call requireRole() at the top so only the right people get in
 *
 * Each controller makes its own with new Authentication(), the user's actual data lives in the User object.
 */
class Authentication
{
    // the login cookie gets our own name, PHP's default name PHPSESSID tells anyone that the site
    // is built with PHP, which helps them work out which attacks to try
    private const COOKIE_NAME = 'RevisionHub';

    // on the live site (secure connection enabled) the name also starts with __Host-
    // browsers have a rule for that start - they only keep the cookie if it came from this exact website
    // over a secure connection, so another website sharing our address (i.e. another site on the uni server)
    // can't slip its own fake login cookie into the browser
    private const SECURE_COOKIE_NAME = '__Host-RevisionHub';

    // people get logged out after 30 minutes of doing nothing, OWASP suggests 15 to 30 for a low risk site like ours
    // public so the tests can use it, the Vue pop-up has its own copy of the 30 minutes in idleTimer.js
    public const IDLE_TIMEOUT_SECONDS = 30 * 60;

    // true on the live site's secure connection (https), the cookie's name and settings depend on it
    private bool $isHttps;

    /**
     * Works out once whether this visit came over a secure connection, the session only starts when it's needed
     */
    public function __construct()
    {
        $this->isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /**
     * Saves who just logged in, after their password has been checked.
     */
    public function saveLogin(User $user): void
    {
        $this->startSession();
        // new session ID after login, so an old ID someone else knew is useless (stops session fixation)
        session_regenerate_id(true);
        // only the ID and account type go in the session, never the password for security
        $_SESSION['userID'] = $user->getUserID();
        $_SESSION['accountType'] = $user->getAccountType();
        // a fresh anti-CSRF code for the logged in session, the page picks it up before its next request
        unset($_SESSION['csrfToken']);
    }

    /**
     * Remembers which unverified account this browser just registered, or logged in to with the right password.
     * This doesn't log them in, it only lets them resend the verification email or fix a mistyped address.
     */
    public static function savePendingVerification(string $userID): void
    {
        (new self())->startSession();
        // new session ID for the same reason as saveLogin(), stops session fixation
        session_regenerate_id(true);
        $_SESSION['pendingVerificationUserID'] = $userID;
    }

    /**
     * Returns the unverified account this browser is allowed to change, read from the session on the server.
     *
     * @return string|null null if this browser hasn't registered or logged in to an unverified account
     */
    public static function getPendingVerificationUserID(): ?string
    {
        (new self())->startSession();

        return $_SESSION['pendingVerificationUserID'] ?? null;
    }

    /**
     * Forgets the unverified account, used once the email has been verified.
     */
    public static function clearPendingVerification(): void
    {
        (new self())->startSession();
        unset($_SESSION['pendingVerificationUserID']);
    }

    /**
     * Returns the ID of whoever is logged in, read from the session on the server.
     * Nothing comes in from the page, the browser only sends its session cookie, so controllers
     * should use this to find the current user, a userID sent in a request could be faked.
     *
     * @return string|null null if nobody is logged in
     */
    public function getLoggedInUserID(): ?string
    {
        $this->startSession();

        // no user ID in the session means nobody's logged in
        if (!isset($_SESSION['userID'])) {
            return null;
        }

        return $_SESSION['userID'];
    }

    /**
     * Stops the request unless someone is logged in with one of these account types.
     * e.g. requireRole(['admin']) or requireRole(['lecturer', 'admin'])
     */
    public function requireRole(array $allowedRoles): void
    {
        $this->startSession();

        // 401 = not logged in
        if (!isset($_SESSION['userID'])) {
            $this->stop(401, 'Your session has ended. Go to the login page to continue');
        }

        // the session only knows what the account was like at login, so the database is checked every time
        // that way a lecturer disabling a student takes effect on their next click, not 30 minutes later
        try {
            $user = User::findById($_SESSION['userID']);
        } catch (\Throwable $e) {
            error_log('Checking the logged in account failed: ' . $e->getMessage());
            $this->stop(500, 'Something went wrong. Try again later');
        }

        // deleted or disabled since they logged in, so they're logged out straight away
        if ($user === null || !$user->isActive()) {
            $this->endSession();
            $this->stop(401, 'Your session has ended. Go to the login page to continue');
        }

        // 403 = logged in, but the wrong account type for this page
        // true makes in_array check the type as well, so only an exact match gets through
        if (!in_array($user->getAccountType(), $allowedRoles, true)) {
            $this->stop(403, 'You do not have permission to view this page');
        }
    }

    /**
     * Gives back this session's anti-CSRF code, making one the first time it's asked for.
     * The page asks for it (GET /api/csrf-token) and sends it back with anything that changes data.
     */
    public function getCsrfToken(): string
    {
        $this->startSession();

        if (!isset($_SESSION['csrfToken'])) {
            // 32 random bytes from PHP's secure random maker, turned into 64 letters and numbers to send as text
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    /**
     * Turns the request away unless it brings this session's anti-CSRF code in an X-CSRF-Token header.
     * mitigates another website making a user's browser send a request to RevisionHub's backend(i.e. "log out"),
     * and the browser adds our login cookie by itself, so it looks like you did it.
     * That website can't read our pages, so it never knows the code, and its request gets turned away.
     */
    public function requireCsrfToken(): void
    {
        $this->startSession();

        // PHP files a header called X-CSRF-Token under HTTP_X_CSRF_TOKEN, ?? gives '' if it's missing
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrfToken'] ?? '';

        // compares the two codes (self:: is how this class calls its own static check further down)
        if (!self::isValidCsrfToken($sessionToken, $sentToken)) {
            $this->stop(403, 'Your session has ended. Refresh the page and try again');
        }
    }

    /**
     * True if the code the page sent matches the one saved in the session.
     * Kept apart from requireCsrfToken() so it can be tested without a real request.
     * static (no object needed to use it) as it only compares the two codes it's given, so the tests can check it
     */
    public static function isValidCsrfToken(string $sessionToken, string $sentToken): bool
    {
        // no code made yet, so nothing can match it (otherwise two empty strings would count as a match)
        if ($sessionToken === '') {
            return false;
        }

        // hash_equals instead of === - PHP's manual says to use it when checking secret codes
        // === stops at the first wrong letter, so the time it takes could give away clues about the code
        // unlikely attack, but recommended by PHP itself, and it doesn't cost anything to do it this way
        return hash_equals($sessionToken, $sentToken);
    }

    /**
     * True if 30 minutes or more have passed since the last request.
     * static and given the two times, so the tests can check it without waiting 30 minutes
     */
    public static function hasBeenIdleTooLong(int $lastActivity, int $now): bool
    {
        return $now - $lastActivity >= self::IDLE_TIMEOUT_SECONDS;
    }

    /**
     * Logs the user out, empties the session and deletes the cookie.
     */
    public function endSession(): void
    {
        $this->startSession();
        $_SESSION = [];

        // tell the browser to delete the cookie by giving it a date in the past
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $cookie['secure'],
        ]);

        // so backspace or refresh doesn't keep the session alive, and the file is deleted from the server
        session_destroy();
    }

    /**
     * Starts the session with safe cookie settings, if it isn't started already.
     * every function above calls this first, so the session only starts when something actually needs it
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // only accept session IDs the server made itself
        // so if someone tries to guess a session ID, it won't work (stops session hijacking)
        // as well as session fixation, where someone tricks a user into using a session ID they know
        // it also prevents links from another site from logging the user in to this site
        ini_set('session.use_strict_mode', '1');

        // PHP has its own clean-up that runs on about 1 in 100 requests, it deletes any session file
        // nobody has touched for 24 minutes (PHP's default), so someone idle for 25 minutes could lose
        // their login before our 30 are up, this line tells the clean-up to wait the full 30 instead
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT_SECONDS);

        // browsers refuse __Host- cookies on a normal connection, so on localhost it's just 'RevisionHub'
        if ($this->isHttps) {
            session_name(self::SECURE_COOKIE_NAME);
        } else {
            session_name(self::COOKIE_NAME);
        }

        session_set_cookie_params([
            'path' => '/',
            // javascript can't read the cookie, so a script injected into the page can't steal it (stops XSS theft)
            'httponly' => true,
            // Strict would also drop it when someone clicks a normal link to us, like one in an email
            // SameSite controls if the cookie gets sent when a request comes from another site
            // Lax is only sent when someone clicks a normal link to us, never with another site's forms (stops CSRF)
            'samesite' => 'Lax',
            // on https (Plesk) the cookie only travels over a secure connection
            'secure' => $this->isHttps,
        ]);

        // don't let the browser keep a saved copy of any reply that uses the session
        // this is the line that makes PHP add Expires (a date back in 1981, so it's always out of date),
        // Cache-Control and Pragma to the reply - all three tell the browser not to save a copy of the page
        // so the next person on a shared computer can't bring up a page with someone else's details on it
        // 'nocache' is already PHP's default, it's written out here so it's clear where those lines come from
        session_cache_limiter('nocache');

        // starts the session, this is also the line that sends Set-Cookie, handing the browser
        // the login cookie with the name and settings above
        session_start();

        $this->checkIdleTimeout();
    }

    /**
     * Logs the user out if they've done nothing for 30 minutes, otherwise the 30 minutes start again
     * every request counts, so the "Stay logged in" button on the warning pop-up only has to send one
     */
    private function checkIdleTimeout(): void
    {
        $now = time();

        // only someone logged in can time out, and there has to be a last time saved to compare with
        $canTimeOut = isset($_SESSION['userID'], $_SESSION['lastActivity']);

        if ($canTimeOut && self::hasBeenIdleTooLong($_SESSION['lastActivity'], $now)) {
            // emptying the session logs them out, so this request gets the same 401 as anyone not logged in
            $_SESSION = [];
        }

        // saves the time of this request, the next 30 minutes count from here
        $_SESSION['lastActivity'] = $now;
    }

    /**
     * Sends an error back as JSON and stops, so nothing else in the request runs.
     * Same job as the controllers' jsonResponse(), this class isn't a controller so it has its own
     * returns never (it always ends the request, so nothing after a stop() call ever runs)
     */
    private function stop(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
