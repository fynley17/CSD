<?php

namespace App\Helpers;

use App\Models\User;

/**
 * Authentication helper, remembers who is logged in using a PHP session
 * a session is a small file on the server, the browser only gets a random ID in a cookie
 * protected routes call requireRole() at the top so only the right people get in
 *
 * The functions are static because there's only one session per request and every file needs it,
 * the user's actual data lives in the User object.
 */
class Authentication
{
    /**
     * Starts the session with safe cookie settings, if it isn't started already.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // only accept session IDs the server made itself
        // so if someone tries to guess a session ID, it won't work (stops session hijacking)
        // as well as session fixation, where someone tricks a user into using a session ID they know
        // it also prevents links from another site from logging the user in to this site
        ini_set('session.use_strict_mode', '1');

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        // the login cookie gets our own name, PHP's default name PHPSESSID tells anyone that the site
        // is built with PHP, which helps them work out which attacks to try

        // on the live site (secure connection enabled) the name also starts with __Host-
        // browsers have a rule for that start - they only keep the cookie if it came from this exact website
        // over a secure connection, so another website sharing our address (i.e. another site on the uni server)
        // can't slip its own fake login cookie into the browser in place of ours
        // browsers refuse __Host- cookies on a normal connection, so on localhost it's just 'RevisionHub'
        if ($isHttps) {
            session_name('__Host-RevisionHub');
        } else {
            session_name('RevisionHub');
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
            'secure' => $isHttps,
        ]);

        session_start();
    }

    /**
     * Saves who just logged in, after their password has been checked.
     */
    public static function saveLogin(User $user): void
    {
        self::startSession();
        // new session ID after login, so an old ID someone else knew is useless (stops session fixation)
        session_regenerate_id(true);
        // only the ID and account type go in the session, never the password for security
        $_SESSION['userID'] = $user->getUserID();
        $_SESSION['accountType'] = $user->getAccountType();
        // a fresh anti-CSRF code for the logged in session, the page picks it up before its next request
        unset($_SESSION['csrfToken']);
    }

    /**
     * Returns the ID of whoever is logged in, read from the session on the server.
     * Nothing comes in from the page, the browser only sends its session cookie, so controllers
     * should use this to find the current user rather than trusting a userID sent in a request.
     *
     * @return string|null null if nobody is logged in
     */
    public static function getLoggedInUserID(): ?string
    {
        self::startSession();

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
    public static function requireRole(array $allowedRoles): void
    {
        self::startSession();

        // 401 = not logged in
        if (!isset($_SESSION['userID'])) {
            self::stop(401, 'Your session has ended. Go to the login page to continue');
        }

        // 403 = logged in, but the wrong account type for this page
        // true makes in_array check the type as well, so only an exact match gets through
        if (!in_array($_SESSION['accountType'], $allowedRoles, true)) {
            self::stop(403, 'You do not have permission to view this page');
        }
    }

    /**
     * Gives back this session's anti-CSRF code, making one the first time it's asked for.
     * The page asks for it (GET /api/csrf-token) and sends it back with anything that changes data.
     */
    public static function getCsrfToken(): string
    {
        self::startSession();

        if (!isset($_SESSION['csrfToken'])) {
            // 32 random bytes from PHP's secure random maker, turned into 64 letters and numbers to send as text
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    /**
     * Turns the request away unless it brings this session's anti-CSRF code in an X-CSRF-Token header.
     * The attack it stops: another website quietly makes your browser send a request to us (i.e. "log out"),
     * and the browser adds our login cookie by itself, so it looks like you did it.
     * That website can't read our pages, so it never knows the code, and its request gets turned away.
     */
    public static function requireCsrfToken(): void
    {
        self::startSession();

        // PHP files a header called X-CSRF-Token under HTTP_X_CSRF_TOKEN, ?? gives '' if it's missing
        $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrfToken'] ?? '';

        if (!self::isValidCsrfToken($sessionToken, $sentToken)) {
            self::stop(403, 'Your session has ended. Refresh the page and try again');
        }
    }

    /**
     * True if the code the page sent matches the one saved in the session.
     * Kept apart from requireCsrfToken() so it can be tested without a real request.
     */
    public static function isValidCsrfToken(string $sessionToken, string $sentToken): bool
    {
        // no code made yet, so nothing can match it (otherwise two empty strings would count as a match)
        if ($sessionToken === '') {
            return false;
        }

        // hash_equals takes the same time however many letters match,
        // so nobody can work the code out a letter at a time by timing how fast we reply
        return hash_equals($sessionToken, $sentToken);
    }

    /**
     * Logs the user out, empties the session and deletes the cookie.
     */
    public static function endSession(): void
    {
        self::startSession();
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
     * Sends an error back as JSON and stops, so nothing else in the request runs.
     */
    private static function stop(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
