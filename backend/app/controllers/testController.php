<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Models\CompletedTest;
use App\Models\Test;
use App\Models\User;

class TestController extends Controller
{
    private const TEST_NOT_FOUND = 'This test is not available. Go back to your tests and choose another';

    /**
     * Loads the tests available to the current student.
     */
    public function getAvailableTests(): void
    {
        $userID = $this->requireActiveStudent();

        try {
            // Delegate the actual filtering to the model.
            $this->jsonResponse(Test::getAvailableForStudents($userID));
        } catch (\Throwable $e) {
            // Log the exception and return a generic error to the client.
            $this->logError('Loading available tests', $e);
            $this->jsonResponse(['error' => 'Unable to load tests. Please try again later.'], 500);
        }
    }

    /**
     * Starts an attempt: draws the test's questions at random from its pool and sends them
     * to the testing page, without the correct answers. Starting again draws a new set.
     */
    public function startTest(string $testID): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $test = Test::getForStudent($testID, $userID);
            if ($test === null) {
                $this->jsonResponse(['error' => self::TEST_NOT_FOUND], 404);
            }

            // remember the drawn questions so the submission is marked against this set
            Authentication::saveTestAttempt($testID, array_column($test['questions'], 'questionID'));

            $this->jsonResponse($test);
        } catch (\Throwable $e) {
            $this->logError('Loading a test', $e);
            $this->jsonResponse(['error' => 'Unable to load this test. Please try again later.'], 500);
        }
    }

    /**
     * Marks the student's answers, saves the attempt and returns the results with the correct answers.
     * Only the questions drawn when the test was started are marked.
     * Expects {"answers": {"<questionID>": "<answerID>" or null}}.
     */
    public function submitTest(string $testID): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data) || !isset($data['answers']) || !is_array($data['answers'])) {
                throw new \InvalidArgumentException('Your answers could not be read. Please try again.');
            }

            $questionIDs = Authentication::getTestAttempt($testID);
            if ($questionIDs === null) {
                throw new \InvalidArgumentException('This attempt could not be found. Please start the test again.');
            }

            $result = CompletedTest::submit($userID, $testID, $questionIDs, $data['answers']);
            if ($result === null) {
                $this->jsonResponse(['error' => self::TEST_NOT_FOUND], 404);
            }

            Authentication::clearTestAttempt($testID);

            $this->jsonResponse($result, 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError('Saving a completed test', $e);
            $this->jsonResponse(['error' => 'Unable to save your test. Please try again.'], 500);
        }
    }

    /**
     * Stops the request unless a student is logged in and their account is still active.
     *
     * @return string the logged in student's user ID
     */
    private function requireActiveStudent(): string
    {
        // Ensure the caller is a student before returning any test data.
        Authentication::requireRole(['student']);

        $userID = null;
        $user = null;
        try {
            // Check the current session and confirm the account is still active.
            $userID = Authentication::getLoggedInUserID();
            $user = $userID === null ? null : User::findById($userID);
        } catch (\Throwable $e) {
            $this->logError('Checking the student session', $e);
            $this->jsonResponse(['error' => 'Something went wrong. Please try again later.'], 500);
        }

        if (
            $user === null
            || !$user->isActive()
            || $user->getAccountType() !== 'student'
        ) {
            Authentication::endSession();
            $this->jsonResponse(['error' => 'Your session has ended. Go to the login page to continue'], 401);

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

        return $userID;
    }

    /**
     * Logs the real error for developers, the browser only gets a generic message.
     */
    private function logError(string $action, \Throwable $e): void
    {
        error_log(sprintf('%s failed (%s): %s', $action, get_class($e), $e->getMessage()));
    }
}
