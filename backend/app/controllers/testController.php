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
            $this->jsonResponse(Test::getAvailableForStudents($userID));
        } catch (\Throwable $e) {
            $this->logError('Loading available tests', $e);
            $this->jsonResponse(
                ['error' => 'Unable to load tests. Please try again later.'],
                500
            );
        }
    }

    /**
     * Starts a test attempt.
     */
    public function startTest(string $testID): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $test = Test::getForStudent($testID, $userID);

            if ($test === null) {
                $this->jsonResponse(['error' => self::TEST_NOT_FOUND], 404);
                return;
            }

            Authentication::saveTestAttempt(
                $testID,
                array_column($test['questions'], 'questionID')
            );

            $this->jsonResponse($test);
        } catch (\Throwable $e) {
            $this->logError('Loading a test', $e);
            $this->jsonResponse(
                ['error' => 'Unable to load this test. Please try again later.'],
                500
            );
        }
    }

    /**
     * Marks and saves a student's completed test.
     */
    public function submitTest(string $testID): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!is_array($data) || !isset($data['answers']) || !is_array($data['answers'])) {
                throw new \InvalidArgumentException(
                    'Your answers could not be read. Please try again.'
                );
            }

            $questionIDs = Authentication::getTestAttempt($testID);

            if ($questionIDs === null) {
                throw new \InvalidArgumentException(
                    'This attempt could not be found. Please start the test again.'
                );
            }

            $result = CompletedTest::submit(
                $userID,
                $testID,
                $questionIDs,
                $data['answers']
            );

            if ($result === null) {
                $this->jsonResponse(['error' => self::TEST_NOT_FOUND], 404);
                return;
            }

            Authentication::clearTestAttempt($testID);

            $this->jsonResponse($result, 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError('Saving a completed test', $e);
            $this->jsonResponse(
                ['error' => 'Unable to save your test. Please try again.'],
                500
            );
        }
    }

    /**
     * Loads all tests for the lecturer dashboard.
     */
    public function getAllForLecturer(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $this->jsonResponse(Test::getAllForLecturer());
        } catch (\Throwable $e) {
            $this->logError('Loading lecturer tests', $e);
            $this->jsonResponse(
                ['error' => 'Unable to load tests. Please try again later.'],
                500
            );
        }
    }

    /**
     * Returns all active subjects for the lecturer test form.
     */
    public function getActiveSubjects(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $this->jsonResponse(Test::getActiveSubjects());
        } catch (\Throwable $e) {
            $this->logError('Loading lecturer subjects', $e);
            $this->jsonResponse(
                ['error' => 'Unable to load subjects. Please try again later.'],
                500
            );
        }
    }

    /**
     * Creates a new test.
     */
    public function createTest(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $data = $this->getTestRequestData();
            $userID = Authentication::getLoggedInUserID();

            if ($userID === null) {
                $this->jsonResponse(['error' => 'Your session has ended.'], 401);
                return;
            }

            $testID = Test::create(
                $data['testName'],
                $data['subjectID'],
                $data['testDescription'],
                $data['timeLimit'],
                $data['dueDate'],
                $data['questionCount'],
                $userID
            );

            $this->jsonResponse([
                'message' => 'Test created successfully.',
                'testID' => $testID
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError('Creating test', $e);
            $this->jsonResponse(
                ['error' => 'Unable to create test. Please try again later.'],
                500
            );
        }
    }

    /**
     * Updates an existing test.
     */
    public function updateTest(string $testID): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $data = $this->getTestRequestData();

            $updated = Test::update(
                $testID,
                $data['testName'],
                $data['subjectID'],
                $data['testDescription'],
                $data['timeLimit'],
                $data['dueDate'],
                $data['questionCount']
            );

            if (!$updated) {
                $this->jsonResponse(['error' => 'Test not found.'], 404);
                return;
            }

            $this->jsonResponse([
                'message' => 'Test updated successfully.'
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError('Updating test', $e);
            $this->jsonResponse(
                ['error' => 'Unable to update test. Please try again later.'],
                500
            );
        }
    }

    /**
     * Deletes an existing test.
     */
    public function deleteTest(string $testID): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $deleted = Test::delete($testID);

            if (!$deleted) {
                $this->jsonResponse(['error' => 'Test not found.'], 404);
                return;
            }

            $this->jsonResponse([
                'message' => 'Test deleted successfully.'
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logError('Deleting test', $e);
            $this->jsonResponse(
                ['error' => 'Unable to delete test. Please try again later.'],
                500
            );
        }
    }

    /**
     * Reads and validates the incoming test request.
     */
    private function getTestRequestData(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid request data.');
        }

        if (
            !isset($data['testName'])
            || !is_string($data['testName'])
            || trim($data['testName']) === ''
        ) {
            throw new \InvalidArgumentException('Test name cannot be empty.');
        }

        if (
            !isset($data['subjectID'])
            || !is_string($data['subjectID'])
            || trim($data['subjectID']) === ''
        ) {
            throw new \InvalidArgumentException('A valid subject must be selected.');
        }

        $timeLimit = $data['timeLimit'] ?? null;

        if ($timeLimit !== null && filter_var($timeLimit, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('Please provide a valid time limit.');
        }

        $questionCount = filter_var(
            $data['questionCount'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($questionCount === false) {
            throw new \InvalidArgumentException('Please provide a valid question count.');
        }

        $dueDate = $data['dueDate'] ?? null;

        if ($dueDate === '') {
            $dueDate = null;
        }

        if ($dueDate !== null && !is_string($dueDate)) {
            throw new \InvalidArgumentException('Please provide a valid due date.');
        }

        $description = $data['testDescription'] ?? null;

        if ($description !== null && !is_string($description)) {
            throw new \InvalidArgumentException('Please provide a valid description.');
        }

        return [
            'testName' => $data['testName'],
            'subjectID' => $data['subjectID'],
            'testDescription' => $description,
            'timeLimit' => $timeLimit === null ? null : (int) $timeLimit,
            'dueDate' => $dueDate,
            'questionCount' => $questionCount
        ];
    }

    /**
     * Stops the request unless an active student is logged in.
     */
    private function requireActiveStudent(): string
    {
        Authentication::requireRole(['student']);

        $userID = null;
        $user = null;

        try {
            $userID = Authentication::getLoggedInUserID();
            $user = $userID === null ? null : User::findById($userID);
        } catch (\Throwable $e) {
            $this->logError('Checking the student session', $e);
            $this->jsonResponse(
                ['error' => 'Something went wrong. Please try again later.'],
                500
            );
        }

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
        }

        return $userID;
    }

    /**
     * Logs errors without exposing internal details to the browser.
     */
    private function logError(string $action, \Throwable $e): void
    {
        error_log(sprintf(
            '%s failed (%s): %s',
            $action,
            get_class($e),
            $e->getMessage()
        ));
    }
}
