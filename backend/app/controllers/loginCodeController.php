<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Helpers\LoginCode;
use App\Models\BackupCode;
use App\Models\User;

/**
 * Login code controller, 2nd half of logging in when MFA is on
 * AuthController already checked the password, this does the emailed code or a backup code
 * extends AuthController to reuse the session helper, lockedMessage() and finishLogin()
 */
class LoginCodeController extends AuthController
{
    // messages shown on the page, the page puts "There is a problem:" in front itself
    // resend wait message is the same one VerificationController uses
    private const MISSING_CODE = 'Enter the security code';
    private const CODE_FORMAT = 'Enter the 6 digit code from the email, or one of your backup codes';
    private const WRONG_CODE = 'Security code is incorrect';
    private const EXPIRED_CODE = 'Security code has expired. Use Resend code to get a new one';
    private const NO_PENDING_LOGIN = 'Your session has ended. Login again to get a new code';
    private const WAIT_BEFORE_RESEND = 'Wait a minute before asking for another email';
    private const CODE_RESENT = 'We have sent a new security code';
    private const NO_NEW_CODES = 'There are no new backup codes to save. Login again to get a new set';

    /**
     * POST /api/login/code, checks the email code or a backup code then logs in
     */
    public function verifyCode(): void
    {
        $code = $this->readBody()['code'] ?? '';

        // page checks this too but anyone can hit the API directly
        if (!is_string($code) || trim($code) === '') {
            $this->jsonResponse(['error' => self::MISSING_CODE], 400);
        }

        // strip spaces and dashes so 123 456 and k7qm-2xpa still work
        $code = BackupCode::normalise($code);

        // not 6 digits or a backup code = 400, doesnt count as a try
        if (!LoginCode::isEmailCode($code) && !BackupCode::isBackupCode($code)) {
            $this->jsonResponse(['error' => self::CODE_FORMAT], 400);
        }

        $pending = $this->findPendingLogin();

        try {
            $user = $this->findPendingUser($pending);

            // locked (wrong codes or another browser), they start over after the 15 mins
            if ($user->isLocked()) {
                $this->authentication->clearPendingLogin();
                $this->jsonResponse(['error' => $this->lockedMessage($user)], 429);
            }

            // backup codes dont expire, only the email one does
            if (LoginCode::isEmailCode($code) && $pending->isExpired(time())) {
                $this->jsonResponse(['error' => self::EXPIRED_CODE], 401);
            }

            // try the email code first, a backup code only gets used up if it matches
            $isRightCode = $pending->matches($code, time()) || BackupCode::useCode($user->getUserID(), $code);

            if (!$isRightCode) {
                $this->wrongCode($user);
            }

            // password and code both right, reset the wrong tries
            $user->clearFailedAttempts();
            $user->saveLoginAttempts();

            // no codes left (first MFA login or just used the last one) so make a new set
            // hashes sit in the session till saveBackupCodes() puts them in the db
            $reply = [];
            if (BackupCode::countLeft($user->getUserID()) === 0) {
                $reply['backupCodes'] = BackupCode::makeSet();
                $this->authentication->saveNewBackupCodes(BackupCode::hashSet($reply['backupCodes']));
            }

            $this->finishLogin($user, $reply);
        } catch (\Throwable $e) {
            $this->serverError('Checking the security code', $e);
        }
    }

    /**
     * POST /api/login/code/resend, new code email, old code stops working
     */
    public function resendCode(): void
    {
        $pending = $this->findPendingLogin();

        // 429 = too many requests, stops the inbox getting flooded
        if (!$pending->canResend(time())) {
            $this->jsonResponse(['error' => self::WAIT_BEFORE_RESEND], 429);
        }

        try {
            $user = $this->findPendingUser($pending);

            // got locked meanwhile, wipe it so they have to login again (that shows the locked pop up)
            // 401 not 429, a 429 from resend means wait a minute
            if ($user->isLocked()) {
                $this->authentication->clearPendingLogin();
                $this->jsonResponse(['error' => self::NO_PENDING_LOGIN], 401);
            }

            if (!$this->emailNewCode($user, $pending->getStartedAt())) {
                $this->jsonResponse(['error' => self::CODE_NOT_SENT], 500);
            }

            $this->jsonResponse(['message' => self::CODE_RESENT]);
        } catch (\Throwable $e) {
            $this->serverError('Resending the security code', $e);
        }
    }

    /**
     * POST /api/login/backup-codes, saves the new codes after they've been copied or downloaded
     * if they refresh before this nothing's saved and they get a new set next login
     */
    public function saveBackupCodes(): void
    {
        // logged in by now, all 3 account types can have codes
        Authentication::requireRole(['student', 'lecturer', 'admin']);

        $hashes = $this->authentication->takeNewBackupCodes();
        if ($hashes === null) {
            $this->jsonResponse(['error' => self::NO_NEW_CODES], 400);
        }

        try {
            BackupCode::saveSet(Authentication::getLoggedInUserID(), $hashes);
            $this->jsonResponse(['message' => 'Backup codes saved']);
        } catch (\Throwable $e) {
            $this->serverError('Saving backup codes', $e);
        }
    }

    /**
     * Gets the half done login for this browser, stops if theres none or its over 30 mins old
     * only there if the password was right
     */
    private function findPendingLogin(): LoginCode
    {
        $pending = $this->authentication->getPendingLogin();

        if ($pending === null || $pending->hasRunOut(time())) {
            $this->authentication->clearPendingLogin();
            $this->jsonResponse(['error' => self::NO_PENDING_LOGIN], 401);
        }

        return $pending;
    }

    /**
     * Gets the account the code is for
     * stops if it got deleted, disabled or unapproved since the password step
     */
    private function findPendingUser(LoginCode $pending): User
    {
        $user = User::findById($pending->getUserID());

        // account deleted, nothing to log in to
        if ($user === null) {
            $this->authentication->clearPendingLogin();
            $this->jsonResponse(['error' => self::NO_PENDING_LOGIN], 401);
        }

        // same message as login, 401 not 403 because api.js retries 403s
        if (!$user->isActive()) {
            $this->authentication->clearPendingLogin();
            $this->jsonResponse(['error' => self::ACCOUNT_DISABLED], 401);
        }

        return $user;
    }

    /**
     * Counts a wrong code and stops the request
     * wrong codes and wrong passwords use the same 3 tries
     */
    private function wrongCode(User $user): void
    {
        $user->addFailedAttempt();

        // 3rd wrong try, kill their code too
        if ($user->isLocked()) {
            $this->authentication->clearPendingLogin();
            $this->sendLockedAlert($user);
            $this->jsonResponse(['error' => $this->lockedMessage($user)], 429);
        }

        $this->jsonResponse(['error' => self::WRONG_CODE], 401);
    }
}
