<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Question;

class QuestionController extends Controller
{
    public function getAllForLecturer(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $questions = Question::getAllForLecturer();
            $this->jsonResponse($questions);
        } catch (\Throwable $e) {
            error_log(
                'Loading lecturer questions failed: ' . $e->getMessage()
            );

            $this->jsonResponse(
                ['error' => 'Unable to load questions. Please try again later.'],
                500
            );
        }
    }

    /**
     * Creates a new question and its answer options.
     */
    public function createQuestion(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $data = $this->getQuestionRequestData();

            $questionID = Question::create(
                $data['subjectID'],
                $data['question'],
                $data['answers']
            );

            $this->jsonResponse([
                'message' => 'Question created successfully.',
                'questionID' => $questionID
            ], 201);

        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(
                'Creating question failed: ' . $e->getMessage()
            );

            $this->jsonResponse(
                ['error' => 'Unable to create question. Please try again later.'],
                500
            );
        }
    }

    /**
     * Updates an existing question and its answer options.
     */
    public function updateQuestion(string $questionID): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $data = $this->getQuestionRequestData();

            $updated = Question::update(
                $questionID,
                $data['subjectID'],
                $data['question'],
                $data['answers']
            );

            if (!$updated) {
                $this->jsonResponse(['error' => 'Question not found.'], 404);
            }

            $this->jsonResponse([
                'message' => 'Question updated successfully.'
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(
                'Updating question failed: ' . $e->getMessage()
            );

            $this->jsonResponse(
                ['error' => 'Unable to update question. Please try again later.'],
                500
            );
        }
    }

    /**
     * Deletes an existing question.
     */
    public function deleteQuestion(string $questionID): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $deleted = Question::delete($questionID);

            if (!$deleted) {
                $this->jsonResponse(['error' => 'Question not found.'], 404);
            }

            $this->jsonResponse([
                'message' => 'Question deleted successfully.'
            ]);

        } catch (\Throwable $e) {
            error_log(
                'Deleting question failed: ' . $e->getMessage()
            );

            $this->jsonResponse(
                ['error' => 'Unable to delete question. Please try again later.'],
                500
            );
        }
    }

    /**
     * Reads and validates the incoming question request.
     */
    private function getQuestionRequestData(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'Invalid request data.'
            );
        }

        if (
            !isset($data['subjectID'])
            || !is_string($data['subjectID'])
            || trim($data['subjectID']) === ''
        ) {
            throw new \InvalidArgumentException(
                'A valid subject must be selected.'
            );
        }

        if (
            !isset($data['question'])
            || !is_string($data['question'])
            || trim($data['question']) === ''
        ) {
            throw new \InvalidArgumentException(
                'Question text cannot be empty.'
            );
        }

        if (!isset($data['answers']) || !is_array($data['answers'])) {
            throw new \InvalidArgumentException(
                'Four answer options are required.'
            );
        }

        return $data;
    }
}
