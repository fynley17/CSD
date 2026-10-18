<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Router;

// Load the routes, including account registration and cohort lookup.
$router = require __DIR__ . '/../routes.php';

// Dispatch the request
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$router->dispatch($uri, $method);
