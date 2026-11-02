<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Cohort;
use App\Models\Student;

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
     * Creates a cohort, the JSON body is {"cohortName": "...", "isActive": true or false}
     * Sends the new cohort back so the page can add it to the list
     */
    public function createCohort(): void
    {
        Authentication::requireRole(['admin']);

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !is_bool($data['isActive'] ?? null)) {
            $this->jsonResponse(['error' => 'isActive must be true or false'], 400);
        }

        try {
            $cohortID = Cohort::create($data['cohortName'] ?? null, $data['isActive']);

            $this->jsonResponse(Cohort::findForManagement($cohortID), 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Creating a cohort failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to create the cohort'], 500);
        }
    }

    /**
     * Saves a cohort's name and status, the JSON body is {"cohortName": "...", "isActive": true or false}
     * Sends the cohort back so the page can update it
     */
    public function updateCohort(string $cohortID): void
    {
        Authentication::requireRole(['admin']);

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !is_bool($data['isActive'] ?? null)) {
            $this->jsonResponse(['error' => 'isActive must be true or false'], 400);
        }

        try {
            if (!Cohort::update($cohortID, $data['cohortName'] ?? null, $data['isActive'])) {
                $this->jsonResponse(['error' => 'Cohort not found'], 404);
            }

            $this->jsonResponse(Cohort::findForManagement($cohortID));
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Updating a cohort failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to update the cohort'], 500);
        }
    }

    /**
     * Deletes a cohort, 409 = it still has students or subjects
     */
    public function deleteCohort(string $cohortID): void
    {
        Authentication::requireRole(['admin']);

        try {
            if (!Cohort::delete($cohortID)) {
                $this->jsonResponse(['error' => 'Cohort not found'], 404);
            }

            $this->jsonResponse(['message' => 'Cohort deleted']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            error_log('Deleting a cohort failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to delete the cohort'], 500);
        }
    }

    /**
     * Sends the students in other cohorts to the add students pop-up
     */
    public function getOtherStudents(string $cohortID): void
    {
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Cohort::getStudentsOutside($cohortID));
        } catch (\Throwable $e) {
            error_log('Loading the students for a cohort failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the students'], 500);
        }
    }

    /**
     * Moves students into this cohort, the JSON body is {"studentNumbers": ["...", "..."]}
     * Both add students and remove from cohort use this, as a student is always in exactly one cohort
     * Sends every cohort back, as the cohorts they came from have changed too
     */
    public function moveStudents(string $cohortID): void
    {
        Authentication::requireRole(['admin']);

        $data = json_decode(file_get_contents('php://input'), true);
        $studentNumbers = is_array($data) ? ($data['studentNumbers'] ?? null) : null;
        if (!self::validStudentNumbers($studentNumbers)) {
            $this->jsonResponse(['error' => 'Choose at least one student'], 400);
        }

        try {
            if (!Cohort::moveStudents($cohortID, array_values(array_unique($studentNumbers)))) {
                $this->jsonResponse(['error' => 'Cohort not found'], 404);
            }

            $this->jsonResponse(Cohort::getAllForManagement());
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Moving students failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to move the students'], 500);
        }
    }

    /**
     * True for a list of at least one student number
     * public and static so a unit test can hand it made up input
     */
    public static function validStudentNumbers(mixed $studentNumbers): bool
    {
        if (!is_array($studentNumbers) || $studentNumbers === [] || !array_is_list($studentNumbers)) {
            return false;
        }

        foreach ($studentNumbers as $studentNumber) {
            if (!Student::validUsername($studentNumber)) {
                return false;
            }
        }

        return true;
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
