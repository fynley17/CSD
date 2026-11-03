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
     * Sends the cohort back, as its students and subjects change with it
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

            $this->jsonResponse(Cohort::findForManagement($cohortID));
        } catch (\Throwable $e) {
            error_log('Updating the cohort status failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to update the cohort'], 500);
        }
    }

    /**
     * Adds subjects to a cohort, the JSON body is {"subjectIDs": ["...", "..."]}
     * Sends the cohort back so the page can show its new subjects
     */
    public function assignSubjects(string $cohortID): void
    {
        Authentication::requireRole(['admin']);

        $data = json_decode(file_get_contents('php://input'), true);
        $subjectIDs = is_array($data) ? ($data['subjectIDs'] ?? null) : null;
        if (!self::validSubjectIDs($subjectIDs)) {
            $this->jsonResponse(['error' => 'Choose at least one subject'], 400);
        }

        try {
            if (!Cohort::assignSubjects($cohortID, array_values(array_unique($subjectIDs)))) {
                $this->jsonResponse(['error' => 'Cohort not found'], 404);
            }

            $this->jsonResponse(Cohort::findForManagement($cohortID));
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Assigning subjects failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to assign the subjects'], 500);
        }
    }

    /**
     * Takes a subject out of a cohort, its tests and past results are kept
     * Sends the cohort back so the page can update its list
     */
    public function removeSubject(string $cohortID, string $subjectID): void
    {
        Authentication::requireRole(['admin']);

        try {
            if (!Cohort::removeSubject($cohortID, $subjectID)) {
                $this->jsonResponse(['error' => 'That subject is not in this cohort'], 404);
            }

            $this->jsonResponse(Cohort::findForManagement($cohortID));
        } catch (\Throwable $e) {
            error_log('Removing a subject failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to remove the subject'], 500);
        }
    }

    /**
     * True for a list of at least one ID, each the same shape the routes accept
     * public and static so a unit test can hand it made up input
     */
    public static function validSubjectIDs(mixed $subjectIDs): bool
    {
        if (!is_array($subjectIDs) || $subjectIDs === [] || !array_is_list($subjectIDs)) {
            return false;
        }

        foreach ($subjectIDs as $subjectID) {
            if (!is_string($subjectID) || preg_match('/^[0-9a-zA-Z-]+$/D', $subjectID) !== 1) {
                return false;
            }
        }

        return true;
    }
}
