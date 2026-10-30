<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Subject;

/**
 * Handles requests for subjects. Only admins can use these.
 */
class SubjectController extends Controller
{
    /**
     * Loads every subject for the subjects table on the admin dashboard.
     */
    public function getAllSubjects(): void
    {
        // Only admins can see the subjects list.
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Subject::getAll());
        } catch (\Throwable $e) {
            // Log the error and send a general message back to the browser.
            error_log('Loading the subjects failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the subjects'], 500);
        }
    }
}
