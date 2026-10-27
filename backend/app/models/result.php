<?php

namespace App\Models;

class Result extends Model
{
    /**
     * Returns the tests a student has completed, newest first.
     *
     * The question count and score are read from the completed attempt, so
     * the result still matches what the student sat if the test is changed
     * later.
     */
    public static function getCompletedForStudent(string $userID): array
    {
        $stmt = self::getDB()->prepare(
            "SELECT
                ct.completedID,
                ct.testID,
                t.testName,
                COALESCE(NULLIF(TRIM(t.testDescription), ''), 'No description provided.') AS testDescription,
                s.subjectName,
                ct.completedDate,
                ct.questionCount,
                ct.pointsEarned,
                ct.percentageCorrect
            FROM CompletedTests AS ct
            INNER JOIN Tests AS t ON t.testID = ct.testID
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            WHERE ct.userID = ?
            ORDER BY ct.completedDate DESC, t.testName"
        );
        $stmt->execute([$userID]);

        // Normalise the SQL output to the types the front end expects.
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($results as &$result) {
            $result['questionCount'] = (int) $result['questionCount'];
            $result['pointsEarned'] = (int) $result['pointsEarned'];
            $result['percentageCorrect'] = (float) $result['percentageCorrect'];
        }
        unset($result);

        return $results;
    }
}
