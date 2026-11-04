<?php

namespace App\Models;

/**
 * Handles password setup tokens for lecturer-created student accounts.
 * Only SHA-256 token hashes are stored in the database.
 */
class PasswordSetup extends Model
{
    public const LINK_LIFETIME_HOURS = 24;
    public const RESEND_WAIT_SECONDS = 60;

    /**
     * Generates a password setup token and stores its hash.
     *
     * A new token replaces any existing token for the account.
     */
    public static function createToken(string $userID): string
    {
        $db = self::getDB();

        $account = $db->prepare(
            "SELECT 1 FROM Users
             WHERE userID = ?
             AND accountType = 'student'
             AND isEmailVerified = 0
             LIMIT 1"
        );
        $account->execute([$userID]);

        if ($account->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                'Student account not found or already verified.'
            );
        }

        $token = self::makeToken();
        $tokenHash = self::hashToken($token);

        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $expirySql = $driver === 'sqlite'
            ? "datetime('now', '+24 hours')"
            : 'DATE_ADD(NOW(), INTERVAL 24 HOUR)';

        $stmt = $db->prepare(
            "REPLACE INTO PasswordSetupTokens
                (userID, tokenHash, expiresAt, sentAt)
             VALUES (?, ?, $expirySql, CURRENT_TIMESTAMP)"
        );

        $stmt->execute([$userID, $tokenHash]);

        return $token;
    }

    /**
     * Finds a password setup token and checks whether it has expired.
     */
    public static function findByToken(string $token): ?array
    {
        if (!self::isValidToken($token)) {
            return null;
        }

        $db = self::getDB();

        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $expirySql = $driver === 'sqlite'
            ? "(expiresAt <= datetime('now'))"
            : '(expiresAt <= NOW())';

        $stmt = $db->prepare(
            "SELECT userID, $expirySql AS isExpired
             FROM PasswordSetupTokens
             WHERE tokenHash = ?
             LIMIT 1"
        );

        $stmt->execute([self::hashToken($token)]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'userID' => $row['userID'],
            'isExpired' => (bool) $row['isExpired'],
        ];
    }

    /**
     * Checks whether another setup email can be sent.
     *
     * Returns true when no token exists or the resend delay has passed.
     */
    public static function canResend(string $userID): bool
    {
        $db = self::getDB();

        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $recentSql = $driver === 'sqlite'
            ? "sentAt > datetime('now', '-60 seconds')"
            : 'sentAt > DATE_SUB(NOW(), INTERVAL 60 SECOND)';

        $stmt = $db->prepare(
            "SELECT 1
             FROM PasswordSetupTokens
             WHERE userID = ?
             AND $recentSql
             LIMIT 1"
        );

        $stmt->execute([$userID]);

        return $stmt->fetchColumn() === false;
    }

    /**
     * Saves a new password, verifies the email and invalidates the token.
     *
     * Returns false if the token is invalid, expired or the account
     * is not eligible to complete password setup.
     */
    public static function setPassword(string $token, string $password): bool
    {
        if (!self::isValidToken($token)) {
            return false;
        }

        if (!Student::validPassword($password)) {
            throw new \InvalidArgumentException(
                'Please use at least 8 characters, including an uppercase letter, '
                . 'a lowercase letter, a number, and a symbol.'
            );
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new \RuntimeException('Unable to secure the new password.');
        }

        $db = self::getDB();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $expiryCondition = $driver === 'sqlite'
            ? "expiresAt > datetime('now')"
            : 'expiresAt > NOW()';

        $lockSql = $driver === 'sqlite' ? '' : ' FOR UPDATE';

        $db->beginTransaction();

        try {
            // Lock the token row so it cannot be used twice concurrently.
            $stmt = $db->prepare(
                "SELECT userID
                 FROM PasswordSetupTokens
                 WHERE tokenHash = ?
                 AND $expiryCondition
                 LIMIT 1$lockSql"
            );

            $stmt->execute([self::hashToken($token)]);

            $userID = $stmt->fetchColumn();

            if ($userID === false) {
                $db->rollBack();
                return false;
            }

            // Only enabled, unverified student accounts can use setup links.
            $stmt = $db->prepare(
                "UPDATE Users
                 SET password = ?, isEmailVerified = 1
                 WHERE userID = ?
                 AND accountType = 'student'
                 AND isEnabled = 1
                 AND isEmailVerified = 0"
            );

            $stmt->execute([$passwordHash, $userID]);

            if ($stmt->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }

            // A successful setup link can never be reused.
            $stmt = $db->prepare(
                'DELETE FROM PasswordSetupTokens WHERE userID = ?'
            );

            $stmt->execute([$userID]);

            $db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Generates a cryptographically secure random token.
     */
    public static function makeToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hashes a token before it is stored or looked up.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Checks that a token has the expected format.
     */
    public static function isValidToken(mixed $token): bool
    {
        return is_string($token)
            && preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }
}
