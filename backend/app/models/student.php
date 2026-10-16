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
        $stmt = self::getDB()->prepare("INSERT INTO Students (name, email) VALUES (?, ?)");
        $result = $stmt->execute([$data['name'], $data['email']]);
        return ['success' => $result];
    }
}
