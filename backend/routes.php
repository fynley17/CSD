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

$router->addRoute(
    'GET',
    '/^\/api\/tests\/completed\/([0-9a-zA-Z-]+)$/',
    [new ResultController(), 'getCompletedTestReview']
);

// Results.
$router->addRoute(
    'GET',
    '/^\/api\/results\/summary$/',
    [new ResultController(), 'getResultsSummary']
);

// Top students in the student's cohort, overall or for one subject (/api/leaderboard/{subjectID}).
$router->addRoute(
    'GET',
    '/^\/api\/leaderboard(?:\/([0-9a-zA-Z-]+))?$/',
    [new LeaderboardController(), 'getLeaderboard']
);

// Admin dashboard routes.
// Totals for the admin dashboard cards, admins only.
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

// Login, logout, session check and the anti-CSRF code.
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

// Test management routes.

// Return all tests for the lecturer/admin management page.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/tests$/',
    [new TestController(), 'getAllForLecturer']
);

// Lecturer question management.
// Return active subjects for the test creation and editing forms.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/test-subjects$/',
    [new TestController(), 'getActiveSubjects']
);

// Create a new test (lecturers only; administrators cannot create tests).
$router->addRoute(
    'POST',
    '/^\/api\/lecturer\/tests$/',
    [new TestController(), 'createTest']
);

// Update an existing test.
$router->addRoute(
    'PUT',
    '/^\/api\/lecturer\/tests\/([0-9a-zA-Z-]+)$/',
    [new TestController(), 'updateTest']
);

// Delete an existing test.
$router->addRoute(
    'DELETE',
    '/^\/api\/lecturer\/tests\/([0-9a-zA-Z-]+)$/',
    [new TestController(), 'deleteTest']
);

// Question management routes for lecturers.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/questions$/',
    [new QuestionController(), 'getAllForLecturer']
);

// Create a question for lecturer question management.
$router->addRoute(
    'POST',
    '/^\/api\/lecturer\/questions$/',
    [new QuestionController(), 'createQuestion']
);

// Update an existing question.
$router->addRoute(
    'PUT',
    '/^\/api\/lecturer\/questions\/([0-9a-zA-Z-]+)$/',
    [new QuestionController(), 'updateQuestion']
);

// Delete an existing question.
$router->addRoute(
    'DELETE',
    '/^\/api\/lecturer\/questions\/([0-9a-zA-Z-]+)$/',
    [new QuestionController(), 'deleteQuestion']
);

// Return active subjects for lecturer question management.
$router->addRoute(
    'GET',
    '/^\/api\/lecturer\/subjects$/',
    [new QuestionController(), 'getActiveSubjects']
);

// Subjects.
// Return all subjects for the admin dashboard, admins only.
$router->addRoute(
    'GET',
    '/^\/api\/subjects$/',
    [new SubjectController(), 'getAllSubjects']
);

// Return all cohorts with their students and subjects for admin management.
$router->addRoute(
    'GET',
    '/^\/api\/admin\/cohorts$/',
    [new CohortController(), 'getCohortsForManagement']
);

// Enable or disable a cohort, admins only.
$router->addRoute(
    'PUT',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)\/status$/',
    [new CohortController(), 'updateCohortStatus']
);

// Create a cohort, or change its name and status, admins only.
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

// Delete an empty cohort, admins only.
$router->addRoute(
    'DELETE',
    '/^\/api\/admin\/cohorts\/([0-9a-zA-Z-]+)$/',
    [new CohortController(), 'deleteCohort']
);

// Students in other cohorts for the add students pop-up, and moving students into a cohort, admins only.
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

// Add subjects to a cohort or take one out, admins only.
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

// Active subjects that aren't in a cohort, for the assign subjects pop-up, admins only.
$router->addRoute(
    'GET',
    '/^\/api\/admin\/subjects\/unassigned$/',
    [new SubjectController(), 'getUnassignedSubjects']
);

return $router;
