
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

// Student management routes.
$router->addRoute(
    'GET',
    '/^\/api\/students$/',
    [new StudentController(), 'getAllStudents']
);

$router->addRoute(
    'POST',
    '/^\/api\/students$/',
    [new StudentController(), 'createStudent']
);

$router->addRoute(
    'PUT',
    '/^\/api\/students\/status$/',
    [new StudentController(), 'updateStudentStatus']
);

// Student registration.
$router->addRoute(
    'POST',
    '/^\/api\/register$/',
    [new StudentController(), 'registerStudent']
);

$router->addRoute(
    'PUT',
    '/^\/api\/students\/approve$/',
    [new StudentController(), 'approveStudent']
);

$router->addRoute(
    'PUT',
    '/^\/api\/students\/decline$/',
    [new StudentController(), 'declineStudent']
);

$router->addRoute(
    'PUT',
    '/^\/api\/students\/update$/',
    [new StudentController(), 'updateStudent']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/students$/',
    [new StudentController(), 'deleteStudent']
);

// Cohorts.
$router->addRoute(
    'GET',
    '/^\/api\/cohorts$/',
    [new CohortController(), 'getAllCohorts']
);

// Tests.
$router->addRoute(
    'GET',
    '/^\/api\/tests$/',
    [new TestController(), 'getAvailableTests']
);

$router->addRoute(
    'POST',
    '/^\/api\/tests\/([0-9a-zA-Z-]+)\/start$/',
    [new TestController(), 'startTest']
);

$router->addRoute(
    'POST',
    '/^\/api\/tests\/([0-9a-zA-Z-]+)\/complete$/',
    [new TestController(), 'submitTest']
);

$router->addRoute(
    'GET',
    '/^\/api\/tests\/completed$/',
    [new ResultController(), 'getCompletedTests']
);

// Results.
$router->addRoute(
    'GET',
    '/^\/api\/results\/summary$/',
    [new ResultController(), 'getResultsSummary']
);

$router->addRoute(
    'GET',
    '/^\/api\/tests\/completed\/([0-9a-zA-Z-]+)$/',
    [new ResultController(), 'getCompletedTestReview']
);

// Leaderboard.
$router->addRoute(
    'GET',
    '/^\/api\/leaderboard(?:\/([0-9a-zA-Z-]+))?$/',
    [new LeaderboardController(), 'getLeaderboard']
);

// Admin dashboard routes.
$router->addRoute(
    'GET',
    '/^\/api\/admin\/dashboard-summary$/',
    [new AdministratorController(), 'getDashboardSummary']
);

$router->addRoute(
    'GET',
    '/^\/api\/admin\/recent-lecturers$/',
    [new AdministratorController(), 'getRecentLecturers']
);

// Email verification.
$router->addRoute(
    'POST',
    '/^\/api\/verify-email$/',
    [new VerificationController(), 'verifyEmail']
);

$router->addRoute(
    'GET',
    '/^\/api\/pending-verification$/',
    [new VerificationController(), 'getPendingVerification']
);

$router->addRoute(
    'POST',
    '/^\/api\/resend-verification$/',
    [new VerificationController(), 'resendVerification']
);

$router->addRoute(
    'POST',
    '/^\/api\/change-email$/',
    [new VerificationController(), 'changeEmail']
);

// Authentication and CSRF.
$router->addRoute(
    'POST',
    '/^\/api\/login$/',
    [new AuthController(), 'login']
);

$router->addRoute(
    'POST',
    '/^\/api\/logout$/',
    [new AuthController(), 'logout']
);

$router->addRoute(
    'GET',
    '/^\/api\/current-user$/',
    [new AuthController(), 'currentUser']
);

$router->addRoute(
    'GET',
    '/^\/api\/csrf-token$/',
    [new AuthController(), 'csrfToken']
);

// Lecturer dashboard.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/dashboard-summary$/',
    [new LecturerController(), 'getDashboardSummary']
);

$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/recent-students$/',
    [new StudentController(), 'getRecentStudentActivity']
);

$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/tests$/',
    [new TestController(), 'getAllForLecturer']
);

// Lecturer question management.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/questions$/',
    [new QuestionController(), 'getAllForLecturer']
);

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

// Subjects.
$router->addRoute(
    'GET',
    '/^\/api\/subjects$/',
    [new SubjectController(), 'getAllSubjects']
);

return $router;
