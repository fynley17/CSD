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
        $authentication = new Authentication();

        // Ensure the caller is a student before returning any test data.
        $authentication->requireRole(['student']);

        try {
            // Check the current session and confirm the account is still active.
            $userID = $authentication->getLoggedInUserID();
            $user = $userID === null ? null : User::findById($userID);

            if (
                $user === null
                || !$user->isActive()
                || $user->getAccountType() !== 'student'
            ) {
                $authentication->endSession();
                $this->jsonResponse(['error' => 'Your session has ended. Go to the login page to continue'], 401);
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
            $this->jsonResponse(['error' => 'Unable to load tests. Please try again later.'], 500);
        }
    }
}
