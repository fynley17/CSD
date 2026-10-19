<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load the database connection settings from the backend environment file.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Read connection settings without placing credentials in application code.
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'] ?? '3306';
$name = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];

$dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
// Use exception mode so registration failures are handled by the controller.
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

return $pdo;
