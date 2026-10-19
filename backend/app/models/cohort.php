<?php

namespace App\Models;

class Cohort extends Model
{
    public static function getAll()
    {
        // Return cohort identifiers and names for the registration form's selector.
        $stmt = self::getDB()->prepare("SELECT * FROM Cohorts");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
