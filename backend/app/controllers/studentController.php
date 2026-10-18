<?php

namespace App\Controllers;

use App\Models\Student;

class StudentController extends Controller
{
    public function getAllStudents()
    {
        $students = Student::getAll();
        $this->jsonResponse($students);
    }

    public function registerStudent()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                throw new \InvalidArgumentException('Invalid registration data');
            }

            $result = Student::register($data);

            if ($result['success']) {
                $this->jsonResponse(['message' => 'Student registered successfully'], 201);
            } else {
                $this->jsonResponse(['error' => 'Failed to register student'], 400);
            }
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Student registration failed (%s): %s',
                get_class($e),
                $e->getMessage()
            ));
            $this->jsonResponse(['error' => 'An unexpected error occurred. Please try again later.'], 500);
        }
    }
}
