<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Result;
use App\Models\User;

class ResultController extends Controller
{
    /**
     * Loads the tests the current student has completed.
     */
    public function getCompletedTests(): void
    {
        // Ensure the caller is a student before returning any result data.
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
                $this->jsonResponse(['error' => 'Your session has ended. Go to the login page to continue'], 401);
            }

            $this->jsonResponse(Result::getCompletedForStudent($userID));
        } catch (\Throwable $e) {
            // Log the exception and return a generic error to the client.
            error_log(sprintf(
                'Loading completed tests failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));
            $this->jsonResponse(['error' => 'Unable to load completed tests. Please try again later.'], 500);
        }
    }
}
