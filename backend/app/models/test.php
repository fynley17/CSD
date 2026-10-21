<?php

namespace App\Models;

class Test extends Model
{
    public static function getAvailableForStudents(): array
    {
        $stmt = self::getDB()->prepare(
            "SELECT
                t.testID,
                t.testName,
                COALESCE(NULLIF(TRIM(t.testDescription), ''), 'No description provided.') AS testDescription,
                t.timeLimit,
                t.dueDate,
                (
                    SELECT COUNT(*)
                    FROM TestQuestions AS question_count
                    WHERE question_count.testID = t.testID
                ) AS questionCount,
                s.subjectName,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', l.firstName, l.lastName)), ''),
                    'Unknown lecturer'
                ) AS createdByName
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Lecturer AS l ON l.userID = t.createdBy
            WHERE EXISTS (
                SELECT 1
                FROM TestQuestions AS tq
                WHERE tq.testID = t.testID
            )
            ORDER BY t.dueDate IS NULL, t.dueDate, t.testName"
        );
        $stmt->execute();

        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($tests as &$test) {
            $test['timeLimit'] = $test['timeLimit'] === null ? null : (int) $test['timeLimit'];
            $test['questionCount'] = (int) $test['questionCount'];
        }
        unset($test);

        return $tests;
    }
}
