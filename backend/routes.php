
<?php

use App\Router;
use App\Controllers\StudentController;
use App\Controllers\CohortController;
use App\Controllers\AuthController;
use App\Controllers\LecturerController;
use App\Controllers\ResultController;
use App\Controllers\TestController;
use App\Controllers\VerificationController;
use App\Controllers\QuestionController;

$router = new Router();

$router->addRoute('GET', '/^\/api\/students$/', [new StudentController(), 'getAllStudents']);

// Accept student details and the selected cohort from the registration form.
$router->addRoute('POST', '/^\/api\/register$/', [new StudentController(), 'registerStudent']);

// Provide the cohort options shown by the registration form.
$router->addRoute('GET', '/^\/api\/cohorts$/', [new CohortController(), 'getAllCohorts']);

// Provide tests with assigned questions to authenticated students.
$router->addRoute('GET', '/^\/api\/tests$/', [new TestController(), 'getAvailableTests']);

// Start a test, then mark and save the finished attempt.
$router->addRoute('POST', '/^\/api\/tests\/([0-9a-zA-Z-]+)\/start$/', [new TestController(), 'startTest']);
$router->addRoute('POST', '/^\/api\/tests\/([0-9a-zA-Z-]+)\/complete$/', [new TestController(), 'submitTest']);

// Provide the tests the authenticated student has already completed.
$router->addRoute('GET', '/^\/api\/tests\/completed$/', [new ResultController(), 'getCompletedTests']);

// Email verification, used by the check your email and verify email pages.
$router->addRoute('POST', '/^\/api\/verify-email$/', [new VerificationController(), 'verifyEmail']);
$router->addRoute('GET', '/^\/api\/pending-verification$/', [new VerificationController(), 'getPendingVerification']);
$router->addRoute('POST', '/^\/api\/resend-verification$/', [new VerificationController(), 'resendVerification']);
$router->addRoute('POST', '/^\/api\/change-email$/', [new VerificationController(), 'changeEmail']);

// one AuthController shared by login, logout, the session check and the secret code
$authController = new AuthController();
$router->addRoute('POST', '/^\/api\/login$/', [new AuthController(), 'login']);
$router->addRoute('POST', '/^\/api\/logout$/', [new AuthController(), 'logout']);
$router->addRoute('GET', '/^\/api\/current-user$/', [new AuthController(), 'currentUser']);
// gives the page its secret code, it sends that back with anything that changes data i.e. login or register
// other websites can't read our replies, so they can't get the code to fake a request as a logged in user
$router->addRoute('GET', '/^\/api\/csrf-token$/', [new AuthController(), 'csrfToken']);

// Lecturer dashboard summary.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/dashboard-summary$/',
    [new LecturerController(), 'getDashboardSummary']
);

// Recent student activity on the lecturer dashboard.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/recent-students$/',
    [new StudentController(), 'getRecentStudentActivity']
);

// Return all tests for the lecturer dashboard.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/tests$/',
    [new TestController(), 'getAllForLecturer']
);

// Return all questions for the lecturer question management page.
$router->addRoute(
    'GET',
    '/^\\/api\\/lecturer\\/questions$/',
    [new QuestionController(), 'getAllForLecturer']
);

return $router;
