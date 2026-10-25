<?php

use App\Router;
use App\Controllers\StudentController;
use App\Controllers\CohortController;
use App\Controllers\AuthController;
use App\Controllers\TestController;
use App\Controllers\VerificationController;

$router = new Router();

$router->addRoute('GET', '/^\\/api\\/students$/', [new StudentController(), 'getAllStudents']);
// Accept student details and the selected cohort from the registration form.
$router->addRoute('POST', '/^\\/api\\/register$/', [new StudentController(), 'registerStudent']);
// Provide the cohort options shown by the registration form.
$router->addRoute('GET', '/^\\/api\\/cohorts$/', [new CohortController(), 'getAllCohorts']);
// Provide tests with assigned questions to authenticated students.
$router->addRoute('GET', '/^\\/api\\/tests$/', [new TestController(), 'getAvailableTests']);

// email verification, used by the check your email and verify email pages
$router->addRoute('POST', '/^\\/api\\/verify-email$/', [new VerificationController(), 'verifyEmail']);
$router->addRoute('GET', '/^\\/api\\/pending-verification$/', [new VerificationController(), 'getPendingVerification']);
$router->addRoute('POST', '/^\\/api\\/resend-verification$/', [new VerificationController(), 'resendVerification']);
$router->addRoute('POST', '/^\\/api\\/change-email$/', [new VerificationController(), 'changeEmail']);

// one AuthController shared by login, logout, the session check and the secret code
$authController = new AuthController();
$router->addRoute('POST', '/^\\/api\\/login$/', [$authController, 'login']);
$router->addRoute('POST', '/^\\/api\\/logout$/', [$authController, 'logout']);
$router->addRoute('GET', '/^\\/api\\/current-user$/', [$authController, 'currentUser']);
// gives the page its secret code, it sends that back with anything that changes data i.e. login or register
// other websites can't read our replies, so they can't get the code to fake a request as a logged in user
$router->addRoute('GET', '/^\\/api\\/csrf-token$/', [$authController, 'csrfToken']);

return $router;
