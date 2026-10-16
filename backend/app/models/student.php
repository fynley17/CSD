<?php

namespace App\Models;

class Student extends Model
{
    public static function getAll()
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM Students");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function register($data)
    {
        $db = self::getDB();
        $stmt = $db->prepare("INSERT INTO Students (name, email) VALUES (?, ?)");
        $result = $stmt->execute([$data['name'], $data['email']]);
        return ['success' => $result];
    }
}
