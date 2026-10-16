<?php

namespace App\Controllers;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class Controller
{
    public function jsonResponse($data, $status = 200)
    {
        header("Content-Type: application/json");
        http_response_code($status);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
