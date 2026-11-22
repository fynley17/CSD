<?php

namespace App\Models;

/**
 * Backup code model, the code that talks to the MfaBackupCodes table.
 * Each account gets 8 codes that work once each, for when the security code email can't be reached.
 * Only hashes are saved, same as passwords, so a leaked database doesn't give the codes away.
 */
class BackupCode extends Model
{
    // how many codes are in a set
    public const SET_SIZE = 8;
    // no 0, O, 1, I or L so a code copied off paper can't be misread
    private const LETTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Throws away the account's old codes and saves a new set of 8.
     *
     * @return string[] the codes to show the user, this is the only time they're ever shown
     */
    public static function createSet(string $userID): array
    {
        $codes = [];
        for ($i = 0; $i < self::SET_SIZE; $i++) {
            $codes[] = self::makeCode();
        }

        $db = self::getDB();

        // delete and save together, so a crash halfway can't leave them with no codes
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM MfaBackupCodes WHERE userID = ?');
            $stmt->execute([$userID]);

            $stmt = $db->prepare('INSERT INTO MfaBackupCodes (userID, codeHash) VALUES (?, ?)');
            foreach ($codes as $code) {
                // hashed without the dash, normalise() takes it off what they type as well
                $stmt->execute([$userID, password_hash(self::normalise($code), PASSWORD_DEFAULT)]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $codes;
    }

    /**
     * How many unused codes the account has left, 0 means a new set gets shown at login
     */
    public static function countLeft(string $userID): int
    {
        $stmt = self::getDB()->prepare('SELECT COUNT(*) FROM MfaBackupCodes WHERE userID = ?');
        $stmt->execute([$userID]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Checks a typed code against the account's codes and deletes it if it matches, so it only works once
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

                // if two logins sent the same code at once only one of them gets to delete the row
                return $delete->rowCount() === 1;
            }
        }

        return false;
    }

    /**
     * Makes one code like K7QM-2XPA, random_int is the secure random maker
     */
    public static function makeCode(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
        }

        // the dash just makes it easier to read and copy down
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
     * True if a tidied up code is 8 of the allowed letters and numbers, checked before the database is touched
     */
    public static function isBackupCode(string $code): bool
    {
        return preg_match('/^[' . self::LETTERS . ']{8}$/D', $code) === 1;
    }
}
