<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Cohort;

class CohortController extends Controller
{
    public function getAllCohorts()
    {
        // Supply the cohort options displayed in the registration form.
        $cohorts = Cohort::getAll();
        $this->jsonResponse($cohorts);
    }

    /**
     * Sends every cohort with its students and subjects to the cohort management page
     */
    public function getCohortsForManagement(): void
    {
        // 401 if not logged in, 403 if not an admin
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Cohort::getAllForManagement());
        } catch (\Throwable $e) {
            // real error goes in the log, the browser just gets a plain message
            error_log('Loading the cohorts failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the cohorts'], 500);
        }
    }

    /**
     * Disables or enables a cohort, the JSON body is {"isActive": true or false}
     */
    public function updateCohortStatus(string $cohortID): void
    {
        Authentication::requireRole(['admin']);

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !is_bool($data['isActive'] ?? null)) {
            $this->jsonResponse(['error' => 'isActive must be true or false'], 400);
        }

        try {
            if (!Cohort::setActive($cohortID, $data['isActive'])) {
                $this->jsonResponse(['error' => 'Cohort not found'], 404);
            }

            $this->jsonResponse(['isActive' => $data['isActive']]);
        } catch (\Throwable $e) {
            error_log('Updating the cohort status failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to update the cohort'], 500);
        }
    }
}
