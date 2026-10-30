<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Administrator;

/**
 * Answers the admin dashboard's requests, only someone logged in as an admin gets an answer
 */
class AdministratorController extends Controller
{
    /**
     * GET /api/admin/dashboard-summary, the totals shown on the four admin dashboard cards
     */
    public function getDashboardSummary(): void
    {
        // turns away anyone who isn't logged in as an admin before anything gets counted
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Administrator::getDashboardSummary());
        } catch (\Throwable $e) {
            // the error goes in the server log, the page only gets a plain message
            error_log('Loading the admin dashboard totals failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the dashboard totals'], 500);
        }
    }


    /**
     * GET /api/admin/recent-lecturers, the lecturers shown in the table on the admin dashboard
     */
    public function getRecentLecturers(): void
    {
        // same check as the cards, only admins get the list
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Administrator::getRecentLecturers());
        } catch (\Throwable $e) {
            error_log('Loading the admin dashboard lecturers failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the lecturers'], 500);
        }
    }
}
