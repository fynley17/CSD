<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Lecturer;

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
}
