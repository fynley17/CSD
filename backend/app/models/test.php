<?php

namespace App\Models;

class Test extends Model
{
    // Matches the Tests.questionCount default.
    private const DEFAULT_QUESTIONS_PER_ATTEMPT = 10;

    // Number of questions in a test's subject pool.
    private const POOL_SIZE = "(
        SELECT COUNT(*)
        FROM Questions AS pool
        WHERE pool.subjectID = t.subjectID
    )";

    // Prevents students earning leaderboard points by repeatedly retaking a test
    // after achieving full marks.
    private const FULL_MARKS_ATTEMPT = "EXISTS (
        SELECT 1
        FROM CompletedTests AS ct
        WHERE ct.testID = t.testID
            AND ct.userID = ?
            AND ct.percentageCorrect >= 100
    )";

    // The due date is the last day points can be earned, so a test is overdue from the day after.
    // The date compared against comes from PHP's clock, so it doesn't matter what timezone the
    // database is set to. Overdue tests can still be taken as practice, they just don't earn points.
    private const OVERDUE = "(t.dueDate IS NOT NULL AND t.dueDate < ?)";

    /**
     * Today's date, the one due dates are checked against.
     */
    public static function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Returns the tests a student can view and start.
     *
     * Only includes tests whose subject is active and has questions,
     * and whose subject belongs to the student's cohort.
     * Tests still open come first, soonest due first, then ones with no due date,
     * then overdue ones, which can only be taken as practice.
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
                " . self::FULL_MARKS_ATTEMPT . " AS hasFullMarks,
                " . self::OVERDUE . " AS isOverdue
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Lecturer AS l ON l.userID = t.createdBy
            WHERE EXISTS (
                SELECT 1
                FROM Students AS student
                WHERE student.userID = ?
                    AND student.cohortID = s.cohortID
            )
            AND s.isActive = 1
            AND t.isActive = 1
            AND " . self::POOL_SIZE . " > 0
            ORDER BY isOverdue, t.dueDate IS NULL, t.dueDate, t.testName"
        );

        $stmt->execute([$userID, self::today(), $userID]);

        // Normalise SQL output to the types expected by the frontend.
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tests as &$test) {
            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];

            // Display the number of questions the student will receive.
            $test['questionCount'] = self::attemptSize(
                $test['questionsPerAttempt'],
                (int) $test['questionCount']
            );

            unset($test['questionsPerAttempt']);

            self::setEarnsPoints($test);
        }

        unset($test);

        return $tests;
    }

    /**
     * Returns all tests for the lecturer/admin management dashboard.
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
                " . self::POOL_SIZE . " AS questionPoolSize,
                t.questionCount AS questionsPerAttempt,
                t.timeLimit,
                t.isActive
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            ORDER BY t.testName
        ";

        $stmt = self::getDB()->query($sql);
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tests as &$test) {
            $test['questionCount'] = (int) $test['questionsPerAttempt'];
            unset($test['questionsPerAttempt']);

            $test['timeLimit'] = $test['timeLimit'] === null
                ? null
                : (int) $test['timeLimit'];

            $test['questionPoolSize'] = (int) $test['questionPoolSize'];

            $test['isActive'] = (bool) $test['isActive'];

            // so lecturers can see which tests have closed and extend or archive them
            $test['isOverdue'] = $test['dueDate'] !== null
                && substr((string) $test['dueDate'], 0, 10) < self::today();
        }

        unset($test);

        return $tests;
    }

    /**
     * Returns active subjects and their associated cohorts
     * for test creation and editing.
     */
    public static function getActiveSubjects(): array
    {
        $stmt = self::getDB()->prepare(
            "SELECT
                s.subjectID,
                s.subjectName,
                s.cohortID,
                c.cohortName,
                (
                    SELECT COUNT(*)
                    FROM Questions AS pool
                    WHERE pool.subjectID = s.subjectID
                ) AS questionPoolSize
            FROM Subjects AS s
            INNER JOIN Cohorts AS c ON c.cohortID = s.cohortID
            WHERE s.isActive = 1
            ORDER BY s.subjectName"
        );

        $stmt->execute();

        // the test form warns when a subject has no questions, as students can't see its tests
        $subjects = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($subjects as &$subject) {
            $subject['questionPoolSize'] = (int) $subject['questionPoolSize'];
        }
        unset($subject);

        return $subjects;
    }

    /**
     * Creates a test.
     * createdBy remains a lecturer ID because the existing
     * Tests foreign key references Lecturer.userID.
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
            $testDescription,
            $timeLimit,
            $dueDate,
            $questionCount
        );

        $testID = self::newUuid();

        $stmt = self::getDB()->prepare(
            "INSERT INTO Tests
                (testID, testName, subjectID, testDescription, timeLimit, dueDate, questionCount, createdBy)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $testID,
            trim($testName),
            $subjectID,
            $testDescription === null || trim($testDescription) === ''
                ? null
                : trim($testDescription),
            $timeLimit,
            $dueDate === '' ? null : $dueDate,
            $questionCount,
            $createdBy
        ]);

        return $testID;
    }

    /**
     * Updates a test.
     * Its subject cannot be changed after attempts exist,
     * because that would change the question pool represented by old results.
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
        $db = self::getDB();

        $existing = $db->prepare(
            "SELECT subjectID FROM Tests WHERE testID = ?"
        );

        $existing->execute([$testID]);

        $currentTest = $existing->fetch(\PDO::FETCH_ASSOC);

        if ($currentTest === false) {
            return false;
        }

        // Validate the test details without checking subject activity.
        self::validateTestData(
            $testName,
            $subjectID,
            $testDescription,
            $timeLimit,
            $dueDate,
            $questionCount,
            false
        );

        // Only require an active subject when changing the test's subject.
        if ((string) $currentTest['subjectID'] !== $subjectID) {
            $subject = $db->prepare(
                "SELECT subjectID
            FROM Subjects
            WHERE subjectID = ? AND isActive = 1"
            );

            $subject->execute([$subjectID]);

            if ($subject->fetchColumn() === false) {
                throw new \InvalidArgumentException(
                    'Please select an active subject.'
                );
            }

            $attempts = $db->prepare(
                "SELECT COUNT(*) FROM CompletedTests WHERE testID = ?"
            );

            $attempts->execute([$testID]);

            if ((int) $attempts->fetchColumn() > 0) {
                throw new \InvalidArgumentException(
                    'The subject cannot be changed because students have completed this test.'
                );
            }
        }

        $stmt = $db->prepare(
            "UPDATE Tests
        SET testName = ?, subjectID = ?, testDescription = ?,
            timeLimit = ?, dueDate = ?, questionCount = ?
        WHERE testID = ?"
        );

        $stmt->execute([
            trim($testName),
            $subjectID,
            $testDescription === null || trim($testDescription) === ''
                ? null
                : trim($testDescription),
            $timeLimit,
            $dueDate === '' ? null : $dueDate,
            $questionCount,
            $testID
        ]);

        // Return true even when the submitted values were unchanged.
        return true;
    }

    /**
     * Archives a test if it has completed attempts.
     * Permanently deletes a test if it has no completed attempts.
     *
     * @return string|false 'archived', 'deleted', or false if not found.
     */
    public static function delete(string $testID): string|false
    {
        $db = self::getDB();

        try {
            $db->beginTransaction();

            $lockSql = "SELECT testID FROM Tests WHERE testID = ?";

            if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lockSql .= " FOR UPDATE";
            }

            $lock = $db->prepare($lockSql);
            $lock->execute([$testID]);

            if ($lock->fetchColumn() === false) {
                $db->rollBack();
                return false;
            }

            $attempts = $db->prepare(
                "SELECT COUNT(*) FROM CompletedTests WHERE testID = ?"
            );
            $attempts->execute([$testID]);

            $hasAttempts = (int) $attempts->fetchColumn() > 0;

            if ($hasAttempts) {
                $archiveTest = $db->prepare(
                    "UPDATE Tests SET isActive = 0 WHERE testID = ?"
                );
                $archiveTest->execute([$testID]);

                $db->commit();

                return 'archived';
            }

            $deleteTest = $db->prepare(
                "DELETE FROM Tests WHERE testID = ?"
            );
            $deleteTest->execute([$testID]);

            $db->commit();

            return 'deleted';
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Validates test details before they are inserted or updated.
     */
    private static function validateTestData(
        string $testName,
        string $subjectID,
        ?string $testDescription,
        ?int $timeLimit,
        ?string $dueDate,
        int $questionCount,
        bool $validateActiveSubject = true
    ): void {
        $testName = trim($testName);

        if ($testName === '' || mb_strlen($testName) > 30) {
            throw new \InvalidArgumentException(
                'Test name must be between 1 and 30 characters.'
            );
        }

        if (
            $testDescription !== null
            && mb_strlen(trim($testDescription)) > 100
        ) {
            throw new \InvalidArgumentException(
                'Test description cannot exceed 100 characters.'
            );
        }

        if ($timeLimit !== null && ($timeLimit < 1 || $timeLimit > 999)) {
            throw new \InvalidArgumentException(
                'Time limit must be between 1 and 999 minutes or left blank.'
            );
        }

        if ($questionCount < 1 || $questionCount > 999) {
            throw new \InvalidArgumentException(
                'Question count must be between 1 and 999.'
            );
        }

        if ($dueDate !== null && $dueDate !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);

            if ($date === false || $date->format('Y-m-d') !== $dueDate) {
                throw new \InvalidArgumentException(
                    'Due date must be a valid date in YYYY-MM-DD format.'
                );
            }
        }

        if ($validateActiveSubject) {
            $subject = self::getDB()->prepare(
                "SELECT subjectID
            FROM Subjects
            WHERE subjectID = ? AND isActive = 1"
            );

            $subject->execute([$subjectID]);

            if ($subject->fetchColumn() === false) {
                throw new \InvalidArgumentException(
                    'Please select an active subject.'
                );
            }
        }
    }

    /**
     * Generates a UUID v4 for a new test.
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
     * Starts an attempt: returns the test with questions drawn at random
     * from its pool, each with its answer options.
     *
     * Correct answers are never sent to the browser while the test is running.
     */
    public static function getForStudent(
        string $testID,
        string $userID
    ): ?array {
        $test = self::findForStudent($testID, $userID);

        // An attempt already underway can still be handed in.
        if (
            $test === null ||
            !$test['subjectIsActive'] ||
            !$test['testIsActive']
        ) {
            return null;
        }

        $pool = self::getQuestions($testID, false);

        if (count($pool) === 0) {
            return null;
        }

        // Shuffle the pool so each question has an equal chance.
        shuffle($pool);

        $questions = array_slice(
            $pool,
            0,
            self::attemptSize(
                $test['questionsPerAttempt'],
                count($pool)
            )
        );

        unset(
            $test['questionsPerAttempt'],
            $test['subjectIsActive'],
            $test['testIsActive']
        );

        $test['timeLimit'] = $test['timeLimit'] === null
            ? null
            : (int) $test['timeLimit'];

        $test['questionCount'] = count($questions);
        $test['questions'] = $questions;

        return $test;
    }

    /**
     * Returns test details if the student is allowed to take the test.
     *
     * Checks the student's cohort against the test's subject cohort,
     * preventing students from accessing another cohort's test by changing the test ID.
     */
    public static function findForStudent(string $testID, string $userID): ?array
    {
        // Same checks as getAvailableForStudents(), so a student can't open another
        // cohort's test, or one whose subject has no questions, by typing its ID into the URL.
        return self::findTest($testID, $userID, true, self::today());
    }

    /**
     * Finds a test the student has already started, for handing it in.
     * There's no cohort check, so an attempt still counts if its subject has left their cohort since it started.
     * Only call this with an attempt from the student's session, startTest only saves one after the cohort check.
     *
     * @param string|null $startedOn the date (Y-m-d) the attempt started. The due date is checked against
     *                               this, not the hand in time, so a test started on its due date
     *                               still earns points if it's handed in after midnight. Defaults to today.
     * @return array|null null if the test doesn't exist or has no questions
     */
    public static function findStartedByStudent(string $testID, string $userID, ?string $startedOn = null): ?array
    {
        return self::findTest($testID, $userID, false, $startedOn ?? self::today());
    }

    /**
     * The shared query behind findForStudent() and findStartedByStudent()
     */
    private static function findTest(string $testID, string $userID, bool $checkCohort, string $onDate): ?array
    {
        $cohortCheck = $checkCohort
            ? "INNER JOIN Students AS student ON student.cohortID = s.cohortID AND student.userID = ?"
            : '';

        $stmt = self::getDB()->prepare(
            "SELECT
                t.testID,
                t.testName,
                t.testDescription,
                t.timeLimit,
                t.dueDate,
                t.questionCount AS questionsPerAttempt,
                s.subjectName,
                s.isActive AS subjectIsActive,
                t.isActive AS testIsActive,
                " . self::FULL_MARKS_ATTEMPT . " AS hasFullMarks,
                " . self::OVERDUE . " AS isOverdue
            FROM Tests AS t
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            $cohortCheck
            WHERE t.testID = ?
            AND " . self::POOL_SIZE . " > 0"
        );
        // the first two ? are the full marks and overdue checks in the SELECT
        $stmt->execute($checkCohort
            ? [$userID, $onDate, $userID, $testID]
            : [$userID, $onDate, $testID]);
        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($test === false) {
            return null;
        }

        self::setEarnsPoints($test);

        return $test;
    }

    /**
     * An attempt earns points unless the student already has full marks on the test
     * or the test is past its due date. Either way it can still be taken as practice.
     * Replaces the hasFullMarks column with earnsPoints, and keeps isOverdue so the
     * page can say why no points are earned.
     */
    private static function setEarnsPoints(array &$test): void
    {
        $test['isOverdue'] = (bool) $test['isOverdue'];
        $test['earnsPoints'] = !$test['hasFullMarks'] && !$test['isOverdue'];
        unset($test['hasFullMarks']);
    }

    /**
     * Calculates how many questions an attempt receives.
     * Never exceeds the available question pool.
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
     * Returns the test's question pool, with answer options.
     *
     * @param bool $includeCorrect True only when marking.
     * @param string[]|null $questionIDs Restricts results to selected questions.
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

        // Retrieve all answers in one query instead of one per question.
        $stmt = $db->prepare(
            "SELECT
                a.answerID,
                a.questionID,
                a.answerText,
                a.isCorrect
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
            $question['answers'] =
                $answersByQuestion[$question['questionID']] ?? [];
        }

        unset($question);

        if ($questionIDs === null) {
            return $questions;
        }

        $questionsByID = array_column(
            $questions,
            null,
            'questionID'
        );

        $chosen = [];

        foreach ($questionIDs as $questionID) {
            if (isset($questionsByID[$questionID])) {
                $chosen[] = $questionsByID[$questionID];
            }
        }

        return $chosen;
    }
}
