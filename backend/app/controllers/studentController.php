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
        Authentication::requireRole(['lecturer']);

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
