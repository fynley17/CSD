<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Test;
use App\Models\User;

class TestController extends Controller
{
    /**
     * Loads the tests available to the current student.
     */
    public function getAvailableTests(): void
    {
        // Ensure the caller is a student before returning any test data.
        Authentication::requireRole(['student']);

        try {
            // Check the current session and confirm the account is still active.
            $userID = Authentication::getLoggedInUserID();
            $user = $userID === null ? null : User::findById($userID);

            if (
                $user === null
                || !$user->isActive()
                || $user->getAccountType() !== 'student'
            ) {
                Authentication::endSession();
                $this->jsonResponse(
                    ['error' => 'Your session has ended. Go to the login page to continue'],
                    401
                );
                return;
            }

            // Delegate the actual filtering to the model.
            $this->jsonResponse(Test::getAvailableForStudents($userID));
        } catch (\Throwable $e) {
            // Log the exception and return a generic error to the client.
            error_log(sprintf(
                'Loading available tests failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));

            $this->jsonResponse(
                ['error' => 'Unable to load tests. Please try again later.'],
                500
            );
        }
    }

    /**
     * Loads all tests for the lecturer dashboard.
     */
    public function getAllForLecturer(): void
    {
        // Ensure only lecturers can access the dashboard test list.
        Authentication::requireRole(['lecturer']);

        try {
            // Retrieve all tests with their subject and question count.
            $tests = Test::getAllForLecturer();

            $this->jsonResponse($tests);
        } catch (\Throwable $e) {
            // Log the exception and return a generic error to the client.
            error_log(sprintf(
                'Loading lecturer tests failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));

            $this->jsonResponse(
                ['error' => 'Unable to load tests. Please try again later.'],
                500
            );
        }
    }
}
