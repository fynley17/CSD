<?php

namespace App\Models;

/**
 * Forgot password links.
 *
 * Each account has at most one link at a time. The email gets the real code and the
 * database only keeps its SHA-256 hash, so a leaked database can't be used to reset anyone.
 * Kept apart from PasswordSetup, so a setup link can never reset a password and a reset
 * link can never finish setting up a new account.
 */
class PasswordReset extends Model
{
    // short, as a reset link is enough to take over the account
    public const LINK_LIFETIME_MINUTES = 60;
    // how long before another reset email can go to the same account, stops the mailbox being flooded
    public const RESEND_WAIT_SECONDS = 60;

    /**
     * Makes a reset link for the account with this email, if there is one that can use it.
     *
     * Only enabled accounts whose email is verified get a link. An unverified student
     * should verify first, and an account made by a lecturer or admin uses its setup link.
     * Returns null, without saying why, when there's no such account or a link was sent
     * less than a minute ago, so the caller can give the same reply either way.
     *
     * @return array{email: string, token: string}|null the address to send to and the code for the link
     */
    public static function createForEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $db = self::getDB();

        $stmt = $db->prepare(
            'SELECT userID, email
             FROM Users
             WHERE email = ?
             AND isEnabled = 1
             AND isEmailVerified = 1
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $account = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($account === false || !self::canResend($account['userID'])) {
            return null;
        }

        $token = self::makeToken();

        // REPLACE swaps out the account's old row, so only the newest link works
        $stmt = $db->prepare(
            'REPLACE INTO PasswordResetTokens (userID, tokenHash, expiresAt, sentAt)
             VALUES (?, ?, ' . self::nowPlusMinutesSql(self::LINK_LIFETIME_MINUTES) . ', CURRENT_TIMESTAMP)'
        );
        $stmt->execute([$account['userID'], self::hashToken($token)]);

        return ['email' => $account['email'], 'token' => $token];
    }

    /**
     * Checks a link hasn't been sent to this account in the last minute.
     */
    public static function canResend(string $userID): bool
    {
        $stmt = self::getDB()->prepare(
            'SELECT 1
             FROM PasswordResetTokens
             WHERE userID = ?
             AND sentAt > ' . self::nowMinusSecondsSql(self::RESEND_WAIT_SECONDS) . '
             LIMIT 1'
        );
        $stmt->execute([$userID]);

        return $stmt->fetchColumn() === false;
    }

    /**
     * Sets a new password using the code from a reset link.
     *
     * Only the password of the account the link was made for is changed, nothing else on the
     * account is touched. The link is deleted in the same transaction, so it can't be used twice.
     *
     * @return string|null the account's email address so it can be told about the change,
     *                     or null if the link is wrong, expired, used, or the account can't reset
     * @throws \InvalidArgumentException if the new password doesn't meet the password rules
     */
    public static function resetPassword(string $token, string $password): ?string
    {
        if (!self::isValidToken($token)) {
            return null;
        }

        if (!User::validPassword($password)) {
            throw new \InvalidArgumentException(
                'Please use at least 8 characters, including an uppercase letter, '
                . 'a lowercase letter, a number, and a symbol.'
            );
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $db = self::getDB();
        $lockSql = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';

        $db->beginTransaction();

        try {
            // locks the link's row, so two requests with the same link can't both use it
            $stmt = $db->prepare(
                'SELECT userID
                 FROM PasswordResetTokens
                 WHERE tokenHash = ?
                 AND expiresAt > ' . self::nowSql() . '
                 LIMIT 1' . $lockSql
            );
            $stmt->execute([self::hashToken($token)]);
            $userID = $stmt->fetchColumn();

            if ($userID === false) {
                $db->rollBack();
                return null;
            }

            // the password only, and only for the account the link belongs to
            // checked again here in case the account was disabled after the link was sent
            $stmt = $db->prepare(
                'UPDATE Users
                 SET password = ?
                 WHERE userID = ?
                 AND isEnabled = 1
                 AND isEmailVerified = 1'
            );
            $stmt->execute([$passwordHash, $userID]);

            if ($stmt->rowCount() !== 1) {
                $db->rollBack();
                return null;
            }

            $stmt = $db->prepare('DELETE FROM PasswordResetTokens WHERE userID = ?');
            $stmt->execute([$userID]);

            $stmt = $db->prepare('SELECT email FROM Users WHERE userID = ?');
            $stmt->execute([$userID]);
            $email = (string) $stmt->fetchColumn();

            $db->commit();

            return $email;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Makes a random link code, 32 random bytes written as 64 hex characters.
     * random_bytes() is safe for security, so nobody can guess the next code.
     */
    public static function makeToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hashes a link code before it's saved or looked up.
     * SHA-256 is enough here because the code is already long and random, unlike a password.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Checks a code looks like one makeToken() made, before the database is touched.
     */
    public static function isValidToken(mixed $token): bool
    {
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }

    // The times come from the database, so they match the expiry checks.
    // SQLite is only used by the unit tests, which is why each has two versions.

    private static function nowSql(): string
    {
        return self::isSqlite() ? "datetime('now')" : 'NOW()';
    }

    private static function nowPlusMinutesSql(int $minutes): string
    {
        return self::isSqlite() ? "datetime('now', '+{$minutes} minutes')" : "NOW() + INTERVAL {$minutes} MINUTE";
    }

    private static function nowMinusSecondsSql(int $seconds): string
    {
        return self::isSqlite() ? "datetime('now', '-{$seconds} seconds')" : "NOW() - INTERVAL {$seconds} SECOND";
    }

    private static function isSqlite(): bool
    {
        return self::getDB()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
