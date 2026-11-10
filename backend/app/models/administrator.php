<?php

namespace App\Models;

use App\Helpers\Validation;

/**
 * Administrator, extends Lecturer because an admin can do every lecturer job as well
 * and they manage subjects and lecturer accounts
 */
class Administrator extends Lecturer
{
    /**
     * Counts what the four admin dashboard cards show
     *
     * @return array<string, int> totals for subjects, lecturers, archived subjects and disabled lecturers
     */
    public static function getDashboardSummary(): array
    {
        // all four numbers in one trip to the database, an archived subject has isActive set to 0
        // only lecturer accounts are counted as lecturers, so admins aren't in those totals
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM Subjects) AS totalSubjects,
                (SELECT COUNT(*) FROM Users WHERE accountType = 'lecturer') AS totalLecturers,
                (SELECT COUNT(*) FROM Subjects WHERE isActive = 0) AS archivedSubjects,
                (SELECT COUNT(*) FROM Users WHERE accountType = 'lecturer' AND isEnabled = 0) AS disabledLecturers
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException('The admin dashboard totals could not be read');
        }

        return [
            'totalSubjects' => (int) $row['totalSubjects'],
            'totalLecturers' => (int) $row['totalLecturers'],
            'archivedSubjects' => (int) $row['archivedSubjects'],
            'disabledLecturers' => (int) $row['disabledLecturers'],
        ];
    }

    /**
     * Gets the lecturers for the admin dashboard table and the lecturer management page, latest login first
     *
     * @param int|null $limit how many to get, null gets every lecturer
     * @param bool $withAdmins true lists admin accounts as well, the management page wants them so a new admin shows up
     * @return list<array{userID: string, firstName: string, lastName: string, name: string, staffID: string,
     *     email: string, isEnabled: bool, lastLogin: ?string, role: string, hasPassword: bool}>
     */
    public static function getRecentLecturers(?int $limit = 5, bool $withAdmins = false): array
    {
        // the dashboard only counts lecturer accounts, same as the cards
        // anyone who's never logged in goes to the bottom
        // the staff ID is the username, that's what lecturers log in with
        $accountTypes = $withAdmins ? "('lecturer', 'admin')" : "('lecturer')";
        $sql = "
            SELECT Users.userID, Lecturer.firstName, Lecturer.lastName, Users.username, Users.email,
                Users.isEnabled, Users.lastLogin, Users.accountType, Users.isEmailVerified
            FROM Users
            JOIN Lecturer ON Lecturer.userID = Users.userID
            WHERE Users.accountType IN $accountTypes
            ORDER BY Users.lastLogin IS NULL, Users.lastLogin DESC
        ";

        // the dashboard only wants a few, the management page wants them all
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
        }

        $stmt = self::getDB()->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $lecturers = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $lecturers[] = [
                'userID' => $row['userID'],
                // the edit form needs the names apart, the table shows them together
                'firstName' => $row['firstName'],
                'lastName' => $row['lastName'],
                'name' => $row['firstName'] . ' ' . $row['lastName'],
                'staffID' => $row['username'],
                'email' => $row['email'],
                'isEnabled' => (int) $row['isEnabled'] === 1,
                'lastLogin' => $row['lastLogin'],
                'role' => $row['accountType'],
                // isEmailVerified stays 0 until they've used the emailed link to set a password
                'hasPassword' => (int) $row['isEmailVerified'] === 1,
            ];
        }

        return $lecturers;
    }

    /**
     * Gets every lecturer and admin for the lecturer management page
     */
    public static function getAllLecturers(): array
    {
        return self::getRecentLecturers(null, true);
    }

    /**
     * Disable account button, the lecturer can't log in until they're enabled again
     */
    public function disableLecturer(Lecturer $lecturer): void
    {
        $this->setLecturerEnabled($lecturer, false);
    }

    /**
     * Enable account button, and the Undo after a disable
     */
    public function enableLecturer(Lecturer $lecturer): void
    {
        $this->setLecturerEnabled($lecturer, true);
    }

    /**
     * Disable and enable only differ by the 1 or 0 saved, so they share this
     */
    private function setLecturerEnabled(Lecturer $lecturer, bool $isEnabled): void
    {
        $this->checkCanManage($lecturer);

        // login already checks isEnabled through User::isActive() so this is all disabling needs
        $stmt = self::getDB()->prepare('UPDATE Users SET isEnabled = ? WHERE userID = ?');
        $stmt->execute([$isEnabled ? 1 : 0, $lecturer->getUserID()]);
    }

    /**
     * Stops an admin changing an admin account, that includes their own
     *
     * @throws \InvalidArgumentException if the account is an admin
     */
    private function checkCanManage(Lecturer $lecturer): void
    {
        // an Administrator is also a Lecturer so it gets past the type hint without this
        if ($lecturer instanceof Administrator) {
            throw new \InvalidArgumentException("Administrator accounts can't be changed on this page");
        }
    }

    /**
     * Saves the changes from the Edit Lecturer pop-up
     *
     * @param array $details firstName, lastName, staffID and email as typed in the form
     * @throws \InvalidArgumentException with a message for the page if something typed isn't allowed
     */
    public function editLecturer(Lecturer $lecturer, array $details): void
    {
        $this->checkCanManage($lecturer);

        $userID = $lecturer->getUserID();
        [$firstName, $lastName, $staffID, $email] = self::checkDetails($details, $userID);

        $db = self::getDB();

        // names are in Lecturer and the login details are in Users, both change or neither does
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE Users SET username = ?, email = ? WHERE userID = ?');
            $stmt->execute([$staffID, $email, $userID]);

            $stmt = $db->prepare('UPDATE Lecturer SET firstName = ?, lastName = ? WHERE userID = ?');
            $stmt->execute([$firstName, $lastName, $userID]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Makes a lecturer or admin account from the Add Lecturer pop up, nobody knows the password yet
     * the controller then emails them a link to set their own
     *
     * @param array $details firstName, lastName, staffID, email and role ('lecturer' or 'admin')
     * @return array{userID: string, staffID: string, email: string, firstName: string, role: string}
     * @throws \InvalidArgumentException with a message for the page if something typed isn't allowed
     */
    public function createLecturer(array $details): array
    {
        [$firstName, $lastName, $staffID, $email] = self::checkDetails($details, null);

        // only these two, anything else sent in is refused
        $role = self::readField($details, 'role');
        if ($role !== 'lecturer' && $role !== 'admin') {
            throw new \InvalidArgumentException('Choose Lecturer or Administrator');
        }

        // random password nobody ever sees, they can't log in until they use the emailed link
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $db = self::getDB();
        $userID = $db->query('SELECT UUID()')->fetchColumn();
        if (!is_string($userID) || $userID === '') {
            throw new \RuntimeException('Unable to make a user ID');
        }

        // an admin gets a Lecturer row too so they can own tests, both rows go in or neither does
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO Users (userID, username, email, password, accountType, approvalStatus, isEnabled, '
                . "isEmailVerified) VALUES (?, ?, ?, ?, ?, 'approved', 1, 0)"
            );
            $stmt->execute([$userID, $staffID, $email, $passwordHash, $role]);

            $stmt = $db->prepare('INSERT INTO Lecturer (userID, firstName, lastName) VALUES (?, ?, ?)');
            $stmt->execute([$userID, $firstName, $lastName]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // two admins adding the same person at once both pass the check above, so the unique keys stop the second
            if ($e instanceof \PDOException && (string) $e->getCode() === '23000') {
                throw new \InvalidArgumentException('An account with this staff ID or email address already exists');
            }
            throw $e;
        }

        return [
            'userID' => $userID,
            'staffID' => $staffID,
            'email' => $email,
            'firstName' => $firstName,
            'role' => $role,
        ];
    }

    /**
     * Resend link button for someone who hasn't set a password yet, links only last 24 hours
     * works on admin rows too or a new admin who lost the email would be stuck
     *
     * @return array{token: string, staffID: string, email: string, firstName: string, role: string}
     * @throws \InvalidArgumentException if they've already set a password or a link went out under a minute ago
     */
    public function makeSetupLink(Lecturer $lecturer): array
    {
        if ($lecturer->isEmailVerified()) {
            throw new \InvalidArgumentException('This account already has a password');
        }

        $userID = $lecturer->getUserID();

        // stops the button being pressed over and over and filling their inbox
        if (!PasswordSetup::canResend($userID)) {
            throw new \InvalidArgumentException('A link was sent less than a minute ago, try again shortly');
        }

        $stmt = self::getDB()->prepare(
            'SELECT Users.email, Lecturer.firstName FROM Users JOIN Lecturer ON Lecturer.userID = Users.userID '
            . 'WHERE Users.userID = ?'
        );
        $stmt->execute([$userID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('The lecturer details could not be read');
        }

        // a new link replaces the old one, so an old email stops working
        return [
            'token' => PasswordSetup::createToken($userID),
            'staffID' => $lecturer->getUsername(),
            'email' => $row['email'],
            'firstName' => $row['firstName'],
            'role' => $lecturer->getAccountType(),
        ];
    }

    /**
     * add and edit both use this so the rules are only written once
     *
     * @param string|null $userID the account being edited, null when adding (there's no one to leave out)
     * @return array{0: string, 1: string, 2: string, 3: string} first name, last name, staff ID, email (lower case)
     * @throws \InvalidArgumentException with a message for the page
     */
    private static function checkDetails(array $details, ?string $userID): array
    {
        $firstName = self::readField($details, 'firstName');
        $lastName = self::readField($details, 'lastName');
        $staffID = self::readField($details, 'staffID');
        $email = strtolower(self::readField($details, 'email'));

        if (!Validation::isValidName($firstName)) {
            throw new \InvalidArgumentException('Enter a first name using letters, spaces, hyphens or apostrophes');
        }
        if (!Validation::isValidName($lastName)) {
            throw new \InvalidArgumentException('Enter a last name using letters, spaces, hyphens or apostrophes');
        }
        if (!Validation::isValidStaffId($staffID)) {
            throw new \InvalidArgumentException('Staff ID must be 3 to 8 letters or numbers, like L1001');
        }
        if (!Validation::isValidEmail($email)) {
            throw new \InvalidArgumentException('Enter an email address in the correct format, like name@example.com');
        }

        if (self::isTaken('username', $staffID, $userID)) {
            throw new \InvalidArgumentException('An account with this staff ID already exists');
        }
        if (self::isTaken('email', $email, $userID)) {
            throw new \InvalidArgumentException('An account with this email address already exists');
        }

        return [$firstName, $lastName, $staffID, $email];
    }

    /**
     * True if someone else already has that staff ID or email
     * when editing their own row is left out, so saving without changing the staff ID or email still works
     *
     * @param string $column only ever 'username' or 'email' from this class, never something typed in
     */
    private static function isTaken(string $column, string $value, ?string $userID): bool
    {
        // adding has no userID to leave out so that part of the query is skipped
        // putting '' in its place made the check find nothing, so a used staff ID got through
        if ($userID === null) {
            $stmt = self::getDB()->prepare("SELECT 1 FROM Users WHERE $column = ? LIMIT 1");
            $stmt->execute([$value]);
        } else {
            $stmt = self::getDB()->prepare("SELECT 1 FROM Users WHERE $column = ? AND userID <> ? LIMIT 1");
            $stmt->execute([$value, $userID]);
        }

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Gets one text box from the form with the spaces trimmed off, missing or not text gives ''
     */
    private static function readField(array $details, string $key): string
    {
        if (!isset($details[$key]) || !is_string($details[$key])) {
            return '';
        }

        return trim($details[$key]);
    }

    /**
     * Delete button, removes the lecturer's account but keeps their tests
     */
    public function deleteLecturer(Lecturer $lecturer): void
    {
        $this->checkCanManage($lecturer);

        $db = self::getDB();
        $lecturerID = $lecturer->getUserID();

        // a test has to belong to a real lecturer so the admin doing the delete takes theirs over
        // nothing makes sure an admin has a Lecturer row, without one the database refuses the move
        $stmt = $db->prepare('SELECT 1 FROM Lecturer WHERE userID = ?');
        $stmt->execute([$this->getUserID()]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                "Your account has no lecturer profile, so this lecturer's tests can't be moved to you"
            );
        }

        // both changes happen or neither does
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE Tests SET createdBy = ? WHERE createdBy = ?');
            $stmt->execute([$this->getUserID(), $lecturerID]);

            // the Lecturer row and their LecturerSubjects rows go with the Users row (cascade in the schema)
            $stmt = $db->prepare('DELETE FROM Users WHERE userID = ?');
            $stmt->execute([$lecturerID]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
