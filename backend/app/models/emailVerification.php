<?php

namespace App\Models;

/**
 * Email verification model: the code that talks to the EmailVerifications table.
 * Each unverified account has one link code, the email gets the real code and the
 * database only keeps its hash, so a leaked database can't be used to verify anyone.
 */
class EmailVerification extends Model
{
    // how long a link keeps working after it's sent
    public const LINK_LIFETIME_HOURS = 24;
    // how long someone has to wait before another email is sent, stops the mailbox being flooded
    public const RESEND_WAIT_SECONDS = 60;

    /**
     * Makes a new link code for an account and saves its hash, any older link stops working.
     *
     * @return string the code to put in the email link
     */
    public static function createToken(string $userID): string
    {
        $token = self::makeToken();

        // REPLACE swaps out the account's old row, so only the newest link works
        // the times come from the database so they match the NOW() checks below
        $stmt = self::getDB()->prepare(
            'REPLACE INTO EmailVerifications (userID, tokenHash, expiresAt, sentAt) '
            . 'VALUES (?, ?, NOW() + INTERVAL ' . self::LINK_LIFETIME_HOURS . ' HOUR, NOW())'
        );
        $stmt->execute([$userID, self::hashToken($token)]);

        return $token;
    }

    /**
     * Finds which account a link code belongs to.
     *
     * @return array|null ['userID' => ..., 'isExpired' => bool], or null if the code doesn't match any account
     */
    public static function findByToken(string $token): ?array
    {
        $stmt = self::getDB()->prepare(
            'SELECT userID, expiresAt <= NOW() AS isExpired FROM EmailVerifications WHERE tokenHash = ?'
        );
        $stmt->execute([self::hashToken($token)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return ['userID' => $row['userID'], 'isExpired' => (bool) $row['isExpired']];
    }

    /**
     * Marks the account's email as verified and deletes its link, so the link can't be used twice.
     */
    public static function markVerified(string $userID): void
    {
        $db = self::getDB();

        // both changes happen together or not at all
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE Users SET isEmailVerified = 1 WHERE userID = ?');
            $stmt->execute([$userID]);

            $stmt = $db->prepare('DELETE FROM EmailVerifications WHERE userID = ?');
            $stmt->execute([$userID]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Checks enough time has passed since the last email to send another one.
     */
    public static function canResend(string $userID): bool
    {
        $stmt = self::getDB()->prepare(
            'SELECT 1 FROM EmailVerifications WHERE userID = ? '
            . 'AND sentAt > NOW() - INTERVAL ' . self::RESEND_WAIT_SECONDS . ' SECOND'
        );
        $stmt->execute([$userID]);

        // no recent row means nothing has been sent in the last minute
        return $stmt->fetchColumn() === false;
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
     * Checks a code from the page looks like one makeToken() made, before the database is touched.
     */
    public static function isValidToken(mixed $token): bool
    {
        return is_string($token) && preg_match('/^[0-9a-f]{64}$/D', $token) === 1;
    }
}
