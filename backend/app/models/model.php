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

    /**
     * A new random ID like MariaDB's uuid(), for rows that need it before they're saved
     */
    protected static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        // marks it as a version 4 (random) UUID
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
