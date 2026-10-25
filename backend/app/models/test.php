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
            $test['timeLimit'] = $test['timeLimit'] === null ? null : (int) $test['timeLimit'];
            $test['questionCount'] = (int) $test['questionCount'];
        }
        unset($test);

        return $tests;
    }

    /**
     * Returns one test with its questions and answer options, ready to be taken.
     *
     * isCorrect is never selected here, so the correct answers can't be read in the
     * browser while the test is running. They're only sent back once it's marked.
     *
     * @return array|null null if the test isn't in the student's cohort or has no questions
     */
    public static function getForStudent(string $testID, string $userID): ?array
    {
        $db = self::getDB();

        // Same cohort check as getAvailableForStudents(), so a student can't open
        // another cohort's test by typing its ID into the URL.
        $stmt = $db->prepare(
            "SELECT t.testID, t.testName, t.testDescription, t.timeLimit, s.subjectName
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Students AS student ON student.cohortID = s.cohortID
            WHERE t.testID = ? AND student.userID = ?"
        );
        $stmt->execute([$testID, $userID]);
        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($test === false) {
            return null;
        }

        $questions = self::getQuestions($testID, false);
        if (count($questions) === 0) {
            return null;
        }

        $test['timeLimit'] = $test['timeLimit'] === null ? null : (int) $test['timeLimit'];
        $test['questionCount'] = count($questions);
        $test['questions'] = $questions;

        return $test;
    }

    /**
     * Returns a test's questions in the lecturer's order, each with its answer options.
     *
     * @param bool $includeCorrect true only when marking, adds isCorrect to every answer
     */
    public static function getQuestions(string $testID, bool $includeCorrect): array
    {
        $db = self::getDB();

        $stmt = $db->prepare(
            "SELECT q.questionID, q.question
            FROM TestQuestions AS tq
            INNER JOIN Questions AS q ON q.questionID = tq.questionID
            WHERE tq.testID = ?
            ORDER BY tq.questionOrder"
        );
        $stmt->execute([$testID]);
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // One query for every answer in the test rather than one per question.
        $stmt = $db->prepare(
            "SELECT a.answerID, a.questionID, a.answerText, a.isCorrect
            FROM Answers AS a
            INNER JOIN TestQuestions AS tq ON tq.questionID = a.questionID
            WHERE tq.testID = ?
            ORDER BY a.answerID"
        );
        $stmt->execute([$testID]);

        $answersByQuestion = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $answer) {
            $option = [
                'answerID' => $answer['answerID'],
                'answerText' => $answer['answerText'],
            ];
            if ($includeCorrect) {
                $option['isCorrect'] = (bool) $answer['isCorrect'];
            }
            $answersByQuestion[$answer['questionID']][] = $option;
        }

        foreach ($questions as &$question) {
            $question['answers'] = $answersByQuestion[$question['questionID']] ?? [];
        }
        unset($question);

        return $questions;
    }
}
