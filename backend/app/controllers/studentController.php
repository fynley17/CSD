<?php

namespace App\Controllers;

use App\Models\Student;

class studentController extends Controller
{
    public function getAllStudents()
    {
        $students = Student::getAll();
        $this->jsonResponse($students);
    }

    public function registerStudent()
    {
        try {
            // Get the JSON input from the request body
            $data = json_decode(file_get_contents('php://input'), true);
            $result = Student::register($data);

            if ($result['success']) {
                $this->jsonResponse(['message' => 'Student registered successfully'], 201);
            } else {
                $this->jsonResponse(['error' => 'Failed to register student'], 400);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
