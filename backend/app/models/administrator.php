<?php

namespace App\Models;

/**
 * Administrator, extends Lecturer because an admin can do every lecturer job as well
 * on top of that they manage subjects and lecturer accounts
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
}
