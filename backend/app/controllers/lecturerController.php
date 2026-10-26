<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Lecturer;
use App\Models\Student;

class LecturerController extends Controller
{
    /**
     * GET /api/lecturer/dashboard-summary
     * Returns the summary counts for an authenticated lecturer.
     */
    public function getDashboardSummary(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $summary = Lecturer::getDashboardSummary();

            $this->jsonResponse($summary);
        } catch (\Throwable $e) {
            error_log(
                'Loading lecturer dashboard summary failed: '
                . $e->getMessage()
            );

            $this->jsonResponse([
                'error' => 'Unable to load dashboard summary. Please try again later.',
            ], 500);
        }
    }
    public static function getRecentStudentActivity(): void
    {
        // Only allow lecturers to access dashboard activity.
        Authentication::requireRole(['lecturer']);

        header('Content-Type: application/json');

        try {
            $students = Student::getRecentActivity();

            echo json_encode($students);
        } catch (\Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'error' => 'Unable to retrieve recent student activity.'
            ]);
        }
    }
}
