
<?php

use App\Router;
use App\Controllers\StudentController;
use App\Controllers\CohortController;
use App\Controllers\AuthController;
use App\Controllers\LeaderboardController;
use App\Controllers\LecturerController;
use App\Controllers\ResultController;
use App\Controllers\TestController;
use App\Controllers\VerificationController;
use App\Controllers\AdministratorController;
use App\Controllers\QuestionController;
use App\Controllers\SubjectController;

$router = new Router();

$router->addRoute('GET', '/^\\/api\\/students$/', [new StudentController(), 'getAllStudents']);

// Accept student details and the selected cohort from the registration form.
$router->addRoute('POST', '/^\\/api\\/register$/', [new StudentController(), 'registerStudent']);

// Provide the cohort options shown by the registration form.
$router->addRoute('GET', '/^\\/api\\/cohorts$/', [new CohortController(), 'getAllCohorts']);

// Provide tests with assigned questions to authenticated students.
$router->addRoute('GET', '/^\\/api\\/tests$/', [new TestController(), 'getAvailableTests']);

// Start a test, then mark and save the finished attempt.
$router->addRoute('POST', '/^\\/api\\/tests\\/([0-9a-zA-Z-]+)\\/start$/', [new TestController(), 'startTest']);
$router->addRoute('POST', '/^\\/api\\/tests\\/([0-9a-zA-Z-]+)\\/complete$/', [new TestController(), 'submitTest']);

// Provide the tests the authenticated student has already completed.
$router->addRoute('GET', '/^\\/api\\/tests\\/completed$/', [new ResultController(), 'getCompletedTests']);

// Provide the details and totals for the top of the authenticated student's results page.
$router->addRoute('GET', '/^\\/api\\/results\\/summary$/', [new ResultController(), 'getResultsSummary']);

// Top students in the student's cohort, overall or for one subject (/api/leaderboard/{subjectID}).
$router->addRoute(
    'GET',
    '/^\\/api\\/leaderboard(?:\\/([0-9a-zA-Z-]+))?$/',
    [new LeaderboardController(), 'getLeaderboard']
);

// Provide one completed test with its questions and answers, used for the PDF export.
$router->addRoute(
    'GET',
    '/^\\/api\\/tests\\/completed\\/([0-9a-zA-Z-]+)$/',
    [new ResultController(), 'getCompletedTestReview']
);

// Totals for the admin dashboard cards, admins only.
$router->addRoute(
    'GET',
    '/^\\/api\\/admin\\/dashboard-summary$/',
    [new AdministratorController(), 'getDashboardSummary']
);

// Email verification, used by the check your email and verify email pages.
$router->addRoute('POST', '/^\\/api\\/verify-email$/', [new VerificationController(), 'verifyEmail']);
$router->addRoute('GET', '/^\\/api\\/pending-verification$/', [new VerificationController(), 'getPendingVerification']);
$router->addRoute('POST', '/^\\/api\\/resend-verification$/', [new VerificationController(), 'resendVerification']);
$router->addRoute('POST', '/^\\/api\\/change-email$/', [new VerificationController(), 'changeEmail']);

// Login, logout, session check and the anti-CSRF code.
$router->addRoute('POST', '/^\\/api\\/login$/', [new AuthController(), 'login']);
$router->addRoute('POST', '/^\\/api\\/logout$/', [new AuthController(), 'logout']);
$router->addRoute('GET', '/^\\/api\\/current-user$/', [new AuthController(), 'currentUser']);
$router->addRoute('GET', '/^\\/api\\/csrf-token$/', [new AuthController(), 'csrfToken']);

// Lecturer dashboard summary.
$router->addRoute(
    'GET',
    '/^\\/api\\/lecturer\\/dashboard-summary$/',
    [new LecturerController(), 'getDashboardSummary']
);

// Recent student activity on the lecturer dashboard.
$router->addRoute(
    'GET',
    '/^\\/api\\/lecturer\\/recent-students$/',
    [new StudentController(), 'getRecentStudentActivity']
);

// Test management routes.

// Return all tests for the lecturer/admin management page.
$router->addRoute(
    'GET',
    '/^\\/api\\/lecturer\\/tests$/',
    [new TestController(), 'getAllForLecturer']
);

// Return active subjects for the test creation and editing forms.
$router->addRoute(
    'GET',
    '/^\\/api\\/lecturer\\/test-subjects$/',
    [new TestController(), 'getActiveSubjects']
);

// Create a new test (lecturers only).
$router->addRoute(
    'POST',
    '/^\\/api\\/lecturer\\/tests$/',
    [new TestController(), 'createTest']
);

// Update an existing test.
$router->addRoute(
    'PUT',
    '/^\\/api\\/lecturer\\/tests\\/([0-9a-zA-Z-]+)$/',
    [new TestController(), 'updateTest']
);

// Delete an existing test.
$router->addRoute(
    'DELETE',
    '/^\\/api\\/lecturer\\/tests\\/([0-9a-zA-Z-]+)$/',
    [new TestController(), 'deleteTest']
);

// Question management routes for lecturers.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/questions$/',
    [new QuestionController(), 'getAllForLecturer']
);

// The lecturers in the admin dashboard table, admins only.
$router->addRoute(
    'POST',
    '/^\/api\/lecturer\/questions$/',
    [new QuestionController(), 'createQuestion']
);

$router->addRoute(
    'PUT',
    '/^\/api\/lecturer\/questions\/([0-9a-zA-Z-]+)$/',
    [new QuestionController(), 'updateQuestion']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/lecturer\/questions\/([0-9a-zA-Z-]+)$/',
    [new QuestionController(), 'deleteQuestion']
);

$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/subjects$/',
    [new QuestionController(), 'getActiveSubjects']
);

// the lecturers in the admin dashboard table, admins only
$router->addRoute(
    'GET',
    '/^\\/api\\/admin\\/recent-lecturers$/',
    [new AdministratorController(), 'getRecentLecturers']
);

// Every subject for the admin dashboard table, admins only.
$router->addRoute('GET', '/^\\/api\\/subjects$/', [new SubjectController(), 'getAllSubjects']);

return $router;
