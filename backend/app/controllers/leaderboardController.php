<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\Leaderboard;
use App\Models\User;

class LeaderboardController extends Controller
{
    /**
     * Loads the top students in the current student's cohort, overall or for one subject,
     * along with the cohort's subjects for the filter.
     *
     * @param string|null $subjectID null for the overall leaderboard
     */
    public function getLeaderboard(?string $subjectID = null): void
    {
        // Ensure the caller is a student before returning any leaderboard data.
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

            $subjects = Leaderboard::getSubjectsForStudent($userID);

            // Only subjects in the student's own cohort can be picked.
            if ($subjectID !== null && !in_array($subjectID, array_column($subjects, 'subjectID'), true)) {
                $this->jsonResponse(['error' => 'This subject is not available. Choose another subject'], 404);
            }

            $this->jsonResponse([
                'subjects' => $subjects,
                'students' => Leaderboard::getTopStudents($userID, $subjectID),
            ]);
        } catch (\Throwable $e) {
            // Log the exception and return a generic error to the client.
            error_log(sprintf(
                'Loading the leaderboard failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));
            $this->jsonResponse(['error' => 'Unable to load the leaderboard. Please try again later.'], 500);
        }
    }
}
