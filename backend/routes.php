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
use App\Controllers\PasswordSetupController;

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
    '/^\/api\/students\/([0-9a-zA-Z-]+)\/status$/',
    [new StudentController(), 'updateStudentStatus']
);

$router->addRoute(
    'POST',
    '/^\/api\/students\/([0-9a-zA-Z-]+)\/approve$/',
    [new StudentController(), 'approveStudent']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/students\/([0-9a-zA-Z-]+)\/application$/',
    [new StudentController(), 'declineStudent']
);

$router->addRoute(
    'PUT',
    '/^\/api\/students\/([0-9a-zA-Z-]+)$/',
    [new StudentController(), 'updateStudent']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/students\/([0-9a-zA-Z-]+)$/',
    [new StudentController(), 'deleteStudent']
);

// Student registration.
$router->addRoute(
    'POST',
    '/^\/api\/register$/',
    [new StudentController(), 'registerStudent']
);

// Cohorts available to students.
$router->addRoute(
    'GET',
    '/^\/api\/cohorts$/',
    [new CohortController(), 'getAllCohorts']
);

// Student tests.
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

// Completed tests.
$router->addRoute(
    'GET',
    '/^\/api\/tests\/completed$/',
    [new ResultController(), 'getCompletedTests']
);

$router->addRoute(
    'GET',
    '/^\/api\/tests\/completed\/([0-9a-zA-Z-]+)$/',
    [new ResultController(), 'getCompletedTestReview']
);

// Student results.
$router->addRoute(
    'GET',
    '/^\/api\/results\/summary$/',
    [new ResultController(), 'getResultsSummary']
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

// Password setup for lecturer-created student accounts.
$router->addRoute(
    'POST',
    '/^\/api\/setup-password$/',
    [new PasswordSetupController(), 'setPassword']
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

// Lecturer test management.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/tests$/',
    [new TestController(), 'getAllForLecturer']
);

$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/test-subjects$/',
    [new TestController(), 'getActiveSubjects']
);

$router->addRoute(
    'POST',
    '/^\/api\/lecturer\/tests$/',
    [new TestController(), 'createTest']
);

$router->addRoute(
    'PUT',
    '/^\/api\/lecturer\/tests\/([0-9a-zA-Z-]+)$/',
    [new TestController(), 'updateTest']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/lecturer\/tests\/([0-9a-zA-Z-]+)$/',
    [new TestController(), 'deleteTest']
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

// Subject management.
$router->addRoute(
    'GET',
    '/^\/api\/subjects\/lecturers$/',
    [new SubjectController(), 'getLecturerOptions']
);

$router->addRoute(
    'POST',
    '/^\/api\/subjects$/',
    [new SubjectController(), 'createSubject']
);

$router->addRoute(
    'PUT',
    '/^\/api\/subjects\/([0-9a-zA-Z-]+)$/',
    [new SubjectController(), 'updateSubject']
);

$router->addRoute(
    'PATCH',
    '/^\/api\/subjects\/([0-9a-zA-Z-]+)\/active$/',
    [new SubjectController(), 'setSubjectActive']
);

// lecturer management page, every lecturer, add, edit, disable or enable, delete and resend link, admins only
$router->addRoute('GET', '/^\\/api\\/admin\\/lecturers$/', [new AdministratorController(), 'getLecturers']);
$router->addRoute('POST', '/^\\/api\\/admin\\/lecturers$/', [new AdministratorController(), 'createLecturer']);
$router->addRoute(
    'POST',
    '/^\\/api\\/admin\\/lecturers\\/([0-9a-zA-Z-]+)\\/setup-link$/',
    [new AdministratorController(), 'resendSetupLink']
);
$router->addRoute(
    'PUT',
    '/^\\/api\\/admin\\/lecturers\\/([0-9a-zA-Z-]+)$/',
    [new AdministratorController(), 'updateLecturer']
);
$router->addRoute(
    'PATCH',
    '/^\\/api\\/admin\\/lecturers\\/([0-9a-zA-Z-]+)\\/enabled$/',
    [new AdministratorController(), 'setLecturerEnabled']
);
$router->addRoute(
    'DELETE',
    '/^\\/api\\/admin\\/lecturers\\/([0-9a-zA-Z-]+)$/',
    [new AdministratorController(), 'deleteLecturer']
);

// Admin cohort management.
$router->addRoute(
    'GET',
    '/^\/api\/admin\/cohorts$/',
    [new CohortController(), 'getCohortsForManagement']
);

$router->addRoute(
    'PUT',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)\/status$/',
    [new CohortController(), 'updateCohortStatus']
);

$router->addRoute(
    'POST',
    '/^\/api\/admin\/cohorts$/',
    [new CohortController(), 'createCohort']
);

$router->addRoute(
    'PUT',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)$/',
    [new CohortController(), 'updateCohort']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)$/',
    [new CohortController(), 'deleteCohort']
);

// Students in other cohorts and moving students into a cohort.
$router->addRoute(
    'GET',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)\/other-students$/',
    [new CohortController(), 'getOtherStudents']
);

$router->addRoute(
    'POST',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)\/students$/',
    [new CohortController(), 'moveStudents']
);

// Add or remove subjects from a cohort.
$router->addRoute(
    'POST',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)\/subjects$/',
    [new CohortController(), 'assignSubjects']
);

$router->addRoute(
    'DELETE',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)\/subjects\/([0-9a-zA-Z-]+)$/',
    [new CohortController(), 'removeSubject']
);

// Active subjects that aren't assigned to a cohort.
$router->addRoute(
    'GET',
    '/^\/api\/admin\/subjects\/unassigned$/',
    [new SubjectController(), 'getUnassignedSubjects']
);

return $router;
