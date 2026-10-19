<?php

namespace App\Models;

class Model
{
    protected static $db;

    public static function init()
    {
        if (self::$db === null) {
            // Reuse one configured PDO connection for model queries.
            self::$db = require_once __DIR__ . '/../../config/database.php';
        }
    }

    protected static function getDB()
    {
        self::init();
        return self::$db;
    }
}
