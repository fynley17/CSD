<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from the config directory.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get environment variables
$host = $_ENV['DB_HOST'];
$name = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];

$dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

return $pdo;
