<?php

namespace App\Helpers;

/**
 * Login code helper, the 6 digit security code emailed after the right password (MFA)
 * one of these lives in the PHP session between the password and the code being typed in,
 * it only keeps a hash of the code so the real code is only ever in the email
 */
class LoginCode
{
    // 6 digits like most sites use, short enough to type from a phone
    public const CODE_LENGTH = 6;
    // how long the code works for after it's sent
    public const LIFETIME_MINUTES = 10;
    // same wait as the verification email, stops someone flooding the inbox
    public const RESEND_WAIT_SECONDS = 60;

    private string $userID;
    private string $codeHash;
    // unix time the email went, the expiry and resend wait both count from this
    private int $sentAt;
    // unix time the password was right, resending doesn't move it
    private int $startedAt;

    /**
     * Makes the pending login for one account, after their password was right
     */
    public function __construct(string $userID, string $codeHash, int $sentAt, int $startedAt)
    {
        $this->userID = $userID;
        $this->codeHash = $codeHash;
        $this->sentAt = $sentAt;
        $this->startedAt = $startedAt;
    }

    /**
     * True when MFA_ENABLED is set to true in the .env, anything else means password only
     */
    public static function isRequired(): bool
    {
        return getenv('MFA_ENABLED') === 'true';
    }

    /**
     * Makes a random 6 digit code
     * random_int is PHP's secure one so the next code can't be guessed from the last
     */
    public static function makeCode(): string
    {
        // str_pad puts the 0s back on the front, i.e. 4211 becomes 004211
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Hashes a code before it goes in the session, like the email verification links
     */
    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * True if what was typed is shaped like an email code (6 digits), not a backup code
     */
    public static function isEmailCode(string $typed): bool
    {
        return preg_match('/^[0-9]{6}$/D', $typed) === 1;
    }

    /**
     * Hides most of the email address so the page can show where the code went
     * i.e. ws123456@example.com becomes w***@example.com
     */
    public static function maskEmail(string $email): string
    {
        $atPosition = strrpos($email, '@');

        // not a normal address, so don't show any of it
        if ($atPosition === false || $atPosition === 0) {
            return '***';
        }

        return substr($email, 0, 1) . '***' . substr($email, $atPosition);
    }

    /**
     * Rebuilds the pending login from what saveForSession() put in the session
     *
     * @return LoginCode|null null if there isn't one or it's not the right shape
     */
    public static function fromSession(mixed $saved): ?LoginCode
    {
        if (!is_array($saved)) {
            return null;
        }
        if (!is_string($saved['userID'] ?? null) || !is_string($saved['codeHash'] ?? null)) {
            return null;
        }
        if (!is_int($saved['sentAt'] ?? null) || !is_int($saved['startedAt'] ?? null)) {
            return null;
        }

        return new LoginCode($saved['userID'], $saved['codeHash'], $saved['sentAt'], $saved['startedAt']);
    }

    /**
     * Turns this into a plain array for $_SESSION, the session can't hold the object itself safely
     */
    public function saveForSession(): array
    {
        return [
            'userID' => $this->userID,
            'codeHash' => $this->codeHash,
            'sentAt' => $this->sentAt,
            'startedAt' => $this->startedAt,
        ];
    }

    /**
     * True once 10 minutes have passed since the email was sent
     * the time is passed in so the tests don't have to wait 10 minutes
     */
    public function isExpired(int $now): bool
    {
        return $now - $this->sentAt >= self::LIFETIME_MINUTES * 60;
    }

    /**
     * True once the whole half finished login is older than the 30 minute idle logout
     * stops someone keeping it going for ever by pressing Resend code
     */
    public function hasRunOut(int $now): bool
    {
        return $now - $this->startedAt >= Authentication::IDLE_TIMEOUT_SECONDS;
    }

    /**
     * True if a minute has passed since the last email, so another one can go
     */
    public function canResend(int $now): bool
    {
        return $now - $this->sentAt >= self::RESEND_WAIT_SECONDS;
    }

    /**
     * True if the typed code is this one and it hasn't run out
     */
    public function matches(string $typed, int $now): bool
    {
        if ($this->isExpired($now)) {
            return false;
        }

        // hash_equals so the check takes the same time however much of the code is right
        return hash_equals($this->codeHash, self::hashCode($typed));
    }

    /**
     * Returns the ID of the account this code logs in to
     */
    public function getUserID(): string
    {
        return $this->userID;
    }

    /**
     * Returns when the password was right, so a resent code keeps the same 30 minute limit
     */
    public function getStartedAt(): int
    {
        return $this->startedAt;
    }
}
