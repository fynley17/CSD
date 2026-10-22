<?php

use App\Router;
use App\Controllers\StudentController;
use App\Controllers\CohortController;
use App\Controllers\AuthController;

// CORS is the browser's rule for which other websites are allowed to read our replies
// only the addresses our Vue page runs on while we build it are on the list, nothing else gets in
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
];

// the page's address only goes back if it's an exact match on the list
// anyone else gets no CORS header at all, so their browser won't let them read the reply
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    // the reply changes depending on who's asking, so browsers and caches mustn't reuse one for someone else
    header('Vary: Origin');
}

// the kinds of request an allowed page can send us, GET just reads and the others change things
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
// the extra labels an allowed page can put on a request, i.e. Content-Type saying the data is JSON
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// before some requests the browser sends a quick OPTIONS check to see if its allowed to send the
// real request, and it doesn't need a reply body
// the lines above already answer it, so reply 204 (fine, nothing else to say) and stop there
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
// gives the page its secret code, it sends that back with anything that changes data i.e. login or register
// other websites can't read our replies, so they can't get the code to fake a request as you (CSRF)
$router->addRoute('GET', '/^\\/api\\/csrf-token$/', [new AuthController(), 'csrfToken']);

return $router;
