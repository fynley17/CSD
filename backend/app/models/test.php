<?php

namespace App\Models;

class Test extends Model
{
    private const DEFAULT_QUESTIONS_PER_ATTEMPT = 10;

    private const POOL_SIZE = "(
        SELECT COUNT(*)
        FROM Questions AS pool
        WHERE pool.subjectID = t.subjectID
    )";

    private const FULL_MARKS_ATTEMPT = "EXISTS (
        SELECT 1
        FROM CompletedTests AS ct
        WHERE ct.testID = t.testID
            AND ct.userID = ?
            AND ct.percentageCorrect >= 100
    )";

    /**
     * Returns the tests a student can view and start.
     */
    public static function getAvailableForStudents(string $userID): array
    {
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

        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tests as &$test) {
            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];

            $test['questionCount'] = self::attemptSize(
                $test['questionsPerAttempt'],
                (int) $test['questionCount']
            );

            unset($test['questionsPerAttempt']);

            $test['earnsPoints'] = (bool) $test['earnsPoints'];
        }

        unset($test);

        return $tests;
    }

    /**
     * Returns all tests for the lecturer dashboard.
     */
    public static function getAllForLecturer(): array
    {
        $sql = "
            SELECT
                t.testID,
                t.testName,
                t.subjectID,
                t.testDescription,
                t.dueDate,
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

        foreach ($tests as &$test) {
            $test['questionCount'] = self::attemptSize(
                $test['questionsPerAttempt'],
                (int) $test['questionCount']
            );

            unset($test['questionsPerAttempt']);

            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];
        }

        unset($test);

        return $tests;
    }

    /**
     * Returns all active subjects and their cohort IDs for the lecturer test form.
     */
    public static function getActiveSubjects(): array
    {
        $stmt = self::getDB()->prepare(
            'SELECT
                subjectID,
                subjectName,
                cohortID
             FROM Subjects
             WHERE isActive = 1
             ORDER BY subjectName'
        );

        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Creates a new test.
     */
    public static function create(
        string $testName,
        string $subjectID,
        ?string $testDescription,
        ?int $timeLimit,
        ?string $dueDate,
        int $questionCount,
        string $createdBy
    ): string {
        self::validateTestData(
            $testName,
            $subjectID,
            $timeLimit,
            $dueDate,
            $questionCount
        );

        $db = self::getDB();
        $testID = self::newUuid();

        $stmt = $db->prepare(
            'INSERT INTO Tests (
                testID,
                testName,
                subjectID,
                testDescription,
                timeLimit,
                dueDate,
                questionCount,
                createdBy
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $testID,
            trim($testName),
            $subjectID,
            $testDescription,
            $timeLimit,
            $dueDate,
            $questionCount,
            $createdBy
        ]);

        return $testID;
    }

    /**
     * Updates an existing test without changing historical results.
     */
    public static function update(
        string $testID,
        string $testName,
        string $subjectID,
        ?string $testDescription,
        ?int $timeLimit,
        ?string $dueDate,
        int $questionCount
    ): bool {
        self::validateTestData(
            $testName,
            $subjectID,
            $timeLimit,
            $dueDate,
            $questionCount
        );

        $stmt = self::getDB()->prepare(
            'UPDATE Tests
             SET testName = ?,
                 subjectID = ?,
                 testDescription = ?,
                 timeLimit = ?,
                 dueDate = ?,
                 questionCount = ?
             WHERE testID = ?'
        );

        $stmt->execute([
            trim($testName),
            $subjectID,
            $testDescription,
            $timeLimit,
            $dueDate,
            $questionCount,
            $testID
        ]);

        return $stmt->rowCount() > 0 || self::exists($testID);
    }

    /**
     * Deletes a test only when it has no completed attempts.
     */
    public static function delete(string $testID): bool
    {
        $db = self::getDB();

        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'SELECT testID
                 FROM Tests
                 WHERE testID = ?
                 FOR UPDATE'
            );

            $stmt->execute([$testID]);

            if ($stmt->fetchColumn() === false) {
                $db->rollBack();
                return false;
            }

            $stmt = $db->prepare(
                'SELECT COUNT(*)
                 FROM CompletedTests
                 WHERE testID = ?'
            );

            $stmt->execute([$testID]);

            if ((int) $stmt->fetchColumn() > 0) {
                throw new \InvalidArgumentException(
                    'This test cannot be deleted because students have already completed it.'
                );
            }

            $stmt = $db->prepare(
                'DELETE FROM Tests WHERE testID = ?'
            );

            $stmt->execute([$testID]);

            $db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Validates test details and subject selection.
     */
    private static function validateTestData(
        string $testName,
        string $subjectID,
        ?int $timeLimit,
        ?string $dueDate,
        int $questionCount
    ): void {
        if (trim($testName) === '' || strlen(trim($testName)) > 30) {
            throw new \InvalidArgumentException(
                'Test name must contain between 1 and 30 characters.'
            );
        }

        if ($timeLimit !== null && $timeLimit < 1) {
            throw new \InvalidArgumentException(
                'Time limit must be greater than zero.'
            );
        }

        if ($questionCount < 1) {
            throw new \InvalidArgumentException(
                'At least one question must be selected.'
            );
        }

        if ($dueDate !== null) {
            $date = \DateTime::createFromFormat('!Y-m-d', $dueDate);

            if (!$date || $date->format('Y-m-d') !== $dueDate) {
                throw new \InvalidArgumentException(
                    'Please provide a valid due date.'
                );
            }
        }

        $stmt = self::getDB()->prepare(
            'SELECT subjectID
             FROM Subjects
             WHERE subjectID = ?
                AND isActive = 1'
        );

        $stmt->execute([$subjectID]);

        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                'Please select a valid active subject.'
            );
        }
    }

    /**
     * Checks whether a test exists.
     */
    private static function exists(string $testID): bool
    {
        $stmt = self::getDB()->prepare(
            'SELECT COUNT(*)
             FROM Tests
             WHERE testID = ?'
        );

        $stmt->execute([$testID]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Generates a UUID.
     */
    private static function newUuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }

    /**
     * Starts an attempt and returns randomly selected questions.
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

        shuffle($pool);

        $questions = array_slice(
            $pool,
            0,
            self::attemptSize($test['questionsPerAttempt'], count($pool))
        );

        unset($test['questionsPerAttempt']);

        $test['timeLimit'] = $test['timeLimit'] === null
            ? null
            : (int) $test['timeLimit'];

        $test['questionCount'] = count($questions);
        $test['questions'] = $questions;

        return $test;
    }

    /**
     * Returns the test's details if the student can take it.
     */
    public static function findForStudent(string $testID, string $userID): ?array
    {
        $stmt = self::getDB()->prepare(
            "SELECT
                t.testID,
                t.testName,
                t.testDescription,
                t.timeLimit,
                t.questionCount AS questionsPerAttempt,
                s.subjectName,
                NOT " . self::FULL_MARKS_ATTEMPT . " AS earnsPoints
             FROM Tests AS t
             INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
             INNER JOIN Students AS student ON student.cohortID = s.cohortID
             WHERE t.testID = ?
                AND student.userID = ?
                AND " . self::POOL_SIZE . " > 0"
        );

        $stmt->execute([$userID, $testID, $userID]);

        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($test === false) {
            return null;
        }

        $test['earnsPoints'] = (bool) $test['earnsPoints'];

        return $test;
    }

    /**
     * Calculates how many questions one attempt receives.
     */
    private static function attemptSize(
        int|string|null $questionsPerAttempt,
        int $poolSize
    ): int {
        $count = (int) $questionsPerAttempt;

        if ($count <= 0) {
            $count = self::DEFAULT_QUESTIONS_PER_ATTEMPT;
        }

        return min($count, $poolSize);
    }

    /**
     * Returns the question pool for a test.
     */
    public static function getQuestions(
        string $testID,
        bool $includeCorrect,
        ?array $questionIDs = null
    ): array {
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
                'answerText' => $answer['answerText']
            ];

            if ($includeCorrect) {
                $option['isCorrect'] = (bool) $answer['isCorrect'];
            }

            $answersByQuestion[$answer['questionID']][] = $option;
        }

        foreach ($questions as &$question) {
            $question['answers'] =
                $answersByQuestion[$question['questionID']] ?? [];
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
