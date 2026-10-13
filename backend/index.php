<?php

try {
    $pdo = require __DIR__ . '/config/database.php';
    echo 'Database connected';
} catch (Throwable $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Database connection failed';
}
