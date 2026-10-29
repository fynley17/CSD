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

// Top students in the student's cohort, overall or for one subject (/api/leaderboard/<subjectID>).
$router->addRoute(
    'GET',
    '/^\/api\/leaderboard(?:\/([0-9a-zA-Z-]+))?$/',
    [new LeaderboardController(), 'getLeaderboard']
);

// Provide one completed test with its questions and answers, used for the PDF export.
$router->addRoute(
    'GET',
    '/^\/api\/tests\/completed\/([0-9a-zA-Z-]+)$/',
    [new ResultController(), 'getCompletedTestReview']
);

// totals for the admin dashboard cards, admins only
$router->addRoute(
    'GET',
    '/^\\/api\\/admin\\/dashboard-summary$/',
    [new AdministratorController(), 'getDashboardSummary']
);

// Email verification, used by the check your email and verify email pages.
$router->addRoute('POST', '/^\/api\/verify-email$/', [new VerificationController(), 'verifyEmail']);
$router->addRoute('GET', '/^\/api\/pending-verification$/', [new VerificationController(), 'getPendingVerification']);
$router->addRoute('POST', '/^\/api\/resend-verification$/', [new VerificationController(), 'resendVerification']);
$router->addRoute('POST', '/^\/api\/change-email$/', [new VerificationController(), 'changeEmail']);

// Login, logout, session check and the anti-CSRF code.
$router->addRoute('POST', '/^\/api\/login$/', [new AuthController(), 'login']);
$router->addRoute('POST', '/^\/api\/logout$/', [new AuthController(), 'logout']);
$router->addRoute('GET', '/^\/api\/current-user$/', [new AuthController(), 'currentUser']);
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
// the lecturers in the admin dashboard table, admins only
$router->addRoute(
    'GET',
    '/^\\/api\\/admin\\/recent-lecturers$/',
    [new AdministratorController(), 'getRecentLecturers']
);

return $router;
