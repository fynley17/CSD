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

    /**
     * Creates a question and its four answer options.
     */
    public static function create(
        string $subjectID,
        string $question,
        array $answers
    ): string {
        self::validateQuestionData($question, $answers);

        $db = self::getDB();
        $questionID = self::newUuid();

        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO Questions (questionID, subjectID, question)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$questionID, $subjectID, trim($question)]);

            self::saveAnswers($db, $questionID, $answers);

            $db->commit();

            return $questionID;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Updates a question and replaces its answer options.
     */
    public static function update(
        string $questionID,
        string $subjectID,
        string $question,
        array $answers
    ): bool {
        self::validateQuestionData($question, $answers);

        $db = self::getDB();

        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE Questions
                 SET subjectID = ?, question = ?, percentageCorrect = 0
                 WHERE questionID = ?'
            );
            $stmt->execute([
                $subjectID,
                trim($question),
                $questionID
            ]);

            if ($stmt->rowCount() === 0) {
                $check = $db->prepare(
                    'SELECT questionID FROM Questions WHERE questionID = ?'
                );
                $check->execute([$questionID]);

                if ($check->fetchColumn() === false) {
                    $db->rollBack();
                    return false;
                }
            }

            $stmt = $db->prepare(
                'DELETE FROM Answers WHERE questionID = ?'
            );
            $stmt->execute([$questionID]);

            self::saveAnswers($db, $questionID, $answers);

            $db->commit();

            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Deletes a question and its answer options.
     */
    public static function delete(string $questionID): bool
    {
        $db = self::getDB();

        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'DELETE FROM Answers WHERE questionID = ?'
            );
            $stmt->execute([$questionID]);

            $stmt = $db->prepare(
                'DELETE FROM Questions WHERE questionID = ?'
            );
            $stmt->execute([$questionID]);

            $deleted = $stmt->rowCount() > 0;

            $db->commit();

            return $deleted;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Validates question text and its four answer options.
     */
    private static function validateQuestionData(
        string $question,
        array $answers
    ): void {
        if (trim($question) === '') {
            throw new \InvalidArgumentException(
                'Question text cannot be empty.'
            );
        }

        if (count($answers) !== 4) {
            throw new \InvalidArgumentException(
                'A question must have exactly four answers.'
            );
        }

        $correctCount = 0;

        foreach ($answers as $answer) {
            if (
                !is_array($answer)
                || !isset($answer['answerText'])
                || !is_string($answer['answerText'])
                || trim($answer['answerText']) === ''
                || strlen(trim($answer['answerText'])) > 255
                || !array_key_exists('isCorrect', $answer)
            ) {
                throw new \InvalidArgumentException(
                    'Each answer must have valid text and a correct-answer status.'
                );
            }

            if (!is_bool($answer['isCorrect']) && !in_array(
                $answer['isCorrect'],
                [0, 1, '0', '1'],
                true
            )) {
                throw new \InvalidArgumentException(
                    'Invalid correct-answer status.'
                );
            }

            if (filter_var($answer['isCorrect'], FILTER_VALIDATE_BOOLEAN)) {
                $correctCount++;
            }
        }

        if ($correctCount !== 1) {
            throw new \InvalidArgumentException(
                'Exactly one answer must be marked correct.'
            );
        }
    }

    /**
     * Inserts the four answer options for a question.
     */
    private static function saveAnswers(
        \PDO $db,
        string $questionID,
        array $answers
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO Answers
                (answerID, questionID, answerText, isCorrect)
             VALUES (?, ?, ?, ?)'
        );

        foreach ($answers as $answer) {
            $stmt->execute([
                self::newUuid(),
                $questionID,
                trim($answer['answerText']),
                filter_var($answer['isCorrect'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0
            ]);
        }
    }

    /**
     * Generates a version 4 UUID.
     */
    private static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($bytes), 4)
        );
    }
}
