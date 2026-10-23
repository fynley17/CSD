<?php

use App\Router;
use App\Controllers\StudentController;
use App\Controllers\CohortController;
use App\Controllers\AuthController;
use App\Controllers\TestController;

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

// when the page is on a different address to PHP, the browser asks PHP first if it's allowed to
// send the login, that question comes in as an 'OPTIONS' request, and the Access-Control lines above are the answer
// so PHP sends back just those lines with code 204 (means "done, nothing else to send"), and exit stops here

// without exit it would carry on to index.php, where the anti-CSRF check turns away anything that isn't a GET
// and doesn't have the secret code - the browser's question never has it, so it would get a 403
// and the browser would never send the login
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
// Provide tests with assigned questions to authenticated students.
$router->addRoute('GET', '/^\\/api\\/tests$/', [new TestController(), 'getAvailableTests']);

// one AuthController shared by login, logout, the session check and the secret code
$authController = new AuthController();
$router->addRoute('POST', '/^\\/api\\/login$/', [$authController, 'login']);
$router->addRoute('POST', '/^\\/api\\/logout$/', [$authController, 'logout']);
$router->addRoute('GET', '/^\\/api\\/current-user$/', [$authController, 'currentUser']);
// gives the page its secret code, it sends that back with anything that changes data i.e. login or register
// other websites can't read our replies, so they can't get the code to fake a request as a logged in user
$router->addRoute('GET', '/^\\/api\\/csrf-token$/', [$authController, 'csrfToken']);

return $router;
