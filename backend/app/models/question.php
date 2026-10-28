<?php

namespace App\Models;

class Question extends Model
{
    public static function getAllForLecturer(): array
    {
        $db = self::getDB();

        // Use aggregation syntax supported by the active database.
        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $answerSql = "
                SELECT
                    questionID,
                    GROUP_CONCAT(answerText, ' | ') AS answers,
                    GROUP_CONCAT(
                        CASE
                            WHEN isCorrect = 1 THEN answerText
                            ELSE NULL
                        END,
                        ' | '
                    ) AS correctAnswer
                FROM (
                    SELECT questionID, answerText, isCorrect
                    FROM Answers
                    ORDER BY questionID, answerText
                ) AS ordered_answers
                GROUP BY questionID
            ";
        } else {
            $answerSql = "
                SELECT
                    questionID,
                    GROUP_CONCAT(
                        answerText
                        ORDER BY answerText
                        SEPARATOR ' | '
                    ) AS answers,
                    GROUP_CONCAT(
                        CASE
                            WHEN isCorrect = 1 THEN answerText
                            ELSE NULL
                        END
                        ORDER BY answerText
                        SEPARATOR ' | '
                    ) AS correctAnswer
                FROM Answers
                GROUP BY questionID
            ";
        }

        $sql = "
            SELECT
                q.questionID,
                q.question,
                COALESCE(a.answers, '') AS answers,
                COALESCE(a.correctAnswer, '') AS correctAnswer,
                s.subjectName,
                q.percentageCorrect
            FROM Questions AS q
            INNER JOIN Subjects AS s
                ON s.subjectID = q.subjectID
            LEFT JOIN (
                {$answerSql}
            ) AS a
                ON a.questionID = q.questionID
            ORDER BY q.question
        ";

        $stmt = $db->query($sql);
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($questions as &$question) {
            $question['percentageCorrect'] =
                (int) $question['percentageCorrect'];
        }

        unset($question);

        return $questions;
    }
}
