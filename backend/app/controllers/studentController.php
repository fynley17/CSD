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

        $students = Student::getAll();

        $this->jsonResponse($students);
    }

    /**
     * Returns recent student activity for the lecturer dashboard.
     */
    public function getRecentStudentActivity(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $students = Student::getRecentActivity();

            $this->jsonResponse($students);
        } catch (\Throwable $e) {
            error_log(
                'Loading recent student activity failed: '
                . $e->getMessage()
            );

            $this->jsonResponse([
                'error' => 'Unable to retrieve recent student activity.',
            ], 500);
        }
    }

    /**
     * Creates an active student account on behalf of a lecturer.
     * Sends the student their temporary login details by email.
     */
    public function createStudent(): void
    {
        Authentication::requireRole(['lecturer']);

        $authentication = new Authentication();
        $authentication->requireCsrfToken();

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!is_array($data)) {
                throw new \InvalidArgumentException(
                    'Please check the student details and try again.'
                );
            }

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
            $this->jsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Lecturer student creation failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));

            $this->jsonResponse([
                'error' => 'Unable to create the student account. Please try again later.',
            ], 500);
        }
    }

    /**
     * Enables or disables a student account.
     */
    public function updateStudentStatus(): void
    {
        Authentication::requireRole(['lecturer', 'admin']);

        $authentication = new Authentication();
        $authentication->requireCsrfToken();

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !is_array($data)
                || empty($data['userID'])
                || !is_string($data['userID'])
                || !array_key_exists('isEnabled', $data)
                || !is_bool($data['isEnabled'])
            ) {
                throw new \InvalidArgumentException(
                    'Please provide a valid student ID and account status.'
                );
            }

            $updated = Student::setEnabled(
                $data['userID'],
                $data['isEnabled']
            );

            if (!$updated) {
                $this->jsonResponse([
                    'error' => 'Student account not found or status unchanged.',
                ], 404);

                return;
            }

            $this->jsonResponse([
                'message' => $data['isEnabled']
                    ? 'Student account enabled successfully.'
                    : 'Student account disabled successfully.',
                'isEnabled' => $data['isEnabled'],
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Updating student status failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));

            $this->jsonResponse([
                'error' => 'Unable to update the student account status.',
            ], 500);
        }
    }

    /**
     * Handles existing student self-registration.
     * This workflow is deliberately unchanged.
     */
    public function registerStudent(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!is_array($data)) {
                throw new \InvalidArgumentException(
                    'Please check your registration details and try again.'
                );
            }

            $result = Student::register($data);

            if ($result['success']) {
                $token = EmailVerification::createToken(
                    $result['userID']
                );

                $emailSent = Mailer::sendVerificationEmail(
                    $result['email'],
                    $token
                );

                Authentication::savePendingVerification(
                    $result['userID']
                );

                $this->jsonResponse([
                    'message' => 'Registration successful. Check your email to verify your address.',
                    'emailSent' => $emailSent,
                ], 201);
            } else {
                $this->jsonResponse([
                    'error' => 'Unable to register the student. Please try again.',
                ], 400);
            }
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Student registration failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));

            $this->jsonResponse([
                'error' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}
