<?php


use App\Router;
use App\Controllers\studentController;

// Allow requests from the frontend origin
header("Access-Control-Allow-Origin: http://127.0.0.1:5174");
// Allow specific HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
// Allow specific headers
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No content
    exit;
}

$router = new Router();

// student routes
$router->addRoute('GET', '/^\\/api\\/students$/', [new studentController(), 'getAllStudents']);
$router->addRoute('POST', '/^\\/api\\/register$/', [new studentController(), 'registerStudent']);
