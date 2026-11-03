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
     * Every subject, for the dashboard table and the management page
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

    /**
     * Lecturers for the pick list on the subject form
     */
    public function getLecturerOptions(): void
    {
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Subject::getLecturerOptions());
        } catch (\Throwable $e) {
            error_log('Loading the lecturer options failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the lecturers'], 500);
        }
    }

    /**
     * Adds a new subject, it starts off active
     */
    public function createSubject(): void
    {
        Authentication::requireRole(['admin']);
        $this->saveSubject(null);
    }

    /**
     * Changes the name, cohort and lecturers of an existing subject
     */
    public function updateSubject(string $subjectID): void
    {
        Authentication::requireRole(['admin']);
        $this->saveSubject($subjectID);
    }

    /**
     * Archive and Restore buttons, isActive false archives it and true brings it back
     */
    public function setSubjectActive(string $subjectID): void
    {
        Authentication::requireRole(['admin']);

        $data = $this->readBody();
        if (!is_bool($data['isActive'] ?? null)) {
            $this->jsonResponse(['error' => 'Choose archive or restore'], 400);
        }

        try {
            if (!Subject::setActive($subjectID, $data['isActive'])) {
                $this->jsonResponse(['error' => 'Subject not found'], 404);
            }
            $this->jsonResponse(['message' => 'Subject updated']);
        } catch (\Throwable $e) {
            error_log('Changing the subject status failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to update the subject'], 500);
        }
    }

    /**
     * Shared by create and edit, the only difference is the ID (null = new subject)
     */
    private function saveSubject(?string $subjectID): void
    {
        $data = $this->readBody();

        // the form could be faked, so check the types before they reach the model
        if (!is_string($data['subjectName'] ?? null)
            || !is_string($data['cohortID'] ?? null)
            || !is_array($data['lecturerIDs'] ?? null)) {
            $this->jsonResponse(['error' => 'The subject details are not valid'], 400);
        }

        // each lecturer ID has to be text too, a list inside the list would crash the save
        foreach ($data['lecturerIDs'] as $lecturerID) {
            if (!is_string($lecturerID)) {
                $this->jsonResponse(['error' => 'The subject details are not valid'], 400);
            }
        }

        try {
            $saved = Subject::save($subjectID, $data['subjectName'], $data['cohortID'], $data['lecturerIDs']);
            if (!$saved) {
                $this->jsonResponse(['error' => 'Subject not found'], 404);
            }
            $this->jsonResponse(['message' => 'Subject saved'], $subjectID === null ? 201 : 200);
        } catch (\InvalidArgumentException $e) {
            // form mistake, safe to send back as the message comes from Subject not the database
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Saving the subject failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to save the subject'], 500);
        }
    }

    /**
     * Sends the subjects that can be added to a cohort to the assign subjects pop-up
     */
    public function getUnassignedSubjects(): void
    {
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Subject::getUnassigned());
        } catch (\Throwable $e) {
            error_log('Loading the unassigned subjects failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the subjects'], 500);
        }
    }
}
