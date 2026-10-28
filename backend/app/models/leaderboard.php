<?php

namespace App\Models;

class Leaderboard extends Model
{
    // How many students the leaderboard shows.
    public const SIZE = 5;

    /**
     * Returns the active subjects in the student's cohort, for the leaderboard filter.
     */
    public static function getSubjectsForStudent(string $userID): array
    {
        $stmt = self::getDB()->prepare(
            "SELECT s.subjectID, s.subjectName
            FROM Subjects AS s
            INNER JOIN Students AS student ON student.cohortID = s.cohortID
            WHERE student.userID = ?
                AND s.isActive = 1
            ORDER BY s.subjectName"
        );
        $stmt->execute([$userID]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Returns the top students in the given student's cohort.
     *
     * With no subject this is the overall leaderboard, ranked by each student's running points
     * total. With a subject, students are ranked by the points they earned on that subject's
     * tests. Only students who have completed at least one test (in the subject) are included,
     * so the table isn't filled with zero scores. Equal points share a rank.
     *
     * Other students' IDs and surnames aren't sent to the browser, only a first name and initial.
     */
    public static function getTopStudents(string $userID, ?string $subjectID = null): array
    {
        // the overall total is the one completedTest keeps on Students.points
        $points = $subjectID === null ? 'MAX(student.points)' : 'SUM(ct.pointsEarned)';
        $subjectFilter = $subjectID === null ? '' : 'AND t.subjectID = ?';

        $stmt = self::getDB()->prepare(
            "SELECT
                student.userID,
                student.firstName,
                student.lastName,
                $points AS points,
                COUNT(ct.completedID) AS testsTaken,
                AVG(ct.percentageCorrect) AS averageScore
            FROM CompletedTests AS ct
            INNER JOIN Tests AS t ON t.testID = ct.testID
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Students AS student ON student.userID = ct.userID
            INNER JOIN Users AS u ON u.userID = student.userID
            WHERE student.cohortID = (SELECT cohortID FROM Students WHERE userID = ?)
                AND s.cohortID = student.cohortID
                AND u.isEnabled = 1
                AND u.approvalStatus = 'approved'
                $subjectFilter
            GROUP BY student.userID, student.firstName, student.lastName
            ORDER BY points DESC, averageScore DESC, student.firstName, student.lastName
            LIMIT " . self::SIZE
        );
        $stmt->execute($subjectID === null ? [$userID] : [$userID, $subjectID]);

        $leaders = [];
        $rank = 0;
        $previousPoints = null;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $index => $row) {
            $rowPoints = (int) $row['points'];
            if ($rowPoints !== $previousPoints) {
                $rank = $index + 1;
                $previousPoints = $rowPoints;
            }

            $leaders[] = [
                'rank' => $rank,
                'name' => self::displayName($row['firstName'], $row['lastName']),
                'isCurrentStudent' => $row['userID'] === $userID,
                'testsTaken' => (int) $row['testsTaken'],
                'averageScore' => round((float) $row['averageScore'], 1),
                'points' => $rowPoints,
            ];
        }

        return $leaders;
    }

    /**
     * First name and surname initial, i.e. "Alex B.".
     */
    private static function displayName(string $firstName, string $lastName): string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        return $lastName === '' ? $firstName : $firstName . ' ' . mb_strtoupper(mb_substr($lastName, 0, 1)) . '.';
    }
}
