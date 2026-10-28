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

    /**
     * Returns one completed attempt with every question, the answer the student picked
     * and the correct answer, used to export the attempt as a PDF.
     *
     * The student's own answers are read from StudentAnswers, so the review matches the
     * questions they were actually asked rather than the test's current pool.
     *
     * @return array|null null if the attempt doesn't exist or belongs to another student
     */
    public static function getReviewForStudent(string $completedID, string $userID): ?array
    {
        $db = self::getDB();

        // userID in the WHERE so a student can't load someone else's attempt by guessing its ID
        $stmt = $db->prepare(
            "SELECT
                ct.completedID,
                ct.testID,
                t.testName,
                s.subjectName,
                st.firstName,
                st.lastName,
                ct.completedDate,
                ct.questionCount,
                ct.pointsEarned,
                ct.percentageCorrect
            FROM CompletedTests AS ct
            INNER JOIN Tests AS t ON t.testID = ct.testID
            INNER JOIN Subjects AS s ON s.subjectID = t.subjectID
            INNER JOIN Students AS st ON st.userID = ct.userID
            WHERE ct.completedID = ? AND ct.userID = ?"
        );
        $stmt->execute([$completedID, $userID]);
        $attempt = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($attempt === false) {
            return null;
        }

        $stmt = $db->prepare(
            "SELECT sa.questionID, q.question, sa.selectedAnswerID, sa.pointsAwarded
            FROM StudentAnswers AS sa
            INNER JOIN Questions AS q ON q.questionID = sa.questionID
            WHERE sa.completedID = ?
            ORDER BY q.question"
        );
        $stmt->execute([$completedID]);
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // One query for every answer option in the attempt rather than one per question.
        $stmt = $db->prepare(
            "SELECT a.answerID, a.questionID, a.answerText, a.isCorrect
            FROM StudentAnswers AS sa
            INNER JOIN Answers AS a ON a.questionID = sa.questionID
            WHERE sa.completedID = ?
            ORDER BY a.answerID"
        );
        $stmt->execute([$completedID]);

        $answersByQuestion = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $answer) {
            $answersByQuestion[$answer['questionID']][] = [
                'answerID' => $answer['answerID'],
                'answerText' => $answer['answerText'],
                'isCorrect' => (bool) $answer['isCorrect'],
            ];
        }

        $correctCount = 0;
        foreach ($questions as &$question) {
            $question['answers'] = $answersByQuestion[$question['questionID']] ?? [];
            $question['pointsAwarded'] = (int) $question['pointsAwarded'];

            // marked against the saved answer, an unanswered question is never correct
            $question['isCorrect'] = false;
            foreach ($question['answers'] as $answer) {
                if ($answer['answerID'] === $question['selectedAnswerID'] && $answer['isCorrect']) {
                    $question['isCorrect'] = true;
                    $correctCount++;
                }
            }
        }
        unset($question);

        return [
            'completedID' => $attempt['completedID'],
            'testID' => $attempt['testID'],
            'testName' => $attempt['testName'],
            'subjectName' => $attempt['subjectName'],
            'studentName' => trim($attempt['firstName'] . ' ' . $attempt['lastName']),
            'completedDate' => $attempt['completedDate'],
            'questionCount' => (int) $attempt['questionCount'],
            'correctCount' => $correctCount,
            'pointsEarned' => (int) $attempt['pointsEarned'],
            'percentageCorrect' => (float) $attempt['percentageCorrect'],
            'questions' => $questions,
        ];
    }
}
