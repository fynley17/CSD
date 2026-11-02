<?php

namespace App\Helpers;

/**
 * Checks for what admins type into the lecturer forms
 * each one only says true or false, the model picks the message to show
 */
class Validation
{
    // Users.username is VARCHAR(8) and the staff ID is the username
    private const STAFF_ID_PATTERN = '/^[A-Za-z0-9]{3,8}$/D';

    /**
     * First or last name, letters spaces apostrophes and hyphens, 2 to 50 characters
     */
    public static function isValidName(mixed $name): bool
    {
        // same rule as student registration so names work the same everywhere
        return is_string($name) && preg_match("/^[a-zA-Z\s'-]{2,50}$/", $name) === 1;
    }

    /**
     * Email in a normal format, and short enough for the email column
     */
    public static function isValidEmail(mixed $email): bool
    {
        return is_string($email) && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Staff ID, 3 to 8 letters or numbers like L1001
     */
    public static function isValidStaffId(mixed $staffId): bool
    {
        return is_string($staffId) && preg_match(self::STAFF_ID_PATTERN, $staffId) === 1;
    }
}
