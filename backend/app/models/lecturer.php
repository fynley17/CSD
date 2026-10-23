<?php

namespace App\Models;

/**
 * Lecturer, a kind of User who looks after tests, questions and students.
 * Everything for logging in comes from User.
 */
class Lecturer extends User
{
    /**
     * Gets the summary counts shown on the lecturer dashboard.
     *
     * @return array<string, int>
     */
    public static function getDashboardSummary(): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM Students) AS totalStudents,
                (
                    SELECT COUNT(*)
                    FROM Users
                    WHERE accountType = 'student'
                      AND approvalStatus = 'pending'
                ) AS pendingApprovals,
                (SELECT COUNT(*) FROM CompletedTests) AS completedTestAttempts,
                (
                    SELECT COUNT(*)
                    FROM Users
                    WHERE isEnabled = 0
                ) AS disabledAccounts
        ";

        $stmt = self::getDB()->query($sql);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to retrieve lecturer dashboard summary.'
            );
        }

        return [
            'totalStudents' => (int) $row['totalStudents'],
            'pendingApprovals' => (int) $row['pendingApprovals'],
            'completedTestAttempts' => (int) $row['completedTestAttempts'],
            'disabledAccounts' => (int) $row['disabledAccounts'],
        ];
    }
}
