<?php

namespace App\Models;

class Question extends Model
{
    /**
     * Returns all active subjects for the lecturer question form.
     */
    public static function getActiveSubjects(): array
    {
        $stmt = self::getDB()->prepare(
            'SELECT subjectID, subjectName
             FROM Subjects
             WHERE isActive = 1
             ORDER BY subjectName'
        );

        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Returns all questions, their answers and subject details
     * for the lecturer question management page.
     */
    public static function getAllForLecturer(): array
    {
        $db = self::getDB();

        // Retrieve questions and their associated subject details.
        $sql = "
            SELECT
                q.questionID,
                q.question,
                q.subjectID,
                s.subjectName,
                q.percentageCorrect
            FROM Questions AS q
            INNER JOIN Subjects AS s
                ON s.subjectID = q.subjectID
            ORDER BY q.question
        ";

        $stmt = $db->query($sql);
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Prepare a query to retrieve individual answers for each question.
        $answerStmt = $db->prepare(
            'SELECT answerID, answerText, isCorrect
             FROM Answers
             WHERE questionID = ?
             ORDER BY answerText'
        );

        // Attach the individual answer records to each question.
        foreach ($questions as &$question) {
            $answerStmt->execute([$question['questionID']]);

            $answers = $answerStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Convert the correct-answer value into a boolean for Vue.
            foreach ($answers as &$answer) {
                $answer['isCorrect'] = (bool) $answer['isCorrect'];
            }

            unset($answer);

            $question['answers'] = $answers;

            // Preserve the existing table display fields.
            $question['correctAnswer'] = implode(
                ' | ',
                array_column(
                    array_filter(
                        $answers,
                        fn ($answer) => $answer['isCorrect']
                    ),
                    'answerText'
                )
            );

            $question['answersText'] = implode(
                ' | ',
                array_column($answers, 'answerText')
            );

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
        self::validateSubject($subjectID);

        $db = self::getDB();
        $questionID = self::newUuid();

        $db->beginTransaction();

        try {
            // Insert the new question.
            $stmt = $db->prepare(
                'INSERT INTO Questions (questionID, subjectID, question)
                 VALUES (?, ?, ?)'
            );

            $stmt->execute([
                $questionID,
                $subjectID,
                trim($question)
            ]);

            // Insert the four associated answer options.
            self::saveAnswers($db, $questionID, $answers);

            $db->commit();

            return $questionID;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Updates a question and its answer options.
     *
     * Questions used in completed tests cannot be edited,
     * protecting historical student answers and results.
     */
    public static function update(
        string $questionID,
        string $subjectID,
        string $question,
        array $answers
    ): bool {
        self::validateQuestionData($question, $answers);
        self::validateSubject($subjectID);

        $db = self::getDB();

        $db->beginTransaction();

        try {
            // Check whether the question exists and lock it during the update.
            $stmt = $db->prepare(
                'SELECT questionID
                 FROM Questions
                 WHERE questionID = ?
                 FOR UPDATE'
            );

            $stmt->execute([$questionID]);

            if ($stmt->fetchColumn() === false) {
                $db->rollBack();
                return false;
            }

            // Prevent editing questions with historical student answers.
            if (self::hasHistoricalAnswers($db, $questionID)) {
                throw new \InvalidArgumentException(
                    'This question cannot be edited because it has already been used in a completed test.'
                );
            }

            // Update the question details.
            $stmt = $db->prepare(
                'UPDATE Questions
                 SET subjectID = ?, question = ?
                 WHERE questionID = ?'
            );

            $stmt->execute([
                $subjectID,
                trim($question),
                $questionID
            ]);

            // Retrieve the existing answer records.
            $stmt = $db->prepare(
                'SELECT answerID
                 FROM Answers
                 WHERE questionID = ?
                 ORDER BY answerText'
            );

            $stmt->execute([$questionID]);

            $existingAnswers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($existingAnswers) !== 4) {
                throw new \RuntimeException(
                    'The question does not have exactly four existing answers.'
                );
            }

            // Update the existing answer records instead of deleting them.
            // This preserves answer IDs and their database relationships.
            $updateAnswer = $db->prepare(
                'UPDATE Answers
                 SET answerText = ?, isCorrect = ?
                 WHERE answerID = ? AND questionID = ?'
            );

            foreach ($answers as $index => $answer) {
                $updateAnswer->execute([
                    trim($answer['answerText']),
                    filter_var(
                        $answer['isCorrect'],
                        FILTER_VALIDATE_BOOLEAN
                    ) ? 1 : 0,
                    $existingAnswers[$index]['answerID'],
                    $questionID
                ]);
            }

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
     * Deletes a question and its answer options.
     *
     * Questions used in completed tests cannot be deleted,
     * protecting historical student answers and results.
     */
    public static function delete(string $questionID): bool
    {
        $db = self::getDB();

        $db->beginTransaction();

        try {
            // Check whether the question exists and lock it during deletion.
            $stmt = $db->prepare(
                'SELECT questionID
                 FROM Questions
                 WHERE questionID = ?
                 FOR UPDATE'
            );

            $stmt->execute([$questionID]);

            if ($stmt->fetchColumn() === false) {
                $db->rollBack();
                return false;
            }

            // Prevent deletion if students have historical answers.
            if (self::hasHistoricalAnswers($db, $questionID)) {
                throw new \InvalidArgumentException(
                    'This question cannot be deleted because it has already been used in a completed test.'
                );
            }

            // Delete the answer options first.
            $stmt = $db->prepare(
                'DELETE FROM Answers
                 WHERE questionID = ?'
            );

            $stmt->execute([$questionID]);

            // Delete the question after its answer options.
            $stmt = $db->prepare(
                'DELETE FROM Questions
                 WHERE questionID = ?'
            );

            $stmt->execute([$questionID]);

            $deleted = $stmt->rowCount() > 0;

            $db->commit();

            return $deleted;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Checks whether a question has historical student answers.
     */
    private static function hasHistoricalAnswers(
        \PDO $db,
        string $questionID
    ): bool {
        $stmt = $db->prepare(
            'SELECT 1
             FROM StudentAnswers
             WHERE questionID = ?
             LIMIT 1'
        );

        $stmt->execute([$questionID]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Validates that the selected subject exists and is active.
     */
    private static function validateSubject(string $subjectID): void
    {
        $stmt = self::getDB()->prepare(
            'SELECT subjectID
             FROM Subjects
             WHERE subjectID = ?
             AND isActive = 1'
        );

        $stmt->execute([$subjectID]);

        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                'The selected subject is invalid or inactive.'
            );
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
                filter_var(
                    $answer['isCorrect'],
                    FILTER_VALIDATE_BOOLEAN
                ) ? 1 : 0
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
