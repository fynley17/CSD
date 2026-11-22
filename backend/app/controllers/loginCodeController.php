<?php

namespace App\Controllers;

use App\Helpers\LoginCode;
use App\Models\BackupCode;
use App\Models\User;

/**
 * Login code controller, the second half of logging in when MFA is switched on
 * the password was already checked by AuthController, this checks the emailed code or a backup code
 * extends AuthController so it reuses the same session helper, locked message and finishLogin()
 */
class LoginCodeController extends AuthController
{
    // messages the user sees, the page adds "There is a problem:" or "Success:" in front
    // the resend wait is worded the same as the verification email one
    private const MISSING_CODE = 'Enter the security code';
    private const CODE_FORMAT = 'Enter the 6 digit code from the email, or one of your backup codes';
    private const WRONG_CODE = 'Security code is incorrect';
    private const EXPIRED_CODE = 'Security code has expired. Use Resend code to get a new one';
    private const NO_PENDING_LOGIN = 'Your session has ended. Login again to get a new code';
    private const WAIT_BEFORE_RESEND = 'Wait a minute before asking for another email';
    private const CODE_RESENT = 'We have sent a new security code';

    /**
     * POST /api/login/code, checks the emailed security code or a backup code then logs them in
     */
    public function verifyCode(): void
    {
        $code = $this->readBody()['code'] ?? '';

        // checked here as well as on the page, anyone can call /api/login/code directly
        if (!is_string($code) || trim($code) === '') {
            $this->jsonResponse(['error' => self::MISSING_CODE], 400);
        }

        // takes out spaces and dashes so 123 456 or k7qm-2xpa still work, the same tidy up suits both kinds
        $code = BackupCode::normalise($code);

        // the wrong shape can't be either kind of code, so it's turned away without using up a try
        if (!LoginCode::isEmailCode($code) && !BackupCode::isBackupCode($code)) {
            $this->jsonResponse(['error' => self::CODE_FORMAT], 400);
        }

        $pending = $this->findPendingLogin();

        try {
            $user = $this->findPendingUser($pending);

            // locked by wrong codes or from another browser, they start again once it's over
            if ($user->isLocked()) {
                $this->authentication->clearPendingLogin();
                $this->jsonResponse(['error' => $this->lockedMessage($user)], 429);
            }

            // only the emailed code runs out after 10 minutes, a backup code still works
            if (LoginCode::isEmailCode($code) && $pending->isExpired(time())) {
                $this->jsonResponse(['error' => self::EXPIRED_CODE], 401);
            }

            // email code first then backup codes, a backup code only gets used up if it's actually right
            $isRightCode = $pending->matches($code, time()) || BackupCode::useCode($user->getUserID(), $code);

            if (!$isRightCode) {
                $this->wrongCode($user);
            }

            // both steps are right now, so the earlier wrong tries can go
            $user->clearFailedAttempts();
            $user->saveLoginAttempts();

            // first MFA login, or they just used their last backup code, so they get a new set to save
            // made before finishLogin() so a database problem doesn't leave them logged in without seeing them
            $reply = [];
            if (BackupCode::countLeft($user->getUserID()) === 0) {
                $reply['backupCodes'] = BackupCode::createSet($user->getUserID());
            }

            $this->finishLogin($user, $reply);
        } catch (\Throwable $e) {
            $this->serverError('Checking the security code', $e);
        }
    }

    /**
     * POST /api/login/code/resend, emails a new security code and the old one stops working
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

            // locked since the password was typed, so they go back to login and get the locked pop up there
            // not a 429, the page already reads a 429 here as "wait a minute"
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
     * Gets this browser's half finished login, and stops if there isn't one or it's over 30 minutes old
     * only a browser that got the password right has one
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
     * Gets the account the code is for, and stops if it was deleted, disabled or unapproved
     * since the password was typed
     */
    private function findPendingUser(LoginCode $pending): User
    {
        $user = User::findById($pending->getUserID());

        // deleted, so there's nothing to log in to, they have to start again
        if ($user === null) {
            $this->authentication->clearPendingLogin();
            $this->jsonResponse(['error' => self::NO_PENDING_LOGIN], 401);
        }

        // same message login gives a disabled account, 401 not 403 as api.js sends a 403 twice
        if (!$user->isActive()) {
            $this->authentication->clearPendingLogin();
            $this->jsonResponse(['error' => self::ACCOUNT_DISABLED], 401);
        }

        return $user;
    }

    /**
     * Counts a wrong code and stops the request
     * wrong codes and wrong passwords share the same 3 tries and 15 minute lock
     */
    private function wrongCode(User $user): void
    {
        $user->addFailedAttempt();

        // that was the 3rd wrong try, so the code they have stops working as well
        if ($user->isLocked()) {
            $this->authentication->clearPendingLogin();
            $this->jsonResponse(['error' => $this->lockedMessage($user)], 429);
        }

        $this->jsonResponse(['error' => self::WRONG_CODE], 401);
    }
}
