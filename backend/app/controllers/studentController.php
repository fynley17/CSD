<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Helpers\Mailer;
use App\Models\EmailVerification;
use App\Models\Student;

class StudentController extends Controller
{
    public function getAllStudents()
    {
        $students = Student::getAll();
        $this->jsonResponse($students);
    }

    public function registerStudent()
    {
        try {
            // Decode the JSON payload sent by the registration form before passing it to the model.
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                throw new \InvalidArgumentException('Please check your registration details and try again.');
            }

            $result = Student::register($data);

            if ($result['success']) {
                // The welcome email waits until the address is verified, so only the link is sent now.
                // The account is already saved, so a failed email doesn't fail the registration,
                // the check your email page lets them send it again.
                $token = EmailVerification::createToken($result['userID']);
                $emailSent = Mailer::sendVerificationEmail($result['email'], $token);

                // Lets this browser resend the link or fix a mistyped address without logging in.
                Authentication::savePendingVerification($result['userID']);

                $this->jsonResponse([
                    'message' => 'Registration successful. Check your email to verify your address.',
                    'emailSent' => $emailSent,
                ], 201);
            } else {
                $this->jsonResponse(['error' => 'Unable to register the student. Please try again.'], 400);
            }
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Student registration failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));
            $this->jsonResponse(['error' => 'An unexpected error occurred. Please try again later.'], 500);
        }
    }
}
