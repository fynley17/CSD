<?php

namespace App\Models;

/**
 * Marks a finished test and saves it to CompletedTests and StudentAnswers.
 * Every attempt gets its own row, so a student can retake a test and keep each result.
 */
class CompletedTest extends Model
{
    // points per question, matching the comments on the StudentAnswers and Students tables
    public const POINTS_CORRECT = 30;
    public const POINTS_INCORRECT = -10;
    public const POINTS_UNANSWERED = 0;

    /**
     * Marks the student's answers, saves the attempt and adds the points to their total.
     *
     * @param array $submittedAnswers questionID => answerID, or null for a question left unanswered
     *                                (e.g. the timer ran out). Missing questions also count as unanswered.
     * @return array|null the marked test for the results screen, null if the student can't take this test
     * @throws \InvalidArgumentException if an answer doesn't belong to its question
     */
    public static function submit(string $userID, string $testID, array $submittedAnswers): ?array
    {
        // reuse the cohort check so a student can't submit a test they couldn't open
        if (Test::getForStudent($testID, $userID) === null) {
            return null;
        }

        $questions = Test::getQuestions($testID, true);
        $markedQuestions = [];
        $correctCount = 0;
        $pointsEarned = 0;

        // Mark using the answers from the database, never anything the browser says is correct.
        foreach ($questions as $question) {
            $selectedAnswerID = $submittedAnswers[$question['questionID']] ?? null;
            $selectedAnswer = null;

            if ($selectedAnswerID !== null) {
                foreach ($question['answers'] as $answer) {
                    if ($answer['answerID'] === $selectedAnswerID) {
                        $selectedAnswer = $answer;
                    }
                }

                if ($selectedAnswer === null) {
                    throw new \InvalidArgumentException('One of your answers was not recognised. Please try again.');
                }
            }

            if ($selectedAnswer === null) {
                $pointsAwarded = self::POINTS_UNANSWERED;
            } elseif ($selectedAnswer['isCorrect']) {
                $pointsAwarded = self::POINTS_CORRECT;
                $correctCount++;
            } else {
                $pointsAwarded = self::POINTS_INCORRECT;
            }

            $pointsEarned += $pointsAwarded;
            $markedQuestions[] = [
                'questionID' => $question['questionID'],
                'question' => $question['question'],
                'selectedAnswerID' => $selectedAnswerID,
                'isCorrect' => $selectedAnswer !== null && $selectedAnswer['isCorrect'],
                'pointsAwarded' => $pointsAwarded,
                'answers' => $question['answers'],
            ];
        }

        $questionCount = count($questions);
        $percentageCorrect = round($correctCount / $questionCount * 100, 2);
        $completedID = self::newUuid();

        $db = self::getDB();
        // all or nothing, so a failure halfway can't leave a result without its answers or points
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO CompletedTests
                    (completedID, userID, testID, questionCount, pointsEarned, percentageCorrect)
                VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$completedID, $userID, $testID, $questionCount, $pointsEarned, $percentageCorrect]);

            $stmt = $db->prepare(
                'INSERT INTO StudentAnswers (completedID, questionID, selectedAnswerID, pointsAwarded)
                VALUES (?, ?, ?, ?)'
            );
            foreach ($markedQuestions as $markedQuestion) {
                $stmt->execute([
                    $completedID,
                    $markedQuestion['questionID'],
                    $markedQuestion['selectedAnswerID'],
                    $markedQuestion['pointsAwarded'],
                ]);
            }

            // Work out each question's percentageCorrect again from every answer saved for it,
            // so it's always right rather than drifting. Unanswered ones (the timer ran out
            // before the student saw them) don't count. 100.0 stops SQLite rounding down to 0 in tests.
            $stmt = $db->prepare(
                'UPDATE Questions SET percentageCorrect = COALESCE((
                    SELECT ROUND(100.0 * SUM(a.isCorrect) / COUNT(*))
                    FROM StudentAnswers AS sa
                    INNER JOIN Answers AS a ON a.answerID = sa.selectedAnswerID
                    WHERE sa.questionID = Questions.questionID
                ), 0)
                WHERE questionID = ?'
            );
            foreach ($markedQuestions as $markedQuestion) {
                $stmt->execute([$markedQuestion['questionID']]);
            }

            // the running total the leaderboard uses, it can go negative
            $stmt = $db->prepare('UPDATE Students SET points = points + ? WHERE userID = ?');
            $stmt->execute([$pointsEarned, $userID]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            'completedID' => $completedID,
            'testID' => $testID,
            'questionCount' => $questionCount,
            'correctCount' => $correctCount,
            'pointsEarned' => $pointsEarned,
            'percentageCorrect' => $percentageCorrect,
            'questions' => $markedQuestions,
        ];
    }

    /**
     * Makes a random (version 4) UUID for the new CompletedTests row.
     * Made here rather than by the database so the ID is known before the answers are saved.
     */
    private static function newUuid(): string
    {
        $bytes = random_bytes(16);
        // set the version and variant bits so it's a valid v4 UUID
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
