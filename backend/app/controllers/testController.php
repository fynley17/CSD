<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Test;
use App\Models\User;

class TestController extends Controller
{
    public function getAvailableTests(): void
    {
        Authentication::requireRole(['student']);

        try {
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

            $this->jsonResponse(Test::getAvailableForStudents($userID));
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Loading available tests failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));
            $this->jsonResponse(['error' => 'Unable to load tests. Please try again later.'], 500);
        }
    }
}
