<?php

namespace App\Models;

/**
 * User model: the code that talks to the Users table for login.
 * It finds an account, checks its password, counts wrong ones towards a lockout and saves the login time.
 * Abstract because nobody is just a User, every account is a Student, Lecturer or Administrator,
 * they all get the login code from here.
 */
abstract class User extends Model
{
    // the Users columns login needs, kept in one place so both searches match
    private const COLUMNS = 'userID, username, password, accountType, approvalStatus, isEnabled, '
        . 'failedAttempts, lockedUntil';

    // 3 wrong passwords in a row locks the account for 15 minutes, then it unlocks by itself
    // public so the login controller and the tests use the same numbers
    public const MAX_FAILED_ATTEMPTS = 3;
    public const LOCK_MINUTES = 15;

    // private so nothing outside this class can change them
    private string $userID;
    private string $username;
    private string $passwordHash;
    private string $accountType;
    private string $approvalStatus;
    private bool $isEnabled;
    private int $failedAttempts;
    // null when the account isn't locked
    private ?string $lockedUntil;

    /**
     * Fills in the shared details from a row of the Users table, used by Student, Lecturer and Administrator.
     */
    public function __construct(array $row)
    {
        $this->userID = $row['userID'];
        $this->username = $row['username'];
        $this->passwordHash = $row['password'];
        $this->accountType = $row['accountType'];
        $this->approvalStatus = $row['approvalStatus'];
        // the database gives 1 or 0, this turns it into true or false
        $this->isEnabled = (bool) $row['isEnabled'];
        $this->failedAttempts = (int) $row['failedAttempts'];
        $this->lockedUntil = $row['lockedUntil'];
    }

    /**
     * Finds an account by username (student number or staff ID).
     *
     * @return User|null null if nobody has that username
     */
    public static function findByUsername(string $username): ?User
    {
        // parameterised query, the username goes in the ? separately so it can't run as SQL (stops SQL injection)
        $stmt = self::getDB()->prepare('SELECT ' . self::COLUMNS . ' FROM Users WHERE username = ?');
        $stmt->execute([$username]);
        return self::makeUser($stmt);
    }

    /**
     * Finds an account by user ID, used to check who is logged in.
     *
     * @return User|null null if the account has been deleted
     */
    public static function findById(string $userID): ?User
    {
        $stmt = self::getDB()->prepare('SELECT ' . self::COLUMNS . ' FROM Users WHERE userID = ?');
        $stmt->execute([$userID]);
        return self::makeUser($stmt);
    }

    /**
     * Turns the 'Users' row that findByUsername() or findById() fetches, into the right kind of user object.
     * It only reads an account that's already in the database, nothing new gets added here (registration does that).
     * Gives back null if no row came back, i.e. nobody has that username or ID.
     */
    private static function makeUser(\PDOStatement $stmt): ?User
    {
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        // fetch returns false if there was no row
        if ($row === false) {
            return null;
        }

        // User can't be made on its own, so the account type picks which one to make
        if ($row['accountType'] === 'admin') {
            return new Administrator($row);
        }
        if ($row['accountType'] === 'lecturer') {
            return new Lecturer($row);
        }
        if ($row['accountType'] === 'student') {
            return new Student($row);
        }

        // the database only allows those three, so anything else means something's gone wrong
        throw new \UnexpectedValueException('Unknown account type: ' . $row['accountType']);
    }

    /**
     * Checks the password they typed matches the saved hash.
     */
    public function login(string $password): bool
    {
        // password_verify hashes what they typed and compares, we never store the real password
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Checks the account's been approved and isn't disabled.
     */
    public function isActive(): bool
    {
        return $this->isEnabled && $this->approvalStatus === 'approved';
    }

    /**
     * True if a lecturer hasn't approved the account yet
     */
    public function isPending(): bool
    {
        return $this->approvalStatus === 'pending';
    }

    /**
     * True while the account is locked after too many wrong passwords.
     */
    public function isLocked(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }

        // still locked if the unlock time hasn't come yet, after that it's unlocked without anyone doing anything
        return strtotime($this->lockedUntil) > time();
    }

    /**
     * Counts one wrong password, and locks the account when it reaches MAX_FAILED_ATTEMPTS.
     * Only changes this object, saveLoginAttempts() is what writes it to the database.
     */
    public function addFailedAttempt(): void
    {
        $this->failedAttempts++;

        if ($this->failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
            // the unlock time is worked out and checked with PHP's clock,
            // so it doesn't matter what timezone the database is set to
            $this->lockedUntil = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
            // back to 0, so they get 3 fresh tries once the lock runs out
            $this->failedAttempts = 0;
        }
    }

    /**
     * Wipes the count after a right password, so old mistakes don't add up towards a lock.
     */
    public function clearFailedAttempts(): void
    {
        $this->failedAttempts = 0;
        $this->lockedUntil = null;
    }

    /**
     * Writes failedAttempts and lockedUntil to the Users table.
     */
    public function saveLoginAttempts(): void
    {
        $stmt = self::getDB()->prepare('UPDATE Users SET failedAttempts = ?, lockedUntil = ? WHERE userID = ?');
        $stmt->execute([$this->failedAttempts, $this->lockedUntil, $this->userID]);
    }

    /**
     * Saves the time of a successful login, the lecturer's student table shows it as last login.
     */
    public function recordLogin(): void
    {
        $stmt = self::getDB()->prepare('UPDATE Users SET lastLogin = NOW() WHERE userID = ?');
        $stmt->execute([$this->userID]);
    }

    // getters, so other files can read these values but not change them
    // no getter for passwordHash, so the hash can't end up being sent to the browser

    /**
     * Returns the user's ID.
     */
    public function getUserID(): string
    {
        return $this->userID;
    }

    /**
     * Returns the username (student number or staff ID).
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Returns the account type (student, lecturer or admin).
     */
    public function getAccountType(): string
    {
        return $this->accountType;
    }

    /**
     * Returns how many wrong passwords in a row there have been since the last lock or good login.
     */
    public function getFailedAttempts(): int
    {
        return $this->failedAttempts;
    }
}
