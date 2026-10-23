<?php

use App\Router;
use App\Controllers\StudentController;
use App\Controllers\CohortController;
use App\Controllers\AuthController;
use App\Controllers\LecturerController;

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
];

if (
    $origin !== ''
    && (
        in_array($origin, $allowedOrigins, true)
        || preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)
    )
) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

$router->addRoute('GET', '/^\\/api\\/students$/', [new StudentController(), 'getAllStudents']);
// Accept student details and the selected cohort from the registration form.
$router->addRoute('POST', '/^\\/api\\/register$/', [new StudentController(), 'registerStudent']);
// Provide the cohort options shown by the registration form.
$router->addRoute('GET', '/^\\/api\\/cohorts$/', [new CohortController(), 'getAllCohorts']);

// login, logout and session check
$router->addRoute('POST', '/^\\/api\\/login$/', [new AuthController(), 'login']);
$router->addRoute('POST', '/^\\/api\\/logout$/', [new AuthController(), 'logout']);
$router->addRoute('GET', '/^\\/api\\/current-user$/', [new AuthController(), 'currentUser']);

// lecturer dashboard summary
$router->addRoute(
    'GET',
    '/^\\/api\\/lecturer\\/dashboard-summary$/',
    [new LecturerController(), 'getDashboardSummary']
);

return $router;
