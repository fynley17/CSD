<?php

namespace App\Models;

use App\Helpers\Validation;

/**
 * User model: the code that talks to the Users table for login.
 * It finds an account, checks its password, counts wrong ones towards a lockout and saves the login time.
 * Abstract because nobody is just a User, every account is a Student, Lecturer or Administrator,
 * they all get the login code from here.
 */
abstract class User extends Model
{
    // the Users columns login needs, kept in one place so both searches match
    private const COLUMNS = 'userID, username, password, accountType, approvalStatus, isEnabled, isEmailVerified, '
        . 'failedAttempts, lockedUntil';

    // 3 wrong passwords within 15 minutes locks the account for 15 minutes, then it unlocks by itself
    // public so the login controller and the tests use the same numbers
    public const MAX_FAILED_ATTEMPTS = 3;
    public const LOCK_MINUTES = 15;
    // wrong passwords only add up if they're within 15 minutes of each other
    public const ATTEMPT_WINDOW_MINUTES = 15;

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
    private bool $isEmailVerified;

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
        $this->isEmailVerified = (bool) $row['isEmailVerified'];
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
     * True if the account has been switched off, whether or not it's been approved or verified
     */
    public function isDisabled(): bool
    {
        return !$this->isEnabled;
    }

    /**
     * True if a lecturer hasn't approved the account yet
     */
    public function isPending(): bool
    {
        return $this->approvalStatus === 'pending';
    }

    /**
    * True once they've clicked the link in their verification email
    */
    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
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
     * How many minutes are left on the lock, 0 if it isn't locked.
     * Rounded up so it never says 0 minutes while the account is still locked.
     */
    public function getMinutesUntilUnlock(): int
    {
        if (!$this->isLocked()) {
            return 0;
        }

        $secondsLeft = strtotime($this->lockedUntil) - time();
        return (int) ceil($secondsLeft / 60);
    }

    /**
     * Counts one wrong password, and locks the account when it reaches MAX_FAILED_ATTEMPTS.
     * Saves straight away, the database does the adding so wrong passwords sent at the same time all count.
     */
    public function addFailedAttempt(): void
    {
        $now = time();
        // the last wrong password has to be newer than this to count, older ones are forgotten
        $countFrom = date('Y-m-d H:i:s', $now - self::ATTEMPT_WINDOW_MINUTES * 60);

        // the database adds the 1 itself, so 20 wrong passwords at once can't all read 0 and all save 1
        // if the last wrong one was over 15 minutes ago (or never), the count starts again at 1
        $stmt = self::getDB()->prepare(
            'UPDATE Users SET failedAttempts = CASE WHEN lastFailedAt IS NULL OR lastFailedAt < ? THEN 1 '
            . 'ELSE failedAttempts + 1 END, lastFailedAt = ? WHERE userID = ?'
        );
        $stmt->execute([$countFrom, date('Y-m-d H:i:s', $now), $this->userID]);

        // reads the real count back, it includes any other wrong passwords that came in at the same time
        $stmt = self::getDB()->prepare('SELECT failedAttempts FROM Users WHERE userID = ?');
        $stmt->execute([$this->userID]);
        $this->failedAttempts = (int) $stmt->fetchColumn();

        if ($this->failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
            // the unlock time is worked out and checked with PHP's clock,
            // so it doesn't matter what timezone the database is set to
            $this->lockedUntil = date('Y-m-d H:i:s', $now + self::LOCK_MINUTES * 60);
            // back to 0, so they get 3 fresh tries once the lock runs out
            $this->failedAttempts = 0;
            $this->saveLoginAttempts();
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

    /**
     * The table this kind of account keeps its first and last name in, Students or Lecturer.
     * Only ever one of those fixed names, never anything from a request, so it's safe to put in the SQL.
     */
    abstract protected static function nameTable(): string;

    /**
     * Everything the My profile page shows, for this account only.
     * The username and account type are there to read, only a lecturer or admin can change them.
     *
     * @return array{username: string, accountType: string, firstName: string, lastName: string,
     *     email: string, pendingEmail: ?string}
     */
    public function getProfile(): array
    {
        $stmt = self::getDB()->prepare(
            'SELECT Users.email, Names.firstName, Names.lastName FROM Users '
            . 'LEFT JOIN ' . static::nameTable() . ' AS Names ON Names.userID = Users.userID '
            . 'WHERE Users.userID = ?'
        );
        $stmt->execute([$this->userID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException('Account not found');
        }

        return [
            'username' => $this->username,
            'accountType' => $this->accountType,
            'firstName' => (string) $row['firstName'],
            'lastName' => (string) $row['lastName'],
            'email' => $row['email'],
            // the new address waiting for its link to be opened, the account still uses 'email' until then
            'pendingEmail' => EmailVerification::findPendingEmail($this->userID),
        ];
    }

    /**
     * The password rules every account follows, used by registration, setup and reset links and My profile.
     * Lives here so Student, Lecturer and Administrator all inherit it.
     * The frontend copy is utils/passwordRules.js.
     * A space doesn't count as the symbol.
     */
    public static function validPassword(mixed $password): bool
    {
        return is_string($password)
            && strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9\s]/', $password) === 1;
    }

    /**
     * Checks the names and email from the My profile form, before anything is saved.
     * public static so the unit tests can pass in made up input.
     *
     * @param mixed $data whatever the page sent, after it's been turned from JSON into PHP
     * @return array{firstName: string, lastName: string, email: string} the trimmed details, email in lower case
     * @throws \InvalidArgumentException with the message to show if something is missing or wrong
     */
    public static function checkProfileDetails(mixed $data): array
    {
        $data = is_array($data) ? $data : [];
        $firstName = is_string($data['firstName'] ?? null) ? trim($data['firstName']) : '';
        $lastName = is_string($data['lastName'] ?? null) ? trim($data['lastName']) : '';
        $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';

        if (!Validation::isValidName($firstName)) {
            throw new \InvalidArgumentException(
                'Enter a first name between 2 and 50 characters, using only letters, spaces, hyphens or apostrophes'
            );
        }
        if (!Validation::isValidName($lastName)) {
            throw new \InvalidArgumentException(
                'Enter a last name between 2 and 50 characters, using only letters, spaces, hyphens or apostrophes'
            );
        }
        if (!Validation::isValidEmail($email)) {
            throw new \InvalidArgumentException('Enter an email address in the correct format, like name@example.com');
        }

        return ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email];
    }

    /**
     * Reads the My profile form for this kind of account. Every account has names and an email,
     * Administrator adds its staff ID on top, so the controller doesn't need to know which kind it has.
     *
     * @return array<string, string> the checked details, ready for updateProfile()
     * @throws \InvalidArgumentException with the message to show if something is missing or wrong
     */
    public function readProfileDetails(mixed $data): array
    {
        return self::checkProfileDetails($data);
    }

    /**
     * Saves this account's own names, and starts a change of email if the address is different.
     * Only ever changes the account this object was made for, there's no user ID to pass in.
     *
     * @param array{firstName: string, lastName: string, email: string} $details from checkProfileDetails()
     * @return string|null the code for the confirm link to email to the new address,
     *                     or null if the address didn't change
     * @throws \InvalidArgumentException if another account already uses the new address,
     *                                   or the account has no row to save its names in
     */
    public function updateProfile(array $details): ?string
    {
        $db = self::getDB();

        // without a Students or Lecturer row there's nothing to save the names into, so say so instead of
        // reporting a save that didn't happen
        $stmt = $db->prepare('SELECT 1 FROM ' . static::nameTable() . ' WHERE userID = ?');
        $stmt->execute([$this->userID]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                'Your name could not be saved because your account details are incomplete. '
                . 'Contact your lecturer or an administrator'
            );
        }

        $stmt = $db->prepare('SELECT email FROM Users WHERE userID = ?');
        $stmt->execute([$this->userID]);
        $currentEmail = strtolower((string) $stmt->fetchColumn());
        $emailChanged = $details['email'] !== $currentEmail;

        if ($emailChanged) {
            $stmt = $db->prepare('SELECT 1 FROM Users WHERE email = ? AND userID <> ? LIMIT 1');
            $stmt->execute([$details['email'], $this->userID]);
            if ($stmt->fetchColumn() !== false) {
                throw new \InvalidArgumentException('Another account is already using this email address');
            }
        }

        // both happen together or not at all, so the names are never saved with half an email change
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE ' . static::nameTable() . ' SET firstName = ?, lastName = ? WHERE userID = ?'
            );
            $stmt->execute([$details['firstName'], $details['lastName'], $this->userID]);

            // the address itself isn't touched here, it only changes once the link in the email is opened
            $token = $emailChanged
                ? EmailVerification::createEmailChangeToken($this->userID, $details['email'])
                : null;

            $db->commit();

            return $token;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Changes this account's password from the My profile page.
     * The new password is checked against the same rules as registration and saved as a hash.
     *
     * @return bool false if the current password is wrong, which counts towards the lock like a wrong login
     * @throws \InvalidArgumentException if the new password breaks the rules or the two don't match
     */
    public function changePassword(string $currentPassword, string $newPassword, string $confirmPassword): bool
    {
        // the new password is checked first, these mistakes don't need to count towards a lock
        if (!self::validPassword($newPassword)) {
            throw new \InvalidArgumentException(
                'Use at least 8 characters, with an uppercase letter, a lowercase letter, a number and a symbol'
            );
        }
        if ($newPassword !== $confirmPassword) {
            throw new \InvalidArgumentException('Your new passwords do not match');
        }

        // someone using a computer that was left signed in can't take over the account without the password,
        // and can't keep guessing it either, 3 wrong ones lock it the same as the login page
        if (!$this->login($currentPassword)) {
            $this->addFailedAttempt();
            return false;
        }

        $this->passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->clearFailedAttempts();

        $db = self::getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE Users SET password = ?, failedAttempts = 0, lockedUntil = NULL WHERE userID = ?'
            );
            $stmt->execute([$this->passwordHash, $this->userID]);

            // a forgot password link sent before now can't be used to undo the change
            $stmt = $db->prepare('DELETE FROM PasswordResetTokens WHERE userID = ?');
            $stmt->execute([$this->userID]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return true;
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
     * Saves a new username for this account, only Administrator uses it, for its own staff ID.
     * The session only holds the user ID, so whoever is logged in stays logged in.
     */
    protected function saveUsername(string $username): void
    {
        $stmt = self::getDB()->prepare('UPDATE Users SET username = ? WHERE userID = ?');
        $stmt->execute([$username, $this->userID]);
        $this->username = $username;
    }

    /**
     * Returns the username (student number or staff ID).
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * gets the email, only the code and locked emails need it so its not in COLUMNS
     */
    public function findEmail(): string
    {
        $stmt = self::getDB()->prepare('SELECT email FROM Users WHERE userID = ?');
        $stmt->execute([$this->userID]);

        return (string) $stmt->fetchColumn();
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
