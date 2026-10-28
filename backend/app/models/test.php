<?php

namespace App\Models;

class Test extends Model
{
    // Matches the Tests.questionCount default, used if a test somehow has no count set.
    private const DEFAULT_QUESTIONS_PER_ATTEMPT = 10;

    // Number of questions in test t's pool: every question that belongs to its subject.
    private const POOL_SIZE = "(
                    SELECT COUNT(*)
                    FROM Questions AS pool
                    WHERE pool.subjectID = t.subjectID
                )";

    // True once the student (the ?) has got full marks on test t. They can still retake it to
    // practise, but it no longer earns points, so it can't be farmed for the leaderboard.
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
     * The query filters by cohort and only includes tests whose subject has
     * questions, so empty or irrelevant assessments are not shown. Tests the
     * student already has full marks on stay listed, with earnsPoints false.
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
                " . self::POOL_SIZE . " AS questionCount,
                s.subjectName,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', l.firstName, l.lastName)), ''),
                    'Unknown lecturer'
                ) AS createdByName,
                t.questionCount AS questionsPerAttempt,
                NOT " . self::FULL_MARKS_ATTEMPT . " AS earnsPoints
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Lecturer AS l ON l.userID = t.createdBy
            WHERE EXISTS (
                SELECT 1
                FROM Students AS student
                WHERE student.userID = ?
                    AND student.cohortID = s.cohortID
            )
            AND " . self::POOL_SIZE . " > 0
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
            $test['earnsPoints'] = (bool) $test['earnsPoints'];
        }

        unset($test);

        return $tests;
    }

    /**
     * Returns all tests for the lecturer dashboard.
     *
     * Includes each test's subject, how many questions an attempt asks and its time limit.
     */
    public static function getAllForLecturer(): array
    {
        $sql = "
            SELECT
                t.testID,
                t.testName,
                s.subjectName,
                " . self::POOL_SIZE . " AS questionCount,
                t.questionCount AS questionsPerAttempt,
                t.timeLimit
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            ORDER BY t.testName
        ";

        $stmt = self::getDB()->query($sql);
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Convert numeric fields to the types expected by the frontend.
        foreach ($tests as &$test) {
            $test['questionCount'] = self::attemptSize($test['questionsPerAttempt'], (int) $test['questionCount']);
            unset($test['questionsPerAttempt']);

            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];
        }

        unset($test);

        return $tests;
    }

    /**
     * Starts an attempt: returns the test with questions drawn at random from its pool,
     * each with its answer options, ready to be taken.
     *
     * The pool is every question that belongs to the test's subject, and the test's
     * questionCount says how many are drawn, so each attempt gets its own set of questions.
     *
     * isCorrect is never selected here, so the correct answers can't be read in the
     * browser while the test is running. They're only sent back once it's marked.
     *
     * @return array|null null if the test isn't in the student's cohort or has no questions.
     *                    earnsPoints is false once the student has full marks on it.
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
     * Returns the test's details if the student can take it, null if they can't.
     * earnsPoints is false once the student has full marks on it.
     */
    public static function findForStudent(string $testID, string $userID): ?array
    {
        // Same checks as getAvailableForStudents(), so a student can't open another
        // cohort's test, or one whose subject has no questions, by typing its ID into the URL.
        $stmt = self::getDB()->prepare(
            "SELECT t.testID, t.testName, t.testDescription, t.timeLimit,
                t.questionCount AS questionsPerAttempt, s.subjectName,
                NOT " . self::FULL_MARKS_ATTEMPT . " AS earnsPoints
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Students AS student ON student.cohortID = s.cohortID
            WHERE t.testID = ? AND student.userID = ?
            AND " . self::POOL_SIZE . " > 0"
        );
        // the first ? is the full marks check in the SELECT
        $stmt->execute([$userID, $testID, $userID]);
        $test = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($test === false) {
            return null;
        }

        $test['earnsPoints'] = (bool) $test['earnsPoints'];

        return $test;
    }

    /**
     * How many questions one attempt gets: the test's questionCount, but never more than
     * the pool holds. The pool should be bigger than the count, or every student gets the same set.
     */
    private static function attemptSize(int|string|null $questionsPerAttempt, int $poolSize): int
    {
        $count = (int) $questionsPerAttempt;
        if ($count <= 0) {
            $count = self::DEFAULT_QUESTIONS_PER_ATTEMPT;
        }

        return min($count, $poolSize);
    }

    /**
     * Returns the test's question pool, every question in its subject, each with its answer options.
     *
     * @param bool $includeCorrect true only when marking, adds isCorrect to every answer
     * @param string[]|null $questionIDs only these questions, in this order (the ones drawn for an
     *                                   attempt). Any that aren't in the test's subject are left out.
     */
    public static function getQuestions(string $testID, bool $includeCorrect, ?array $questionIDs = null): array
    {
        $db = self::getDB();

        $stmt = $db->prepare(
            "SELECT q.questionID, q.question
            FROM Tests AS t
            INNER JOIN Questions AS q ON q.subjectID = t.subjectID
            WHERE t.testID = ?
            ORDER BY q.questionID"
        );
        $stmt->execute([$testID]);
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // One query for every answer in the test rather than one per question.
        $stmt = $db->prepare(
            "SELECT a.answerID, a.questionID, a.answerText, a.isCorrect
            FROM Tests AS t
            INNER JOIN Questions AS q ON q.subjectID = t.subjectID
            INNER JOIN Answers AS a ON a.questionID = q.questionID
            WHERE t.testID = ?
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
