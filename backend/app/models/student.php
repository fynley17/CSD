<?php

namespace App\Models;

class Student extends Model
{
    public static function getAll()
    {
        $stmt = self::getDB()->prepare("SELECT * FROM Students");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function register($data)
    {

        // Validate & sanitize input data
        if (empty($data['firstName']) || !self::names($data['firstName'])) {
            throw new \InvalidArgumentException('not valid first name');
        }
        if (empty($data['lastName']) || !self::names($data['lastName'])) {
            throw new \InvalidArgumentException('not valid last name');
        }
        if (empty($data['email']) || !self::ValidEmail($data['email'])) {
            throw new \InvalidArgumentException('not valid email');
        }
        if (empty($data['studentNumber']) || !self::validUsername($data['studentNumber'])) {
            throw new \InvalidArgumentException('not valid student number');
        }

        $stmt = self::getDB()->prepare("INSERT INTO Students (firstName, lastName, email) VALUES (?, ?, ?)");
        $result = $stmt->execute([$data['firstName'], $data['lastName'], $data['email']]);
        return ['success' => $result];
    }

    // Helpers
    public static function names($name)
    {
        return preg_match("/^[a-zA-Z\s'-]{2,50}$/", $name);
    }

    public static function validUsername($studentNumber)
    {
        return preg_match("/^WS[0-9]{6}$/D", $studentNumber);
    }

    private static function ValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
