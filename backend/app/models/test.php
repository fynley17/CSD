<?php

namespace App\Models;

class Test extends Model
{
    // True once the student (the ?) has got full marks on test t. That test is then finished
    // for them, so it can't be retaken over and over to keep earning points.
    private const FULL_MARKS_ATTEMPT = "EXISTS (
                SELECT 1
                FROM CompletedTests AS ct
                WHERE ct.testID = t.testID
                    AND ct.userID = ?
                    AND ct.percentageCorrect >= 100
            )";

    /**
     * Returns the tests a student can view and start.
     *
     * The query filters by cohort and only includes tests with assigned
     * questions, so empty or irrelevant assessments are not shown. Tests the
     * student already has full marks on are left out too.
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
                ) AS createdByName,
                t.questionCount AS questionsPerAttempt
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
            AND NOT " . self::FULL_MARKS_ATTEMPT . "
            ORDER BY t.dueDate IS NULL, t.dueDate, t.testName"
        );
        $stmt->execute([$userID, $userID]);

        // Normalise the SQL output to the types the front end expects.
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($tests as &$test) {
            $test['timeLimit'] = $test['timeLimit'] === null ? null : (int) $test['timeLimit'];
            // the card shows how many questions the student will get, not the size of the pool
            $test['questionCount'] = self::attemptSize($test['questionsPerAttempt'], (int) $test['questionCount']);
            unset($test['questionsPerAttempt']);
        }
        unset($test);

        return $tests;
    }

    /**
     * Starts an attempt: returns the test with questions drawn at random from its pool,
     * each with its answer options, ready to be taken.
     *
     * The pool is every question assigned to the test in TestQuestions, and the test's
     * questionCount says how many are drawn, so each attempt gets its own set of questions.
     *
     * isCorrect is never selected here, so the correct answers can't be read in the
     * browser while the test is running. They're only sent back once it's marked.
     *
     * @return array|null null if the test isn't in the student's cohort, has no questions,
     *                    or the student already has full marks on it
     */
    public static function getForStudent(string $testID, string $userID): ?array
    {
        $test = self::findForStudent($testID, $userID);
        if ($test === null) {
            return null;
        }

        $pool = self::getQuestions($testID, false);
        if (count($pool) === 0) {
            return null;
        }

        // shuffle the whole pool and keep the first few, so every question has the same chance
        shuffle($pool);
        $questions = array_slice($pool, 0, self::attemptSize($test['questionsPerAttempt'], count($pool)));
        unset($test['questionsPerAttempt']);

        $test['timeLimit'] = $test['timeLimit'] === null ? null : (int) $test['timeLimit'];
        $test['questionCount'] = count($questions);
        $test['questions'] = $questions;

        return $test;
    }

    /**
     * True if the student can take this test right now, without drawing any questions.
     */
    public static function isAvailableToStudent(string $testID, string $userID): bool
    {
        return self::findForStudent($testID, $userID) !== null;
    }

    /**
     * Returns the test's details if the student can take it, null if they can't.
     */
    private static function findForStudent(string $testID, string $userID): ?array
    {
        // Same checks as getAvailableForStudents(), so a student can't open another
        // cohort's test, or an empty one, by typing its ID into the URL.
        $stmt = self::getDB()->prepare(
            "SELECT t.testID, t.testName, t.testDescription, t.timeLimit,
                t.questionCount AS questionsPerAttempt, s.subjectName
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Students AS student ON student.cohortID = s.cohortID
            WHERE t.testID = ? AND student.userID = ?
            AND EXISTS (
                SELECT 1
                FROM TestQuestions AS tq
                WHERE tq.testID = t.testID
            )
            AND NOT " . self::FULL_MARKS_ATTEMPT
        );
        $stmt->execute([$testID, $userID, $userID]);
        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $test === false ? null : $test;
    }

    /**
     * How many questions one attempt gets: the test's questionCount, but never more than
     * the pool holds. No questionCount set means every question in the pool.
     */
    private static function attemptSize(int|string|null $questionsPerAttempt, int $poolSize): int
    {
        if ($questionsPerAttempt === null || (int) $questionsPerAttempt <= 0) {
            return $poolSize;
        }

        return min((int) $questionsPerAttempt, $poolSize);
    }

    /**
     * Returns a test's questions in the lecturer's order, each with its answer options.
     *
     * @param bool $includeCorrect true only when marking, adds isCorrect to every answer
     * @param string[]|null $questionIDs only these questions, in this order (the ones drawn for an
     *                                   attempt). Any that aren't in the test are left out.
     */
    public static function getQuestions(string $testID, bool $includeCorrect, ?array $questionIDs = null): array
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

        if ($questionIDs === null) {
            return $questions;
        }

        $questionsByID = array_column($questions, null, 'questionID');
        $chosen = [];
        foreach ($questionIDs as $questionID) {
            if (isset($questionsByID[$questionID])) {
                $chosen[] = $questionsByID[$questionID];
            }
        }

        return $chosen;
    }
}
