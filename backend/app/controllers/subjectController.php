<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Subject;

/**
 * Handles the subject requests, admins only
 */
class SubjectController extends Controller
{
    /**
     * Sends every subject to the subjects table on the admin dashboard
     */
    public function getAllSubjects(): void
    {
        // 401 if not logged in, 403 if not an admin
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Subject::getAll());
        } catch (\Throwable $e) {
            // real error goes in the log, the browser just gets a plain message
            error_log('Loading the subjects failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the subjects'], 500);
        }
    }
}
