<?php

namespace App\Helpers;

/**
 * Login code helper, the 6 digit code emailed after the password (MFA)
 * one of these sits in the session between typing the password and typing the code
 * only the hash is kept, the actual code is only in the email
 */
class LoginCode
{
    // 6 digits, same as most sites
    public const CODE_LENGTH = 6;
    // how long the code works for after it's sent
    public const LIFETIME_MINUTES = 10;
    // same wait as the verify email, stops inbox spam
    public const RESEND_WAIT_SECONDS = 60;

    private string $userID;
    private string $codeHash;
    // unix time the email was sent, expiry and resend wait count from here
    private int $sentAt;
    // when the password was typed, resending doesnt change it
    private int $startedAt;

    /**
     * Pending login for one account once their password was right
     */
    public function __construct(string $userID, string $codeHash, int $sentAt, int $startedAt)
    {
        $this->userID = $userID;
        $this->codeHash = $codeHash;
        $this->sentAt = $sentAt;
        $this->startedAt = $startedAt;
    }

    /**
     * True if MFA_ENABLED=true in .env, anything else is password only
     */
    public static function isRequired(): bool
    {
        return getenv('MFA_ENABLED') === 'true';
    }

    /**
     * Makes a random 6 digit code
     * random_int not rand() as rand can be predicted
     */
    public static function makeCode(): string
    {
        // pads the front with 0s e.g. 4211 to 004211
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Hash for the session, same as the verify email links
     */
    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * True for 6 digits (email code), false for anything else e.g. a backup code
     */
    public static function isEmailCode(string $typed): bool
    {
        return preg_match('/^[0-9]{' . self::CODE_LENGTH . '}$/D', $typed) === 1;
    }

    /**
     * Hides most of the email for the page
     * ws123456@example.com becomes w***@example.com
     */
    public static function maskEmail(string $email): string
    {
        $atPosition = strrpos($email, '@');

        // weird address, just hide it all
        if ($atPosition === false || $atPosition === 0) {
            return '***';
        }

        return substr($email, 0, 1) . '***' . substr($email, $atPosition);
    }

    /**
     * Builds it back from what saveForSession() stored
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
     * plain array for $_SESSION, fromSession() checks each bit on the way back
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
     * True once 10 mins have passed since the email
     * time gets passed in so tests dont actually wait
     */
    public function isExpired(int $now): bool
    {
        return $now - $this->sentAt >= self::LIFETIME_MINUTES * 60;
    }

    /**
     * True once the half done login is older than 30 mins (same as idle logout)
     * otherwise pressing Resend could keep it going forever
     */
    public function hasRunOut(int $now): bool
    {
        return $now - $this->startedAt >= Authentication::IDLE_TIMEOUT_SECONDS;
    }

    /**
     * True if its been a minute since the last email
     */
    public function canResend(int $now): bool
    {
        return $now - $this->sentAt >= self::RESEND_WAIT_SECONDS;
    }

    /**
     * True if the typed code matches and hasnt expired
     */
    public function matches(string $typed, int $now): bool
    {
        if ($this->isExpired($now)) {
            return false;
        }

        // hash_equals takes the same time if its nearly right or way off (timing attacks)
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
     * When the password was typed, resent codes keep the same 30 min limit
     */
    public function getStartedAt(): int
    {
        return $this->startedAt;
    }
}
