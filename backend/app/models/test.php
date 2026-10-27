<?php

namespace App\Models;

class Test extends Model
{
    /**
     * Returns the tests a student can view and start.
     *
     * The query filters by cohort and only includes tests with assigned
     * questions, so empty or irrelevant assessments are not shown.
     */
    public static function getAvailableForStudents(string $userID): array
    {
        // Keep the result set to the student's cohort and only tests that are
        // ready to be attempted.
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
                FROM Students AS student
                WHERE student.userID = ?
                    AND student.cohortID = s.cohortID
            )
            AND EXISTS (
                SELECT 1
                FROM TestQuestions AS tq
                WHERE tq.testID = t.testID
            )
            ORDER BY t.dueDate IS NULL, t.dueDate, t.testName"
        );

        $stmt->execute([$userID]);

        // Normalise the SQL output to the types the front end expects.
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tests as &$test) {
            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];

            $test['questionCount'] = (int) $test['questionCount'];
        }

        unset($test);

        return $tests;
    }

    /**
     * Returns all tests for the lecturer dashboard.
     *
     * Includes each test's subject, question count and time limit.
     */
    public static function getAllForLecturer(): array
    {
        $sql = "
            SELECT
                t.testID,
                t.testName,
                s.subjectName,
                (
                    SELECT COUNT(*)
                    FROM TestQuestions AS tq
                    WHERE tq.testID = t.testID
                ) AS questionCount,
                t.timeLimit
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            ORDER BY t.testName
        ";

        $stmt = self::getDB()->query($sql);
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Convert numeric fields to the types expected by the frontend.
        foreach ($tests as &$test) {
            $test['questionCount'] = (int) $test['questionCount'];

            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];
        }

        unset($test);

        return $tests;
    }
}
