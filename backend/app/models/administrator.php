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
     * Gets the lecturers for the table on the admin dashboard, the ones who logged in most recently first
     *
     * @return list<array{name: string, staffID: string, email: string, isEnabled: bool, lastLogin: ?string}>
     */
    public static function getRecentLecturers(int $limit = 5): array
    {
        // only lecturer accounts ,like the cards - and someone who's never logged in goes to the bottom
        // the staff ID is the username, that's what lecturers log in with
        $sql = "
            SELECT Lecturer.firstName, Lecturer.lastName, Users.username, Users.email, Users.isEnabled, Users.lastLogin
            FROM Users
            JOIN Lecturer ON Lecturer.userID = Users.userID
            WHERE Users.accountType = 'lecturer'
            ORDER BY Users.lastLogin IS NULL, Users.lastLogin DESC
            LIMIT ?
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $lecturers = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $lecturers[] = [
                'name' => $row['firstName'] . ' ' . $row['lastName'],
                'staffID' => $row['username'],
                'email' => $row['email'],
                'isEnabled' => (int) $row['isEnabled'] === 1,
                'lastLogin' => $row['lastLogin'],
            ];
        }

        return $lecturers;
    }

}
