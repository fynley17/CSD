<?php

namespace App\Models;

class Student extends Model
{
    public static function getAll()
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM students");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function register($data)
    {
        $db = self::getDB();
        $stmt = $db->prepare("INSERT INTO students (name, email) VALUES (?, ?)");
        $result = $stmt->execute([$data['name'], $data['email']]);
        return ['success' => $result];
    }
}
