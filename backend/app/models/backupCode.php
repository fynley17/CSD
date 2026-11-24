<?php

namespace App\Models;

/**
 * Backup code model, talks to the MfaBackupCodes table
 * 8 one use codes per account for when the email isn't getting through
 * only hashes get saved like passwords, a leaked db doesnt give the codes away
 */
class BackupCode extends Model
{
    // how many codes are in a set
    public const SET_SIZE = 8;
    // characters in one code, not counting the dash
    public const CODE_LENGTH = 8;
    // no 0 O 1 I or L, too easy to mix up copying off paper
    private const LETTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Makes 8 new codes, doesnt save them
     *
     * @return string[] the codes for the page, only time they're shown
     */
    public static function makeSet(): array
    {
        $codes = [];
        for ($i = 0; $i < self::SET_SIZE; $i++) {
            $codes[] = self::makeCode();
        }

        return $codes;
    }

    /**
     * Hashes each code, these wait in the session till the codes are copied or downloaded
     *
     * @return string[] password_hash of each code, without the dash
     */
    public static function hashSet(array $codes): array
    {
        $hashes = [];
        foreach ($codes as $code) {
            // no dash, normalise() strips it from what they type too
            $hashes[] = password_hash(self::normalise($code), PASSWORD_DEFAULT);
        }

        return $hashes;
    }

    /**
     * Deletes their old codes and saves the new hashes
     */
    public static function saveSet(string $userID, array $hashes): void
    {
        $db = self::getDB();

        // one transaction so a crash halfway doesnt leave them with no codes
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM MfaBackupCodes WHERE userID = ?');
            $stmt->execute([$userID]);

            $stmt = $db->prepare('INSERT INTO MfaBackupCodes (userID, codeHash) VALUES (?, ?)');
            foreach ($hashes as $hash) {
                $stmt->execute([$userID, $hash]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Unused codes left, 0 = new set at next login
     */
    public static function countLeft(string $userID): int
    {
        $stmt = self::getDB()->prepare('SELECT COUNT(*) FROM MfaBackupCodes WHERE userID = ?');
        $stmt->execute([$userID]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Checks the typed code and deletes it on a match so it only works once
     */
    public static function useCode(string $userID, string $typed): bool
    {
        $typed = self::normalise($typed);
        if (!self::isBackupCode($typed)) {
            return false;
        }

        $stmt = self::getDB()->prepare('SELECT backupCodeID, codeHash FROM MfaBackupCodes WHERE userID = ?');
        $stmt->execute([$userID]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (password_verify($typed, $row['codeHash'])) {
                $delete = self::getDB()->prepare('DELETE FROM MfaBackupCodes WHERE backupCodeID = ?');
                $delete->execute([$row['backupCodeID']]);

                // two logins with the same code at once, only one gets to delete the row
                return $delete->rowCount() === 1;
            }
        }

        return false;
    }

    /**
     * Makes one code like K7QM-2XPA using random_int
     */
    public static function makeCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
        }

        // dash is just for reading
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    /**
     * Tidies up what was typed, k7qm 2xpa or k7qm-2xpa both become K7QM2XPA
     */
    public static function normalise(string $typed): string
    {
        return strtoupper(str_replace([' ', '-'], '', $typed));
    }

    /**
     * True if its 8 allowed characters, checked before hitting the db
     */
    public static function isBackupCode(string $code): bool
    {
        return preg_match('/^[' . self::LETTERS . ']{' . self::CODE_LENGTH . '}$/D', $code) === 1;
    }
}
