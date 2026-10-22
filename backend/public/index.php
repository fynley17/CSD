<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Router;
use App\Helpers\Authentication;

// PHP adds an X-Powered-By line to every reply saying it's PHP and the exact version, i.e. PHP/8.4.27
// that tells attackers which known weaknesses to look up, so the line's taken off before anything is sent
header_remove('X-Powered-By');

// Load the routes, including account registration and cohort lookup.
$router = require __DIR__ . '/../routes.php';

// Dispatch the request
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// GET only reads data, everything else (login, register, sign out) has to bring the session's secret code
// checked here once before any route runs, so a new route can't forget it, no code means a 403 and nothing happens
if ($method !== 'GET') {
    $authentication = new Authentication();
    $authentication->requireCsrfToken();
}

$router->dispatch($uri, $method);
