<?php

namespace App\Models;

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
     * @return list<array{userID: string, firstName: string, lastName: string, name: string, staffID: string,
     *     email: string, isEnabled: bool, lastLogin: ?string}>
     */
    public static function getRecentLecturers(?int $limit = 5): array
    {
        // only lecturer accounts, same as the cards. Anyone who's never logged in goes to the bottom
        // the staff ID is the username, that's what lecturers log in with
        $sql = "
            SELECT Users.userID, Lecturer.firstName, Lecturer.lastName, Users.username, Users.email,
                Users.isEnabled, Users.lastLogin
            FROM Users
            JOIN Lecturer ON Lecturer.userID = Users.userID
            WHERE Users.accountType = 'lecturer'
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
            ];
        }

        return $lecturers;
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

}
