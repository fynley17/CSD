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
     * Get tests available to the logged-in student.
     */
    public function getAvailableTests(): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $this->jsonResponse(Test::getAvailableForStudents($userID));
        } catch (\Throwable $e) {
            $this->logError('Loading available tests', $e);
            $this->jsonResponse([
                'error' => 'Unable to load tests. Please try again later.'
            ], 500);
        }
    }

    /**
     * Start a test for the logged-in student.
     */
    public function startTest(string $testID): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $test = Test::getForStudent($testID, $userID);

            if ($test === null) {
                $this->jsonResponse([
                    'error' => self::TEST_NOT_FOUND
                ], 404);
            }

            Authentication::saveTestAttempt(
                $testID,
                array_column($test['questions'], 'questionID')
            );

            $this->jsonResponse($test);
        } catch (\Throwable $e) {
            $this->logError('Loading a test', $e);
            $this->jsonResponse([
                'error' => 'Unable to load this test. Please try again later.'
            ], 500);
        }
    }

    /**
     * Submit a completed test.
     */
    public function submitTest(string $testID): void
    {
        $userID = $this->requireActiveStudent();

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !is_array($data)
                || !isset($data['answers'])
                || !is_array($data['answers'])
            ) {
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
                $this->jsonResponse([
                    'error' => self::TEST_NOT_FOUND
                ], 404);
            }

            Authentication::clearTestAttempt($testID);

            $this->jsonResponse($result, 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            $this->logError('Saving a completed test', $e);
            $this->jsonResponse([
                'error' => 'Unable to save your test. Please try again.'
            ], 500);
        }
    }

    /**
     * Check that the current user is an active student.
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
            $this->jsonResponse([
                'error' => 'Something went wrong. Please try again later.'
            ], 500);
        }

        if (
            $user === null
            || !$user->isActive()
            || $user->getAccountType() !== 'student'
        ) {
            Authentication::endSession();

            $this->jsonResponse([
                'error' => 'Your session has ended. Go to the login page to continue'
            ], 401);
        }

        return $userID;
    }

    /**
     * Get all tests for the lecturer/admin management page.
     */
    public function getAllForLecturer(): void
    {
        Authentication::requireRole(['lecturer', 'admin']);

        try {
            $tests = Test::getAllForLecturer();
            $this->jsonResponse($tests);
        } catch (\Throwable $e) {
            $this->logError('Loading management tests', $e);
            $this->jsonResponse([
                'error' => 'Unable to load tests. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get active subjects available when creating or editing a test.
     */
    public function getActiveSubjects(): void
    {
        Authentication::requireRole(['lecturer', 'admin']);

        try {
            $subjects = Test::getActiveSubjects();
            $this->jsonResponse($subjects);
        } catch (\Throwable $e) {
            $this->logError('Loading active test subjects', $e);
            $this->jsonResponse([
                'error' => 'Unable to load subjects. Please try again later.'
            ], 500);
        }
    }

    /**
     * Create a new test.
     * Only lecturers can create tests because Tests.createdBy
     * references Lecturer.userID.
     */
    public function createTest(): void
    {
        Authentication::requireRole(['lecturer']);

        try {
            $data = $this->getTestRequestData();

            $createdBy = Authentication::getLoggedInUserID();

            if ($createdBy === null) {
                $this->jsonResponse([
                    'error' => 'Your session has ended. Please log in again.'
                ], 401);
            }

            $testID = Test::create(
                $data['testName'],
                $data['subjectID'],
                $data['testDescription'],
                $data['timeLimit'],
                $data['dueDate'],
                $data['questionCount'],
                $createdBy
            );

            $this->jsonResponse([
                'message' => 'Test created successfully.',
                'testID' => $testID
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            $this->logError('Creating test', $e);
            $this->jsonResponse([
                'error' => 'Unable to create test. Please try again later.'
            ], 500);
        }
    }

    /**
     * Update an existing test.
     */
    public function updateTest(string $testID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);

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
                $this->jsonResponse([
                    'error' => 'Test not found.'
                ], 404);
            }

            $this->jsonResponse([
                'message' => 'Test updated successfully.'
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            $this->logError('Updating test', $e);
            $this->jsonResponse([
                'error' => 'Unable to update test. Please try again later.'
            ], 500);
        }
    }

    /**
     * Delete a test and if not completed by any students, remove it from the database.
     * If students have completed the test, it will be archived instead.
     */
    public function deleteTest(string $testID): void
    {
        Authentication::requireRole(['lecturer', 'admin']);

        try {
            $result = Test::delete($testID);

            if ($result === false) {
                $this->jsonResponse([
                    'error' => 'Test not found.'
                ], 404);
                return;
            }

            if ($result === 'archived') {
                $this->jsonResponse([
                    'message' => 'Test archived successfully. Existing student results have been preserved.'
                ]);
                return;
            }

            $this->jsonResponse([
                'message' => 'Test deleted successfully.'
            ]);
        } catch (\Throwable $e) {
            $this->logError('Deleting test', $e);
            $this->jsonResponse([
                'error' => 'Unable to delete test. Please try again later.'
            ], 500);
        }
    }

    /**
     * Read and validate the incoming test request.
     */
    private function getTestRequestData(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid request data.');
        }

        return self::validateTestRequestData($data);
    }

    /**
     * Validate test request data.
     * Kept separate so it can be tested independently with PHPUnit.
     */
    public static function validateTestRequestData(array $data): array
    {
        if (
            !isset($data['testName'])
            || !is_string($data['testName'])
            || trim($data['testName']) === ''
        ) {
            throw new \InvalidArgumentException(
                'Test name cannot be empty.'
            );
        }

        if (
            !isset($data['subjectID'])
            || !is_string($data['subjectID'])
            || trim($data['subjectID']) === ''
        ) {
            throw new \InvalidArgumentException(
                'A valid subject must be selected.'
            );
        }

        $testDescription = $data['testDescription'] ?? null;

        if (
            $testDescription !== null
            && !is_string($testDescription)
        ) {
            throw new \InvalidArgumentException(
                'Test description must be text.'
            );
        }

        $timeLimit = null;

        if (
            isset($data['timeLimit'])
            && $data['timeLimit'] !== ''
        ) {
            $timeLimit = filter_var(
                $data['timeLimit'],
                FILTER_VALIDATE_INT
            );

            if ($timeLimit === false) {
                throw new \InvalidArgumentException(
                    'Time limit must be a valid number.'
                );
            }
        }

        $dueDate = $data['dueDate'] ?? null;

        if ($dueDate !== null && !is_string($dueDate)) {
            throw new \InvalidArgumentException(
                'Due date must be a valid date.'
            );
        }

        $questionCount = filter_var(
            $data['questionCount'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $questionCount === false
            || $questionCount === null
        ) {
            throw new \InvalidArgumentException(
                'Question count must be a valid number.'
            );
        }

        return [
            'testName' => trim($data['testName']),
            'subjectID' => trim($data['subjectID']),
            'testDescription' => $testDescription,
            'timeLimit' => $timeLimit,
            'dueDate' => $dueDate,
            'questionCount' => $questionCount
        ];
    }

    /**
     * Log server-side errors without exposing internal details to users.
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
