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
}
