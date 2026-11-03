<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Helpers\Mailer;
use App\Models\EmailVerification;
use App\Models\Student;

class StudentController extends Controller
{
    public function getAllStudents(): void
    {
        Authentication::requireRole(['lecturer', 'admin']);
        $this->jsonResponse(Student::getAll());
    }

    /** Returns recent student activity for the lecturer dashboard. */
    public function getRecentStudentActivity(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $this->jsonResponse(Student::getRecentActivity());
        } catch (\Throwable $e) {
            error_log('Loading recent student activity failed: ' . $e->getMessage());
            $this->jsonResponse([
                'error' => 'Unable to retrieve recent student activity.',
            ], 500);
        }
    }

    /** Creates an active student account on behalf of a lecturer. */
    public function createStudent(): void
    {
        Authentication::requireRole(['lecturer']);
        $this->requireCsrf();

        try {
            $data = $this->readJsonBody();
            $result = Student::createByLecturer($data);

            $emailSent = Mailer::sendStudentAccountDetails(
                $result['email'],
                $result['firstName'],
                $result['username'],
                $result['temporaryPassword']
            );

            $this->jsonResponse([
                'message' => $emailSent
                    ? 'Student account created successfully. Login details have been emailed.'
                    : 'Student account created successfully, but the email could not be sent.',
                'emailSent' => $emailSent,
                'student' => [
                    'userID' => $result['userID'],
                    'username' => $result['username'],
                    'email' => $result['email'],
                    'firstName' => $result['firstName'],
                    'lastName' => $result['lastName'],
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf('Lecturer student creation failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse([
                'error' => 'Unable to create the student account. Please try again later.',
            ], 500);
        }
    }

    /** Enables or disables a student account. */
    public function updateStudentStatus(string $userID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);
        $this->requireCsrf();

        try {
            $userID = $this->readUserID(['userID' => $userID]);
            $data = $this->readJsonBody();

            if (!array_key_exists('isEnabled', $data) || !is_bool($data['isEnabled'])) {
                throw new \InvalidArgumentException('Please provide a valid account status.');
            }

            if (!Student::setEnabled($userID, $data['isEnabled'])) {
                $this->jsonResponse(['error' => 'Student account not found.'], 404);
                return;
            }

            $this->jsonResponse([
                'message' => $data['isEnabled']
                    ? 'Student account enabled successfully.'
                    : 'Student account disabled successfully.',
                'isEnabled' => $data['isEnabled'],
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf('Updating student status failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse(['error' => 'Unable to update the student account status.'], 500);
        }
    }

    /** Approves a pending self-registration application. */
    public function approveStudent(string $userID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);
        $this->requireCsrf();

        try {
            $userID = $this->readUserID(['userID' => $userID]);

            if (!Student::approveApplication($userID)) {
                $this->jsonResponse(['error' => 'Pending student application not found.'], 404);
                return;
            }

            $this->jsonResponse(['message' => 'Student application approved successfully.']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf('Approving student application failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse(['error' => 'Unable to approve the student application.'], 500);
        }
    }

    /** Declines and removes a pending student application. */
    public function declineStudent(string $userID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);
        $this->requireCsrf();

        try {
            $userID = $this->readUserID(['userID' => $userID]);

            if (!Student::declineApplication($userID)) {
                $this->jsonResponse(['error' => 'Pending student application not found.'], 404);
                return;
            }

            $this->jsonResponse(['message' => 'Student application declined successfully.']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf('Declining student application failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse(['error' => 'Unable to decline the student application.'], 500);
        }
    }

    /** Updates an existing student's details. */
    public function updateStudent(string $userID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);
        $this->requireCsrf();

        try {
            $userID = $this->readUserID(['userID' => $userID]);
            $data = $this->readJsonBody();
            Student::updateDetails($userID, $data);
            $this->jsonResponse(['message' => 'Student details updated successfully.']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\OutOfBoundsException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            error_log(sprintf('Updating student details failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse(['error' => 'Unable to update the student details.'], 500);
        }
    }

    /** Permanently deletes a student account and its dependent records. */
    public function deleteStudent(string $userID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);
        $this->requireCsrf();

        try {
            $userID = $this->readUserID(['userID' => $userID]);

            if (!Student::deleteStudent($userID)) {
                $this->jsonResponse(['error' => 'Student account not found.'], 404);
                return;
            }

            $this->jsonResponse(['message' => 'Student account deleted successfully.']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf('Deleting student failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse(['error' => 'Unable to delete the student account.'], 500);
        }
    }

    /** Handles existing student self-registration; this workflow is unchanged. */
    public function registerStudent(): void
    {
        try {
            $data = $this->readJsonBody();
            $result = Student::register($data);

            if (!$result['success']) {
                $this->jsonResponse(['error' => 'Unable to register the student. Please try again.'], 400);
                return;
            }

            $token = EmailVerification::createToken($result['userID']);
            $emailSent = Mailer::sendVerificationEmail($result['email'], $token);
            Authentication::savePendingVerification($result['userID']);

            $this->jsonResponse([
                'message' => 'Registration successful. Check your email to verify your address.',
                'emailSent' => $emailSent,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf('Student registration failed (%s): %s', get_class($e), $e->getMessage()));
            $this->jsonResponse(['error' => 'An unexpected error occurred. Please try again later.'], 500);
        }
    }

    private function requireCsrf(): void
    {
        $authentication = new Authentication();
        $authentication->requireCsrfToken();
    }

    /** @return array<string, mixed> */
    private function readJsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Please provide valid request details.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function readUserID(array $data): string
    {
        if (empty($data['userID']) || !is_string($data['userID'])) {
            throw new \InvalidArgumentException('Please provide a valid student ID.');
        }

        return $data['userID'];
    }
}
